<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Services\FrankfurterService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ExpensePeriodReportPrintController extends Controller
{
    private const STORAGE_CURRENCY = 'BAM';

    public function __invoke(Request $request, FrankfurterService $frankfurterService): View|Response
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

        $formatMoney = fn (float|int|string $amount): string => $this->formatMoney(
            $amount,
            $currency,
            $frankfurterService,
        );

        if ($request->query('download') === 'pdf') {
            $pdf = $this->buildPdfReport(
                user: $user,
                expenses: $expenses,
                from: $validated['from'],
                to: $validated['to'],
                type: $type,
                currency: $currency,
                incomeTotal: $incomeTotal,
                expenseTotal: $expenseTotal,
                balance: $incomeTotal - $expenseTotal,
                formatMoney: $formatMoney,
            );

            $filename = "spendly-report-{$validated['from']}-{$validated['to']}.pdf";

            return response($pdf, 200, [
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Content-Type' => 'application/pdf',
            ]);
        }

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
            'formatMoney' => $formatMoney,
        ]);
    }

    private function formatMoney(float|int|string $amount, string $currency, FrankfurterService $frankfurterService): string
    {
        $convertedAmount = $frankfurterService->convert((float) $amount, self::STORAGE_CURRENCY, $currency) ?? (float) $amount;

        return number_format($convertedAmount, 2)." {$currency}";
    }

    /**
     * @param  Collection<int, Expense>  $expenses
     */
    private function buildPdfReport(
        mixed $user,
        Collection $expenses,
        string $from,
        string $to,
        string $type,
        string $currency,
        float $incomeTotal,
        float $expenseTotal,
        float $balance,
        callable $formatMoney,
    ): string {
        $objects = [];
        $pages = [];
        $current = '';
        $y = 0.0;

        $escape = fn (string $value): string => str_replace(
            ['\\', '(', ')', "\r", "\n"],
            ['\\\\', '\\(', '\\)', ' ', ' '],
            Str::ascii($value),
        );

        $text = function (float $x, float $y, string $value, int $size = 10) use (&$current, $escape): void {
            $current .= "BT /F1 {$size} Tf {$x} {$y} Td ({$escape($value)}) Tj ET\n";
        };

        $line = function (float $x1, float $y1, float $x2, float $y2) use (&$current): void {
            $current .= "{$x1} {$y1} m {$x2} {$y2} l S\n";
        };

        $startPage = function () use (&$current, &$y): void {
            $current = "0.8 w\n";
            $y = 790;
        };

        $finishPage = function () use (&$pages, &$current): void {
            $pages[] = $current;
        };

        $typeLabel = $type === 'all' ? 'All' : ($type === 'income' ? 'Income' : 'Expense');
        $fromLabel = CarbonImmutable::parse($from)->format('d.m.Y.');
        $toLabel = CarbonImmutable::parse($to)->format('d.m.Y.');

        $startPage();
        $text(50, $y, 'Period report', 22);
        $text(395, $y, 'Generated '.now()->format('d.m.Y. H:i'), 10);
        $y -= 22;
        $text(50, $y, "{$fromLabel} - {$toLabel}", 10);
        $y -= 16;
        $text(50, $y, "Type: {$typeLabel}", 10);
        $y -= 16;
        $line(50, $y, 545, $y);

        $y -= 28;
        $text(50, $y, 'User', 14);
        $y -= 20;
        $text(50, $y, 'Name', 8);
        $text(215, $y, 'Email', 8);
        $text(380, $y, 'Preferred currency', 8);
        $y -= 14;
        $text(50, $y, (string) $user->name, 10);
        $text(215, $y, (string) $user->email, 10);
        $text(380, $y, $currency, 10);

        $y -= 34;
        $text(50, $y, 'Summary', 14);
        $y -= 20;
        $text(50, $y, 'Entries', 8);
        $text(170, $y, 'Income', 8);
        $text(290, $y, 'Expenses', 8);
        $text(410, $y, 'Balance', 8);
        $y -= 14;
        $text(50, $y, (string) $expenses->count(), 10);
        $text(170, $y, $formatMoney($incomeTotal), 10);
        $text(290, $y, $formatMoney($expenseTotal), 10);
        $text(410, $y, $formatMoney($balance), 10);

        $y -= 34;
        $text(50, $y, 'Entries', 14);
        $y -= 20;

        $drawTableHeader = function () use (&$y, $text, $line): void {
            $text(50, $y, 'Date', 8);
            $text(105, $y, 'Type', 8);
            $text(165, $y, 'Name', 8);
            $text(305, $y, 'Tax', 8);
            $text(355, $y, 'Base', 8);
            $text(425, $y, 'Tax amount', 8);
            $text(500, $y, 'Total', 8);
            $y -= 7;
            $line(50, $y, 545, $y);
            $y -= 14;
        };

        $drawTableHeader();

        if ($expenses->isEmpty()) {
            $text(50, $y, 'No entries found for this period.', 10);
        }

        foreach ($expenses as $expense) {
            if ($y < 70) {
                $finishPage();
                $startPage();
                $text(50, $y, 'Period report continued', 14);
                $y -= 28;
                $drawTableHeader();
            }

            $isIncome = $expense->type === 'income';
            $text(50, $y, $expense->date?->format('d.m.Y.') ?? '-', 8);
            $text(105, $y, $isIncome ? 'Income' : 'Expense', 8);
            $text(165, $y, Str::limit((string) $expense->name, 24), 8);
            $text(305, $y, $isIncome ? '-' : ($expense->tax?->tax_rate ? "{$expense->tax->tax_rate}%" : '-'), 8);
            $text(355, $y, $isIncome ? '-' : $formatMoney($expense->value), 8);
            $text(425, $y, $isIncome ? '-' : $formatMoney($expense->tax_amount), 8);
            $text(500, $y, $formatMoney($expense->amount), 8);
            $y -= 12;
            $line(50, $y, 545, $y);
            $y -= 10;
        }

        $finishPage();

        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Kids ['.collect(range(0, count($pages) - 1))->map(fn (int $index): string => (3 + ($index * 3)).' 0 R')->implode(' ').'] /Count '.count($pages).' >>';

        foreach ($pages as $page) {
            $contentObjectNumber = count($objects) + 2;
            $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 {$contentObjectNumber} 0 R >> >> /Contents ".($contentObjectNumber + 1).' 0 R >>';
            $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
            $objects[] = '<< /Length '.strlen($page)." >>\nstream\n{$page}endstream";
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }
}
