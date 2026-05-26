<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('category.name'),
                TextColumn::make('unit')
                    ->badge()
                    ->searchable(),
                TextColumn::make('purchase_price')
                    ->label('Purchase Price')
                    ->money('ETB')
                    ->sortable(),
                TextColumn::make('additional_costs')
                    ->label('Add. Costs')
                    ->money('ETB')
                    ->sortable(),
                TextColumn::make('selling_price')
                    ->money('ETB')
                    ->sortable(),
                TextColumn::make('current_stock')
                    ->label('Stock')
                    ->numeric(decimalPlaces: 2)
                    ->color(fn (Product $record) => $record->isLowStock() ? 'danger' : 'success')
                    ->badge()
                    ->suffix(fn (Product $record) => ' '.$record->unit->value),
                TextColumn::make('minimum_stock_alert')
                    ->label('minimum-stock')
                    ->numeric()
                    ->sortable(),
                ImageColumn::make('image'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
