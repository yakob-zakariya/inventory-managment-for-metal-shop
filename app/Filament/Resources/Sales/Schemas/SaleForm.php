<?php

namespace App\Filament\Resources\Sales\Schemas;

use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Enums\SaleStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class SaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')->required(),
                        TextInput::make('phone'),
                        TextInput::make('city'),
                    ])
                    ->helperText('Leave empty for walk-in customers'),

                DatePicker::make('sale_date')
                    ->required()
                    ->default(now()),

                Select::make('payment_type')
                    ->options(PaymentType::class)
                    ->required()
                    ->default(PaymentType::CASH)
                    ->reactive(),

                Select::make('status')
                    ->options(SaleStatus::class)
                    ->required()
                    ->default(SaleStatus::DRAFT),

                Section::make('Payment Details (Cash Only)')
                    ->schema([
                        Select::make('payment_account_id')
                            ->label('Payment Account')
                            ->options(\App\Models\Account::pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->helperText('Required for cash sales'),
                            
                        Select::make('payment_method')
                            ->options(PaymentMethod::class)
                            ->default(PaymentMethod::CASH)
                            ->helperText('Required for cash sales'),
                    ])
                    ->description('Payment will be recorded automatically for cash sales.')
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed(),

                Textarea::make('notes')
                    ->rows(3)
                    ->columnSpanFull(),

                Hidden::make('user_id')
                    ->default(Auth::id()),

                // Sale Items Repeater
                Repeater::make('items')
                    ->relationship()
                    ->schema([
                        Select::make('product_id')
                            ->relationship('product', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                if ($state) {
                                    $product = \App\Models\Product::find($state);
                                    if ($product) {
                                        $set('unit_price', $product->selling_price);
                                        // Recalculate total
                                        $quantity = $get('quantity') ?? 0;
                                        $set('total_price', $quantity * $product->selling_price);
                                    }
                                }
                            }),

                        TextInput::make('quantity')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $unitPrice = $get('unit_price') ?? 0;
                                $set('total_price', $state * $unitPrice);
                            }),

                        TextInput::make('unit_price')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->prefix('$')
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $quantity = $get('quantity') ?? 0;
                                $set('total_price', $quantity * $state);
                            }),

                        TextInput::make('total_price')
                            ->numeric()
                            ->disabled()
                            ->dehydrated()
                            ->prefix('$'),
                    ])
                    ->columns(4)
                    ->defaultItems(1)
                    ->columnSpanFull()
                    ->required()
                    ->minItems(1)
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        // Calculate grand total
                        $total = 0;
                        if (is_array($state)) {
                            foreach ($state as $item) {
                                $total += $item['total_price'] ?? 0;
                            }
                        }
                        $set('grand_total_display', $total);
                    }),

                Placeholder::make('grand_total_display')
                    ->label('Grand Total')
                    ->content(function (callable $get): string {
                        return 'ETB ' . number_format($get('grand_total_display') ?? 0, 2);
                    })
                    ->columnSpanFull(),
            ]);
    }
}
