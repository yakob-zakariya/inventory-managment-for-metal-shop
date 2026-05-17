<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SalesPurchasesChart extends ChartWidget
{
    protected ?string $heading = 'Sales vs Purchases (Last 30 Days)';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $salesData = [];
        $purchasesData = [];
        $labels = [];

        // Get data for last 30 days
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $labels[] = $date->format('M d');

            // Sales for this day - sum from sale_items
            $salesAmount = DB::table('sales')
                ->join('sale_items', 'sales.id', '=', 'sale_items.sale_id')
                ->whereDate('sales.sale_date', $date->toDateString())
                ->sum('sale_items.total_price');
            $salesData[] = $salesAmount;

            // Purchases for this day - sum from purchase_items
            $purchasesAmount = DB::table('purchases')
                ->join('purchase_items', 'purchases.id', '=', 'purchase_items.purchase_id')
                ->whereDate('purchases.purchase_date', $date->toDateString())
                ->sum('purchase_items.total_price');
            $purchasesData[] = $purchasesAmount;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Sales',
                    'data' => $salesData,
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Purchases',
                    'data' => $purchasesData,
                    'borderColor' => 'rgb(239, 68, 68)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }
}
