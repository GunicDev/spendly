<?php

namespace App\Filament\Pages;

use App\Services\FrankfurterService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class Currencies extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static ?string $navigationLabel = 'Currencies';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'currencies';

    protected string $view = 'filament-panels::pages.page';

    public ?array $data = [];

    /**
     * @var array<string, array{name: string, symbol: string|null}>
     */
    public array $currencies = [];

    /**
     * @var array<string, float>
     */
    public array $rates = [];

    public ?string $rateDate = null;

    public ?string $error = null;

    public function getTitle(): string
    {
        return 'Currencies';
    }

    public function mount(FrankfurterService $frankfurter): void
    {
        $this->currencies = $frankfurter->currencies();

        $this->form->fill([
            'amount' => 1,
            'baseCurrency' => Auth::user()?->preferred_currency ?? 'BAM',
        ]);

        $this->loadRates();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns([
                'default' => 1,
                'md' => 2,
            ])
            ->components([
                TextInput::make('amount')
                    ->label('Amount')
                    ->numeric()
                    ->minValue(0)
                    ->live(debounce: 300)
                    ->afterStateUpdated(fn (): null => $this->resetTable()),
                Select::make('baseCurrency')
                    ->label('Convert from')
                    ->options(fn (): array => app(FrankfurterService::class)->currencyOptions())
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (?string $state): void {
                        $this->data['baseCurrency'] = strtoupper($state ?? 'BAM');
                        $this->loadRates();
                        $this->resetTable();
                    }),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(
                fn (
                    ?string $search,
                    ?string $sortColumn,
                    ?string $sortDirection,
                    int $page,
                    int | string $recordsPerPage,
                ): LengthAwarePaginator => $this->currencyRows(
                    search: $search,
                    sortColumn: $sortColumn,
                    sortDirection: $sortDirection,
                    page: $page,
                    recordsPerPage: $recordsPerPage,
                ),
            )
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Currency')
                    ->sortable(),
                TextColumn::make('rate')
                    ->label('Rate')
                    ->formatStateUsing(
                        fn (?float $state): string => $state !== null ? number_format($state, 6) : 'No rate',
                    )
                    ->sortable(),
                TextColumn::make('converted')
                    ->label('Converted amount')
                    ->formatStateUsing(
                        fn (?float $state, array $record): string => $state !== null
                            ? number_format($state, 2) . " {$record['code']}"
                            : 'Unavailable',
                    )
                    ->sortable(),
            ])
            ->searchable()
            ->defaultSort('code')
            ->paginated([10, 25, 50]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make([
                    'default' => 1,
                    'md' => 3,
                ])
                    ->schema([
                        Section::make('Base currency')
                            ->schema([
                                Text::make(fn (): string => $this->baseCurrencyLabel()),
                            ]),
                        Section::make('Latest rate date')
                            ->schema([
                                Text::make(fn (): string => $this->rateDate ?? 'Unavailable'),
                            ]),
                        Section::make('Tracked currencies')
                            ->schema([
                                Text::make(fn (): string => (string) $this->trackedCurrenciesCount()),
                            ]),
                    ]),
                Section::make('Currency converter')
                    ->schema([
                        EmbeddedSchema::make('form'),
                        Actions::make([
                            Action::make('refreshRates')
                                ->label('Refresh rates')
                                ->action('refreshRates'),
                        ]),
                        Text::make(fn (): ?string => $this->error)
                            ->color('danger')
                            ->hidden(fn (): bool => blank($this->error)),
                    ]),
                Section::make('Rates')
                    ->schema([
                        EmbeddedTable::make(),
                    ]),
            ]);
    }

    public function refreshRates(): void
    {
        $this->loadRates(refresh: true);
        $this->resetTable();

        Notification::make()
            ->title('Currency rates refreshed')
            ->success()
            ->send();
    }

    /**
     * @return LengthAwarePaginator<
     *     string,
     *     array{code: string, name: string, symbol: string|null, rate: float|null, converted: float|null}
     * >
     */
    public function currencyRows(
        ?string $search = null,
        ?string $sortColumn = null,
        ?string $sortDirection = null,
        int $page = 1,
        int | string $recordsPerPage = 10,
    ): LengthAwarePaginator {
        $amount = is_numeric($this->data['amount'] ?? null) ? (float) $this->data['amount'] : 1.0;

        return app(FrankfurterService::class)->paginatedCurrencyRows(
            baseCurrency: $this->getBaseCurrency(),
            rates: $this->rates,
            amount: $amount,
            search: $search,
            sortColumn: $sortColumn,
            sortDirection: $sortDirection,
            page: $page,
            perPage: $recordsPerPage,
        );
    }

    public function baseCurrencyLabel(): string
    {
        $baseCurrency = $this->getBaseCurrency();
        $name = $this->currencies[$baseCurrency]['name'] ?? $baseCurrency;

        return "{$baseCurrency} - {$name}";
    }

    private function loadRates(bool $refresh = false): void
    {
        $data = app(FrankfurterService::class)->latestRates($this->getBaseCurrency(), refresh: $refresh);

        $this->rates = $data['rates'];
        $this->rateDate = $data['date'];
        $this->error = empty($this->rates)
            ? 'Rates are not available right now. Try refreshing again in a moment.'
            : null;
    }

    private function getBaseCurrency(): string
    {
        return strtoupper($this->data['baseCurrency'] ?? Auth::user()?->preferred_currency ?? 'BAM');
    }

    private function trackedCurrenciesCount(): int
    {
        return max(count($this->currencies) - 1, 0);
    }
}
