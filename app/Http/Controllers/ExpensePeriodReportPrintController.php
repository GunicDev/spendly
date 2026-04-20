<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Services\FrankfurterService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpensePeriodReportPrintController extends Controller
{
    private const STORAGE_CURRENCY = 'BAM';

    public function __invoke(Request $request, FrankfurterService $frankfurterService): View
    {
        abort_unless($request->user(), 404);

        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'type' => ['nullable', 'in:all,expense,income'],
        ]);

        $user = $request->user();
        $currency = $user->preferred_currency ?? self::STORAGE_CURRENCY;
        $type = $validated['type'] ?? 'all';

        $expenses = Expense::query()
            ->with(['tax', 'user'])
            ->where('user_id', $user->id)
            ->whereDate('date', '>=', $validated['from'])
            ->whereDate('date', '<=', $validated['to'])
            ->when($type !== 'all', fn ($query) => $query->where('type', $type))
            ->orderBy('date')
            ->orderBy('name')
            ->get();

        $incomeTotal = (float) $expenses
            ->where('type', 'income')
            ->sum('amount');

        $expenseTotal = (float) $expenses
            ->where('type', 'expense')
            ->sum('amount');

        return view('reports.expense-period-print', [
            'currency' => $currency,
            'expenseTotal' => $expenseTotal,
            'expenses' => $expenses,
            'from' => $validated['from'],
            'incomeTotal' => $incomeTotal,
            'balance' => $incomeTotal - $expenseTotal,
            'to' => $validated['to'],
            'type' => $type,
            'user' => $user,
            'formatMoney' => fn (float|int|string $amount): string => $this->formatMoney(
                $amount,
                $currency,
                $frankfurterService,
            ),
        ]);
    }

    private function formatMoney(float|int|string $amount, string $currency, FrankfurterService $frankfurterService): string
    {
        $convertedAmount = $frankfurterService->convert((float) $amount, self::STORAGE_CURRENCY, $currency) ?? (float) $amount;

        return number_format($convertedAmount, 2)." {$currency}";
    }
}
