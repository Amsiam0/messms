# Mess Management System (messms) - Agent Documentation

## Project Overview

This is a **Mess Management System** built with **Laravel 12** and **Filament 4.0** admin panel. It manages member meals, expenses, payments, and generates reports for a mess/hostel facility.

## Tech Stack

- **Framework**: Laravel 12.0
- **Admin Panel**: Filament 4.0 (TALL stack - Tailwind, Alpine, Livewire, Laravel)
- **Database**: SQLite (development)
- **Authentication**: Session-based (web guard)
- **Permissions**: Spatie Laravel Permission package
- **PHP Version**: 8.2+

## User Roles & Permissions

### Roles
1. **admin** - Full system access
2. **member** - Can only submit expense and payment requests

### Permissions
- `manage_meals` - Can access and manage meals
- `manage_expenses` - Can manage expenses
- `manage_payments` - Can manage payments
- `manage_members` - Can manage members
- `view_reports` - Can view reports

### Permission Logic
- Admins have full access to everything
- Members can only see Expense Requests and Payment Requests sections
- Members can be granted additional permissions (e.g., a member with `manage_meals` can access the Meals section)

## Database Structure

### Core Tables

#### `users`
- `id`, `name`, `email`, `password`
- Relationships:
  - `hasOne(Member)` via `user_id` on members table
  - `belongsToMany(Role)` via Spatie Permission
  - `belongsToMany(Permission)` via Spatie Permission

#### `members`
- `id`, `name`, `balance`, `status`, `user_id` (nullable)
- Relationships:
  - `belongsTo(User)` via `user_id`
  - `hasMany(MealItem)`
  - `hasMany(Payment)`
  - `hasMany(ExpenseRequest)`
  - `hasMany(PaymentRequest)`
  - `belongsToMany(Expense)` via `expense_member` pivot (for fixed cost expenses)

#### `meals`
- `id`, `date`
- Relationships:
  - `hasMany(MealItem)`

#### `meal_items`
- `id`, `meal_id`, `member_id`, `breakfast`, `lunch`, `dinner`
- Tracks how many meals each member consumed (0.5, 1, etc.)
- Relationships:
  - `belongsTo(Meal)`
  - `belongsTo(Member)`

#### `expenses`
- `id`, `note`, `amount`, `is_fixed_cost`
- `is_fixed_cost`: If true, the expense is distributed among selected members
- Relationships:
  - `belongsToMany(Member)` via `expense_member` pivot (effectOn)

#### `payments`
- `id`, `note`, `amount`, `type`, `member_id`
- `type`: 'in' (money in) or 'out' (money out)
- Relationships:
  - `belongsTo(Member)`

#### `expense_requests`
- `id`, `note`, `amount`, `is_fixed_cost`, `member_id`, `status`, `approved_by`, `approved_at`
- `status`: 'pending', 'approved', 'rejected'
- Relationships:
  - `belongsTo(Member)` - who requested
  - `belongsTo(User, 'approved_by')` - who approved (approvedBy)

#### `payment_requests`
- `id`, `note`, `amount`, `type`, `member_id`, `status`, `approved_by`, `approved_at`
- Similar structure to expense_requests
- Relationships:
  - `belongsTo(Member)` - who requested
  - `belongsTo(User, 'approved_by')` - who approved (approvedBy)

### Spatie Permission Tables
- `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`

## Key Features

### 1. Member Management
- Admin can manage members (name, balance, status)
- Members can have linked user accounts for login
- Track member balances

### 2. Meal Management
- Create meals for specific dates
- Track member meal consumption (breakfast, lunch, dinner)
- **Copy Feature**: Can copy a meal to a single date or date range
- Only admins or users with `manage_meals` permission can access

### 3. Expense Management
- Two types of expenses:
  - **Regular Expense**: General mess expense
  - **Fixed Cost Expense**: Distributed among selected members
- When creating expense, admin can optionally create a payment and update member balance

### 4. Payment Management
- Track payments (type: 'in' or 'out')
- Linked to members
- Updates member balances using observers (increment/decrement)

### 5. Request/Approval Workflow
Members can submit requests for:
- **Expense Requests**: Request to add an expense
- **Payment Requests**: Request to record a payment

**Approval Process (Admin Only)**:

#### Expense Request Approval:
- If fixed cost: Admin selects which members are affected
- Admin can optionally create a payment and update member balance
- Creates actual Expense record
- Updates request status to 'approved'

#### Payment Request Approval:
- Admin can choose to update member balance or not
- Creates actual Payment record
- Updates request status to 'approved'

### 6. Reports
- Date range selection
- Shows total expenses for selected period
- Admin only access

### 7. Tabs for Filtering
- Both request tables have tabs: All, Pending, Approved, Rejected
- Badges show count for each status
- Admins see pending request count in navigation badges

## File Structure Conventions

### Filament Resources
Pattern: `app/Filament/Resources/{Entity}/{EntityResource}.php`

Each resource has subdirectories:
- `Pages/` - List, Create, Edit pages
- `Schemas/` - Form definitions
- `Tables/` - Table definitions

Example:
```
app/Filament/Resources/
├── ExpenseRequests/
│   ├── ExpenseRequestResource.php
│   ├── Pages/
│   │   ├── ListExpenseRequests.php
│   │   ├── CreateExpenseRequest.php
│   │   └── EditExpenseRequest.php
│   ├── Schemas/
│   │   └── ExpenseRequestForm.php
│   └── Tables/
│       └── ExpenseRequestsTable.php
```

### Models
Location: `app/Models/`
- All models use `protected $guarded = ['id'];`
- Relationships are defined as methods
- Observers used for balance updates (Payment model)

## Key Workflows

### 1. User Creation Workflow
1. Admin creates user via UserResource
2. Assigns role (admin or member)
3. If member: Links to a Member record
4. If member: Optionally assigns permissions
5. On save: `CreateUser::mutateFormDataBeforeCreate()` stores role, member_id, permissions
6. After save: `CreateUser::afterCreate()` assigns role, permissions, and links member

### 2. Expense Request Approval Workflow
1. Member submits expense request
2. Admin sees request in ExpenseRequests with 'Approve' action
3. Admin clicks approve:
   - If fixed cost: Modal shows member selection (multiple)
   - Shows option to create payment and select member
4. On approval:
   - Creates Expense record
   - If fixed cost: Syncs affected members to expense
   - If make payment: Creates Payment and updates member balance
   - Updates request status to 'approved'

### 3. Payment Request Approval Workflow
1. Member submits payment request
2. Admin sees request with 'Approve' action
3. Admin clicks approve:
   - Modal shows "Update Member Balance" checkbox (default: true)
4. On approval:
   - Creates Payment record
   - If checkbox checked: Updates member balance based on type
   - Updates request status to 'approved'

### 4. Meal Copy Workflow
1. Admin selects existing meal
2. Clicks copy action (document-duplicate icon)
3. Chooses copy type:
   - Single Date: Copy to one specific date
   - Date Range: Copy to multiple dates
4. System:
   - Skips dates that already have meals
   - Creates new Meal records
   - Copies all MealItems with same breakfast/lunch/dinner values
   - Shows notification with count of copied/skipped meals

## Important Patterns

### 1. Query Scoping
Members can only see their own requests:
```php
->modifyQueryUsing(function (Builder $query) {
    if (auth()->user()?->hasRole('member')) {
        $query->where('member_id', auth()->user()?->member?->id);
    }
})
```

### 2. Visibility Control
Admin-only resources:
```php
public static function shouldRegisterNavigation(): bool
{
    return auth()->user()?->hasRole('admin') ?? false;
}
```

Resources with permissions:
```php
public static function shouldRegisterNavigation(): bool
{
    return auth()->user()?->hasRole('admin') ||
           auth()->user()?->hasPermissionTo('manage_meals') ?? false;
}

public static function canViewAny(): bool
{
    return auth()->user()?->hasRole('admin') ||
           auth()->user()?->hasPermissionTo('manage_meals') ?? false;
}
```

### 3. Form Field Visibility
Conditional fields based on role:
```php
Select::make('member_id')
    ->visible(fn() => auth()->user()?->hasRole('member'))
    ->disabled(fn() => auth()->user()?->hasRole('member'))
    ->default(fn() => auth()->user()?->member?->id)
```

### 4. Live Form Updates
Using `->live()` for reactive forms:
```php
Select::make('copy_type')
    ->live()

DatePicker::make('copy_date')
    ->visible(fn ($get) => $get('copy_type') === 'single')
```

### 5. Table Actions with Forms
Actions can have forms for user input:
```php
Action::make('approve')
    ->form([...])
    ->action(function ($record, array $data) {
        // Process approval with form data
    })
```

### 6. Navigation Badges
Show pending counts:
```php
public static function getNavigationBadge(): ?string
{
    if (auth()->user()?->hasRole('admin')) {
        $count = ExpenseRequest::where('status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }
    return null;
}

public static function getNavigationBadgeColor(): ?string
{
    return 'warning';
}
```

### 7. Table Tabs (Filament 4)
Defined in List page class, not table class:
```php
// In ListExpenseRequests.php
public function getTabs(): array
{
    return [
        'all' => Tab::make('All')
            ->badge(fn() => ExpenseRequest::count()),
        'pending' => Tab::make('Pending')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
            ->badge(fn() => ExpenseRequest::where('status', 'pending')->count())
            ->badgeColor('warning'),
    ];
}
```

## Important Notes

1. **User-Member Relationship**: Users and Members are separate. A Member record can exist without a user (can't login), and a User can be admin (not linked to any member).

2. **Balance Updates**: Payment observers handle automatic balance updates. When directly manipulating balances in other contexts, use `increment()` or `decrement()` methods.

3. **Request Status**: Once approved or rejected, requests cannot be edited (EditAction visibility is limited to pending status).

4. **Fixed Cost Distribution**: Fixed cost expenses don't automatically update member balances - they're tracked via the expense_member pivot table for reporting purposes.

5. **Filament 4 Differences**:
   - Tabs must be in List page via `getTabs()` method
   - Use `Schemas` instead of forms
   - Actions namespace changed to `Filament\Actions\`
   - Use `recordActions()` instead of `actions()`

6. **Copy Feature Validation**: The meal copy feature automatically skips dates that already have meals to prevent duplicates.

## Common Tasks

### Adding a New Permission
1. Add to `PermissionsSeeder.php`
2. Run seeder: `php artisan db:seed --class=PermissionsSeeder`
3. Add to UserForm options
4. Update relevant resource's `shouldRegisterNavigation()` and `canViewAny()` methods

### Creating a New Request Type
1. Create migration with: note, amount, member_id, status, approved_by, approved_at
2. Create model with relationships
3. Create Filament resource with form, table, pages
4. Add approve/reject actions to table
5. Add query scoping for members
6. Add navigation badge for admins
7. Add tabs to List page

### Adding Report Calculations
Reports are in: `app/Filament/Pages/ReportPage.php`
Custom view: `resources/views/filament/pages/report-page.blade.php`

## Testing Credentials Setup

To test the system:
1. Create admin user and assign 'admin' role
2. Create member records
3. Create member users and link to members
4. Optionally grant permissions to member users

## Seeders

- `RoleAndUserSeeder.php` - Creates admin and member roles
- `PermissionsSeeder.php` - Creates all permissions

Run with: `php artisan db:seed --class=PermissionsSeeder`
