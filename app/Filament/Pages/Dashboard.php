<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ExpenseOverview;
use App\Models\Expense;
use App\Models\Tax;
use App\Services\FrankfurterService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class Dashboard extends BaseDashboard implements Tables\Contracts\HasTable
{
    use HasFiltersAction;
    use Tables\Concerns\InteractsWithTable {
        HasFiltersAction::normalizeTableFilterValuesFromQueryString insteadof Tables\Concerns\InteractsWithTable;
    }

    public ?array $data = [];

    public bool $isExpenseFormVisible = true;

    protected Width | string | null $maxContentWidth = Width::Full;

    protected static ?string $navigationLabel = 'Dashboard';

    private const STORAGE_CURRENCY = 'BAM';

    public function getTitle(): string
    {
        return 'Dashboard';
    }

    public function mount(): void
    {
        $this->form->fill([
            'type' => 'expense',
            'date' => now()->toDateString(),
            'tax_id' => $this->getDefaultTaxId(),
            'tax_amount' => 0,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->model(Expense::class)
            ->statePath('data')
            ->columns(1)
            ->components($this->getExpenseFormSchema());
    }

    public function create(): void
    {
        $data = $this->preferredCurrencyAmountsToStoredAmounts(
            $this->calculateExpenseAmounts($this->form->getState()),
        );

        Expense::create([
            ...$data,
            'user_id' => Auth::id(),
        ]);

        $this->form->fill([
            'type' => 'expense',
            'date' => now()->toDateString(),
            'tax_id' => $this->getDefaultTaxId(),
            'tax_amount' => 0,
        ]);

        $this->resetTable();
        $this->dispatch('expense-created');

        Notification::make()
            ->title('Entry added')
            ->success()
            ->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getExpensesQuery())
            ->emptyStateHeading('No expenses added')
            ->emptyStateDescription('Add your first income or expense using the form on the left.')
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
                    ->label('Value')
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
            ->recordActions([
                EditAction::make()
                    ->schema($this->getExpenseFormSchema())
                    ->modalHeading('Edit entry')
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
                Grid::make([
                    'default' => 1,
                    'lg' => 3,
                ])
                    ->schema([
                        Section::make('Add income or expense')
                            ->schema([
                                EmbeddedSchema::make('form'),
                                Actions::make([
                                    Action::make('create')
                                        ->label('Add entry')
                                        ->submit(null)
                                        ->action('create'),
                                ]),
                            ])
                            ->columnSpan([
                                'default' => 1,
                                'lg' => 1,
                            ])
                            ->extraAttributes([
                                'class' => 'spendly-dashboard-form-section spendly-dashboard-equal-height',
                            ])
                            ->hidden(fn (): bool => ! $this->isExpenseFormVisible),
                        Section::make()
                            ->schema([
                                EmbeddedTable::make(),
                            ])
                            ->extraAttributes(fn (): array => [
                                'class' => $this->isExpenseFormVisible
                                    ? 'spendly-dashboard-table-section spendly-dashboard-equal-height'
                                    : 'spendly-dashboard-table-section',
                            ])
                            ->columnSpan(fn (): array => [
                                'default' => 1,
                                'lg' => $this->isExpenseFormVisible ? 2 : 'full',
                            ]),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleExpenseForm')
                ->label(fn (): string => $this->isExpenseFormVisible ? 'Hide form' : 'Show form')
                ->icon(fn (): string => $this->isExpenseFormVisible ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                ->color('gray')
                ->action(fn (): bool => $this->isExpenseFormVisible = ! $this->isExpenseFormVisible),
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
                    Grid::make([
                        'default' => 1,
                        'md' => 2,
                    ])
                        ->schema([
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
                ->live()
                ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                    if ($state === 'income') {
                        $set('tax_id', null);
                    }

                    $this->updateCalculatedAmountFields($get, $set);
                })
                ->required(),
                  TextInput::make('name')
                ->label('Name')
                ->maxLength(255)
                ->required(),
            Grid::make([
                'default' => 1,
                'md' => 2,
            ])
                ->schema([
                    TextInput::make('value')
                        ->label('Value')
                        ->inputMode('decimal')
                        ->numeric()
                        ->prefix(fn (): string => $this->getPreferredCurrency())
                        ->helperText('Enter the amount before tax in your preferred currency.')
                        ->minValue(0.01)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set): null => $this->updateCalculatedAmountFields($get, $set))
                        ->required(),
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
                ]),
            Grid::make([
                'default' => 1,
                'md' => 2,
            ])
                ->schema([
                    TextInput::make('tax_amount')
                        ->label('Tax amount')
                        ->inputMode('decimal')
                        ->numeric()
                        ->prefix(fn (): string => $this->getPreferredCurrency())
                        ->disabled()
                        ->dehydrated(false)
                        ->hidden(fn (Get $get): bool => $get('type') !== 'expense'),
                    TextInput::make('amount')
                        ->label('Total amount')
                        ->inputMode('decimal')
                        ->numeric()
                        ->prefix(fn (): string => $this->getPreferredCurrency())
                        ->disabled()
                        ->dehydrated(false),
                ]),
            Textarea::make('description')
                ->label('Description')
                ->rows(4)
                ->maxLength(1000),
            DatePicker::make('date')
                ->label('Date')
                ->required(),
        ];
    }

    protected function formatMoney(float | int | string $amount): string
    {
        $currency = $this->getPreferredCurrency();
        $convertedAmount = app(FrankfurterService::class)->convert((float) $amount, self::STORAGE_CURRENCY, $currency) ?? (float) $amount;

        return number_format($convertedAmount, 2) . " {$currency}";
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
            'value' => $get('value'),
            'tax_id' => $get('tax_id'),
        ]);

        if (($data['type'] ?? null) === 'income') {
            $set('tax_id', null);
        }

        $set('tax_amount', $data['tax_amount'] ?? 0);
        $set('amount', $data['amount'] ?? null);

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function calculateExpenseAmounts(array $data): array
    {
        if (! isset($data['value']) || ! is_numeric($data['value'])) {
            return $data;
        }

        $value = (float) $data['value'];
        $taxRate = 0.0;

        if (($data['type'] ?? 'expense') === 'income') {
            $data['tax_id'] = null;
        } elseif (isset($data['tax_id']) && filled($data['tax_id'])) {
            $taxRate = (float) (Tax::query()->whereKey($data['tax_id'])->value('tax_rate') ?? 0);
        }

        $taxAmount = round($value * $taxRate / 100, 2);

        $data['value'] = round($value, 2);
        $data['tax_amount'] = $taxAmount;
        $data['amount'] = round($value + $taxAmount, 2);

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
