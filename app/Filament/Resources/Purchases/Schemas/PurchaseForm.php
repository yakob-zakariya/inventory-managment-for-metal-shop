<?php

namespace App\Filament\Resources\Purchases\Schemas;

use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use App\Models\Account;
use App\Models\Product;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
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
                            ->options(function () {
                                return Account::query()
                                    ->get()
                                    ->mapWithKeys(function ($account) {
                                        return [
                                            $account->id => $account->name.' (Balance: ETB '.number_format($account->balance, 2).')',
                                        ];
                                    });
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(onBlur: true)
                            ->helperText('Select account with sufficient balance')
                            ->afterStateUpdated(function ($state, $set, $get, $livewire) {
                                if (! $state) {
                                    return;
                                }

                                $account = Account::find($state);
                                if (! $account) {
                                    return;
                                }

                                // Get the total from items
                                $items = $get('../../items') ?? [];
                                $total = 0;
                                foreach ($items as $item) {
                                    $total += floatval($item['total_price'] ?? 0);
                                }

                                if ($total > 0 && $account->balance < $total) {
                                    Notification::make()
                                        ->danger()
                                        ->title('Insufficient Balance')
                                        ->body("Account '{$account->name}' has insufficient balance. Available: ETB ".number_format($account->balance, 2).', Required: ETB '.number_format($total, 2))
                                        ->persistent()
                                        ->send();
                                }
                            })
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, \Closure $fail) {
                                        $account = Account::find($value);
                                        if (! $account) {
                                            $fail('Selected account not found.');

                                            return;
                                        }

                                        // Get the total from the form
                                        $items = request()->input('items', []);
                                        $total = 0;
                                        foreach ($items as $item) {
                                            $total += floatval($item['total_price'] ?? 0);
                                        }

                                        if ($total > 0 && $account->balance < $total) {
                                            $fail('Insufficient balance. Available: ETB '.number_format($account->balance, 2).', Required: ETB '.number_format($total, 2));
                                        }
                                    };
                                },
                            ]),

                        Select::make('payment_method')
                            ->label('Payment Method')
                            ->options(PaymentMethod::class)
                            ->required()
                            ->default(PaymentMethod::CASH),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->description('Payment will be automatically recorded when you save. Ensure the account has sufficient balance.')
                    ->visible(fn ($get) => $get('payment_type') === 'cash'), // ✅ KEY LINE

                // ✅ PAYABLE SECTION - Shows when payment_type is 'credit' AND record exists
                Section::make('📋 Payable Details (Credit)')
                    ->schema([
                        Placeholder::make('payable_amount')
                            ->label('Total Amount')
                            ->content(fn ($record) => $record && $record->payable
                                ? 'ETB '.number_format($record->payable->amount, 2)
                                : 'Not yet created'),

                        Placeholder::make('payable_paid')
                            ->label('Paid Amount')
                            ->content(fn ($record) => $record && $record->payable
                                ? 'ETB '.number_format($record->payable->paid_amount, 2)
                                : '-'),

                        Placeholder::make('payable_remaining')
                            ->label('Remaining Balance')
                            ->content(fn ($record) => $record && $record->payable
                                ? 'ETB '.number_format($record->payable->remaining_balance, 2)
                                : '-'),

                        Placeholder::make('payable_due_date')
                            ->label('Due Date')
                            ->content(fn ($record) => $record && $record->payable && $record->payable->due_date
                                ? $record->payable->due_date->format('M d, Y')
                                : 'Not set'),

                        Placeholder::make('payable_status')
                            ->label('Status')
                            ->content(function ($record) {
                                if (! $record || ! $record->payable) {
                                    return '⏳ Pending creation';
                                }

                                $payable = $record->payable;

                                if ($payable->is_fully_paid) {
                                    return '✅ Fully Paid';
                                }

                                if ($payable->is_overdue) {
                                    return '🔴 Overdue';
                                }

                                return '⏳ Pending';
                            }),
                    ])
                    ->columns(3)
                    ->columnSpanFull()
                    ->description('Payable will be created automatically when you save.')
                    ->visible(fn ($get) => $get('payment_type') === 'credit'),

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
                                            $product = Product::find($state);
                                            if ($product) {
                                                $set('unit_price', $product->purchase_price);
                                                $quantity = $get('quantity') ?? 1;
                                                $set('total_price', $quantity * $product->purchase_price);
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
                            ->addActionLabel('+ Add Item')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => Product::find($state['product_id'])?->name ?? 'New Item'),

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
                    ])
                    ->columnSpanFull(),
            ])
            ->columns(3);
    }
}
