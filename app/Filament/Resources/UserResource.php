<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Services\FrankfurterService;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->role === 'admin';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('password')
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->maxLength(255),
                Select::make('role')
                    ->options([
                        'user' => 'User',
                        'admin' => 'Admin',
                    ])
                    ->default('user')
                    ->required(),
                Select::make('preferred_currency')
                    ->label('Preferred currency')
                    ->options(fn (): array => app(FrankfurterService::class)->currencyOptions())
                    ->searchable()
                    ->default('BAM')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->recordUrl(null)
            ->currentSelectionLivewireProperty('selectedTableRecords')
            ->columns([
                TextColumn::make('name')
                    ->extraCellAttributes(fn (User $record): array => static::getSelectableCellAttributes($record))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->extraCellAttributes(fn (User $record): array => static::getSelectableCellAttributes($record))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->extraCellAttributes(fn (User $record): array => static::getSelectableCellAttributes($record))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('preferred_currency')
                    ->label('Currency')
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->extraCellAttributes(fn (User $record): array => static::getSelectableCellAttributes($record))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        'user' => 'User',
                        'admin' => 'Admin',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function getSelectableCellAttributes(User $record): array
    {
        return [
            'style' => 'cursor: pointer; transition: background-color 150ms ease;',
            'x-on:click' => "toggleSelectedRecord('{$record->getKey()}')",
            'x-on:mouseenter' => "\$el.closest('tr').querySelectorAll('td').forEach((cell) => cell.style.backgroundColor = 'rgba(107, 114, 128, 0.08)')",
            'x-on:mouseleave' => "\$el.closest('tr').querySelectorAll('td').forEach((cell) => cell.style.backgroundColor = '')",
        ];
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
