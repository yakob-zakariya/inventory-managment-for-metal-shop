<?php

namespace App\Filament\Resources\Purchases\Pages;

use App\Enums\PaymentType;
use App\Enums\PurchaseStatus;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Models\Account;
use App\Models\Payable;
use App\Models\Payment;
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
        // ✅ Always start purchases as PENDING
        // Use "Mark as Received" action in the table to complete the purchase
        $data['status'] = PurchaseStatus::PENDING;

        // Store payment data temporarily for cash purchases
        if ($data['payment_type'] === PaymentType::CASH->value || $data['payment_type'] === 'cash') {
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

        // Calculate total from items
        $total = 0;
        if (isset($data['items'])) {
            foreach ($data['items'] as $item) {
                $total += floatval($item['total_price'] ?? 0);
            }
        }

        if ($data['payment_type'] === PaymentType::CASH->value || $data['payment_type'] === 'cash') {
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

        // ✅ CASH PURCHASE - Create payment immediately
        // Note: Purchase starts as PENDING. Stock is NOT updated yet.
        // Use "Mark as Received" action in the table to complete the purchase and update stock.
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
                    ->body('ETB '.number_format($purchase->total_amount, 2)." paid from {$account->name}. Use 'Mark as Received' to update stock.")
                    ->send();
            } catch (\Exception $e) {
                Notification::make()
                    ->warning()
                    ->title('Payment Error')
                    ->body($e->getMessage())
                    ->send();
            }
        }

        // ✅ CREDIT PURCHASE - Payable will be created when you mark as received
        if ($purchase->payment_type === PaymentType::CREDIT) {
            Notification::make()
                ->info()
                ->title('Purchase Created')
                ->body("Use 'Mark as Received' to complete the purchase and create the payable.")
                ->send();
        }
    }
}
