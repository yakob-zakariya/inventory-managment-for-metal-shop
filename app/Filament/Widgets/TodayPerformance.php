<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class TodayPerformance extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // Calculate today's sales
        $salesToday = DB::table('sales')
            ->join('sale_items', 'sales.id', '=', 'sale_items.sale_id')
            ->whereDate('sales.sale_date', now()->toDateString())
            ->sum('sale_items.total_price') ?? 0;

        // Calculate today's profit
        $profitToday = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->whereDate('sales.sale_date', now()->toDateString())
            ->selectRaw('SUM((sale_items.unit_price - (products.purchase_price + COALESCE(products.additional_costs, 0))) * sale_items.quantity) as profit')
            ->value('profit') ?? 0;

        return [
            Stat::make('Today\'s Sales', 'ETB '.number_format($salesToday, 2))
                ->description('Total sales today')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('info'),

            Stat::make('Today\'s Profit', 'ETB '.number_format($profitToday, 2))
                ->description('Profit from today\'s sales')
                ->descriptionIcon('heroicon-o-currency-dollar')
                ->color($profitToday >= 0 ? 'success' : 'danger'),
        ];
    }
}
