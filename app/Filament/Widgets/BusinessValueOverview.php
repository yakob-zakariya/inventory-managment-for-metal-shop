<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use App\Models\CapitalTransaction;
use App\Models\Payable;
use App\Models\Product;
use App\Models\Receivable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BusinessValueOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    protected function getStats(): array
    {
        // Calculate total inventory value (current_stock × purchase_price only)
        $inventoryValue = Product::selectRaw('SUM(current_stock * purchase_price) as total_value')
            ->value('total_value') ?? 0;

        // Calculate total account balances
        $totalAccountBalance = Account::sum('balance') ?? 0;

        // Outstanding receivables (money customers owe us)
        $outstandingReceivables = Receivable::where('remaining_balance', '>', 0)
            ->sum('remaining_balance') ?? 0;

        // Outstanding payables (money we owe suppliers)
        $outstandingPayables = Payable::where('remaining_balance', '>', 0)
            ->sum('remaining_balance') ?? 0;

        // Calculate outstanding loans
        $loansReceived = CapitalTransaction::where('category', 'loan_to_business')
            ->sum('amount') ?? 0;

        $loansRepaid = CapitalTransaction::where('category', 'loan_repayment')
            ->sum('amount') ?? 0;

        $outstandingLoans = $loansReceived - $loansRepaid;

        // Net Business Value = Inventory + Accounts + Receivables - Payables - Loans
        $netBusinessValue = $inventoryValue + $totalAccountBalance + $outstandingReceivables - $outstandingPayables - $outstandingLoans;

        // Total Assets
        $totalAssets = $inventoryValue + $totalAccountBalance + $outstandingReceivables;

        return [
            Stat::make('Total Inventory Value', 'ETB '.number_format($inventoryValue, 2))
                ->description('Value of all products in stock')
                ->descriptionIcon('heroicon-o-cube')
                ->color('info'),

            Stat::make('Total Account Balance', 'ETB '.number_format($totalAccountBalance, 2))
                ->description('Sum of all account balances')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color($totalAccountBalance >= 0 ? 'success' : 'danger'),

            Stat::make('Total Assets', 'ETB '.number_format($totalAssets, 2))
                ->description('Inventory + Accounts + Receivables')
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color('info'),

            Stat::make('Net Business Value', 'ETB '.number_format($netBusinessValue, 2))
                ->description('Assets - Payables - Loans')
                ->descriptionIcon('heroicon-o-currency-dollar')
                ->color($netBusinessValue >= 0 ? 'success' : 'danger'),
        ];
    }
}
