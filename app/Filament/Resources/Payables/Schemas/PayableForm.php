<?php

namespace App\Filament\Resources\Payables\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PayableForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('purchase_id')
                    ->relationship('purchase', 'id')
                    ->required()
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Payables are created automatically from credit purchases'),

                Select::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->required()
                    ->disabled()
                    ->dehydrated(false),

                TextInput::make('amount')
                    ->numeric()
                    ->prefix('$')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Total amount from purchase'),

                TextInput::make('remaining_balance')
                    ->numeric()
                    ->prefix('$')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Amount still owed'),

                DatePicker::make('due_date')
                    ->helperText('When payment is due (editable)'),

                Placeholder::make('payment_status')
                    ->label('Payment Status')
                    ->content(fn ($record) => $record ? 
                        ($record->is_fully_paid ? '✅ Fully Paid' : '⚠️ Balance Due: $' . number_format($record->remaining_balance, 2)) 
                        : 'Not yet saved')
                    ->hidden(fn ($context) => $context === 'create'),
            ]);
    }
}
