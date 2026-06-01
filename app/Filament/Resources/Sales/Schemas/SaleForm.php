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
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Grid;
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

                // Payment Type
                Select::make('payment_type')
                    ->label('Payment Type')
                    ->options([
                        'cash' => 'Cash',
                        'credit' => 'Credit',
                    ])
                    ->required()
                    ->default('cash')
                    ->live()
                    ->columnSpan(1)
                    ->helperText('Cash: Payment recorded immediately. Credit: Receivable created for later collection.'),

                Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),

                // ✅ PAYMENT SECTION
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
                    ->visible(fn ($get) => $get('payment_type') === 'cash'),

                // ✅ RECEIVABLE SECTION
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

                // ✅ IMPROVED: Sale Items - CLEANER LAYOUT (5 columns instead of 7)
                Section::make('📦 Sale Items')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                // ROW 1: Main transaction fields
                                Select::make('product_id')
                                    ->label('Product')
                                    ->relationship('product', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateHydrated(function ($state, $set, $get) {
                                        // When editing, populate cost_price and item_profit
                                        if ($state) {
                                            $product = Product::find($state);
                                            if ($product) {
                                                $set('cost_price', $product->total_cost);

                                                $quantity = $get('quantity') ?? 0;
                                                $unitPrice = $get('unit_price') ?? 0;
                                                $profit = ($unitPrice - $product->total_cost) * $quantity;
                                                $set('item_profit', $profit);
                                            }
                                        }
                                    })
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        if ($state) {
                                            $product = Product::find($state);
                                            if ($product) {
                                                // Set prices
                                                $set('unit_price', $product->selling_price);
                                                $set('cost_price', $product->total_cost);

                                                $quantity = $get('quantity') ?? 1;
                                                $total = $quantity * $product->selling_price;
                                                $set('total_price', $total);

                                                // Calculate profit
                                                $profit = ($product->selling_price - $product->total_cost) * $quantity;
                                                $set('item_profit', $profit);
                                            }
                                        }
                                    })
                                    ->columnSpan(2),

                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0.01)
                                    ->default(1)
                                    ->live(onBlur: true)
                                    ->helperText(function ($get): ?string {
                                        $productId = $get('product_id');
                                        if (! $productId) {
                                            return null;
                                        }

                                        $product = Product::find($productId);
                                        if (! $product) {
                                            return null;
                                        }

                                        $stock = $product->current_stock ?? 0;

                                        return "Available: {$stock}";
                                    })
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        $unitPrice = $get('unit_price') ?? 0;
                                        $costPrice = $get('cost_price') ?? 0;

                                        $set('total_price', $state * $unitPrice);

                                        // Recalculate profit
                                        $profit = ($unitPrice - $costPrice) * $state;
                                        $set('item_profit', $profit);
                                    })
                                    ->rules([
                                        fn ($get, $livewire) => function ($attribute, $value, $fail) use ($get, $livewire) {
                                            $productId = $get('product_id');
                                            if (! $productId) {
                                                return;
                                            }

                                            $product = Product::find($productId);
                                            if (! $product) {
                                                return;
                                            }

                                            $currentStock = $product->current_stock ?? 0;

                                            // Bug 3 Fix: When editing a completed sale, restore the original quantity first
                                            // This allows users to increase quantities within the available stock
                                            $originalQuantity = 0;

                                            // Check if we're editing an existing sale item
                                            if ($livewire instanceof EditRecord) {
                                                $record = $livewire->getRecord();
                                                $itemId = $get('id'); // Get the item ID from the repeater

                                                if ($record && $itemId && $record->status === SaleStatus::COMPLETED) {
                                                    // Find the original item to get its quantity
                                                    $originalItem = $record->items()->where('id', $itemId)->first();
                                                    if ($originalItem && $originalItem->product_id == $productId) {
                                                        $originalQuantity = $originalItem->quantity;
                                                    }
                                                }
                                            }

                                            // Calculate available stock: current + original (will be restored)
                                            $availableStock = $currentStock + $originalQuantity;

                                            if ($value > $availableStock) {
                                                $fail("Insufficient stock for {$product->name}. Available: {$availableStock}, Requested: {$value}");
                                            }
                                        },
                                    ])
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

                                        // Recalculate profit
                                        $profit = ($state - $costPrice) * $quantity;
                                        $set('item_profit', $profit);
                                    })
                                    ->columnSpan(1),

                                TextInput::make('total_price')
                                    ->label('Total Amount')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->prefix('ETB')
                                    ->columnSpan(1),

                                // ROW 2: Cost and Profit information
                                TextInput::make('cost_price')
                                    ->label('Cost Price (per unit)')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->prefix('ETB')
                                    ->helperText('Auto-filled from product')
                                    ->extraAttributes(['class' => 'text-gray-600'])
                                    ->default(fn ($get) => $get('product_id') ? Product::find($get('product_id'))?->total_cost : null)
                                    ->columnSpan(2),

                                TextInput::make('item_profit')
                                    ->label('💰 Profit')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->prefix('ETB')
                                    ->helperText('(Selling - Cost) × Quantity')
                                    ->extraAttributes(fn ($state) => [
                                        'class' => ($state ?? 0) >= 0 ? 'text-green-600 font-bold' : 'text-red-600 font-bold',
                                    ])
                                    ->default(function ($get) {
                                        $productId = $get('product_id');
                                        $quantity = $get('quantity') ?? 0;
                                        $unitPrice = $get('unit_price') ?? 0;

                                        if (! $productId) {
                                            return 0;
                                        }

                                        $product = Product::find($productId);
                                        if (! $product) {
                                            return 0;
                                        }

                                        return ($unitPrice - $product->total_cost) * $quantity;
                                    })
                                    ->columnSpan(3),
                            ])
                            ->columns(5)
                            ->defaultItems(1)
                            ->columnSpanFull()
                            ->required()
                            ->minItems(1)
                            ->live()
                            ->addActionLabel('+ Add Item')
                            ->deleteAction(
                                fn ($action) => $action->requiresConfirmation()
                            )
                            ->collapsible()
                            ->collapsed(false)
                            ->cloneable()
                            ->reorderable()
                            ->itemLabel(fn (array $state): ?string => Product::find($state['product_id'])?->name ?? 'New Item')
                            ->addActionLabel('➕ Add Another Product')
                            ->reorderableWithButtons()
                            ->defaultItems(1)
                            ->extraAttributes([
                                'class' => 'repeater-with-spacing',
                            ]),

                        // ✅ TOTALS - Clean grid layout
                        Grid::make(2)
                            ->schema([
                                Placeholder::make('grand_total')
                                    ->label('Total Amount')
                                    ->content(function ($get): string {
                                        $items = $get('items') ?? [];
                                        $total = 0;
                                        foreach ($items as $item) {
                                            $total += floatval($item['total_price'] ?? 0);
                                        }

                                        return 'ETB '.number_format($total, 2);
                                    }),

                                Placeholder::make('total_profit')
                                    ->label('Total Profit')
                                    ->content(function ($get): string {
                                        $items = $get('items') ?? [];
                                        $totalProfit = 0;

                                        foreach ($items as $item) {
                                            $totalProfit += floatval($item['item_profit'] ?? 0);
                                        }

                                        return 'ETB '.number_format($totalProfit, 2);
                                    }),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ])
            ->columns(3);
    }
}
