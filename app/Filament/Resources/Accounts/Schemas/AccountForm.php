<?php

namespace App\Filament\Resources\Accounts\Schemas;

use App\Enums\AccountType;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., Main Cash Register, Bank Account'),

                Select::make('type')
                    ->options(AccountType::class)
                    ->required()
                    ->native(false),

                Placeholder::make('balance')
                    ->label('Current Balance')
                    ->content(fn ($record) => $record ? '$' . number_format($record->balance, 2) : '$0.00')
                    ->helperText('Balance is updated automatically through payments')
                    ->hidden(fn ($context) => $context === 'create'),

                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull()
                    ->placeholder('Optional notes about this account'),
            ]);
    }
}
