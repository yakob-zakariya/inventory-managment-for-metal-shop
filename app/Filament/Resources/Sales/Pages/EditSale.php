<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Enums\SaleStatus;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Product;
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
     * Populate virtual payment fields and item cost/profit from the related records
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load the first payment for this sale (if it exists)
        $payment = $this->record->payments()->first();

        if ($payment) {
            $data['payment_account_id'] = $payment->account_id;
            $data['payment_method'] = $payment->payment_method->value;
        }

        // Load items with their products to populate cost_price and item_profit
        $items = $this->record->items()->with('product')->get();

        if ($items->isNotEmpty()) {
            $data['items'] = $items->map(function ($item) {
                $itemData = [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                ];

                // Add cost_price and item_profit if product exists
                if ($item->product) {
                    $itemData['cost_price'] = $item->product->cost_price;
                    $profit = ($item->unit_price - $item->product->cost_price) * $item->quantity;
                    $itemData['item_profit'] = $profit;
                }

                return $itemData;
            })->toArray();
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
