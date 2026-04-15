<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

class ExpenseOverview extends BaseWidget
{
    protected ?string $pollingInterval = '5s';

    protected function getStats(): array
    {
        $query = Expense::query()
            ->where('user_id', Auth::id());

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
}
