<?php

namespace App\Filament\Resources\Purchases\Pages;

use App\Enums\PurchaseStatus;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Services\PurchaseService;
use Filament\Actions;
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
                
                \Filament\Notifications\Notification::make()
                    ->success()
                    ->title('Purchase Completed')
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

        // Check if status changed to cancelled
        if ($purchase->status === PurchaseStatus::CANCELLED && 
            $originalStatus !== PurchaseStatus::CANCELLED->value) {
            
            try {
                $service = app(PurchaseService::class);
                $service->cancelPurchase($purchase);
                
                \Filament\Notifications\Notification::make()
                    ->success()
                    ->title('Purchase Cancelled')
                    ->body('Stock movements have been reversed.')
                    ->send();
            } catch (\Exception $e) {
                \Filament\Notifications\Notification::make()
                    ->danger()
                    ->title('Error')
                    ->body($e->getMessage())
                    ->send();
            }
        }
    }
}
