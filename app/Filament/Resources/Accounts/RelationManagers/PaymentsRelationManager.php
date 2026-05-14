<?php

namespace App\Filament\Resources\Accounts\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Operational Payments';

    protected static ?string $recordTitleAttribute = 'payment_date';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Payments are created from Purchase/Sale/Expense forms
                // So we don't need a form here - make it read-only
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment_date')
                    ->date()
                    ->sortable()
                    ->label('Date'),

                TextColumn::make('payable_type')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->badge()
                    ->color(fn (string $state): string => match (class_basename($state)) {
                        'Purchase' => 'danger',
                        'Sale' => 'success',
                        'Expense' => 'warning',
                        'Payable' => 'danger',
                        'Receivable' => 'success',
                        default => 'gray',
                    })
                    ->label('Type'),

                TextColumn::make('payable_id')
                    ->label('Reference')
                    ->formatStateUsing(function ($record): string {
                        $type = class_basename($record->payable_type);
                        return "{$type} #{$record->payable_id}";
                    }),

                TextColumn::make('amount')
                    ->money('ETB')
                    ->sortable()
                    ->color(function ($record): string {
                        return in_array(class_basename($record->payable_type), ['Sale', 'Receivable']) ? 'success' : 'danger';
                    })
                    ->formatStateUsing(function ($record): string {
                        $prefix = in_array(class_basename($record->payable_type), ['Sale', 'Receivable']) ? '+' : '-';
                        return $prefix . ' ' . number_format($record->amount, 2);
                    }),

                TextColumn::make('payment_method')
                    ->formatStateUsing(function ($state): string {
                        if ($state instanceof \App\Enums\PaymentMethod) {
                            return $state->label();
                        }
                        return str_replace('_', ' ', ucwords($state, '_'));
                    })
                    ->label('Method'),

                TextColumn::make('notes')
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 30) {
                            return null;
                        }
                        return $state;
                    })
                    ->toggleable(),

                TextColumn::make('user.name')
                    ->label('Recorded By')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('payment_date', 'desc')
            ->filters([
                //
            ])
            ->headerActions([
                // No create action - payments are created from Purchase/Sale/Expense forms
            ])
            ->recordActions([
                // Read-only - no edit/delete actions
                // Payments should be managed from their source (Purchase/Sale/Expense)
            ])
            ->emptyStateHeading('No payments yet')
            ->emptyStateDescription('Payments will appear here when you make purchases, sales, or pay expenses using this account.');
    }
}
