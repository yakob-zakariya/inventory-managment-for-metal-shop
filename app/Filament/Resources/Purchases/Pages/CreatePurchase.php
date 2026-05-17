<?php

namespace App\Filament\Resources\Purchases\Pages;

use App\Enums\PaymentType;
use App\Enums\PurchaseStatus;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Models\Account;
use App\Models\Payable;
use App\Models\Payment;
use App\Services\PurchaseService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;

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

        // Store payment data temporarily for cash purchases
        if ($data['payment_type'] === 'cash') {
            $this->paymentAccountId = $data['payment_account_id'] ?? null;
            $this->paymentMethod = $data['payment_method'] ?? 'cash';
        }

        // Remove payment fields from purchase data
        unset($data['payment_account_id'], $data['payment_method']);

        return $data;
    }

    protected function beforeCreate(): void
    {
        // ✅ Validate CASH purchases BEFORE creating the record
        $data = $this->data;

        if ($data['payment_type'] === 'cash') {
            // Check payment fields are filled
            if (empty($this->paymentAccountId)) {
                Notification::make()
                    ->danger()
                    ->title('Validation Error')
                    ->body('Payment account is required for cash purchases.')
                    ->persistent()
                    ->send();

                throw new Halt;
            }

            // Check account balance
            $account = Account::find($this->paymentAccountId);
            if (! $account) {
                Notification::make()
                    ->danger()
                    ->title('Validation Error')
                    ->body('Selected account not found.')
                    ->persistent()
                    ->send();

                throw new Halt;
            }

            $total = $data['total_amount'] ?? 0;

            if ($total > 0 && $account->balance < $total) {
                Notification::make()
                    ->danger()
                    ->title('Insufficient Balance')
                    ->body("Account '{$account->name}' has only ETB ".number_format($account->balance, 2).
                           ' but purchase total is ETB '.number_format($total, 2).'. Please add funds to the account first or select a different account.')
                    ->persistent()
                    ->send();

                throw new Halt;
            }
        }
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
                    ->body('ETB '.number_format($purchase->total_amount, 2)." paid from {$account->name}")
                    ->send();
            } catch (\Exception $e) {
                Notification::make()
                    ->warning()
                    ->title('Payment Error')
                    ->body($e->getMessage())
                    ->send();
            }
        }

        // ✅ 3. CREDIT PURCHASE - Payable is created automatically by PurchaseService::completePurchase()
        // No need to create it here to avoid duplicates
    }
}
