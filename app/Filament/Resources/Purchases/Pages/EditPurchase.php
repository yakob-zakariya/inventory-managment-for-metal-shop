<?php

namespace App\Filament\Resources\Purchases\Pages;

use App\Enums\PurchaseStatus;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Services\PurchaseService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPurchase extends EditRecord
{
    protected static string $resource = PurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Populate virtual payment fields from the related payment record
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load the first payment for this purchase (if it exists)
        $payment = $this->record->payments()->first();

        if ($payment) {
            $data['payment_account_id'] = $payment->account_id;
            $data['payment_method'] = $payment->payment_method->value;
        }

        return $data;
    }

    /**
     * Hook that runs after the record and its relationships are saved
     */
    protected function afterSave(): void
    {
        $purchase = $this->record;

        // Get the original status before the update
        $originalData = $this->data; // This has the old data
        $originalStatus = $this->record->getOriginal('status');

        // Check if status changed to completed
        if ($purchase->status === PurchaseStatus::COMPLETED &&
            $originalStatus !== PurchaseStatus::COMPLETED->value &&
            $purchase->items()->count() > 0) {

            try {
                $service = app(PurchaseService::class);
                $service->completePurchase($purchase);

                Notification::make()
                    ->success()
                    ->title('Purchase Completed')
                    ->body('Stock has been updated automatically.')
                    ->send();
            } catch (\Exception $e) {
                Notification::make()
                    ->danger()
                    ->title('Error')
                    ->body($e->getMessage())
                    ->send();
            }
        }

        // Check if status changed to cancelled
        if ($purchase->status === PurchaseStatus::CANCELLED &&
            $originalStatus !== PurchaseStatus::CANCELLED->value) {

            try {
                $service = app(PurchaseService::class);
                $service->cancelPurchase($purchase);

                Notification::make()
                    ->success()
                    ->title('Purchase Cancelled')
                    ->body('Stock movements have been reversed.')
                    ->send();
            } catch (\Exception $e) {
                Notification::make()
                    ->danger()
                    ->title('Error')
                    ->body($e->getMessage())
                    ->send();
            }
        }

        // CRITICAL: Trigger the observer to synchronize payments/payables when items change
        // This ensures the PurchaseObserver::updated() method runs to sync payment amounts
        // Even if the Purchase model itself hasn't changed, the items may have changed
        // We need to mark the model as dirty and save it to trigger the updated event
        $purchase->updated_at = now();
        $purchase->save();
    }
}
