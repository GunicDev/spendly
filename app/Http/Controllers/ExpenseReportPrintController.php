<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Services\FrankfurterService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseReportPrintController extends Controller
{
    private const STORAGE_CURRENCY = 'BAM';

    public function __invoke(Request $request, Expense $expense, FrankfurterService $frankfurterService): View
    {
        abort_unless($request->user() && $expense->user_id === $request->user()->id, 404);

        $expense->loadMissing(['tax', 'user']);

        $currency = $request->user()->preferred_currency ?? self::STORAGE_CURRENCY;

        return view('reports.expense-print', [
            'autoPrint' => $request->boolean('print'),
            'currency' => $currency,
            'expense' => $expense,
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
