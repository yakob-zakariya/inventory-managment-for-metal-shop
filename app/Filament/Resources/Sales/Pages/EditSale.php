<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Enums\SaleStatus;
use App\Filament\Resources\Sales\SaleResource;
use App\Services\SaleService;
use Filament\Actions;
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

        // Check if status changed to cancelled
        if ($sale->status === SaleStatus::CANCELLED && 
            $originalStatus !== SaleStatus::CANCELLED->value) {
            
            try {
                $service = app(SaleService::class);
                $service->cancelSale($sale);
                
                \Filament\Notifications\Notification::make()
                    ->success()
                    ->title('Sale Cancelled')
                    ->body('Stock has been restored.')
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
