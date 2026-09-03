<x-filament-panels::page>

    {{-- Date range --}}
    <x-filament::section>
        <form wire:submit.prevent="generateReport"
              class="flex flex-col gap-4 sm:flex-row sm:items-end">
            <div class="flex-1">
                <label for="dateFrom" class="mb-1 block text-sm font-medium text-gray-950 dark:text-white">
                    From
                </label>
                <x-filament::input.wrapper>
                    <x-filament::input type="date" id="dateFrom" wire:model="dateFrom" />
                </x-filament::input.wrapper>
            </div>

            <div class="flex-1">
                <label for="dateTo" class="mb-1 block text-sm font-medium text-gray-950 dark:text-white">
                    To
                </label>
                <x-filament::input.wrapper>
                    <x-filament::input type="date" id="dateTo" wire:model="dateTo" />
                </x-filament::input.wrapper>
            </div>

            <x-filament::button type="submit" icon="heroicon-m-arrow-path">
                Generate
            </x-filament::button>
        </form>
    </x-filament::section>

    @if (filled($data))
        {{-- Everything inside #report-capture ends up in the downloaded image. --}}
        <div class="flex justify-end">
            <x-filament::button
                type="button"
                color="gray"
                icon="heroicon-m-camera"
                x-data
                x-on:click="$dispatch('download-report')">
                Save as image
            </x-filament::button>
        </div>

        <div id="report-capture" class="rounded-xl bg-white p-6 dark:bg-gray-900">

            <div class="mb-4">
                <h2 class="text-lg font-bold text-gray-950 dark:text-white">Mess Report</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ \Carbon\Carbon::parse($data['dateFrom'])->format('d M Y') }}
                    &ndash;
                    {{ \Carbon\Carbon::parse($data['dateTo'])->format('d M Y') }}
                </p>
            </div>

            {{-- Headline figures --}}
            <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ([
                    ['Total Variable Cost', '৳' . number_format($data['totalVariableExpenses'], 2)],
                    ['Total Fixed Cost', '৳' . number_format($data['totalFixedExpenses'], 2)],
                    ['Total Meals', rtrim(rtrim(number_format($data['totalMeals'], 2), '0'), '.')],
                    ['Rate / Meal', '৳' . number_format($data['ratePerMeal'], 2)],
                ] as [$label, $value])
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</div>
                        <div class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ $value }}</div>
                    </div>
                @endforeach
            </div>

            @if ($data['totalMeals'] <= 0)
                <p class="mb-4 rounded-lg bg-warning-50 p-3 text-sm text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
                    No meals were recorded in this range, so variable cost cannot be shared out.
                </p>
            @endif

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-3 py-2 text-left font-semibold text-gray-950 dark:text-white">Name</th>
                            <th class="px-3 py-2 text-right font-semibold text-gray-950 dark:text-white">Balance</th>
                            <th class="px-3 py-2 text-center font-semibold text-gray-950 dark:text-white">Meals (B+L+D)</th>
                            <th class="px-3 py-2 text-right font-semibold text-gray-950 dark:text-white">Fixed Cost</th>
                            <th class="px-3 py-2 text-right font-semibold text-gray-950 dark:text-white">Variable Cost</th>
                            <th class="px-3 py-2 text-right font-semibold text-gray-950 dark:text-white">Total Cost</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($data['members'] as $member)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="px-3 py-2 font-medium text-gray-950 dark:text-white">{{ $member['name'] }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-gray-700 dark:text-gray-300">
                                    {{ number_format($member['balance'], 2) }}
                                </td>
                                <td class="px-3 py-2 text-center tabular-nums text-gray-700 dark:text-gray-300">
                                    <span class="text-gray-400 dark:text-gray-500">
                                        {{ $member['breakfast'] }}+{{ $member['lunch'] }}+{{ $member['dinner'] }}
                                    </span>
                                    <span class="font-semibold text-gray-950 dark:text-white">= {{ $member['meals'] }}</span>
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums text-gray-700 dark:text-gray-300">
                                    {{ number_format($member['fixedCost'], 2) }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums text-gray-700 dark:text-gray-300">
                                    {{ number_format($member['variableCost'], 2) }}
                                </td>
                                <td class="px-3 py-2 text-right font-semibold tabular-nums text-gray-950 dark:text-white">
                                    {{ number_format($member['totalCost'], 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>

                    <tfoot>
                        <tr class="border-t-2 border-gray-300 font-bold dark:border-gray-600">
                            <td class="px-3 py-2 text-left text-gray-950 dark:text-white">Total</td>
                            <td class="px-3 py-2"></td>
                            <td class="px-3 py-2 text-center tabular-nums text-gray-950 dark:text-white">
                                {{ $data['totalMeals'] }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums text-gray-950 dark:text-white">
                                {{ number_format($data['totalFixedExpenses'], 2) }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums text-gray-950 dark:text-white">
                                {{ number_format($data['totalVariableExpenses'], 2) }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums text-gray-950 dark:text-white">
                                {{ number_format($data['totalCost'], 2) }}
                            </td>
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

                if (! window.html2canvas) {
                    await new Promise((resolve, reject) => {
                        const tag = document.createElement('script');
                        tag.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
                        tag.onload = resolve;
                        tag.onerror = reject;
                        document.head.appendChild(tag);
                    }).catch(() => null);
                }

                if (! window.html2canvas) {
                    alert('Could not load the image library. Check your connection and try again.');
                    return;
                }

                const dark = document.documentElement.classList.contains('dark');

                const canvas = await window.html2canvas(node, {
                    backgroundColor: dark ? '#111827' : '#ffffff',
                    scale: 2,
                });

                const link = document.createElement('a');
                link.download = `mess-report-{{ $data['dateFrom'] }}-to-{{ $data['dateTo'] }}.png`;
                link.href = canvas.toDataURL('image/png');
                link.click();
            });
        </script>
        @endscript
    @endif

</x-filament-panels::page>
