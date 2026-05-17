<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class MonthlyPerformance extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        // Calculate this month's sales
        $salesThisMonth = DB::table('sales')
            ->join('sale_items', 'sales.id', '=', 'sale_items.sale_id')
            ->whereMonth('sales.sale_date', now()->month)
            ->whereYear('sales.sale_date', now()->year)
            ->sum('sale_items.total_price') ?? 0;

        // Calculate this month's profit from sales
        $profitThisMonth = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->whereMonth('sales.sale_date', now()->month)
            ->whereYear('sales.sale_date', now()->year)
            ->selectRaw('SUM((sale_items.unit_price - products.cost_price) * sale_items.quantity) as profit')
            ->value('profit') ?? 0;

        // Calculate this month's expenses
        $expensesThisMonth = Expense::whereMonth('expense_date', now()->month)
            ->whereYear('expense_date', now()->year)
            ->sum('amount') ?? 0;

        // Calculate net profit (profit - expenses)
        $netProfitThisMonth = $profitThisMonth - $expensesThisMonth;

        // Get profit trend for last 7 days
        $profitTrend = $this->getProfitTrend();

        return [
            Stat::make('This Month\'s Sales', 'ETB '.number_format($salesThisMonth, 2))
                ->description('Total sales this month')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('info'),

            Stat::make('This Month\'s Profit', 'ETB '.number_format($profitThisMonth, 2))
                ->description('Profit from sales')
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color($profitThisMonth >= 0 ? 'success' : 'danger')
                ->chart($profitTrend),

            Stat::make('This Month\'s Expenses', 'ETB '.number_format($expensesThisMonth, 2))
                ->description('Total expenses')
                ->descriptionIcon('heroicon-o-arrow-trending-down')
                ->color('warning'),

            Stat::make('Net Profit This Month', 'ETB '.number_format($netProfitThisMonth, 2))
                ->description('Profit - Expenses')
                ->descriptionIcon('heroicon-o-currency-dollar')
                ->color($netProfitThisMonth >= 0 ? 'success' : 'danger'),
        ];
    }

    private function getProfitTrend(): array
    {
        // Get profit for last 7 days
        $profits = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $profit = DB::table('sale_items')
                ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                ->join('products', 'sale_items.product_id', '=', 'products.id')
                ->whereDate('sales.sale_date', $date)
                ->selectRaw('SUM((sale_items.unit_price - products.cost_price) * sale_items.quantity) as profit')
                ->value('profit');

            $profits[] = $profit ?? 0;
        }

        return $profits;
    }
}
