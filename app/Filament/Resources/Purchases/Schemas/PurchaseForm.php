<?php

namespace App\Filament\Resources\Purchases\Schemas;

use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Enums\PurchaseStatus;
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

class PurchaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Supplier & Dates
                Select::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')->required(),
                        TextInput::make('phone'),
                        TextInput::make('address'),
                        TextInput::make('city'),
                    ])
                    ->columnSpan(2),

                DatePicker::make('purchase_date')
                    ->required()
                    ->default(now())
                    ->columnSpan(1),

                DatePicker::make('expected_date')
                    ->label('Expected Delivery')
                    ->after('purchase_date')
                    ->columnSpan(1),

                // Payment Type - THIS IS THE KEY FIELD
                Select::make('payment_type')
                    ->label('Payment Type')
                    ->options([
                        'cash' => 'Cash',
                        'credit' => 'Credit',
                    ])
                    ->required()
                    ->default('cash')
                    ->live() // ✅ CRITICAL - Makes it reactive
                    ->columnSpan(1),

                Select::make('status')
                    ->options(PurchaseStatus::class)
                    ->required()
                    ->default(PurchaseStatus::PENDING)
                    ->columnSpan(1),

                Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),

                // ✅ PAYMENT SECTION - Shows/hides based on payment_type
                Section::make('💰 Payment Details (Cash Only)')
                    ->schema([
                        Select::make('payment_account_id')
                            ->label('Payment Account')
                            ->options(\App\Models\Account::pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Which account pays for this?'),

                        Select::make('payment_method')
                            ->label('Payment Method')
                            ->options(PaymentMethod::class)
                            ->required()
                            ->default(PaymentMethod::CASH),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->description('Payment will be automatically recorded when you save.')
                    ->visible(fn ($get) => $get('payment_type') === 'cash'), // ✅ KEY LINE

                Hidden::make('user_id')
                    ->default(Auth::id()),

                // Purchase Items
                Section::make('📦 Purchase Items')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Select::make('product_id')
                                    ->relationship('product', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        if ($state) {
                                            $product = \App\Models\Product::find($state);
                                            if ($product) {
                                                $set('unit_price', $product->cost_price);
                                                $quantity = $get('quantity') ?? 1;
                                                $set('total_price', $quantity * $product->cost_price);
                                            }
                                        }
                                    })
                                    ->columnSpan(2),

                                TextInput::make('quantity')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0.01)
                                    ->default(1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        $unitPrice = $get('unit_price') ?? 0;
                                        $set('total_price', $state * $unitPrice);
                                    })
                                    ->columnSpan(1),

                                TextInput::make('unit_price')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->prefix('ETB')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        $quantity = $get('quantity') ?? 1;
                                        $set('total_price', $quantity * $state);
                                    })
                                    ->columnSpan(1),

                                TextInput::make('total_price')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->prefix('ETB')
                                    ->columnSpan(1),
                            ])
                            ->columns(5)
                            ->defaultItems(1)
                            ->columnSpanFull()
                            ->required()
                            ->minItems(1)
                            ->live()
                            ->addActionLabel('+ Add Item'),

                        Placeholder::make('grand_total')
                            ->label('📊 TOTAL AMOUNT')
                            ->content(function ($get): string {
                                $items = $get('items') ?? [];
                                $total = 0;
                                foreach ($items as $item) {
                                    $total += floatval($item['total_price'] ?? 0);
                                }
                                return '🏷️ ETB ' . number_format($total, 2);
                            }),
                    ])
                    ->columnSpanFull(),
            ])
            ->columns(3);
    }
}