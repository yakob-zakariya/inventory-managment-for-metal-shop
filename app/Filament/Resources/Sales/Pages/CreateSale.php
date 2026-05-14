<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Enums\PaymentType;
use App\Enums\SaleStatus;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Account;
use App\Models\Payment;
use App\Services\SaleService;
use Filament\Resources\Pages\CreateRecord;

class CreateSale extends CreateRecord
{
    protected static string $resource = SaleResource::class;

    /**
     * Hook before creating the record
     */
    protected function beforeCreate(): void
    {
        // Validate payment fields for cash sales
        $data = $this->data;
        
        // Check if payment_type is CASH (handle both enum instance and string value)
        $isCash = false;
        if (isset($data['payment_type'])) {
            if ($data['payment_type'] instanceof PaymentType) {
                $isCash = $data['payment_type'] === PaymentType::CASH;
            } else {
                $isCash = $data['payment_type'] === 'cash' || $data['payment_type'] === PaymentType::CASH->value;
            }
        }
        
        if ($isCash) {
            if (empty($data['payment_account_id']) || empty($data['payment_method'])) {
                // Show notification
                \Filament\Notifications\Notification::make()
                    ->danger()
                    ->title('Validation Error')
                    ->body('Payment account and payment method are required for cash sales.')
                    ->send();
                
                // Halt the process
                throw new \Filament\Support\Exceptions\Halt();
            }
        }
    }

    /**
     * Mutate form data before creating the record
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Remove payment fields from sale data (they're not in the sales table)
        unset($data['payment_account_id'], $data['payment_method']);
        
        return $data;
    }

    /**
     * Hook that runs after the record and its relationships are saved
     */
    protected function afterCreate(): void
    {
        $sale = $this->record;
        $formData = $this->data;

        // If sale was created with status "completed", process it
        if ($sale->status === SaleStatus::COMPLETED && $sale->items()->count() > 0) {
            try {
                $service = app(SaleService::class);
                $service->completeSale($sale);
                
                \Filament\Notifications\Notification::make()
                    ->success()
                    ->title('Sale Completed')
                    ->body('Stock has been updated automatically.')
                    ->send();
            } catch (\Exception $e) {
                \Filament\Notifications\Notification::make()
                    ->danger()
                    ->title('Error')
                    ->body($e->getMessage())
                    ->send();
            }
        }

        // If payment_type is CASH and payment_account_id is provided, create payment
        if ($sale->payment_type === PaymentType::CASH && 
            isset($formData['payment_account_id']) && 
            $formData['payment_account_id']) {
            
            try {
                $account = Account::findOrFail($formData['payment_account_id']);
                $totalAmount = $sale->total_amount;

                // Create payment record
                Payment::create([
                    'account_id' => $account->id,
                    'user_id' => $sale->user_id,
                    'payable_type' => get_class($sale),
                    'payable_id' => $sale->id,
                    'amount' => $totalAmount,
                    'payment_method' => $formData['payment_method'] ?? 'cash',
                    'payment_date' => $sale->sale_date,
                    'notes' => 'Auto-created payment for cash sale',
                ]);

                // Update account balance (money IN)
                $account->balance += $totalAmount;
                $account->save();

                \Filament\Notifications\Notification::make()
                    ->success()
                    ->title('Payment Recorded')
                    ->body("Payment of $" . number_format($totalAmount, 2) . " recorded successfully.")
                    ->send();
            } catch (\Exception $e) {
                \Filament\Notifications\Notification::make()
                    ->warning()
                    ->title('Payment Error')
                    ->body('Sale created but payment failed: ' . $e->getMessage())
                    ->send();
            }
        }
    }
}
