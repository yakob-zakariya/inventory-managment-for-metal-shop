<?php

namespace App\Filament\Widgets;

use App\Models\CapitalTransaction;
use App\Models\Payable;
use App\Models\Receivable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinancialObligations extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        // Outstanding receivables (money customers owe us)
        $outstandingReceivables = Receivable::where('remaining_balance', '>', 0)
            ->sum('remaining_balance') ?? 0;

        // Count of customers with outstanding receivables
        $customersWithDebt = Receivable::where('remaining_balance', '>', 0)
            ->distinct('customer_id')
            ->count('customer_id');

        // Outstanding payables (money we owe suppliers)
        $outstandingPayables = Payable::where('remaining_balance', '>', 0)
            ->sum('remaining_balance') ?? 0;

        // Count of suppliers we owe money to
        $suppliersWeOwe = Payable::where('remaining_balance', '>', 0)
            ->distinct('supplier_id')
            ->count('supplier_id');

        // Calculate outstanding loans from capital transactions
        $loansReceived = CapitalTransaction::where('category', 'loan_to_business')
            ->sum('amount') ?? 0;

        $loansRepaid = CapitalTransaction::where('category', 'loan_repayment')
            ->sum('amount') ?? 0;

        $outstandingLoans = $loansReceived - $loansRepaid;

        return [
            Stat::make('Outstanding Receivables', 'ETB '.number_format($outstandingReceivables, 2))
                ->description($customersWithDebt.' customer'.($customersWithDebt !== 1 ? 's' : '').' owe us money')
                ->descriptionIcon('heroicon-o-arrow-trending-down')
                ->color('warning'),

            Stat::make('Outstanding Payables', 'ETB '.number_format($outstandingPayables, 2))
                ->description('We owe money to '.$suppliersWeOwe.' supplier'.($suppliersWeOwe !== 1 ? 's' : ''))
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color('danger'),

            Stat::make('Outstanding Loans', 'ETB '.number_format($outstandingLoans, 2))
                ->description('Loans received - Loans repaid')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color($outstandingLoans > 0 ? 'danger' : 'success'),
        ];
    }
}
