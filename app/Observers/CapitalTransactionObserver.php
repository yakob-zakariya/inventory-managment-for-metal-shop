<?php

namespace App\Observers;

use App\Models\CapitalTransaction;

class CapitalTransactionObserver
{
    /**
     * Handle the CapitalTransaction "created" event.
     */
    public function created(CapitalTransaction $capitalTransaction): void
    {
        $this->updateAccountBalance($capitalTransaction, 'add');
    }

    /**
     * Handle the CapitalTransaction "updated" event.
     */
    public function updated(CapitalTransaction $capitalTransaction): void
    {
        // If amount or type changed, we need to reverse the old transaction and apply the new one
        if ($capitalTransaction->isDirty(['amount', 'type'])) {
            // Reverse the original transaction
            $originalAmount = $capitalTransaction->getOriginal('amount');
            $originalType = $capitalTransaction->getOriginal('type');
            
            $account = $capitalTransaction->account;
            if ($originalType === 'deposit') {
                $account->balance -= $originalAmount;
            } else {
                $account->balance += $originalAmount;
            }
            $account->save();
            
            // Apply the new transaction
            $this->updateAccountBalance($capitalTransaction, 'add');
        }
    }

    /**
     * Handle the CapitalTransaction "deleted" event.
     */
    public function deleted(CapitalTransaction $capitalTransaction): void
    {
        $this->updateAccountBalance($capitalTransaction, 'remove');
    }

    /**
     * Update the account balance based on the transaction
     */
    private function updateAccountBalance(CapitalTransaction $capitalTransaction, string $operation): void
    {
        $account = $capitalTransaction->account;
        
        if ($operation === 'add') {
            // Adding a transaction
            if ($capitalTransaction->type === 'deposit') {
                // Deposit increases balance
                $account->balance += $capitalTransaction->amount;
            } else {
                // Withdrawal decreases balance
                $account->balance -= $capitalTransaction->amount;
            }
        } else {
            // Removing a transaction (reverse the operation)
            if ($capitalTransaction->type === 'deposit') {
                // Reverse deposit
                $account->balance -= $capitalTransaction->amount;
            } else {
                // Reverse withdrawal
                $account->balance += $capitalTransaction->amount;
            }
        }
        
        $account->save();
    }
}
