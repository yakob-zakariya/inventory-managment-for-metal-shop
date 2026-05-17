<?php

namespace App\Filament\Resources\Shared\Schemas;

use App\Enums\PaymentMethod;
use App\Models\Account;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

class PaymentFormSchema
{
    /**
     * Create payment form schema for receivables or payables
     *
     * @param  mixed  $record  Receivable or Payable model instance
     * @return array Form schema components
     */
    public static function make($record): array
    {
        return [
            Hidden::make('max_amount')
                ->default($record->remaining_balance),

            Hidden::make('payable_type')
                ->default(get_class($record)),

            Hidden::make('payable_id')
                ->default($record->id),

            Select::make('account_id')
                ->label('Account')
                ->options(function () {
                    return Account::query()
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (Account $account) => [
                            $account->id => sprintf(
                                '%s (Balance: %s ETB)',
                                $account->name,
                                number_format($account->balance, 2)
                            ),
                        ]);
                })
                ->searchable()
                ->required()
                ->helperText('Select the account to record this payment'),

            TextInput::make('amount')
                ->label('Payment Amount')
                ->numeric()
                ->required()
                ->minValue(0.01)
                ->maxValue(fn (Get $get) => $get('max_amount'))
                ->suffix('ETB')
                ->helperText(fn (Get $get) => 'Maximum: '.number_format($get('max_amount'), 2).' ETB'
                )
                ->live(onBlur: true),

            Select::make('payment_method')
                ->label('Payment Method')
                ->options(PaymentMethod::class)
                ->required()
                ->default(PaymentMethod::CASH),

            DatePicker::make('payment_date')
                ->label('Payment Date')
                ->required()
                ->default(now())
                ->maxDate(now()),

            Textarea::make('notes')
                ->label('Notes')
                ->rows(3)
                ->maxLength(500),
        ];
    }
}
