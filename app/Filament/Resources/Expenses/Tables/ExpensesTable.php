<?php

namespace App\Filament\Resources\Expenses\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ExpensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('expense_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('category')
                    ->searchable()
                    ->sortable()
                    ->badge(),

                TextColumn::make('amount')
                    ->money('ETB')
                    ->sortable(),

                TextColumn::make('paid_amount')
                    ->money('ETB')
                    ->sortable()
                    ->getStateUsing(fn ($record) => $record->paid_amount)
                    ->color('success'),

                TextColumn::make('remaining_balance')
                    ->money('ETB')
                    ->sortable()
                    ->getStateUsing(fn ($record) => $record->remaining_balance)
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success'),

                TextColumn::make('user.name')
                    ->label('Created By')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('description')
                    ->limit(50)
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options([
                        'Rent' => 'Rent',
                        'Salary' => 'Salary',
                        'Utilities' => 'Utilities',
                        'Transport' => 'Transport',
                        'Office Supplies' => 'Office Supplies',
                        'Marketing' => 'Marketing',
                        'Insurance' => 'Insurance',
                        'Maintenance' => 'Maintenance',
                        'Taxes' => 'Taxes',
                        'Other' => 'Other',
                    ]),

                Filter::make('unpaid')
                    ->query(fn ($query) => $query->whereRaw('amount > (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payable_type = ? AND payable_id = expenses.id)', ['App\\Models\\Expense']))
                    ->label('Unpaid Only'),
            ])
            ->defaultSort('expense_date', 'desc')
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
