<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Expense;
use App\Models\Payable;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Receivable;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Exception;

class PaymentService
{
    /**
     * Process a payment for any payable (Purchase, Sale, Expense, Payable, Receivable)
     */
    public function processPayment(
        Model $payable,
        Account $account,
        float $amount,
        string $paymentMethod,
        string $paymentDate,
        ?string $notes = null,
        int $userId
    ): Payment {
        return DB::transaction(function () use ($payable, $account, $amount, $paymentMethod, $paymentDate, $notes, $userId) {
            // Validate amount
            $this->validatePaymentAmount($payable, $amount);

            // Create payment record
            $payment = Payment::create([
                'account_id' => $account->id,
                'user_id' => $userId,
                'payable_type' => get_class($payable),
                'payable_id' => $payable->id,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'payment_date' => $paymentDate,
                'notes' => $notes,
            ]);

            // Update account balance
            $this->updateAccountBalance($account, $payable, $amount);

            // Update payable/receivable balance if applicable
            $this->updatePayableBalance($payable, $amount);

            return $payment;
        });
    }

    /**
     * Validate payment amount doesn't exceed remaining balance
     */
    protected function validatePaymentAmount(Model $payable, float $amount): void
    {
        $remainingBalance = $this->getRemainingBalance($payable);

        if ($amount > $remainingBalance) {
            throw new Exception("Payment amount ($" . number_format($amount, 2) . ") exceeds remaining balance ($" . number_format($remainingBalance, 2) . ")");
        }

        if ($amount <= 0) {
            throw new Exception("Payment amount must be greater than zero");
        }
    }

    /**
     * Get remaining balance for a payable
     */
    protected function getRemainingBalance(Model $payable): float
    {
        if ($payable instanceof Payable || $payable instanceof Receivable) {
            return $payable->remaining_balance;
        }

        if ($payable instanceof Expense) {
            return $payable->remaining_balance;
        }

        if ($payable instanceof Purchase || $payable instanceof Sale) {
            return $payable->remaining_balance;
        }

        throw new Exception("Invalid payable type");
    }

    /**
     * Update account balance based on payment direction
     */
    protected function updateAccountBalance(Account $account, Model $payable, float $amount): void
    {
        // Money IN (customer pays us)
        if ($payable instanceof Sale || $payable instanceof Receivable) {
            $account->balance += $amount;
        }
        // Money OUT (we pay supplier/expense)
        else {
            $account->balance -= $amount;
        }

        $account->save();
    }

    /**
     * Update payable/receivable remaining balance
     */
    protected function updatePayableBalance(Model $payable, float $amount): void
    {
        if ($payable instanceof Payable || $payable instanceof Receivable) {
            $payable->remaining_balance -= $amount;
            $payable->save();
        }
    }
}
