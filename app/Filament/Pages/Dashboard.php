<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ExpenseOverview;
use App\Models\Expense;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
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
    use Tables\Concerns\InteractsWithTable;

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
                    ->formatStateUsing(fn (string $state): string => number_format((float) $state, 2) . ' KM')
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
                ->prefix('KM')
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
}
