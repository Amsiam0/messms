# Per-Member Meal Schedule & Daily Meal Generation

Date: 2026-09-03
Status: Approved for planning

## Problem

Meals are entered by hand for every date. In practice each member eats on a
fixed weekly rhythm — one member takes lunch on Saturday and Sunday, another
on Friday and Saturday, while both take dinner every day. The admin retypes
that same pattern daily.

We want each member's weekly rhythm stored once, and the day's meal sheet
created from it automatically.

## Scope

In scope:

- A per-member weekly schedule: a quantity for breakfast, lunch and dinner on
  each of the seven weekdays.
- A service that materialises a given date's `Meal` and `MealItem` rows from
  those schedules.
- An artisan command, run daily by the scheduler, plus a manual "Generate"
  action in the Meals UI.
- An editing surface: admins edit any active member's schedule; a member edits
  only their own.

Out of scope (deliberately deferred):

- One-off exceptions ("skip dinner next Tuesday").
- Guest meals or meals for non-members.
- Retroactive regeneration or reconciliation of past dates.
- Notifying members that their schedule changed.

## Decisions

| Question | Decision |
|---|---|
| Config granularity | A quantity per (member, weekday, meal type). Floats, matching `meal_items`, so `0.5` works. |
| Trigger | Scheduled artisan command **and** a manual button. |
| Re-run behaviour | Fill gaps, never overwrite. Idempotent. |
| Edit surface | Filament nav page; admin sees all, member sees own. |
| Row coverage | Every active member gets a row, including all-zero rows and members with no schedule. |

## Data model

New table `meal_schedules`:

```php
Schema::create('meal_schedules', function (Blueprint $table) {
    $table->id();
    $table->foreignId('member_id')->constrained()->cascadeOnDelete();
    $table->unsignedTinyInteger('weekday');       // 0 = Sunday .. 6 = Saturday
    $table->float('breakfast', 2)->default(0);
    $table->float('lunch', 2)->default(0);
    $table->float('dinner', 2)->default(0);
    $table->timestamps();
    $table->unique(['member_id', 'weekday']);
});
```

`weekday` follows Carbon's `dayOfWeek` (0 = Sunday) so no translation is needed
at generation time. `float($column, 2)` matches the existing `meal_items`
migration; consistency with surrounding code beats using a newer column type
for three fields.

`App\Models\MealSchedule` — `$guarded = ['id']`, `belongsTo(Member::class)`,
casts `weekday` to integer and the three meal columns to float.

`App\Models\Member` gains `mealSchedules(): HasMany`.

**Missing row means zero.** A member with no `meal_schedules` row for a weekday
is treated as breakfast/lunch/dinner all `0`. There is no separate "unconfigured"
state, so existing members keep working the moment the migration lands.

### Uniqueness of `meals.date`

Generation assumes at most one `Meal` per date — an invariant the existing
"Copy Meal" action already relies on but the schema does not enforce. A second
migration adds `unique('date')` to `meals`.

That migration must first detect pre-existing duplicate dates and abort with a
clear message naming them, rather than failing with a raw index error. If
duplicates exist in the target database, they get merged by hand before the
migration is re-run.

## Generation

`App\Services\MealGenerator` — no Filament or console dependency, so it is
directly unit-testable and shared by both callers.

```php
public function generateFor(CarbonInterface $date): GenerationResult
public function generateRange(CarbonInterface $from, CarbonInterface $to): GenerationResult
```

`generateFor`, inside a transaction:

1. `$weekday = $date->dayOfWeek`.
2. Find the `Meal` for that date (`whereDate('date', $date)`), or create it.
3. Collect `member_id`s that already have a `MealItem` on that meal.
4. Load `Member::active()` excluding those ids.
5. Load their `MealSchedule` rows for `$weekday`, keyed by `member_id`.
6. Create one `MealItem` per remaining member, taking quantities from the
   schedule or `0` when absent.

Existing rows are never read for their values and never written to, so a
hand-corrected day survives any number of re-runs.

`generateRange` iterates dates inclusively and accumulates results. `from`
later than `to` is an error, not an empty run.

`GenerationResult` is a readonly value object carrying `datesProcessed`,
`mealsCreated`, `itemsCreated` and `membersAlreadyPresent`, plus a
`summary(): string` used verbatim by both the console output and the Filament
notification body.

### Membership is read per date

`generateRange` processes dates in ascending order and re-reads
`Member::active()` for each one. Deactivating a member has no effect on rows
already written for earlier dates in the same run; it simply stops new rows
being created from that point on. Past dates are never revisited.

## Command and schedule

`App\Console\Commands\GenerateMeals`, signature:

```
meals:generate {--date=} {--from=} {--to=}
```

- No options: today.
- `--date`: that single date.
- `--from` with `--to`: inclusive range. Supplying only one of the pair is an
  error.
- `--date` together with `--from`/`--to` is an error.
- Unparseable dates exit non-zero with a readable message.

The command is a thin wrapper: parse, delegate to `MealGenerator`, print
`GenerationResult::summary()`.

Registered in `routes/console.php`:

```php
Schedule::command('meals:generate')->dailyAt('00:05');
```

**Deployment requirement:** the scheduler only fires if the host runs
`* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`. This is
documented in the README. Until that cron entry exists, the manual button is
the working path — which is why both triggers are in the first release.

## UI

### Meal Schedule resource

`App\Filament\Resources\MealSchedules\MealScheduleResource`, model `Member`,
navigation label "Meal Schedule".

Access mirrors the existing role patterns in this codebase:

- `shouldRegisterNavigation()` / `canViewAny()`: an admin, a user with
  `manage_meals`, or any user linked to a member.
- `getEloquentQuery()`: `Member::active()`, further narrowed to
  `where('id', auth()->user()->member->id)` when the viewer is a member without
  `manage_meals`.

A member who also holds `manage_meals` sees every member's schedule, matching
how that permission already opens the whole Meals section.

Deactivated members are excluded from the query, consistent with the rule that
they appear nowhere but the member list. A user whose own member record has
been deactivated therefore sees an empty list rather than an error.

Table columns: member name, and a summary column rendering each meal type with
the days it applies to — `Lunch: Sat, Sun · Dinner: daily`. A member with no
schedule renders `Not configured`.

A row action opens a modal holding a fixed seven-row grid, one row per weekday,
three numeric inputs each. Quantities are `numeric`, `minValue(0)`, and step in
halves. The form loads from existing rows and defaults absent ones to `0`.

Saving delegates to `MealSchedule::syncForMember(Member $member, array $byWeekday)`,
which upserts the seven rows on the `(member_id, weekday)` unique key. Keeping
this off the resource means the form and any future importer share one write
path.

### Generate action on the Meals list

A header action on the Meals list page, "Generate from config", with the same
single-date / date-range choice the existing Copy Meal action uses, so the two
feel alike. It calls `MealGenerator` and reports `summary()` in a notification.
Visible to admins and users with `manage_meals`.

## Report impact

`ReportPage` sums `meal_items`. All-zero rows contribute zero, so per-member
costs and totals are unchanged by this feature. No report code changes.

## Testing

Pest feature tests. `tests/Pest.php` currently has `RefreshDatabase`
commented out for the `Feature` suite; it gets enabled. No factories exist
beyond `UserFactory`, and none are added — tests build `Member`, `Meal` and
`MealSchedule` rows directly, which keeps the fixtures explicit about the
quantities under test.

`tests/Feature/MealGeneratorTest.php`:

1. A configured member gets a row carrying that weekday's exact quantities.
2. The schedule for a *different* weekday is not applied.
3. A member with no schedule gets an all-zero row.
4. An inactive member gets no row.
5. Re-running leaves a hand-edited row untouched and creates no duplicate.
6. A member created after the first run receives a row on the second run.
7. A range covers every date inclusive of both endpoints.
8. `from` after `to` raises.

`tests/Feature/GenerateMealsCommandTest.php`:

9. No options generates today.
10. `--date` generates that date.
11. `--from`/`--to` generates the range.
12. Malformed or contradictory options exit non-zero and generate nothing.

## Build order

1. Migration + `MealSchedule` model + `Member::mealSchedules()`.
2. `meals.date` unique migration with the duplicate guard.
3. `MealGenerator` + `GenerationResult`, with tests 1–8 driving it.
4. `meals:generate` command + scheduler entry, with tests 9–12.
5. `MealSchedule::syncForMember()`.
6. `MealScheduleResource` (list, summary column, edit-schedule modal, role scoping).
7. "Generate from config" action on the Meals list.
8. README note on the cron entry.

Steps 1–4 deliver working generation with no UI; the app stays usable
throughout.
