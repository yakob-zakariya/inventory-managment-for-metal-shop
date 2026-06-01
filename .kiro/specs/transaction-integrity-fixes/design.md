# Transaction Integrity Fixes Bugfix Design

## Overview

This design addresses six critical data integrity bugs in the transaction management system where editing or deleting transactions fails to properly synchronize related records. The bugs occur because the system creates side effects (Payment records, Account balance changes, Stock movements, Payable/Receivable records) when transactions are created, but does not clean up or update these side effects when transactions are edited or deleted.

The fix approach uses **Eloquent Model Observers** to automatically handle cleanup and synchronization during model lifecycle events (deleting, updating). This ensures that all related records remain consistent with their parent transactions without requiring manual intervention in every controller or form handler.

**Key Design Decisions:**
- Use Model Observers (not lifecycle hooks in Filament pages) for centralized, reusable logic
- Implement transaction-safe operations using database transactions
- Calculate deltas for payment/payable/receivable adjustments to handle partial payments
- Restore stock quantities before validation when editing completed sales
- Validate account balance before allowing purchase amount increases
- Provide clear error messages to users when operations cannot be completed

## Glossary

- **Bug_Condition (C)**: The condition that triggers each bug - when transactions are edited/deleted without synchronizing related records
- **Property (P)**: The desired behavior - all related records (Payments, Payables, Receivables, Account balances, Stock) remain synchronized with their parent transactions
- **Preservation**: Existing transaction creation and status change behaviors that must remain unchanged
- **Observer**: Laravel Eloquent event listener that responds to model lifecycle events (creating, updating, deleting, etc.)
- **Polymorphic Relationship**: Payment model uses `payable_type` and `payable_id` to relate to multiple models (Expense, Purchase, Sale, Receivable, Payable)
- **Delta Calculation**: Computing the difference between old and new amounts to adjust balances incrementally
- **Stock Restoration**: Adding back the original sale quantity to available stock before validating new quantity requirements
- **Transaction Safety**: Using database transactions to ensure all-or-nothing operations that can be rolled back on error

## Bug Details

### Bug Condition 1: Expense Deletion Leaves Orphaned Payments

The bug manifests when an Expense with an associated Payment is deleted. The `DeleteAction` in Filament's EditExpense page directly deletes the Expense record without cleaning up the related Payment or restoring the Account balance.

**Formal Specification:**
```
FUNCTION isBugCondition1(input)
  INPUT: input of type ExpenseDeletionEvent
  OUTPUT: boolean
  
  RETURN input.expense.payments()->exists()
         AND input.expense IS deleted
         AND input.expense.payments() ARE NOT deleted
         AND input.expense.payments()->first()->account.balance IS NOT restored
END FUNCTION
```

### Bug Condition 2: Cash Purchase Editing Doesn't Update Payment Amount

The bug manifests when a Purchase with payment_type=CASH is edited and the total_amount changes due to item modifications. The EditPurchase page saves the Purchase and its items but does not update the associated Payment amount or adjust the Account balance.

**Formal Specification:**
```
FUNCTION isBugCondition2(input)
  INPUT: input of type PurchaseUpdateEvent
  OUTPUT: boolean
  
  RETURN input.purchase.payment_type == PaymentType::CASH
         AND input.purchase.payments()->exists()
         AND input.newTotalAmount != input.oldTotalAmount
         AND input.purchase.payments()->first()->amount == input.oldTotalAmount
         AND input.purchase.payments()->first()->account.balance NOT adjusted by delta
END FUNCTION
```

### Bug Condition 3: Sales Editing Stock Validation Fails Incorrectly

The bug manifests when a completed Sale is edited to increase item quantities. The stock validation in SaleService::completeSale() checks current stock availability without first restoring the original quantities that were deducted when the sale was completed.

**Formal Specification:**
```
FUNCTION isBugCondition3(input)
  INPUT: input of type SaleUpdateEvent
  OUTPUT: boolean
  
  RETURN input.sale.status == SaleStatus::COMPLETED
         AND input.newQuantity > input.oldQuantity
         AND stockValidation USES currentStock WITHOUT restoring input.oldQuantity
         AND validation FAILS even though (currentStock + oldQuantity) >= newQuantity
END FUNCTION
```

### Bug Condition 4: Credit Purchase Editing Doesn't Update Payable Amount

The bug manifests when a Purchase with payment_type=CREDIT is edited and the total_amount changes. The EditPurchase page saves the Purchase but does not update the associated Payable amount or remaining_balance.

**Formal Specification:**
```
FUNCTION isBugCondition4(input)
  INPUT: input of type PurchaseUpdateEvent
  OUTPUT: boolean
  
  RETURN input.purchase.payment_type == PaymentType::CREDIT
         AND input.purchase.payable EXISTS
         AND input.newTotalAmount != input.oldTotalAmount
         AND input.purchase.payable.amount == input.oldTotalAmount
         AND input.purchase.payable.remaining_balance NOT adjusted by delta
END FUNCTION
```

### Bug Condition 5: Credit Sale Editing Doesn't Update Receivable Amount

The bug manifests when a Sale with payment_type=CREDIT is edited and the total_amount changes. The EditSale page saves the Sale but does not update the associated Receivable amount or remaining_balance.

**Formal Specification:**
```
FUNCTION isBugCondition5(input)
  INPUT: input of type SaleUpdateEvent
  OUTPUT: boolean
  
  RETURN input.sale.payment_type == PaymentType::CREDIT
         AND input.sale.receivable EXISTS
         AND input.newTotalAmount != input.oldTotalAmount
         AND input.sale.receivable.amount == input.oldTotalAmount
         AND input.sale.receivable.remaining_balance NOT adjusted by delta
END FUNCTION
```

### Bug Condition 6: Purchase Editing Doesn't Validate Account Balance

The bug manifests when a Purchase with payment_type=CASH is edited to increase the total_amount. The system does not validate that the Account has sufficient balance for the additional amount before allowing the edit.

**Formal Specification:**
```
FUNCTION isBugCondition6(input)
  INPUT: input of type PurchaseUpdateEvent
  OUTPUT: boolean
  
  RETURN input.purchase.payment_type == PaymentType::CASH
         AND input.newTotalAmount > input.oldTotalAmount
         AND delta = (input.newTotalAmount - input.oldTotalAmount)
         AND input.purchase.payments()->first()->account.balance < delta
         AND update IS allowed
         AND account.balance BECOMES negative
END FUNCTION
```

### Examples

**Bug 1 Example:**
- Create Expense: ETB 500 from "Petty Cash" account (balance: ETB 1000 → ETB 500)
- Delete Expense: Payment record remains, account balance stays at ETB 500
- Expected: Payment deleted, account balance restored to ETB 1000

**Bug 2 Example:**
- Create Purchase: ETB 1000 cash from "Main Account" (balance: ETB 5000 → ETB 4000)
- Edit Purchase: Change items to total ETB 1500
- Actual: Payment shows ETB 1000, account balance stays at ETB 4000
- Expected: Payment updated to ETB 1500, account balance adjusted to ETB 3500

**Bug 3 Example:**
- Create completed Sale: 29 units of Product A (stock: 30 → 1)
- Edit Sale: Change quantity to 30 units
- Actual: Validation fails "only 1 available"
- Expected: Validation recognizes 1 + 29 = 30 available, allows edit

**Bug 4 Example:**
- Create Credit Purchase: ETB 2000 (Payable: ETB 2000, remaining: ETB 2000)
- Edit Purchase: Change items to total ETB 2500
- Actual: Payable shows ETB 2000, remaining ETB 2000
- Expected: Payable updated to ETB 2500, remaining adjusted to ETB 2500

**Bug 5 Example:**
- Create Credit Sale: ETB 1000 (Receivable: ETB 1000, remaining: ETB 1000)
- Customer pays ETB 400 (remaining: ETB 600)
- Edit Sale: Change items to total ETB 1200
- Actual: Receivable shows ETB 1000, remaining ETB 600
- Expected: Receivable updated to ETB 1200, remaining adjusted to ETB 800

**Bug 6 Example:**
- Create Cash Purchase: ETB 1000 from "Petty Cash" (balance: ETB 1300 → ETB 300)
- Edit Purchase: Change items to total ETB 1500 (needs additional ETB 500)
- Actual: Edit allowed, account balance becomes -ETB 200
- Expected: Edit prevented with error "Insufficient balance. Available: ETB 300, Required: ETB 500"

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- Transaction creation with Payments, Payables, Receivables must continue to work exactly as before
- Status changes (PENDING → COMPLETED, COMPLETED → CANCELLED) must continue to trigger stock movements via services
- Stock validation for new transactions must continue to work as before
- Account balance validation for new cash transactions must continue to work as before
- Transactions without payments (e.g., PENDING purchases) must continue to work without errors

**Scope:**
All inputs that do NOT involve editing or deleting existing transactions with related records should be completely unaffected by this fix. This includes:
- Creating new Expenses, Purchases, Sales with payments
- Changing transaction status (which triggers service methods)
- Creating transactions without payments (CREDIT purchases/sales)
- Viewing, listing, and filtering transactions

## Hypothesized Root Cause

Based on the bug description and code analysis, the root causes are:

1. **Missing Cascade Delete Logic**: The Expense model does not have a `deleting` observer to clean up related Payments and restore Account balances when deleted. Filament's DeleteAction directly calls `$record->delete()` without any cleanup logic.

2. **No Update Synchronization for Payments**: The Purchase and Sale models do not have `updating` observers to detect total_amount changes and synchronize the associated Payment amounts and Account balances. The EditPurchase and EditSale pages save the parent record and items but do not touch the Payment records.

3. **Stock Validation Without Restoration**: The SaleService::completeSale() method validates stock availability using current stock levels without accounting for the fact that editing a completed sale should first restore the original quantities. The EditSale page calls `afterSave()` which triggers status change logic, but there's no logic to handle editing an already-completed sale.

4. **No Update Synchronization for Payables/Receivables**: The Purchase and Sale models do not have `updating` observers to detect total_amount changes and synchronize the associated Payable/Receivable amounts and remaining_balance values.

5. **Missing Balance Validation on Edit**: The EditPurchase page does not validate account balance when the total_amount increases. The `beforeCreate()` validation in CreatePurchase only runs on creation, not on updates.

6. **Lifecycle Hook Limitations**: Using Filament page lifecycle hooks (`afterSave()`, `beforeDelete()`) is not sufficient because:
   - Logic is duplicated across multiple pages
   - Direct model operations (e.g., via Tinker, API, other controllers) bypass the hooks
   - Testing requires instantiating Filament pages instead of just models

## Correctness Properties

Property 1: Bug Condition 1 - Expense Deletion Cleanup

_For any_ Expense deletion where the expense has associated Payment records, the system SHALL delete all associated Payment records and restore the Account balance by adding back the total payment amount before the Expense is deleted.

**Validates: Requirements 2.1, 2.2, 2.3**

Property 2: Bug Condition 2 - Cash Purchase Payment Synchronization

_For any_ Purchase update where payment_type is CASH and the total_amount changes, the system SHALL update the associated Payment amount to match the new total_amount and adjust the Account balance by the delta (new_total - old_total).

**Validates: Requirements 2.4, 2.5, 2.6, 2.7**

Property 3: Bug Condition 3 - Sale Stock Validation with Restoration

_For any_ Sale update where status is COMPLETED and item quantities change, the system SHALL first restore the original quantities to stock before validating the new quantities, using the formula: available_stock = current_stock + original_sale_quantity.

**Validates: Requirements 2.8, 2.9, 2.10**

Property 4: Bug Condition 4 - Credit Purchase Payable Synchronization

_For any_ Purchase update where payment_type is CREDIT and the total_amount changes, the system SHALL update the associated Payable amount to match the new total_amount and adjust the remaining_balance by the delta (new_total - old_total).

**Validates: Requirements 2.11, 2.12, 2.13**

Property 5: Bug Condition 5 - Credit Sale Receivable Synchronization

_For any_ Sale update where payment_type is CREDIT and the total_amount changes, the system SHALL update the associated Receivable amount to match the new total_amount and adjust the remaining_balance by the delta (new_total - old_total).

**Validates: Requirements 2.14, 2.15, 2.16**

Property 6: Bug Condition 6 - Purchase Account Balance Validation

_For any_ Purchase update where payment_type is CASH and the total_amount increases, the system SHALL validate that the Account has sufficient balance for the additional amount (new_total - old_total) and prevent the update if insufficient, displaying an appropriate error message.

**Validates: Requirements 2.17, 2.18, 2.19**

Property 7: Preservation - Transaction Creation Behavior

_For any_ new transaction creation (Expense, Purchase, Sale) with payments, the system SHALL produce exactly the same behavior as the original code, creating Payment records and adjusting Account balances as before.

**Validates: Requirements 3.1, 3.2, 3.3**

Property 8: Preservation - Status Change Behavior

_For any_ transaction status change (PENDING → COMPLETED, COMPLETED → CANCELLED), the system SHALL produce exactly the same behavior as the original code, triggering service methods to create or reverse stock movements.

**Validates: Requirements 3.6, 3.7, 3.8, 3.9**

Property 9: Preservation - Transactions Without Payments

_For any_ transaction without associated payments (e.g., PENDING purchases, CREDIT transactions before completion), the system SHALL continue to work without errors during edit or delete operations.

**Validates: Requirements 3.12**

## Fix Implementation

### Changes Required

Assuming our root cause analysis is correct, we will implement the following changes:

#### 1. Create ExpenseObserver

**File**: `app/Observers/ExpenseObserver.php` (new file)

**Purpose**: Handle cleanup when Expenses are deleted

**Specific Changes**:
1. **Create Observer Class**: Use `php artisan make:observer ExpenseObserver --model=Expense`
2. **Implement `deleting()` Method**: 
   - Wrap in DB transaction for safety
   - Load all associated Payment records via `$expense->payments()`
   - For each Payment, restore the Account balance: `$payment->account->balance += $payment->amount`
   - Delete all Payment records: `$expense->payments()->delete()`
   - Log the operation for audit trail
3. **Register Observer**: Add to `App\Providers\AppServiceProvider::boot()`: `Expense::observe(ExpenseObserver::class)`

**Implementation Details**:
```php
public function deleting(Expense $expense): void
{
    DB::transaction(function () use ($expense) {
        // Restore account balances and delete payments
        foreach ($expense->payments as $payment) {
            $account = $payment->account;
            $account->balance += $payment->amount;
            $account->save();
        }
        
        $expense->payments()->delete();
    });
}
```

#### 2. Create PurchaseObserver

**File**: `app/Observers/PurchaseObserver.php` (new file)

**Purpose**: Handle Payment and Payable synchronization when Purchases are updated

**Specific Changes**:
1. **Create Observer Class**: Use `php artisan make:observer PurchaseObserver --model=Purchase`
2. **Implement `updating()` Method**:
   - Check if `payment_type` is CASH and total_amount changed
   - Calculate delta: `$delta = $newTotal - $oldTotal`
   - Validate account balance if delta > 0: `if ($delta > 0 && $account->balance < $delta) throw exception`
   - Update Payment amount: `$payment->amount = $newTotal`
   - Adjust Account balance: `$account->balance -= $delta`
   - Check if `payment_type` is CREDIT and total_amount changed
   - Calculate delta: `$delta = $newTotal - $oldTotal`
   - Update Payable amount: `$payable->amount = $newTotal`
   - Adjust Payable remaining_balance: `$payable->remaining_balance += $delta`
   - Wrap all operations in DB transaction
3. **Register Observer**: Add to `AppServiceProvider::boot()`

**Implementation Details**:
```php
public function updating(Purchase $purchase): void
{
    // Get original values before they're overwritten
    $oldTotal = $purchase->getOriginal('total_amount');
    $newTotal = $purchase->total_amount;
    
    if ($oldTotal == $newTotal) {
        return; // No change in total
    }
    
    $delta = $newTotal - $oldTotal;
    
    // Handle CASH purchases
    if ($purchase->payment_type === PaymentType::CASH) {
        $payment = $purchase->payments()->first();
        if ($payment) {
            // Validate balance if increasing
            if ($delta > 0) {
                $account = $payment->account;
                if ($account->balance < $delta) {
                    throw new \Exception(
                        "Insufficient balance. Available: ETB " . number_format($account->balance, 2) .
                        ", Required: ETB " . number_format($delta, 2)
                    );
                }
            }
            
            DB::transaction(function () use ($payment, $newTotal, $delta) {
                $payment->amount = $newTotal;
                $payment->save();
                
                $account = $payment->account;
                $account->balance -= $delta;
                $account->save();
            });
        }
    }
    
    // Handle CREDIT purchases
    if ($purchase->payment_type === PaymentType::CREDIT) {
        $payable = $purchase->payable;
        if ($payable) {
            DB::transaction(function () use ($payable, $newTotal, $delta) {
                $payable->amount = $newTotal;
                $payable->remaining_balance += $delta;
                $payable->save();
            });
        }
    }
}
```

#### 3. Create SaleObserver

**File**: `app/Observers/SaleObserver.php` (new file)

**Purpose**: Handle Receivable synchronization when Sales are updated

**Specific Changes**:
1. **Create Observer Class**: Use `php artisan make:observer SaleObserver --model=Sale`
2. **Implement `updating()` Method**:
   - Check if `payment_type` is CREDIT and total_amount changed
   - Calculate delta: `$delta = $newTotal - $oldTotal`
   - Update Receivable amount: `$receivable->amount = $newTotal`
   - Adjust Receivable remaining_balance: `$receivable->remaining_balance += $delta`
   - Wrap in DB transaction
3. **Register Observer**: Add to `AppServiceProvider::boot()`

**Implementation Details**:
```php
public function updating(Sale $sale): void
{
    $oldTotal = $sale->getOriginal('total_amount');
    $newTotal = $sale->total_amount;
    
    if ($oldTotal == $newTotal) {
        return;
    }
    
    $delta = $newTotal - $oldTotal;
    
    // Handle CREDIT sales
    if ($sale->payment_type === PaymentType::CREDIT) {
        $receivable = $sale->receivable;
        if ($receivable) {
            DB::transaction(function () use ($receivable, $newTotal, $delta) {
                $receivable->amount = $newTotal;
                $receivable->remaining_balance += $delta;
                $receivable->save();
            });
        }
    }
}
```

#### 4. Modify StockService for Stock Restoration

**File**: `app/Services/StockService.php`

**Purpose**: Add method to validate stock with restoration for sale edits

**Specific Changes**:
1. **Add New Method `validateStockAvailabilityWithRestoration()`**:
   - Accept parameters: `array $items`, `Sale $originalSale`
   - For each item, calculate: `available = currentStock + originalQuantity`
   - Validate: `newQuantity <= available`
   - Return array of error messages if validation fails
2. **Keep Existing `validateStockAvailability()` Unchanged**: This is used for new sales

**Implementation Details**:
```php
public function validateStockAvailabilityWithRestoration(array $items, Sale $originalSale): array
{
    $errors = [];
    
    // Build map of original quantities
    $originalQuantities = [];
    foreach ($originalSale->items as $item) {
        $originalQuantities[$item->product_id] = $item->quantity;
    }
    
    foreach ($items as $item) {
        $product = Product::find($item['product_id']);
        $newQuantity = $item['quantity'];
        $currentStock = $this->getCurrentStock($product);
        
        // Restore original quantity if this product was in the original sale
        $originalQuantity = $originalQuantities[$item['product_id']] ?? 0;
        $availableStock = $currentStock + $originalQuantity;
        
        if ($newQuantity > $availableStock) {
            $errors[] = "Insufficient stock for {$product->name}. Available (after restoration): {$availableStock}, Required: {$newQuantity}";
        }
    }
    
    return $errors;
}
```

#### 5. Modify EditSale Page for Stock Validation

**File**: `app/Filament/Resources/Sales/Pages/EditSale.php`

**Purpose**: Use stock restoration validation when editing completed sales

**Specific Changes**:
1. **Add `beforeSave()` Method**:
   - Check if sale status is COMPLETED
   - If items changed, use `StockService::validateStockAvailabilityWithRestoration()`
   - Throw exception with clear error message if validation fails
2. **Keep `afterSave()` Unchanged**: Status change logic remains the same

**Implementation Details**:
```php
protected function beforeSave(): void
{
    $sale = $this->record;
    
    // Only validate if sale is already completed
    if ($sale->status === SaleStatus::COMPLETED) {
        $newItems = $this->data['items'] ?? [];
        
        if (!empty($newItems)) {
            $stockService = app(StockService::class);
            $errors = $stockService->validateStockAvailabilityWithRestoration($newItems, $sale);
            
            if (!empty($errors)) {
                Notification::make()
                    ->danger()
                    ->title('Insufficient Stock')
                    ->body(implode("\n", $errors))
                    ->persistent()
                    ->send();
                
                throw new Halt;
            }
        }
    }
}
```

#### 6. Modify EditPurchase Page for Account Balance Validation

**File**: `app/Filament/Resources/Purchases/Pages/EditPurchase.php`

**Purpose**: Validate account balance when cash purchase total increases

**Specific Changes**:
1. **Add `beforeSave()` Method**:
   - Check if payment_type is CASH
   - Calculate delta between new and old total_amount
   - If delta > 0, validate account has sufficient balance
   - Throw exception with clear error message if insufficient
2. **Keep `afterSave()` Unchanged**: Status change logic remains the same

**Implementation Details**:
```php
protected function beforeSave(): void
{
    $purchase = $this->record;
    
    // Only validate CASH purchases
    if ($purchase->payment_type === PaymentType::CASH) {
        $oldTotal = $purchase->getOriginal('total_amount');
        $newTotal = $this->data['total_amount'] ?? 0;
        $delta = $newTotal - $oldTotal;
        
        // Only validate if total is increasing
        if ($delta > 0) {
            $payment = $purchase->payments()->first();
            if ($payment) {
                $account = $payment->account;
                
                if ($account->balance < $delta) {
                    Notification::make()
                        ->danger()
                        ->title('Insufficient Balance')
                        ->body("Account '{$account->name}' has only ETB " . number_format($account->balance, 2) .
                               " but additional ETB " . number_format($delta, 2) . " is required.")
                        ->persistent()
                        ->send();
                    
                    throw new Halt;
                }
            }
        }
    }
}
```

#### 7. Register Observers in AppServiceProvider

**File**: `app/Providers/AppServiceProvider.php`

**Purpose**: Register all observers to activate them

**Specific Changes**:
1. **Import Observer Classes**: Add use statements
2. **Add to `boot()` Method**:
   ```php
   Expense::observe(ExpenseObserver::class);
   Purchase::observe(PurchaseObserver::class);
   Sale::observe(SaleObserver::class);
   ```

## Testing Strategy

### Validation Approach

The testing strategy follows a two-phase approach: first, surface counterexamples that demonstrate the bugs on unfixed code, then verify the fixes work correctly and preserve existing behavior.

### Exploratory Bug Condition Checking

**Goal**: Surface counterexamples that demonstrate the bugs BEFORE implementing the fix. Confirm or refute the root cause analysis. If we refute, we will need to re-hypothesize.

**Test Plan**: Write tests that create transactions with payments, then edit or delete them. Run these tests on the UNFIXED code to observe failures and understand the root cause.

**Test Cases**:
1. **Expense Deletion Test**: Create expense with payment, delete it, assert payment still exists and account balance not restored (will fail on unfixed code)
2. **Cash Purchase Edit Test**: Create cash purchase, edit to increase total, assert payment amount unchanged and account balance not adjusted (will fail on unfixed code)
3. **Sale Stock Validation Test**: Create completed sale with 29 units, edit to 30 units with only 1 in stock, assert validation fails incorrectly (will fail on unfixed code)
4. **Credit Purchase Edit Test**: Create credit purchase, edit to increase total, assert payable amount unchanged (will fail on unfixed code)
5. **Credit Sale Edit Test**: Create credit sale, edit to increase total, assert receivable amount unchanged (will fail on unfixed code)
6. **Purchase Balance Validation Test**: Create cash purchase, edit to increase total beyond account balance, assert edit is allowed and balance goes negative (will fail on unfixed code)

**Expected Counterexamples**:
- Payment records remain after expense deletion
- Account balances not restored after expense deletion
- Payment amounts don't match purchase totals after edits
- Payable/Receivable amounts don't match transaction totals after edits
- Stock validation fails for legitimate sale edits
- Negative account balances allowed for purchase edits

### Fix Checking

**Goal**: Verify that for all inputs where the bug conditions hold, the fixed system produces the expected behavior.

**Pseudocode:**
```
FOR ALL transaction WHERE isBugCondition1(transaction) DO
  result := deleteExpense_fixed(transaction)
  ASSERT payments_deleted(result) AND account_balance_restored(result)
END FOR

FOR ALL transaction WHERE isBugCondition2(transaction) DO
  result := updatePurchase_fixed(transaction)
  ASSERT payment_amount_synchronized(result) AND account_balance_adjusted(result)
END FOR

FOR ALL transaction WHERE isBugCondition3(transaction) DO
  result := updateSale_fixed(transaction)
  ASSERT stock_validation_with_restoration(result)
END FOR

FOR ALL transaction WHERE isBugCondition4(transaction) DO
  result := updatePurchase_fixed(transaction)
  ASSERT payable_amount_synchronized(result)
END FOR

FOR ALL transaction WHERE isBugCondition5(transaction) DO
  result := updateSale_fixed(transaction)
  ASSERT receivable_amount_synchronized(result)
END FOR

FOR ALL transaction WHERE isBugCondition6(transaction) DO
  result := updatePurchase_fixed(transaction)
  ASSERT account_balance_validated(result) OR operation_prevented(result)
END FOR
```

### Preservation Checking

**Goal**: Verify that for all inputs where the bug conditions do NOT hold, the fixed system produces the same result as the original system.

**Pseudocode:**
```
FOR ALL transaction WHERE NOT isBugCondition(transaction) DO
  ASSERT originalBehavior(transaction) = fixedBehavior(transaction)
END FOR
```

**Testing Approach**: Property-based testing is recommended for preservation checking because:
- It generates many test cases automatically across the input domain
- It catches edge cases that manual unit tests might miss
- It provides strong guarantees that behavior is unchanged for all non-buggy inputs

**Test Plan**: Observe behavior on UNFIXED code first for transaction creation and status changes, then write property-based tests capturing that behavior.

**Test Cases**:
1. **Transaction Creation Preservation**: Verify creating new expenses, purchases, sales with payments continues to work exactly as before
2. **Status Change Preservation**: Verify changing purchase/sale status (PENDING → COMPLETED, COMPLETED → CANCELLED) continues to trigger stock movements
3. **Transactions Without Payments Preservation**: Verify editing/deleting transactions without payments continues to work without errors
4. **Stock Validation for New Sales Preservation**: Verify creating new sales with insufficient stock continues to be prevented

### Unit Tests

- Test ExpenseObserver::deleting() deletes payments and restores account balances
- Test PurchaseObserver::updating() updates payment amounts and adjusts account balances for CASH purchases
- Test PurchaseObserver::updating() updates payable amounts for CREDIT purchases
- Test PurchaseObserver::updating() validates account balance and throws exception when insufficient
- Test SaleObserver::updating() updates receivable amounts for CREDIT sales
- Test StockService::validateStockAvailabilityWithRestoration() correctly calculates available stock
- Test EditSale::beforeSave() validates stock with restoration for completed sales
- Test EditPurchase::beforeSave() validates account balance for cash purchase increases
- Test observers do not interfere when total_amount is unchanged
- Test observers handle edge cases (no payments, no payables, no receivables)

### Property-Based Tests

- Generate random expense amounts and account balances, verify deletion always cleans up correctly
- Generate random purchase edits with varying deltas, verify payment synchronization always works
- Generate random sale edits with varying stock levels, verify stock validation with restoration always works
- Generate random credit purchase/sale edits, verify payable/receivable synchronization always works
- Generate random transaction creations, verify preservation of original behavior across many scenarios

### Integration Tests

- Test full expense lifecycle: create with payment, delete, verify cleanup
- Test full cash purchase lifecycle: create, edit to increase total, edit to decrease total, verify synchronization
- Test full credit purchase lifecycle: create, edit total, make partial payment, edit total again, verify payable consistency
- Test full credit sale lifecycle: create, edit total, receive partial payment, edit total again, verify receivable consistency
- Test full sale lifecycle: create completed sale, edit quantities, verify stock validation with restoration
- Test error scenarios: attempt to edit purchase with insufficient balance, verify error message and rollback
- Test concurrent edits: simulate race conditions, verify transaction safety
