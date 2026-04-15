<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use Carbon\CarbonImmutable;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

class ExpenseOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    protected ?string $pollingInterval = '3s';

    protected function getStats(): array
    {
        $query = $this->getFilteredExpenseQuery();

        $income = (clone $query)
            ->where('type', 'income')
            ->sum('amount');

        $expense = (clone $query)
            ->where('type', 'expense')
            ->sum('amount');

        $balance = $income - $expense;

        return [
            Stat::make('Income', $this->formatMoney($income))
                ->color('success'),
            Stat::make('Expenses', $this->formatMoney($expense))
                ->color('danger'),
            Stat::make('Balance', $this->formatMoney($balance))
                ->color($balance >= 0 ? 'success' : 'danger'),
        ];
    }

    #[On('expense-created')]
    #[On('expense-updated')]
    #[On('expense-deleted')]
    public function refreshStats(): void {}

    protected function formatMoney(float | int | string $amount): string
    {
        return number_format((float) $amount, 2) . ' KM';
    }

    protected function getFilteredExpenseQuery(): Builder
    {
        $query = Expense::query()
            ->where('user_id', Auth::id());

        $filters = $this->pageFilters ?? [];

        return match ($filters['period'] ?? 'all') {
            'month' => $this->applyMonthFilter($query, $filters['month'] ?? null),
            'range' => $this->applyDateRangeFilter(
                $query,
                $filters['startDate'] ?? null,
                $filters['endDate'] ?? null,
            ),
            default => $query,
        };
    }

    protected function applyMonthFilter(Builder $query, ?string $month): Builder
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $month ?? '')) {
            return $query;
        }

        [$year, $monthNumber] = array_map('intval', explode('-', $month));

        if (! checkdate($monthNumber, 1, $year)) {
            return $query;
        }

        $date = CarbonImmutable::createFromFormat('Y-m-d', "{$month}-01");

        return $query->whereBetween('date', [
            $date->startOfMonth()->toDateString(),
            $date->endOfMonth()->toDateString(),
        ]);
    }

    protected function applyDateRangeFilter(Builder $query, ?string $startDate, ?string $endDate): Builder
    {
        return $query
            ->when($startDate, fn (Builder $query): Builder => $query->whereDate('date', '>=', $startDate))
            ->when($endDate, fn (Builder $query): Builder => $query->whereDate('date', '<=', $endDate));
    }
}
