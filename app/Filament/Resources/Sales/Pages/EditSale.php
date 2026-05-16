<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Enums\SaleStatus;
use App\Filament\Resources\Sales\SaleResource;
use App\Services\SaleService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSale extends EditRecord
{
    protected static string $resource = SaleResource::class;

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
        // Load the first payment for this sale (if it exists)
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
        $sale = $this->record;

        // Get the original status before the update
        $originalStatus = $this->record->getOriginal('status');

        // Check if status changed to completed
        if ($sale->status === SaleStatus::COMPLETED &&
            $originalStatus !== SaleStatus::COMPLETED->value &&
            $sale->items()->count() > 0) {

            try {
                $service = app(SaleService::class);
                $service->completeSale($sale);

                Notification::make()
                    ->success()
                    ->title('Sale Completed')
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
        if ($sale->status === SaleStatus::CANCELLED &&
            $originalStatus !== SaleStatus::CANCELLED->value) {

            try {
                $service = app(SaleService::class);
                $service->cancelSale($sale);

                Notification::make()
                    ->success()
                    ->title('Sale Cancelled')
                    ->body('Stock has been restored.')
                    ->send();
            } catch (\Exception $e) {
                Notification::make()
                    ->danger()
                    ->title('Error')
                    ->body($e->getMessage())
                    ->send();
            }
        }
    }
}
