<?php

namespace App\Filament\Resources\Sales\Schemas;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Account;
use App\Models\Product;
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
                // Customer & Dates
                Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')->required(),
                        TextInput::make('phone'),
                        TextInput::make('address'),
                        TextInput::make('city'),
                    ])
                    ->helperText('Leave empty for walk-in customers')
                    ->columnSpan(2),

                DatePicker::make('sale_date')
                    ->required()
                    ->default(now())
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
                    ->options(SaleStatus::class)
                    ->required()
                    ->default(SaleStatus::DRAFT)
                    ->columnSpan(1),

                Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),

                // ✅ PAYMENT SECTION - Shows/hides based on payment_type
                Section::make('💰 Payment Details (Cash Only)')
                    ->schema([
                        Select::make('payment_account_id')
                            ->label('Payment Account')
                            ->options(Account::pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Which account receives this payment?'),

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

                // ✅ RECEIVABLE SECTION - Shows when payment_type is 'credit' AND record exists
                Section::make('📋 Receivable Details (Credit)')
                    ->schema([
                        Placeholder::make('receivable_amount')
                            ->label('Total Amount')
                            ->content(fn ($record) => $record && $record->receivable
                                ? 'ETB '.number_format($record->receivable->amount, 2)
                                : 'Not yet created'),

                        Placeholder::make('receivable_paid')
                            ->label('Received Amount')
                            ->content(fn ($record) => $record && $record->receivable
                                ? 'ETB '.number_format($record->receivable->paid_amount, 2)
                                : '-'),

                        Placeholder::make('receivable_remaining')
                            ->label('Remaining Balance')
                            ->content(fn ($record) => $record && $record->receivable
                                ? 'ETB '.number_format($record->receivable->remaining_balance, 2)
                                : '-'),

                        Placeholder::make('receivable_due_date')
                            ->label('Due Date')
                            ->content(fn ($record) => $record && $record->receivable && $record->receivable->due_date
                                ? $record->receivable->due_date->format('M d, Y')
                                : 'Not set'),

                        Placeholder::make('receivable_status')
                            ->label('Status')
                            ->content(function ($record) {
                                if (! $record || ! $record->receivable) {
                                    return '⏳ Pending creation';
                                }

                                $receivable = $record->receivable;

                                if ($receivable->is_fully_paid) {
                                    return '✅ Fully Paid';
                                }

                                if ($receivable->is_overdue) {
                                    return '🔴 Overdue';
                                }

                                return '⏳ Pending';
                            }),
                    ])
                    ->columns(3)
                    ->columnSpanFull()
                    ->description('Receivable will be created automatically when you save.')
                    ->visible(fn ($get) => $get('payment_type') === 'credit'),

                Hidden::make('user_id')
                    ->default(Auth::id()),

                // Sale Items
                Section::make('📦 Sale Items')
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
                                            $product = Product::find($state);
                                            if ($product) {
                                                // Set selling price
                                                $set('unit_price', $product->selling_price);

                                                // Set cost price for profit calculation
                                                $set('cost_price', $product->cost_price);

                                                $quantity = $get('quantity') ?? 1;
                                                $set('total_price', $quantity * $product->selling_price);

                                                // Calculate profit
                                                $profit = ($product->selling_price - $product->cost_price) * $quantity;
                                                $set('profit', $profit);
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
                                        $costPrice = $get('cost_price') ?? 0;

                                        $set('total_price', $state * $unitPrice);

                                        // Calculate profit
                                        $profit = ($unitPrice - $costPrice) * $state;
                                        $set('profit', $profit);
                                    })
                                    ->columnSpan(1),

                                TextInput::make('unit_price')
                                    ->label('Selling Price')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->prefix('ETB')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        $quantity = $get('quantity') ?? 1;
                                        $costPrice = $get('cost_price') ?? 0;

                                        $set('total_price', $quantity * $state);

                                        // Calculate profit
                                        $profit = ($state - $costPrice) * $quantity;
                                        $set('profit', $profit);
                                    })
                                    ->columnSpan(1),

                                TextInput::make('cost_price')
                                    ->label('Cost Price')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->prefix('ETB')
                                    ->helperText('Auto-filled from product')
                                    ->columnSpan(1),

                                TextInput::make('total_price')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->prefix('ETB')
                                    ->columnSpan(1),

                                TextInput::make('profit')
                                    ->label('Profit')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->prefix('ETB')
                                    ->extraAttributes(fn ($state) => [
                                        'style' => ($state ?? 0) >= 0
                                            ? 'color: green; font-weight: bold;'
                                            : 'color: red; font-weight: bold;',
                                    ])
                                    ->helperText('Selling - Cost')
                                    ->columnSpan(1),
                            ])
                            ->columns(7)
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

                                return '🏷️ ETB '.number_format($total, 2);
                            }),

                        Placeholder::make('total_profit')
                            ->label('💰 TOTAL PROFIT')
                            ->content(function ($get): string {
                                $items = $get('items') ?? [];
                                $totalProfit = 0;
                                foreach ($items as $item) {
                                    $totalProfit += floatval($item['profit'] ?? 0);
                                }

                                $color = $totalProfit >= 0 ? 'green' : 'red';

                                return '<span style="color: '.$color.'; font-weight: bold; font-size: 1.1em;">💵 ETB '.number_format($totalProfit, 2).'</span>';
                            })
                            ->extraAttributes(['class' => 'profit-display']),
                    ])
                    ->columnSpanFull(),
            ])
            ->columns(3);
    }
}
