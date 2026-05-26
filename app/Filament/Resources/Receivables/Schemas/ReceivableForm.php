<?php

namespace App\Filament\Resources\Receivables\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ReceivableForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('sale_id')
                    ->relationship('sale', 'id')
                    ->required()
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Receivables are created automatically from credit sales'),

                Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->required()
                    ->disabled()
                    ->dehydrated(false),

                TextInput::make('amount')
                    ->numeric()
                    ->prefix('ETB')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Total amount from sale'),

                TextInput::make('remaining_balance')
                    ->numeric()
                    ->prefix('ETB')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Amount still owed by customer'),

                DatePicker::make('due_date')
                    ->helperText('When payment is expected (editable)'),

                Placeholder::make('payment_status')
                    ->label('Payment Status')
                    ->content(fn ($record) => $record ?
                        ($record->is_fully_paid ? '✅ Fully Paid' : '⚠️ Balance Due: $'.number_format($record->remaining_balance, 2))
                        : 'Not yet saved')
                    ->hidden(fn ($context) => $context === 'create'),
            ]);
    }
}
