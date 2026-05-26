<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Enums\PaymentMethod;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('payable_type')
                    ->label('Type')
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->badge()
                    ->sortable(),

                TextColumn::make('payable_id')
                    ->label('Transaction')
                    ->formatStateUsing(fn ($record) => "#{$record->payable_id}"),

                TextColumn::make('amount')
                    ->money('ETB')
                    ->sortable()
                    ->color('success'),

                TextColumn::make('payment_method')
                    ->badge()
                    ->sortable(),

                TextColumn::make('account.name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Processed By')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('notes')
                    ->limit(50)
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('payable_type')
                    ->label('Payment Type')
                    ->options([
                        'App\\Models\\Purchase' => 'Purchase',
                        'App\\Models\\Sale' => 'Sale',
                        'App\\Models\\Expense' => 'Expense',
                        'App\\Models\\Payable' => 'Payable',
                        'App\\Models\\Receivable' => 'Receivable',
                    ]),

                SelectFilter::make('payment_method')
                    ->options(PaymentMethod::class),

                SelectFilter::make('account')
                    ->relationship('account', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('payment_date', 'desc')
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
