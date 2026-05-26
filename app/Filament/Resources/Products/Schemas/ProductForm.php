<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\UnitType;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                TextInput::make('name')
                    ->required(),

                Select::make('category_id')
                    ->relationship(name: 'category', titleAttribute: 'name')
                    ->required(),

                Select::make('unit')
                    ->options(UnitType::class)
                    ->required(),

                TextInput::make('purchase_price')
                    ->label('Purchase Price')
                    ->required()
                    ->numeric()
                    ->prefix('ETB')
                    ->helperText('Exact price from supplier'),

                TextInput::make('additional_costs')
                    ->label('Additional Costs')
                    ->numeric()
                    ->prefix('ETB')
                    ->helperText('Optional: Transport, loading, unloading, etc.'),

                TextInput::make('selling_price')
                    ->required()
                    ->numeric()
                    ->prefix('ETB'),

                // Display current stock as read-only (only on edit)
                Placeholder::make('current_stock')
                    ->label('Current Stock')
                    ->content(fn ($record) => $record ? number_format($record->current_stock, 2).' '.$record->unit->label() : '0.00')
                    ->helperText('Stock is managed through Stock Movements')
                    ->hidden(fn ($context) => $context === 'create'), // Hide on create form

                TextInput::make('minimum_stock_alert')
                    ->numeric()
                    ->helperText('Alert when stock falls below this level'),

                FileUpload::make('image')
                    ->image(),
            ]);
    }
}
