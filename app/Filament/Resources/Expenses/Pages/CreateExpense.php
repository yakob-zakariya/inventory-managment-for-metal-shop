<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Filament\Resources\Expenses\ExpenseResource;
use App\Models\Account;
use App\Models\Payment;
use Filament\Resources\Pages\CreateRecord;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;

    /**
     * Hook before creating the record
     */
    protected function beforeCreate(): void
    {
        $data = $this->data;
        
        // Check if payment account is selected and validate balance
        if (isset($data['payment_account_id']) && $data['payment_account_id']) {
            $amount = $data['amount'] ?? 0;
            
            if ($amount > 0) {
                $account = Account::find($data['payment_account_id']);
                if ($account && $account->balance < $amount) {
                    \Filament\Notifications\Notification::make()
                        ->danger()
                        ->title('Insufficient Balance')
                        ->body("Account '{$account->name}' has insufficient balance. Available: " . number_format($account->balance, 2) . " ETB, Required: " . number_format($amount, 2) . " ETB")
                        ->send();
                    
                    throw new \Filament\Support\Exceptions\Halt();
                }
            }
        }
    }

    /**
     * Mutate form data before creating the record
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Remove payment fields from expense data (they're not in the expenses table)
        unset($data['payment_account_id'], $data['payment_method']);
        
        return $data;
    }

    /**
     * Hook that runs after the record is created
     */
    protected function afterCreate(): void
    {
        $expense = $this->record;
        $formData = $this->data;

        // If payment_account_id is provided, create payment
        if (isset($formData['payment_account_id']) && $formData['payment_account_id']) {
            try {
                $account = Account::findOrFail($formData['payment_account_id']);

                // Create payment record
                Payment::create([
                    'account_id' => $account->id,
                    'user_id' => $expense->user_id,
                    'payable_type' => get_class($expense),
                    'payable_id' => $expense->id,
                    'amount' => $expense->amount,
                    'payment_method' => $formData['payment_method'] ?? 'cash',
                    'payment_date' => $expense->expense_date,
                    'notes' => 'Auto-created payment for expense',
                ]);

                // Update account balance (money OUT)
                $account->balance -= $expense->amount;
                $account->save();

                \Filament\Notifications\Notification::make()
                    ->success()
                    ->title('Payment Recorded')
                    ->body("Payment of $" . number_format($expense->amount, 2) . " recorded successfully.")
                    ->send();
            } catch (\Exception $e) {
                \Filament\Notifications\Notification::make()
                    ->warning()
                    ->title('Payment Error')
                    ->body('Expense created but payment failed: ' . $e->getMessage())
                    ->send();
            }
        }
    }
}
