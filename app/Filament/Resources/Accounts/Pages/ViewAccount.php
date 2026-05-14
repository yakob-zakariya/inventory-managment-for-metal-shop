<?php

namespace App\Filament\Resources\Accounts\Pages;

use App\Filament\Resources\Accounts\AccountResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\TextEntry;
use Filament\Schemas\Schema;

class ViewAccount extends ViewRecord
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function schema(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account Information')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Account Name'),
                        
                        TextEntry::make('type')
                            ->badge(),
                        
                        TextEntry::make('balance')
                            ->money('ETB')
                            ->color(fn ($state) => $state < 0 ? 'danger' : 'success')
                            ->size('lg')
                            ->weight('bold'),
                        
                        TextEntry::make('description')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Section::make('Transaction Summary')
                    ->schema([
                        TextEntry::make('total_deposits')
                            ->label('Total Deposits')
                            ->money('ETB')
                            ->color('success')
                            ->state(function ($record) {
                                return $record->capitalTransactions()
                                    ->where('type', 'deposit')
                                    ->sum('amount');
                            }),

                        TextEntry::make('total_withdrawals')
                            ->label('Total Withdrawals')
                            ->money('ETB')
                            ->color('danger')
                            ->state(function ($record) {
                                return $record->capitalTransactions()
                                    ->where('type', 'withdrawal')
                                    ->sum('amount');
                            }),

                        TextEntry::make('total_payments')
                            ->label('Total Payments')
                            ->money('ETB')
                            ->color('info')
                            ->state(function ($record) {
                                return $record->payments()->sum('amount');
                            }),

                        TextEntry::make('payment_count')
                            ->label('Payment Transactions')
                            ->state(function ($record) {
                                return $record->payments()->count();
                            }),

                        TextEntry::make('capital_transaction_count')
                            ->label('Capital Transactions')
                            ->state(function ($record) {
                                return $record->capitalTransactions()->count();
                            }),
                    ])
                    ->columns(3),
            ]);
    }
}
