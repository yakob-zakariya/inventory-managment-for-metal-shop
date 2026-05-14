<?php

namespace App\Filament\Resources\StockMovements\Schemas;

use App\Enums\StockMovementType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class StockMovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->relationship('product', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),

                Select::make('type')
                    ->options(StockMovementType::class)
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        // Auto-set quantity fields based on type
                        if (in_array($state, ['purchase', 'adjustment'])) {
                            $set('quantity_out', 0);
                        } elseif (in_array($state, ['sale', 'damage'])) {
                            $set('quantity_in', 0);
                        }
                    }),

                DatePicker::make('transaction_date')
                    ->required()
                    ->default(now()),

                TextInput::make('quantity_in')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->suffix('units')
                    ->helperText('For purchases and stock increases'),

                TextInput::make('quantity_out')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->suffix('units')
                    ->helperText('For sales, damage, and stock decreases'),

                Textarea::make('notes')
                    ->rows(3)
                    ->columnSpanFull()
                    ->helperText('Explain the reason for this stock movement'),

                Hidden::make('user_id')
                    ->default(Auth::id()),
            ]);
    }
}
