<?php

namespace App\Filament\Resources\Purchases\Pages;

use App\Enums\PaymentType;
use App\Enums\PurchaseStatus;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Models\Account;
use App\Models\Payment;
use App\Models\Payable;
use App\Services\PurchaseService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreatePurchase extends CreateRecord
{
    protected static string $resource = PurchaseResource::class;

    // Store payment data temporarily
    protected $paymentAccountId;
    protected $paymentMethod;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Calculate total from items
        $total = 0;
        if (isset($data['items'])) {
            foreach ($data['items'] as $item) {
                $total += floatval($item['total_price'] ?? 0);
            }
        }
        $data['total_amount'] = $total;

        // ✅ Validate CASH purchases (data is still string here, not enum)
        if ($data['payment_type'] === 'cash') {
            // Check payment fields are filled
            if (empty($data['payment_account_id'])) {
                Notification::make()
                    ->danger()
                    ->title('Validation Error')
                    ->body('Payment account is required for cash purchases.')
                    ->send();
                $this->halt();
            }

            // Check account balance
            $account = Account::find($data['payment_account_id']);
            if ($account && $account->balance < $total) {
                Notification::make()
                    ->danger()
                    ->title('Insufficient Balance')
                    ->body("Account '{$account->name}' has only ETB " . number_format($account->balance, 2) . 
                           " but needs ETB " . number_format($total, 2))
                    ->send();
                $this->halt();
            }

            // Store temporarily (not in purchases table)
            $this->paymentAccountId = $data['payment_account_id'];
            $this->paymentMethod = $data['payment_method'] ?? 'cash';
        }

        // Remove payment fields from purchase data
        unset($data['payment_account_id'], $data['payment_method']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $purchase = $this->record;

        // ✅ 1. STOCK MOVEMENTS (if status is completed)
        if ($purchase->status === PurchaseStatus::COMPLETED) {
            try {
                app(PurchaseService::class)->completePurchase($purchase);
                
                Notification::make()
                    ->success()
                    ->title('Stock Updated')
                    ->body('Inventory updated automatically.')
                    ->send();
            } catch (\Exception $e) {
                Notification::make()
                    ->danger()
                    ->title('Stock Error')
                    ->body($e->getMessage())
                    ->send();
            }
        }

        // ✅ 2. CASH PURCHASE - Create payment
        // CRITICAL FIX: Compare with PaymentType::CASH enum, not string 'cash'
        if ($purchase->payment_type === PaymentType::CASH && $this->paymentAccountId) {
            try {
                $account = Account::findOrFail($this->paymentAccountId);

                // Create payment record with polymorphic relationship
                Payment::create([
                    'user_id' => $purchase->user_id,
                    'account_id' => $account->id,
                    'payable_type' => get_class($purchase),
                    'payable_id' => $purchase->id,
                    'amount' => $purchase->total_amount,
                    'payment_method' => $this->paymentMethod,
                    'payment_date' => $purchase->purchase_date,
                    'notes' => "Cash payment for purchase #{$purchase->id}",
                ]);

                // ✅ REDUCE ACCOUNT BALANCE
                $account->balance -= $purchase->total_amount;
                $account->save();

                Notification::make()
                    ->success()
                    ->title('Payment Recorded')
                    ->body("ETB " . number_format($purchase->total_amount, 2) . " paid from {$account->name}")
                    ->send();
            } catch (\Exception $e) {
                Notification::make()
                    ->warning()
                    ->title('Payment Error')
                    ->body($e->getMessage())
                    ->send();
            }
        }

        // ✅ 3. CREDIT PURCHASE - Create payable
        // CRITICAL FIX: Compare with PaymentType::CREDIT enum, not string 'credit'
        if ($purchase->payment_type === PaymentType::CREDIT) {
            try {
                Payable::create([
                    'purchase_id' => $purchase->id,
                    'supplier_id' => $purchase->supplier_id,
                    'amount' => $purchase->total_amount,
                    'remaining_balance' => $purchase->total_amount,
                    'due_date' => now()->addDays(30),
                ]);

                Notification::make()
                    ->info()
                    ->title('Credit Purchase Created')
                    ->body('Payable record created. Due in 30 days.')
                    ->send();
            } catch (\Exception $e) {
                Notification::make()
                    ->warning()
                    ->title('Payable Error')
                    ->body($e->getMessage())
                    ->send();
            }
        }
    }
}