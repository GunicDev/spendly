<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\FrankfurterService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class Profile extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?string $navigationLabel = 'Profile';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'profile';

    protected string $view = 'filament-panels::pages.page';

    public ?array $data = [];

    /**
     * @var array<string, string>
     */
    public array $currencyOptions = [];

    public function getTitle(): string
    {
        return 'Profile';
    }

    public function mount(FrankfurterService $frankfurter): void
    {
        /** @var User $user */
        $user = Auth::user();

        $this->currencyOptions = $frankfurter->currencyOptions();

        $this->form->fill([
            'name' => $user->name,
            'email' => $user->email,
            'role' => ucfirst($user->role),
            'preferredCurrency' => $user->preferred_currency ?? 'BAM',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(1)
            ->components([
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('role')
                    ->label('Role')
                    ->disabled()
                    ->dehydrated(false),
                Select::make('preferredCurrency')
                    ->label('Main currency')
                    ->options(fn (): array => $this->currencyOptions)
                    ->searchable()
                    ->required(),
            ]);
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
                        Section::make('Account')
                            ->schema([
                                Text::make(fn (): string => 'Email: ' . ($this->data['email'] ?? '')),
                                Text::make(fn (): string => 'Role: ' . ($this->data['role'] ?? '')),
                                Text::make(fn (): string => 'Member since: ' . (Auth::user()?->created_at?->format('d.m.Y.') ?? 'Unknown')),
                            ])
                            ->columnSpan([
                                'default' => 1,
                                'lg' => 1,
                            ]),
                        Section::make('Profile settings')
                            ->description('Your preferred currency is used as the main view for converted totals and exchange rates.')
                            ->schema([
                                Form::make([EmbeddedSchema::make('form')])
                                    ->id('profile-form')
                                    ->livewireSubmitHandler('save')
                                    ->footer([
                                        Actions::make([
                                            Action::make('save')
                                                ->label('Save profile')
                                                ->submit('save'),
                                        ]),
                                    ]),
                            ])
                            ->columnSpan([
                                'default' => 1,
                                'lg' => 2,
                            ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        $validCurrencies = array_keys($this->currencyOptions);

        $this->validate([
            'data.name' => ['required', 'string', 'max:255'],
            'data.preferredCurrency' => ['required', 'string', Rule::in($validCurrencies)],
        ]);

        $data = $this->form->getState();

        /** @var User $user */
        $user = Auth::user();

        $user->update([
            'name' => $data['name'],
            'preferred_currency' => $data['preferredCurrency'],
        ]);

        Notification::make()
            ->title('Profile saved')
            ->body('Your preferred currency is now used for converted totals and rates.')
            ->success()
            ->send();
    }
}
