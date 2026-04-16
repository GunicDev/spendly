<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ExpenseOverview;
use App\Models\Expense;
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
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Dashboard extends BaseDashboard implements Tables\Contracts\HasTable
{
    use HasFiltersAction;
    use Tables\Concerns\InteractsWithTable {
        HasFiltersAction::normalizeTableFilterValuesFromQueryString insteadof Tables\Concerns\InteractsWithTable;
    }

    public ?array $data = [];

    protected static ?string $navigationLabel = 'Dashboard';

    public function getTitle(): string
    {
        return 'Dashboard';
    }

    public function mount(): void
    {
        $this->form->fill([
            'type' => 'expense',
            'date' => now()->toDateString(),
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
        $data = $this->form->getState();

        Expense::create([
            ...$data,
            'user_id' => Auth::id(),
        ]);

        $this->form->fill([
            'type' => 'expense',
            'date' => now()->toDateString(),
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
                    ->color(fn (string $state): string => $state === 'income' ? 'success' : 'danger'),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),               
                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn (string $state): string => $this->formatMoney($state))
                    ->sortable(),
                TextColumn::make('date')
                    ->label('Date')
                    ->date('d.m.Y.')
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Description')
                    ->limit(38)
                    ->toggleable(),
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
                            ]),
                        Section::make()
                            ->schema([
                                EmbeddedTable::make(),
                            ])
                            ->columnSpan([
                                'default' => 1,
                                'lg' => 2,
                            ]),
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
                ->required(),
            TextInput::make('amount')
                ->label('Amount')
                ->inputMode('decimal')
                ->numeric()
                ->prefix('BAM')
                ->helperText('Entries are saved in BAM and shown in your preferred currency.')
                ->minValue(0.01)
                ->required(),
            TextInput::make('name')
                ->label('Name')
                ->maxLength(255)
                ->required(),
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
        $convertedAmount = app(FrankfurterService::class)->convert((float) $amount, 'BAM', $currency) ?? (float) $amount;

        return number_format($convertedAmount, 2) . " {$currency}";
    }

    protected function getPreferredCurrency(): string
    {
        return Auth::user()?->preferred_currency ?? 'BAM';
    }
}
