<?php

namespace App\Filament\Resources\Expenses\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use App\Enums\PaymentMethod;
use Filament\Schemas\Components\Section;

use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('category')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., Rent, Salary, Utilities, Transport')
                    ->datalist([
                        'Rent',
                        'Salary',
                        'Utilities',
                        'Transport',
                        'Office Supplies',
                        'Marketing',
                        'Insurance',
                        'Maintenance',
                        'Taxes',
                        'Other',
                    ]),

                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->prefix('$')
                    ->minValue(0.01),

                DatePicker::make('expense_date')
                    ->required()
                    ->default(now())
                    ->maxDate(now()),

                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull()
                    ->placeholder('Optional details about this expense'),

                Hidden::make('user_id')
                    ->default(Auth::id()),

                Section::make('Payment Details')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('payment_account_id')
                            ->label('Payment Account')
                            ->options(\App\Models\Account::pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload(),
                            
                        Select::make('payment_method')
                            ->options(\App\Enums\PaymentMethod::class)
                            ->required()
                            ->default(\App\Enums\PaymentMethod::CASH),
                    ])
                    ->visible(fn ($context) => $context === 'create')
                    ->description('Payment will be recorded automatically when expense is created'),


                // Display payment status (read-only)
                Placeholder::make('payment_status')
                    ->label('Payment Status')
                    ->content(fn ($record) => $record ? 
                        ($record->is_fully_paid ? '✅ Fully Paid' : '⚠️ Unpaid: $' . number_format($record->remaining_balance, 2)) 
                        : 'Not yet saved')
                    ->hidden(fn ($context) => $context === 'create'),
            ]);
    }
}
