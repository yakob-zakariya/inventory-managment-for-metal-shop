<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Enums\PaymentType;
use App\Enums\SaleStatus;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Account;
use App\Models\Payment;
use App\Models\Receivable;
use App\Services\SaleService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateSale extends CreateRecord
{
    protected static string $resource = SaleResource::class;

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

        // ✅ Validate CASH sales (data is still string here, not enum)
        if ($data['payment_type'] === 'cash') {
            // Check payment fields are filled
            if (empty($data['payment_account_id'])) {
                Notification::make()
                    ->danger()
                    ->title('Validation Error')
                    ->body('Payment account is required for cash sales.')
                    ->send();
                $this->halt();
            }

            // Store temporarily (not in sales table)
            $this->paymentAccountId = $data['payment_account_id'];
            $this->paymentMethod = $data['payment_method'] ?? 'cash';
        }

        // Remove payment fields from sale data
        unset($data['payment_account_id'], $data['payment_method']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $sale = $this->record;

        // ✅ 1. STOCK MOVEMENTS (if status is completed)
        if ($sale->status === SaleStatus::COMPLETED && $sale->items()->count() > 0) {
            try {
                app(SaleService::class)->completeSale($sale);
                
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

        // ✅ 2. CASH SALE - Create payment
        if ($sale->payment_type === PaymentType::CASH && $this->paymentAccountId) {
            try {
                $account = Account::findOrFail($this->paymentAccountId);

                // Create payment record with polymorphic relationship
                Payment::create([
                    'user_id' => $sale->user_id,
                    'account_id' => $account->id,
                    'payable_type' => get_class($sale),
                    'payable_id' => $sale->id,
                    'amount' => $sale->total_amount,
                    'payment_method' => $this->paymentMethod,
                    'payment_date' => $sale->sale_date,
                    'notes' => "Cash payment for sale #{$sale->id}",
                ]);

                // ✅ INCREASE ACCOUNT BALANCE (money IN)
                $account->balance += $sale->total_amount;
                $account->save();

                Notification::make()
                    ->success()
                    ->title('Payment Recorded')
                    ->body("ETB " . number_format($sale->total_amount, 2) . " received in {$account->name}")
                    ->send();
            } catch (\Exception $e) {
                Notification::make()
                    ->warning()
                    ->title('Payment Error')
                    ->body($e->getMessage())
                    ->send();
            }
        }

        // ✅ 3. CREDIT SALE - Create receivable
        if ($sale->payment_type === PaymentType::CREDIT) {
            try {
                Receivable::create([
                    'sale_id' => $sale->id,
                    'customer_id' => $sale->customer_id,
                    'amount' => $sale->total_amount,
                    'remaining_balance' => $sale->total_amount,
                    'due_date' => now()->addDays(30),
                ]);

                Notification::make()
                    ->info()
                    ->title('Credit Sale Created')
                    ->body('Receivable record created. Due in 30 days.')
                    ->send();
            } catch (\Exception $e) {
                Notification::make()
                    ->warning()
                    ->title('Receivable Error')
                    ->body($e->getMessage())
                    ->send();
            }
        }
    }
}
