<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaxResource\Pages;
use App\Models\Tax;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class TaxResource extends Resource
{
    protected static ?string $model = Tax::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::CurrencyDollar;

    protected static ?string $recordTitleAttribute = 'tax_name';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->role === 'admin';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('tax_name')
                    ->label('Tax Name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('tax_rate')
                    ->label('Tax Rate (%)')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->maxValue(100)
                    ->step(0.01),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('tax_name')
            ->recordUrl(null)
            ->recordAction(null)
            ->currentSelectionLivewireProperty('selectedTableRecords')
            ->columns([
                TextColumn::make('tax_name')
                    ->extraCellAttributes(fn (Tax $record): array => static::getSelectableCellAttributes($record))
                    ->label('Tax Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tax_rate')
                    ->extraCellAttributes(fn (Tax $record): array => static::getSelectableCellAttributes($record))
                    ->label('Tax Rate (%)')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('tax_rate')
                    ->label('Tax Rate (%)')
                    ->options([
                        '0' => '0%',
                        '5' => '5%',
                        '10' => '10%',
                        '15' => '15%',
                        '20' => '20%',
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->modal(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    protected static function getSelectableCellAttributes(Tax $record): array
    {
        return [
            'style' => 'cursor: pointer; transition: background-color 150ms ease;',
            'x-on:click' => "toggleSelectedRecord('{$record->getKey()}')",
            'x-on:mouseenter' => "\$el.closest('tr').querySelectorAll('td').forEach((cell) => cell.style.backgroundColor = 'rgba(107, 114, 128, 0.08)')",
            'x-on:mouseleave' => "\$el.closest('tr').querySelectorAll('td').forEach((cell) => cell.style.backgroundColor = '')",
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTaxes::route('/'),
        ];
    }
}
