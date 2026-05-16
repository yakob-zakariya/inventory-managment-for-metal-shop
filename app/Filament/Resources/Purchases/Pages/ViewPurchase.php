<?php

namespace App\Filament\Resources\Purchases\Pages;

use App\Filament\Resources\Purchases\PurchaseResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchase extends ViewRecord
{
    protected static string $resource = PurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
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
}
