<?php

namespace App\Filament\Resources\Payables\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PayablesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('purchase.id')
                    ->label('Purchase #')
                    ->sortable(),

                TextColumn::make('supplier.name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('amount')
                    ->money()
                    ->sortable(),

                TextColumn::make('paid_amount')
                    ->money()
                    ->sortable()
                    ->getStateUsing(fn ($record) => $record->paid_amount)
                    ->color('success'),

                TextColumn::make('remaining_balance')
                    ->money()
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),

                TextColumn::make('due_date')
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => $record->is_overdue ? 'danger' : null),

                TextColumn::make('status')
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->is_fully_paid ? 'Paid' : ($record->is_overdue ? 'Overdue' : 'Pending'))
                    ->color(fn ($state) => match($state) {
                        'Paid' => 'success',
                        'Overdue' => 'danger',
                        'Pending' => 'warning',
                    }),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('supplier')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('unpaid')
                    ->query(fn ($query) => $query->where('remaining_balance', '>', 0))
                    ->label('Unpaid Only'),

                Filter::make('overdue')
                    ->query(fn ($query) => $query->where('due_date', '<', now())->where('remaining_balance', '>', 0))
                    ->label('Overdue Only'),
            ])
            ->defaultSort('due_date', 'asc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
