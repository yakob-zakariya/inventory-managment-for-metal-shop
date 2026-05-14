<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Services\PaymentService;
use Filament\Resources\Pages\CreateRecord;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected function afterCreate(): void
    {
        $payment = $this->record;
        
        // Update account balance and payable/receivable balance
        try {
            $payable = $payment->payable;
            $account = $payment->account;
            
            // Update account balance
            if ($payable instanceof \App\Models\Sale || $payable instanceof \App\Models\Receivable) {
                // Money IN (customer pays us)
                $account->balance += $payment->amount;
            } else {
                // Money OUT (we pay supplier/expense)
                $account->balance -= $payment->amount;
            }
            $account->save();
            
            // Update payable/receivable balance
            if ($payable instanceof \App\Models\Payable || $payable instanceof \App\Models\Receivable) {
                $payable->remaining_balance -= $payment->amount;
                $payable->save();
            }
            
            \Filament\Notifications\Notification::make()
                ->success()
                ->title('Payment Processed')
                ->body('Account balance and payable/receivable updated automatically.')
                ->send();
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('Payment Error')
                ->body($e->getMessage())
                ->send();
        }
    }
}
