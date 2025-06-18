<x-filament-panels::page>



    <div>

        <form class="flex" wire:submit.prevent="generateReport">

            <div style="display: flex; gap: 10px;margin-bottom: 10px">
                <x-filament::input.wrapper>
                    <x-filament::input type="date" wire:model="dateFrom" />
                </x-filament::input.wrapper>

                <x-filament::input.wrapper>
                    <x-filament::input type="date" wire:model="dateTo" />
                </x-filament::input.wrapper>
            </div>
            <x-filament::button type="submit" color="success">
                Genarate
            </x-filament::button>

        </form>
    </div>

    <div>
        @if (isset($data) && count($data) > 0)
            Total veriable Cost = {{ $data['totalVeriableExpenses'] }}

            <table border="1" style="border-collapse: collapse;width:100%;text-align: center" class="w-full">
                <tr>
                    <th>Name</th>
                    <th>Balance</th>
                    <th>Total Meal</th>
                    <th>Total Fixed Cost</th>
                    <th>Total Veriable Cost</th>
                    <th>Total Cost</th>
                </tr>


                @php
                    $totalMeal = 0;

                    foreach ($data['members'] as $member) {
                        $totalMeal += $member['totalMeal'];
                    }
                @endphp
                <tbody>
                    @foreach ($data['members'] as $member)
                        <tr>
                            <td>{{ $member['name'] }}</td>
                            <td>{{ $member['balance'] }}</td>
                            <td>{{ $member['totalBreakfast'] }} + {{ $member['totalLunch'] }} +
                                {{ $member['totalDinner'] }}
                                = {{ $member['totalMeal'] }}

                            </td>
                            <td>{{ $member['totalFixedExpenses'] }}</td>
                            <td>{{ number_format(($data['totalVeriableExpenses'] / $totalMeal) * $member['totalMeal'], 2) }}
                            </td>
                            <td>
                                {{ ceil($member['totalFixedExpenses'] + ($data['totalVeriableExpenses'] / $totalMeal) * $member['totalMeal']) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td>Total</td>
                        <td></td>
                        <td>{{ $totalMeal }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>


</x-filament-panels::page>
