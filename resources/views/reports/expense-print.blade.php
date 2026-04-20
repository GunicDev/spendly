<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Report - {{ $expense->name }}</title>
    <style>
        @page {
            margin: 18mm;
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
            font-size: 14px;
            line-height: 1.5;
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
            padding: 24mm 22mm;
            width: calc(100% - 32px);
        }

        .header {
            border-bottom: 2px solid #172033;
            display: flex;
            justify-content: space-between;
            gap: 24px;
            padding-bottom: 18px;
        }

        h1 {
            font-size: 28px;
            line-height: 1.15;
            margin: 0 0 8px;
        }

        h2 {
            border-bottom: 1px solid #d7dde7;
            font-size: 16px;
            margin: 28px 0 12px;
            padding-bottom: 7px;
        }

        .muted {
            color: #5c667a;
        }

        .badge {
            border: 1px solid #d7dde7;
            border-radius: 6px;
            display: inline-block;
            font-weight: 700;
            padding: 4px 8px;
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

        .grid {
            display: grid;
            gap: 14px 24px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .user-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .field {
            border-bottom: 1px solid #edf0f5;
            padding-bottom: 10px;
        }

        .label {
            color: #5c667a;
            display: block;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .value {
            display: block;
            font-size: 15px;
            margin-top: 3px;
            overflow-wrap: anywhere;
        }

        .description {
            border: 1px solid #d7dde7;
            border-radius: 6px;
            margin-top: 8px;
            min-height: 90px;
            padding: 12px;
            white-space: pre-wrap;
        }

        .totals {
            margin-top: 18px;
            width: 100%;
            border-collapse: collapse;
        }

        .totals th,
        .totals td {
            border-bottom: 1px solid #d7dde7;
            padding: 10px 0;
            text-align: right;
        }

        .totals th {
            color: #5c667a;
            font-size: 12px;
            text-align: left;
            text-transform: uppercase;
        }

        .totals .grand-total th,
        .totals .grand-total td {
            border-bottom: 2px solid #172033;
            font-size: 18px;
            font-weight: 700;
        }

        @media (max-width: 640px) {
            .toolbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .grid,
            .header {
                grid-template-columns: 1fr;
                display: grid;
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
            <button class="button" type="button" onclick="window.print()">Print</button>
            <a class="button secondary" href="{{ url()->previous() }}">Back</a>
        </div>
    </div>

    <main class="sheet">
        <header class="header">
            <div>
                <h1>Entry report</h1>
                <div class="muted">Generated {{ now()->format('d.m.Y. H:i') }}</div>
            </div>
            <div>
                <span class="badge {{ $expense->type }}">
                    {{ $expense->type === 'income' ? 'Income' : 'Expense' }}
                </span>
            </div>
        </header>

        <section>
            <h2>User</h2>
            <div class="grid user-grid">
                <div class="field">
                    <span class="label">Name</span>
                    <span class="value">{{ $expense->user?->name }}</span>
                </div>
                <div class="field">
                    <span class="label">Email</span>
                    <span class="value">{{ $expense->user?->email }}</span>
                </div>
                <div class="field">
                    <span class="label">Preferred currency</span>
                    <span class="value">{{ $currency }}</span>
                </div>
            </div>
        </section>

        <section>
            <h2>Entry</h2>
            <div class="grid">
                <div class="field">
                    <span class="label">Name</span>
                    <span class="value">{{ $expense->name }}</span>
                </div>
                <div class="field">
                    <span class="label">Date</span>
                    <span class="value">{{ $expense->date?->format('d.m.Y.') }}</span>
                </div>
                @if ($expense->type !== 'income')
                    <div class="field">
                        <span class="label">Tax</span>
                        <span class="value">{{ $expense->tax?->tax_rate ? "{$expense->tax->tax_rate}%" : '-' }}</span>
                    </div>
                @endif
            </div>

            <table class="totals">
                @if ($expense->type !== 'income')
                    <tr>
                        <th>Amount without tax</th>
                        <td>{{ $formatMoney($expense->value) }}</td>
                    </tr>
                    <tr>
                        <th>Tax amount</th>
                        <td>{{ $formatMoney($expense->tax_amount) }}</td>
                    </tr>
                @endif
                <tr class="grand-total">
                    <th>Total</th>
                    <td>{{ $formatMoney($expense->amount) }}</td>
                </tr>
            </table>
        </section>

        @if (filled($expense->description))
            <section>
                <h2>Description</h2>
                <div class="description">{{ $expense->description }}</div>
            </section>
        @endif
    </main>
</body>
</html>
