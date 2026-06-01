<?php

namespace App\Providers;

use App\Models\CapitalTransaction;
use App\Models\Purchase;
use App\Models\Sale;
use App\Observers\CapitalTransactionObserver;
use App\Observers\PurchaseObserver;
use App\Observers\SaleObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        CapitalTransaction::observe(CapitalTransactionObserver::class);
        Purchase::observe(PurchaseObserver::class);
        Sale::observe(SaleObserver::class);
    }
}
