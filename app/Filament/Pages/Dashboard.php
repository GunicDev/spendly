<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ExpenseOverview;
use App\Models\Expense;
use App\Models\Tax;
use App\Services\FrankfurterService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Js;
use Illuminate\Validation\ValidationException;

class Dashboard extends BaseDashboard implements Tables\Contracts\HasTable
{
    use HasFiltersAction;
    use Tables\Concerns\InteractsWithTable {
        HasFiltersAction::normalizeTableFilterValuesFromQueryString insteadof Tables\Concerns\InteractsWithTable;
    }

    protected Width|string|null $maxContentWidth = Width::Full;

    protected static ?string $navigationLabel = 'Dashboard';

    private const STORAGE_CURRENCY = 'BAM';

    public function getTitle(): string
    {
        return 'Dashboard';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getExpensesQuery())
            ->emptyStateHeading('No expenses added')
            ->emptyStateDescription('Add your first income or expense from the table action.')
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'income' ? 'Income' : 'Expense')
                    ->color(fn (string $state): string => $state === 'income' ? 'success' : 'danger')
                    ->width('12%'),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->width('18%')
                    ->grow(),
                TextColumn::make('value')
                    ->label('Amount without tax')
                    ->formatStateUsing(fn (string $state): string => $this->formatMoney($state))
                    ->sortable()
                    ->alignEnd()
                    ->width('14%')
                    ->grow(),
                TextColumn::make('tax.tax_rate')
                    ->label('Tax')
                    ->formatStateUsing(fn (?string $state): string => $state ?? '-')
                    ->sortable()
                    ->width('14%')
                    ->grow(),
                TextColumn::make('tax_amount')
                    ->label('Tax amount')
                    ->formatStateUsing(fn (string $state): string => $this->formatMoney($state))
                    ->sortable()
                    ->alignEnd()
                    ->width('14%')
                    ->grow(),
                TextColumn::make('amount')
                    ->label('Total')
                    ->formatStateUsing(fn (string $state): string => $this->formatMoney($state))
                    ->sortable()
                    ->alignEnd()
                    ->width('14%')
                    ->grow(),
                TextColumn::make('date')
                    ->label('Date')
                    ->date('d.m.Y.')
                    ->sortable()
                    ->width('12%')
                    ->grow(),
                TextColumn::make('description')
                    ->label('Description')
                    ->limit(38)
                    ->toggleable()
                    ->width('14%')
                    ->grow(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'income' => 'Income',
                        'expense' => 'Expense',
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add entry')
                    ->model(Expense::class)
                    ->schema($this->getExpenseFormSchema())
                    ->modalHeading('Add entry')
                    ->modalWidth(Width::FourExtraLarge)
                    ->createAnother(false)
                    ->successNotificationTitle('Entry added')
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$this->preferredCurrencyAmountsToStoredAmounts(
                            $this->calculateExpenseAmounts($data),
                        ),
                        'user_id' => Auth::id(),
                    ])
                    ->after(fn () => $this->dispatch('expense-created')),
            ])
            ->recordActions([
                Action::make('report')
                    ->label('Report')
                    ->color('gray')
                    ->modalHeading(fn (Expense $record): string => "Report: {$record->name}")
                    ->modalWidth(Width::FourExtraLarge)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->extraModalFooterActions([
                        Action::make('printReport')
                            ->label('Print')
                            ->color('primary')
                            ->alpineClickHandler(fn (Expense $record): string => $this->getPrintReportClickHandler($record)),
                        Action::make('openReportPdfPreview')
                            ->label('Open PDF preview')
                            ->color('gray')
                            ->url(fn (Expense $record): string => route('expenses.report.print', [
                                'expense' => $record,
                            ]))
                            ->openUrlInNewTab(),
                    ])
                    ->schema($this->getExpenseReportSchema()),
                EditAction::make()
                    ->schema($this->getExpenseFormSchema())
                    ->modalHeading('Edit entry')
                    ->modalWidth(Width::FourExtraLarge)
                    ->successNotificationTitle('Entry updated')
                    ->mutateRecordDataUsing(fn (array $data): array => $this->storedAmountsToPreferredCurrencyAmounts($data))
                    ->mutateDataUsing(fn (array $data): array => $this->preferredCurrencyAmountsToStoredAmounts(
                        $this->calculateExpenseAmounts($data),
                    ))
                    ->after(fn () => $this->dispatch('expense-updated')),
                DeleteAction::make()
                    ->after(fn () => $this->dispatch('expense-deleted')),
            ])
            ->paginated([5, 10, 25]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        EmbeddedTable::make(),
                    ])
                    ->extraAttributes([
                        'class' => 'spendly-dashboard-table-section',
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            FilterAction::make()
                ->label('Filter overview')
                ->modalHeading('Filter overview')
                ->modalWidth('xl')
                ->schema([
                    Select::make('period')
                        ->label('Period')
                        ->options([
                            'all' => 'All time',
                            'month' => 'Month',
                            'range' => 'Date range',
                        ])
                        ->default('all')
                        ->live()
                        ->required()
                        ->columnSpanFull(),
                    Group::make([
                        TextInput::make('month')
                            ->label('Month')
                            ->type('month')
                            ->default(now()->format('Y-m'))
                            ->hidden(fn (Get $get): bool => $get('period') !== 'month')
                            ->columnSpanFull(),
                        DatePicker::make('startDate')
                            ->label('From')
                            ->live()
                            ->maxDate(fn (Get $get): ?string => $get('endDate'))
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                $endDate = $get('endDate');

                                if ($state && $endDate && $state > $endDate) {
                                    $set('endDate', null);
                                }
                            })
                            ->hidden(fn (Get $get): bool => $get('period') !== 'range'),
                        DatePicker::make('endDate')
                            ->label('To')
                            ->live()
                            ->minDate(fn (Get $get): ?string => $get('startDate'))
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                $startDate = $get('startDate');

                                if ($state && $startDate && $state < $startDate) {
                                    $set('startDate', null);
                                }
                            })
                            ->hidden(fn (Get $get): bool => $get('period') !== 'range'),
                    ])
                        ->columns([
                            'default' => 1,
                            'sm' => 2,
                        ])
                        ->columnSpanFull(),
                ]),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ExpenseOverview::class,
        ];
    }

    protected function getExpensesQuery(): Builder
    {
        return Expense::query()
            ->with('tax')
            ->where('user_id', Auth::id());
    }

    protected function getExpenseFormSchema(): array
    {
        return [
            ToggleButtons::make('type')
                ->options([
                    'income' => 'Income',
                    'expense' => 'Expense',
                ])
                ->colors([
                    'income' => 'success',
                    'expense' => 'danger',
                ])
                ->inline()
                ->grouped()
                ->default('expense')
                ->live()
                ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                    if ($state === 'income') {
                        $set('tax_id', null);
                    }

                    $this->updateCalculatedAmountFields($get, $set);
                })
                ->required(),
            Group::make([
                TextInput::make('name')
                    ->label('Name')
                    ->maxLength(255)
                    ->required(),
                TextInput::make('amount')
                    ->label('Total amount')
                    ->inputMode('decimal')
                    ->numeric()
                    ->prefix(fn (): string => $this->getPreferredCurrency())
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set): null => $this->updateCalculatedAmountFields($get, $set))
                    ->required(),
            ])
                ->columns([
                    'default' => 2,
                ]),
            Group::make([
                Select::make('tax_id')
                    ->label('Tax')
                    ->options(fn (): array => Tax::query()
                        ->orderBy('tax_name')
                        ->get()
                        ->mapWithKeys(fn (Tax $tax): array => [$tax->getKey() => $this->formatTaxLabel($tax)])
                        ->all())
                    ->searchable()
                    ->preload()
                    ->default(fn (): ?int => $this->getDefaultTaxId())
                    ->live()
                    ->hidden(fn (Get $get): bool => $get('type') !== 'expense')
                    ->afterStateUpdated(fn (Get $get, Set $set): null => $this->updateCalculatedAmountFields($get, $set)),
                TextInput::make('tax_amount')
                    ->label('Tax amount')
                    ->inputMode('decimal')
                    ->numeric()
                    ->hidden(fn (Get $get): bool => $get('type') !== 'expense')
                    ->prefix(fn (): string => $this->getPreferredCurrency())
                    ->disabled()
                    ->dehydrated(false)
                    ->hidden(fn (Get $get): bool => $get('type') !== 'expense'),
                TextInput::make('value')
                    ->label('Amount without tax')
                    ->inputMode('decimal')
                    ->numeric()->hidden(fn (Get $get): bool => $get('type') !== 'expense')
                    ->prefix(fn (): string => $this->getPreferredCurrency())
                    ->disabled()
                    ->dehydrated(false),
            ])
                ->columns([
                    'default' => 3,
                ]),

            Textarea::make('description')
                ->label('Description')
                ->rows(4)
                ->maxLength(1000),
            DatePicker::make('date')
                ->label('Date')
                ->default(now()->toDateString())
                ->required(),
        ];
    }

    protected function getExpenseReportSchema(): array
    {
        return [
            Section::make('User')
                ->schema([
                    TextEntry::make('report_user_name')
                        ->label('Name')
                        ->state(fn (Expense $record): ?string => $record->user?->name ?? Auth::user()?->name),
                    TextEntry::make('report_user_email')
                        ->label('Email')
                        ->state(fn (Expense $record): ?string => $record->user?->email ?? Auth::user()?->email),
                    TextEntry::make('report_user_currency')
                        ->label('Preferred currency')
                        ->state(fn (): string => $this->getPreferredCurrency())
                        ->badge(),
                ])
                ->columns([
                    'default' => 1,
                    'sm' => 2,
                ]),
            Section::make('Entry')
                ->schema([
                    TextEntry::make('type')
                        ->label('Type')
                        ->formatStateUsing(fn (string $state): string => $state === 'income' ? 'Income' : 'Expense')
                        ->badge()
                        ->color(fn (string $state): string => $state === 'income' ? 'success' : 'danger'),
                    TextEntry::make('name')
                        ->label('Name'),
                    TextEntry::make('date')
                        ->label('Date')
                        ->date('d.m.Y.'),
                    TextEntry::make('tax.tax_rate')
                        ->label('Tax')
                        ->placeholder('-'),
                    TextEntry::make('value')
                        ->label('Amount without tax')
                        ->state(fn (Expense $record): string => $this->formatMoney($record->value)),
                    TextEntry::make('tax_amount')
                        ->label('Tax amount')
                        ->state(fn (Expense $record): string => $this->formatMoney($record->tax_amount)),
                    TextEntry::make('amount')
                        ->label('Total')
                        ->state(fn (Expense $record): string => $this->formatMoney($record->amount)),
                    TextEntry::make('description')
                        ->label('Description')
                        ->placeholder('-')
                        ->columnSpanFull(),
                ])
                ->columns([
                    'default' => 1,
                    'sm' => 2,
                    'lg' => 3,
                ]),
        ];
    }

    protected function formatMoney(float|int|string $amount): string
    {
        $currency = $this->getPreferredCurrency();
        $convertedAmount = app(FrankfurterService::class)->convert((float) $amount, self::STORAGE_CURRENCY, $currency) ?? (float) $amount;

        return number_format($convertedAmount, 2)." {$currency}";
    }

    protected function getPrintReportClickHandler(Expense $record): string
    {
        $url = Js::from(route('expenses.report.print', [
            'expense' => $record,
        ]));

        return <<<JS
            (() => {
                const existingFrame = document.getElementById('spendly-report-print-frame');

                if (existingFrame) {
                    existingFrame.remove();
                }

                const frame = document.createElement('iframe');
                frame.id = 'spendly-report-print-frame';
                frame.src = {$url};
                frame.style.position = 'fixed';
                frame.style.right = '0';
                frame.style.bottom = '0';
                frame.style.width = '1px';
                frame.style.height = '1px';
                frame.style.border = '0';
                frame.style.opacity = '0';
                frame.style.pointerEvents = 'none';

                frame.addEventListener('load', () => {
                    setTimeout(() => {
                        frame.contentWindow.focus();
                        frame.contentWindow.print();
                    }, 250);
                }, { once: true });

                document.body.appendChild(frame);
            })()
            JS;
    }

    protected function getPreferredCurrency(): string
    {
        return Auth::user()?->preferred_currency ?? self::STORAGE_CURRENCY;
    }

    protected function getDefaultTaxId(): ?int
    {
        return Tax::query()
            ->where('is_default', true)
            ->value('id');
    }

    protected function formatTaxLabel(Tax $tax): string
    {
        return "{$tax->tax_name} ({$tax->tax_rate}%)";
    }

    protected function updateCalculatedAmountFields(Get $get, Set $set): null
    {
        $data = $this->calculateExpenseAmounts([
            'type' => $get('type'),
            'amount' => $get('amount'),
            'tax_id' => $get('tax_id'),
        ]);

        if (($data['type'] ?? null) === 'income') {
            $set('tax_id', null);
        }

        $set('tax_amount', $data['tax_amount'] ?? 0);
        $set('value', $data['value'] ?? null);

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function calculateExpenseAmounts(array $data): array
    {
        if (! isset($data['amount']) || ! is_numeric($data['amount'])) {
            return $data;
        }

        $amount = (float) $data['amount'];
        $taxRate = 0.0;

        if (($data['type'] ?? 'expense') === 'income') {
            $data['tax_id'] = null;
        } elseif (isset($data['tax_id']) && filled($data['tax_id'])) {
            $taxRate = (float) (Tax::query()->whereKey($data['tax_id'])->value('tax_rate') ?? 0);
        }

        $value = $taxRate > 0
            ? round($amount / (1 + ($taxRate / 100)), 2)
            : round($amount, 2);

        $data['value'] = $value;
        $data['tax_amount'] = round($amount - $value, 2);
        $data['amount'] = round($amount, 2);

        return $data;
    }

    private function preferredCurrencyAmountsToStoredAmounts(array $data): array
    {
        foreach (['value', 'tax_amount', 'amount'] as $field) {
            if (! isset($data[$field]) || ! is_numeric($data[$field])) {
                continue;
            }

            $convertedAmount = app(FrankfurterService::class)->convert(
                (float) $data[$field],
                $this->getPreferredCurrency(),
                self::STORAGE_CURRENCY,
            );

            if ($convertedAmount === null) {
                throw ValidationException::withMessages([
                    "data.{$field}" => 'Currency conversion is unavailable right now. Try again in a moment.',
                ]);
            }

            $data[$field] = round($convertedAmount, 2);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function storedAmountsToPreferredCurrencyAmounts(array $data): array
    {
        foreach (['value', 'tax_amount', 'amount'] as $field) {
            if (! isset($data[$field]) || ! is_numeric($data[$field])) {
                continue;
            }

            $convertedAmount = app(FrankfurterService::class)->convert(
                (float) $data[$field],
                self::STORAGE_CURRENCY,
                $this->getPreferredCurrency(),
            );

            $data[$field] = round($convertedAmount ?? (float) $data[$field], 2);
        }

        return $data;
    }
}
