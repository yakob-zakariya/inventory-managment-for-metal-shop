<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Exceptions\ConcurrentModificationException;
use App\Exceptions\PaymentValidationException;
use App\Models\Account;
use App\Models\Payable;
use App\Models\Payment;
use App\Models\Receivable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /**
     * Record a payment against a receivable or payable
     *
     * @param  string  $payableType  Receivable::class or Payable::class
     * @param  int  $payableId  ID of the receivable or payable
     * @param  int  $accountId  ID of the account to use for payment
     * @param  float  $amount  Payment amount
     * @param  PaymentMethod  $paymentMethod  Payment method (cash, bank transfer, etc.)
     * @param  Carbon  $paymentDate  Date of payment
     * @param  string|null  $notes  Optional payment notes
     * @return Payment Created payment record
     *
     * @throws PaymentValidationException
     * @throws ConcurrentModificationException
     */
    public function recordPayment(
        string $payableType,
        int $payableId,
        int $accountId,
        float $amount,
        PaymentMethod $paymentMethod,
        Carbon $paymentDate,
        ?string $notes = null
    ): Payment {
        return DB::transaction(function () use (
            $payableType,
            $payableId,
            $accountId,
            $amount,
            $paymentMethod,
            $paymentDate,
            $notes
        ) {
            // Lock the receivable/payable record to prevent concurrent modifications
            $payable = $payableType::lockForUpdate()->findOrFail($payableId);

            // Validate amount
            if ($amount <= 0) {
                throw PaymentValidationException::invalidAmount($amount);
            }

            if ($amount > $payable->remaining_balance) {
                throw PaymentValidationException::amountExceedsBalance(
                    $amount,
                    $payable->remaining_balance
                );
            }

            // Create payment record
            $payment = Payment::create([
                'account_id' => $accountId,
                'user_id' => auth()->id(),
                'payable_type' => $payableType,
                'payable_id' => $payableId,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'payment_date' => $paymentDate,
                'notes' => $notes,
            ]);

            // Update remaining balance on receivable/payable
            $payable->decrement('remaining_balance', $amount);

            // Update account balance
            $account = Account::lockForUpdate()->findOrFail($accountId);

            if ($payableType === Receivable::class) {
                // Money coming in (receivable payment)
                $account->increment('balance', $amount);
            } else {
                // Money going out (payable payment)
                $account->decrement('balance', $amount);
            }

            return $payment;
        });
    }

    /**
     * Validate payment amount against remaining balance
     *
     * @param  string  $payableType  Receivable::class or Payable::class
     * @param  int  $payableId  ID of the receivable or payable
     * @param  float  $amount  Payment amount to validate
     *
     * @throws PaymentValidationException
     */
    public function validatePaymentAmount(
        string $payableType,
        int $payableId,
        float $amount
    ): void {
        if ($amount <= 0) {
            throw PaymentValidationException::invalidAmount($amount);
        }

        $payable = $payableType::findOrFail($payableId);

        if ($amount > $payable->remaining_balance) {
            throw PaymentValidationException::amountExceedsBalance(
                $amount,
                $payable->remaining_balance
            );
        }
    }

    /**
     * Get all available accounts for payment transactions
     *
     * @return Collection<Account>
     */
    public function getAvailableAccounts(): Collection
    {
        return Account::query()
            ->orderBy('name')
            ->get();
    }
}
