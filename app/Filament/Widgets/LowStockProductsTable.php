<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LowStockProductsTable extends TableWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Low Stock Products')
            ->description('Products below minimum stock level')
            ->query(
                Product::query()
                    ->whereColumn('current_stock', '<', 'minimum_stock_alert')
                    ->orWhere('current_stock', '<=', 0)
                    ->orderByRaw('CASE WHEN current_stock <= 0 THEN 0 ELSE 1 END')
                    ->orderBy('current_stock', 'asc')
            )
            ->columns([
                ImageColumn::make('image')
                    ->label('Image')
                    ->circular()
                    ->defaultImageUrl(url('/images/placeholder.png')),

                TextColumn::make('name')
                    ->label('Product Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('current_stock')
                    ->label('Current Stock')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->current_stock <= 0 ? 'danger' : 'warning'),

                TextColumn::make('minimum_stock_alert')
                    ->label('Minimum Stock')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('unit')
                    ->label('Unit')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('purchase_price')
                    ->label('Purchase Price')
                    ->money('ETB')
                    ->sortable(),

                TextColumn::make('selling_price')
                    ->label('Selling Price')
                    ->money('ETB')
                    ->sortable(),

                TextColumn::make('stock_value')
                    ->label('Stock Value')
                    ->money('ETB')
                    ->state(fn ($record) => $record->current_stock * $record->purchase_price)
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw("current_stock * purchase_price {$direction}")),
            ])
            ->defaultSort('current_stock', 'asc')
            ->paginated([10, 25, 50]);
    }
}
