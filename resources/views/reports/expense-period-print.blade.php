<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Period report - {{ $from }} to {{ $to }}</title>
    <style>
        @page {
            margin: 16mm;
            size: A4;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #eef1f5;
            color: #172033;
            font-family: Arial, sans-serif;
            font-size: 13px;
            line-height: 1.45;
        }

        .toolbar {
            align-items: center;
            background: #172033;
            color: #fff;
            display: flex;
            gap: 12px;
            justify-content: space-between;
            padding: 14px 24px;
        }

        .toolbar-title {
            font-weight: 700;
        }

        .toolbar-actions {
            display: flex;
            gap: 10px;
        }

        .button {
            background: #f59e0b;
            border: 0;
            border-radius: 6px;
            color: #111827;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
            padding: 9px 14px;
            text-decoration: none;
        }

        .button.secondary {
            background: #fff;
            color: #172033;
        }

        .sheet {
            background: #fff;
            margin: 28px auto;
            max-width: 210mm;
            min-height: 297mm;
            padding: 20mm 18mm;
            width: calc(100% - 32px);
        }

        .header {
            border-bottom: 2px solid #172033;
            display: flex;
            gap: 24px;
            justify-content: space-between;
            padding-bottom: 18px;
        }

        h1 {
            font-size: 27px;
            line-height: 1.15;
            margin: 0 0 8px;
        }

        h2 {
            border-bottom: 1px solid #d7dde7;
            font-size: 16px;
            margin: 26px 0 12px;
            padding-bottom: 7px;
        }

        .muted {
            color: #5c667a;
        }

        .grid {
            display: grid;
            gap: 14px 24px;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .summary-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .field {
            border-bottom: 1px solid #edf0f5;
            padding-bottom: 10px;
        }

        .label {
            color: #5c667a;
            display: block;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .value {
            display: block;
            font-size: 14px;
            margin-top: 3px;
            overflow-wrap: anywhere;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border-bottom: 1px solid #d7dde7;
            padding: 9px 7px;
            text-align: left;
            vertical-align: top;
        }

        th {
            color: #5c667a;
            font-size: 11px;
            text-transform: uppercase;
        }

        .amount {
            text-align: right;
            white-space: nowrap;
        }

        .badge {
            border: 1px solid #d7dde7;
            border-radius: 6px;
            display: inline-block;
            font-weight: 700;
            padding: 3px 7px;
        }

        .badge.income {
            background: #dcfce7;
            border-color: #86efac;
            color: #166534;
        }

        .badge.expense {
            background: #fee2e2;
            border-color: #fca5a5;
            color: #991b1b;
        }

        .empty {
            border: 1px solid #d7dde7;
            border-radius: 6px;
            color: #5c667a;
            padding: 14px;
        }

        @media (max-width: 760px) {
            .toolbar,
            .header {
                align-items: flex-start;
                flex-direction: column;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            .sheet {
                margin: 0;
                min-height: auto;
                padding: 18px;
                width: 100%;
            }
        }

        @media print {
            body {
                background: #fff;
            }

            .header {
                align-items: flex-start;
                display: flex;
                flex-direction: row;
            }

            .grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .summary-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }

            .toolbar {
                display: none;
            }

            .sheet {
                margin: 0;
                max-width: none;
                min-height: auto;
                padding: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <span class="toolbar-title">Report preview</span>
            <span class="muted">Print or save as PDF</span>
        </div>
        <div class="toolbar-actions">
            <a class="button" href="{{ request()->fullUrlWithQuery(['download' => 'pdf']) }}">Download PDF</a>
            <button class="button" type="button" onclick="window.print()">Print</button>
            <a class="button secondary" href="{{ url()->previous() }}">Back</a>
        </div>
    </div>

    <main class="sheet">
        <header class="header">
            <div>
                <h1>Period report</h1>
                <div class="muted">
                    {{ \Carbon\CarbonImmutable::parse($from)->format('d.m.Y.') }}
                    -
                    {{ \Carbon\CarbonImmutable::parse($to)->format('d.m.Y.') }}
                </div>
                <div class="muted">
                    Type: {{ $type === 'all' ? 'All' : ($type === 'income' ? 'Income' : 'Expense') }}
                </div>
            </div>
            <div class="muted">Generated {{ now()->format('d.m.Y. H:i') }}</div>
        </header>

        <section>
            <h2>User</h2>
            <div class="grid">
                <div class="field">
                    <span class="label">Name</span>
                    <span class="value">{{ $user->name }}</span>
                </div>
                <div class="field">
                    <span class="label">Email</span>
                    <span class="value">{{ $user->email }}</span>
                </div>
                <div class="field">
                    <span class="label">Preferred currency</span>
                    <span class="value">{{ $currency }}</span>
                </div>
            </div>
        </section>

        <section>
            <h2>Summary</h2>
            <div class="grid summary-grid">
                <div class="field">
                    <span class="label">Entries</span>
                    <span class="value">{{ $expenses->count() }}</span>
                </div>
                <div class="field">
                    <span class="label">Income</span>
                    <span class="value">{{ $formatMoney($incomeTotal) }}</span>
                </div>
                <div class="field">
                    <span class="label">Expenses</span>
                    <span class="value">{{ $formatMoney($expenseTotal) }}</span>
                </div>
                <div class="field">
                    <span class="label">Balance</span>
                    <span class="value">{{ $formatMoney($balance) }}</span>
                </div>
            </div>
        </section>

        <section>
            <h2>Entries</h2>

            @if ($expenses->isEmpty())
                <div class="empty">No entries found for this period.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Name</th>
                            <th>Tax</th>
                            <th class="amount">Amount without tax</th>
                            <th class="amount">Tax amount</th>
                            <th class="amount">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($expenses as $expense)
                            <tr>
                                <td>{{ $expense->date?->format('d.m.Y.') }}</td>
                                <td>
                                    <span class="badge {{ $expense->type }}">
                                        {{ $expense->type === 'income' ? 'Income' : 'Expense' }}
                                    </span>
                                </td>
                                <td>
                                    <strong>{{ $expense->name }}</strong>
                                    @if (filled($expense->description))
                                        <div class="muted">{{ $expense->description }}</div>
                                    @endif
                                </td>
                                <td>{{ $expense->type === 'income' ? '-' : ($expense->tax?->tax_rate ? "{$expense->tax->tax_rate}%" : '-') }}</td>
                                <td class="amount">{{ $expense->type === 'income' ? '-' : $formatMoney($expense->value) }}</td>
                                <td class="amount">{{ $expense->type === 'income' ? '-' : $formatMoney($expense->tax_amount) }}</td>
                                <td class="amount"><strong>{{ $formatMoney($expense->amount) }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </main>
</body>
</html>
