<x-filament-panels::page>

    {{--
        This page cannot use Tailwind utility classes: no custom Filament theme
        is registered, so the panel ships only Filament's own precompiled CSS
        and arbitrary utilities render as nothing. Styling is therefore scoped
        here, in plain CSS with literal colours - which html2canvas can also
        parse, unlike the oklch() values Filament's stylesheet uses.
    --}}
    @verbatim
        <style>
            .mr-card { background:#fff; color:#111827; border:1px solid #e5e7eb; border-radius:12px; padding:24px; }
            .dark .mr-card { background:#111827; color:#f9fafb; border-color:#374151; }

            .mr-title { font-size:18px; font-weight:700; margin:0; }
            .mr-range { font-size:13px; color:#6b7280; margin:2px 0 20px; }
            .dark .mr-range { color:#9ca3af; }

            .mr-stats { display:flex; flex-wrap:wrap; gap:12px; margin-bottom:20px; }
            .mr-stat { flex:1 1 160px; border:1px solid #e5e7eb; border-radius:10px; padding:12px 14px; }
            .dark .mr-stat { border-color:#374151; }
            .mr-stat-label { font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:#6b7280; }
            .dark .mr-stat-label { color:#9ca3af; }
            .mr-stat-value { font-size:20px; font-weight:700; margin-top:4px; }

            .mr-note { border-radius:10px; padding:10px 14px; margin-bottom:16px; font-size:13px;
                       background:#fffbeb; color:#92400e; border:1px solid #fde68a; }
            .dark .mr-note { background:rgba(245,158,11,.12); color:#fbbf24; border-color:rgba(245,158,11,.3); }

            .mr-scroll { overflow-x:auto; }
            .mr-table { width:100%; border-collapse:collapse; font-size:13px; }
            .mr-table th, .mr-table td { padding:10px 12px; white-space:nowrap; }
            .mr-table thead th { text-align:left; font-weight:600; border-bottom:2px solid #e5e7eb; }
            .dark .mr-table thead th { border-bottom-color:#374151; }
            .mr-table tbody tr { border-bottom:1px solid #f3f4f6; }
            .dark .mr-table tbody tr { border-bottom-color:#1f2937; }
            .mr-table tbody tr:nth-child(even) { background:#fafafa; }
            .dark .mr-table tbody tr:nth-child(even) { background:rgba(255,255,255,.03); }

            /* Qualified with .mr-table so these beat `.mr-table thead th`,
               which would otherwise left-align every header regardless of the
               column's own alignment. */
            .mr-table th.mr-num, .mr-table td.mr-num { text-align:right; font-variant-numeric:tabular-nums; }
            .mr-table th.mr-mid, .mr-table td.mr-mid { text-align:center; font-variant-numeric:tabular-nums; }
            .mr-name { font-weight:500; }
            .mr-muted { color:#9ca3af; }
            .mr-strong { font-weight:700; }
            .mr-neg { color:#dc2626; }
            .dark .mr-neg { color:#f87171; }

            .mr-table tfoot td { border-top:2px solid #d1d5db; font-weight:700; padding-top:12px; }
            .dark .mr-table tfoot td { border-top-color:#4b5563; }

            .mr-actions { display:flex; justify-content:flex-end; gap:8px; margin-bottom:8px; flex-wrap:wrap; }
            .mr-settled { border-radius:10px; padding:10px 14px; margin-bottom:16px; font-size:13px;
                          background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; }
            .dark .mr-settled { background:rgba(16,185,129,.12); color:#6ee7b7; border-color:rgba(16,185,129,.3); }
        </style>
    @endverbatim

    <x-filament::section>
        <form wire:submit.prevent="generateReport" class="fi-fo-field-wrp">
            <div style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end;">
                <div style="flex:1 1 180px;">
                    <label for="dateFrom" style="display:block; font-size:13px; font-weight:500; margin-bottom:4px;">From</label>
                    <x-filament::input.wrapper>
                        <x-filament::input type="date" id="dateFrom" wire:model="dateFrom" />
                    </x-filament::input.wrapper>
                </div>
                <div style="flex:1 1 180px;">
                    <label for="dateTo" style="display:block; font-size:13px; font-weight:500; margin-bottom:4px;">To</label>
                    <x-filament::input.wrapper>
                        <x-filament::input type="date" id="dateTo" wire:model="dateTo" />
                    </x-filament::input.wrapper>
                </div>
                <x-filament::button type="submit" icon="heroicon-m-arrow-path">Generate</x-filament::button>
            </div>
        </form>
    </x-filament::section>

    @if (filled($data))
        <div class="mr-actions">
            <x-filament::button type="button" color="gray" icon="heroicon-m-camera"
                                x-data x-on:click="$dispatch('download-report')">
                Save as image
            </x-filament::button>

            @if (! $settlement && $data['totalCost'] > 0)
                <x-filament::button
                    type="button"
                    color="danger"
                    icon="heroicon-m-banknotes"
                    wire:click="settleReport"
                    wire:loading.attr="disabled"
                    wire:confirm="Charge {{ count($data['members']) }} member(s) a total of {{ number_format($data['totalCost'], 2) }} for {{ \Carbon\Carbon::parse($data['dateFrom'])->format('d M Y') }} to {{ \Carbon\Carbon::parse($data['dateTo'])->format('d M Y') }}?\n\nThis creates an 'out' payment per member and reduces their balance. It can only be done once for this period.">
                    Charge members
                </x-filament::button>
            @endif
        </div>

        <div id="report-capture" class="mr-card">
            <h2 class="mr-title">Mess Report</h2>
            <p class="mr-range">
                {{ \Carbon\Carbon::parse($data['dateFrom'])->format('d M Y') }}
                &ndash;
                {{ \Carbon\Carbon::parse($data['dateTo'])->format('d M Y') }}
            </p>

            <div class="mr-stats">
                @foreach ([
                    ['Total Variable Cost', '৳' . number_format($data['totalVariableExpenses'], 2)],
                    ['Total Fixed Cost', '৳' . number_format($data['totalFixedExpenses'], 2)],
                    ['Total Meals', rtrim(rtrim(number_format($data['totalMeals'], 2), '0'), '.')],
                    ['Rate / Meal', '৳' . number_format($data['ratePerMeal'], 2)],
                ] as [$label, $value])
                    <div class="mr-stat">
                        <div class="mr-stat-label">{{ $label }}</div>
                        <div class="mr-stat-value">{{ $value }}</div>
                    </div>
                @endforeach
            </div>

            @if ($settlement)
                <div class="mr-settled">
                    Settled on {{ $settlement['settled_on'] }}@if ($settlement['settled_by']) by {{ $settlement['settled_by'] }}@endif
                    &mdash; {{ $settlement['members'] }} member(s) charged
                    {{ number_format($settlement['total'], 2) }}.
                </div>
            @endif

            @if ($data['totalMeals'] <= 0)
                <div class="mr-note">
                    No meals were recorded in this range, so variable cost cannot be shared out.
                </div>
            @endif

            <div class="mr-scroll">
                <table class="mr-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th class="mr-num">Balance</th>
                            <th class="mr-mid">Meals (B+L+D)</th>
                            <th class="mr-num">Fixed Cost</th>
                            <th class="mr-num">Variable Cost</th>
                            <th class="mr-num">Total Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($data['members'] as $member)
                            <tr>
                                <td class="mr-name">{{ $member['name'] }}</td>
                                <td class="mr-num {{ $member['balance'] < 0 ? 'mr-neg' : '' }}">
                                    {{ number_format($member['balance'], 2) }}
                                </td>
                                <td class="mr-mid">
                                    <span class="mr-muted">{{ $member['breakfast'] }}+{{ $member['lunch'] }}+{{ $member['dinner'] }}</span>
                                    <span class="mr-strong">= {{ $member['meals'] }}</span>
                                </td>
                                <td class="mr-num">{{ number_format($member['fixedCost'], 2) }}</td>
                                <td class="mr-num">{{ number_format($member['variableCost'], 2) }}</td>
                                <td class="mr-num mr-strong">{{ number_format($member['totalCost'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>Total</td>
                            <td></td>
                            <td class="mr-mid">{{ $data['totalMeals'] }}</td>
                            <td class="mr-num">{{ number_format($data['totalFixedExpenses'], 2) }}</td>
                            <td class="mr-num">{{ number_format($data['totalVariableExpenses'], 2) }}</td>
                            <td class="mr-num">{{ number_format($data['totalCost'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        @script
        <script>
            window.addEventListener('download-report', async () => {
                const node = document.getElementById('report-capture');
                if (! node) return;

                // html2canvas-pro, unlike html2canvas 1.x, understands the
                // oklch() colours Tailwind 4 / Filament emit.
                if (! window.html2canvas) {
                    await new Promise((resolve, reject) => {
                        const tag = document.createElement('script');
                        tag.src = 'https://cdn.jsdelivr.net/npm/html2canvas-pro@1.5.11/dist/html2canvas-pro.min.js';
                        tag.onload = resolve;
                        tag.onerror = reject;
                        document.head.appendChild(tag);
                    }).catch(() => null);
                }

                if (! window.html2canvas) {
                    alert('Could not load the image library. Check your connection and try again.');
                    return;
                }

                try {
                    const dark = document.documentElement.classList.contains('dark');
                    const canvas = await window.html2canvas(node, {
                        backgroundColor: dark ? '#111827' : '#ffffff',
                        scale: 2,
                    });
                    const link = document.createElement('a');
                    link.download = 'mess-report-{{ $data['dateFrom'] }}-to-{{ $data['dateTo'] }}.png';
                    link.href = canvas.toDataURL('image/png');
                    link.click();
                } catch (e) {
                    console.error(e);
                    alert('Could not render the image: ' + e.message);
                }
            });
        </script>
        @endscript
    @endif

</x-filament-panels::page>
