# Implementation Plan

## Phase 1: Exploratory Bug Condition Tests (BEFORE Fix)

- [x] 1. Write bug condition exploration tests for all six bugs
  - **Property 1: Bug Condition** - Transaction Integrity Bugs Exist
  - **CRITICAL**: These tests MUST FAIL on unfixed code - failure confirms the bugs exist
  - **DO NOT attempt to fix the tests or the code when they fail**
  - **NOTE**: These tests encode the expected behavior - they will validate the fixes when they pass after implementation
  - **GOAL**: Surface counterexamples that demonstrate the bugs exist
  - Test Bug 1: Expense deletion leaves orphaned payments and doesn't restore account balance
  - Test Bug 2: Cash purchase editing doesn't update payment amount or adjust account balance
  - Test Bug 3: Sale editing stock validation fails incorrectly without restoring original quantities
  - Test Bug 4: Credit purchase editing doesn't update payable amount or remaining_balance
  - Test Bug 5: Credit sale editing doesn't update receivable amount or remaining_balance
  - Test Bug 6: Purchase editing doesn't validate account balance and allows negative balances
  - Run tests on UNFIXED code
  - **EXPECTED OUTCOME**: Tests FAIL (this is correct - it proves the bugs exist)
  - Document counterexamples found to understand root cause
  - Mark task complete when tests are written, run, and failures are documented
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 1.9, 1.10, 1.11, 1.12, 1.13, 1.14, 1.15, 1.16, 1.17, 1.18_

- [ ] 2. Write preservation property tests (BEFORE implementing fix)
  - **Property 2: Preservation** - Existing Transaction Behaviors Unchanged
  - **IMPORTANT**: Follow observation-first methodology
  - Observe behavior on UNFIXED code for non-buggy inputs
  - Test transaction creation with payments continues to work (expenses, purchases, sales)
  - Test status changes (PENDING → COMPLETED, COMPLETED → CANCELLED) continue to trigger stock movements
  - Test transactions without payments continue to work during edit/delete
  - Test stock validation for new sales continues to prevent insufficient stock
  - Test account balance validation for new cash transactions continues to work
  - Write property-based tests capturing observed behavior patterns from Preservation Requirements
  - Property-based testing generates many test cases for stronger guarantees
  - Run tests on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (this confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 3.9, 3.10, 3.11, 3.12, 3.13, 3.14_

## Phase 2: Implementation

- [ ] 3. Create Model Observers for transaction integrity

  - [ ] 3.1 Create ExpenseObserver
    - Run `php artisan make:observer ExpenseObserver --model=Expense --no-interaction`
    - Implement `deleting()` method to handle cleanup when expenses are deleted
    - Wrap operations in DB transaction for safety
    - Load all associated Payment records via `$expense->payments()`
    - For each Payment, restore the Account balance: `$payment->account->balance += $payment->amount`
    - Delete all Payment records: `$expense->payments()->delete()`
    - _Bug_Condition: isBugCondition1(input) where input.expense.payments()->exists() AND input.expense IS deleted_
    - _Expected_Behavior: payments_deleted(result) AND account_balance_restored(result) from design_
    - _Preservation: Transaction creation and status changes remain unchanged_
    - _Requirements: 2.1, 2.2, 2.3_

  - [x] 3.2 Create PurchaseObserver
    - Run `php artisan make:observer PurchaseObserver --model=Purchase --no-interaction`
    - Implement `updating()` method to handle Payment and Payable synchronization
    - Get original values before they're overwritten: `$oldTotal = $purchase->getOriginal('total_amount')`
    - Calculate delta: `$delta = $newTotal - $oldTotal`
    - Handle CASH purchases: validate balance if increasing, update Payment amount, adjust Account balance
    - Handle CREDIT purchases: update Payable amount and remaining_balance
    - Wrap all operations in DB transaction
    - Throw exception with clear error message if account balance insufficient
    - _Bug_Condition: isBugCondition2(input) for CASH and isBugCondition4(input) for CREDIT and isBugCondition6(input) for balance validation_
    - _Expected_Behavior: payment_amount_synchronized(result) AND account_balance_adjusted(result) AND payable_amount_synchronized(result) AND account_balance_validated(result) from design_
    - _Preservation: Transaction creation and status changes remain unchanged_
    - _Requirements: 2.4, 2.5, 2.6, 2.7, 2.11, 2.12, 2.13, 2.17, 2.18, 2.19_

  - [ ] 3.3 Create SaleObserver
    - Run `php artisan make:observer SaleObserver --model=Sale --no-interaction`
    - Implement `updating()` method to handle Receivable synchronization
    - Get original values: `$oldTotal = $sale->getOriginal('total_amount')`
    - Calculate delta: `$delta = $newTotal - $oldTotal`
    - Handle CREDIT sales: update Receivable amount and remaining_balance
    - Wrap operations in DB transaction
    - _Bug_Condition: isBugCondition5(input) where input.sale.payment_type == CREDIT AND total_amount changes_
    - _Expected_Behavior: receivable_amount_synchronized(result) from design_
    - _Preservation: Transaction creation and status changes remain unchanged_
    - _Requirements: 2.14, 2.15, 2.16_

  - [ ] 3.4 Register observers in AppServiceProvider
    - Open `app/Providers/AppServiceProvider.php`
    - Import observer classes: `use App\Observers\{ExpenseObserver, PurchaseObserver, SaleObserver};`
    - Import model classes: `use App\Models\{Expense, Purchase, Sale};`
    - Add to `boot()` method:
      - `Expense::observe(ExpenseObserver::class);`
      - `Purchase::observe(PurchaseObserver::class);`
      - `Sale::observe(SaleObserver::class);`
    - _Requirements: All observer requirements_

- [ ] 4. Add stock restoration validation to StockService

  - [ ] 4.1 Add validateStockAvailabilityWithRestoration method
    - Open `app/Services/StockService.php`
    - Add new method `validateStockAvailabilityWithRestoration(array $items, Sale $originalSale): array`
    - Build map of original quantities from `$originalSale->items`
    - For each item, calculate: `$availableStock = $currentStock + $originalQuantity`
    - Validate: `$newQuantity <= $availableStock`
    - Return array of error messages if validation fails
    - Keep existing `validateStockAvailability()` method unchanged (used for new sales)
    - _Bug_Condition: isBugCondition3(input) where sale is COMPLETED and quantities change_
    - _Expected_Behavior: stock_validation_with_restoration(result) using formula available_stock = current_stock + original_sale_quantity from design_
    - _Preservation: Stock validation for new sales remains unchanged_
    - _Requirements: 2.8, 2.9, 2.10_

- [ ] 5. Modify EditSale page for stock validation with restoration

  - [ ] 5.1 Add beforeSave method to EditSale
    - Open `app/Filament/Resources/Sales/Pages/EditSale.php`
    - Add `beforeSave()` method
    - Check if sale status is COMPLETED: `if ($sale->status === SaleStatus::COMPLETED)`
    - Get new items from form data: `$newItems = $this->data['items'] ?? []`
    - Use `StockService::validateStockAvailabilityWithRestoration($newItems, $sale)`
    - If validation fails, show Notification with error messages and throw `Halt` exception
    - Keep `afterSave()` method unchanged (status change logic remains the same)
    - _Bug_Condition: isBugCondition3(input) where sale is COMPLETED and quantities change_
    - _Expected_Behavior: stock_validation_with_restoration(result) from design_
    - _Preservation: Status change logic in afterSave() remains unchanged_
    - _Requirements: 2.8, 2.9, 2.10_

- [ ] 6. Modify EditPurchase page for account balance validation

  - [ ] 6.1 Add beforeSave method to EditPurchase
    - Open `app/Filament/Resources/Purchases/Pages/EditPurchase.php`
    - Add `beforeSave()` method
    - Check if payment_type is CASH: `if ($purchase->payment_type === PaymentType::CASH)`
    - Calculate delta: `$delta = $newTotal - $oldTotal`
    - If delta > 0, validate account has sufficient balance
    - If insufficient, show Notification with error message and throw `Halt` exception
    - Keep `afterSave()` method unchanged (status change logic remains the same)
    - _Bug_Condition: isBugCondition6(input) where payment_type is CASH and total increases_
    - _Expected_Behavior: account_balance_validated(result) OR operation_prevented(result) from design_
    - _Preservation: Status change logic in afterSave() remains unchanged_
    - _Requirements: 2.17, 2.18, 2.19_

  - [ ] 6.2 Verify bug condition exploration tests now pass
    - **Property 1: Expected Behavior** - Transaction Integrity Bugs Fixed
    - **IMPORTANT**: Re-run the SAME tests from task 1 - do NOT write new tests
    - The tests from task 1 encode the expected behavior
    - When these tests pass, it confirms the expected behavior is satisfied
    - Run bug condition exploration tests from step 1
    - **EXPECTED OUTCOME**: Tests PASS (confirms bugs are fixed)
    - Verify Bug 1: Expense deletion now deletes payments and restores account balance
    - Verify Bug 2: Cash purchase editing now updates payment amount and adjusts account balance
    - Verify Bug 3: Sale editing stock validation now works correctly with restoration
    - Verify Bug 4: Credit purchase editing now updates payable amount and remaining_balance
    - Verify Bug 5: Credit sale editing now updates receivable amount and remaining_balance
    - Verify Bug 6: Purchase editing now validates account balance and prevents negative balances
    - _Requirements: Expected Behavior Properties from design (2.1-2.19)_

  - [ ] 6.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Existing Transaction Behaviors Unchanged
    - **IMPORTANT**: Re-run the SAME tests from task 2 - do NOT write new tests
    - Run preservation property tests from step 2
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - Confirm transaction creation with payments still works
    - Confirm status changes still trigger stock movements
    - Confirm transactions without payments still work
    - Confirm stock validation for new sales still works
    - Confirm account balance validation for new transactions still works
    - _Requirements: Preservation Requirements from design (3.1-3.14)_

## Phase 3: Comprehensive Testing

- [ ] 7. Write unit tests for observers and services

  - [ ] 7.1 Test ExpenseObserver::deleting()
    - Test deletes all associated payments
    - Test restores account balances correctly
    - Test handles expenses without payments gracefully
    - Test transaction rollback on error
    - _Requirements: 2.1, 2.2, 2.3_

  - [ ] 7.2 Test PurchaseObserver::updating()
    - Test updates payment amount for CASH purchases when total changes
    - Test adjusts account balance correctly (both increase and decrease)
    - Test validates account balance and throws exception when insufficient
    - Test updates payable amount for CREDIT purchases when total changes
    - Test adjusts payable remaining_balance correctly
    - Test does not interfere when total_amount is unchanged
    - Test handles purchases without payments/payables gracefully
    - Test transaction rollback on error
    - _Requirements: 2.4, 2.5, 2.6, 2.7, 2.11, 2.12, 2.13, 2.17, 2.18, 2.19_

  - [ ] 7.3 Test SaleObserver::updating()
    - Test updates receivable amount for CREDIT sales when total changes
    - Test adjusts receivable remaining_balance correctly
    - Test does not interfere when total_amount is unchanged
    - Test handles sales without receivables gracefully
    - Test transaction rollback on error
    - _Requirements: 2.14, 2.15, 2.16_

  - [ ] 7.4 Test StockService::validateStockAvailabilityWithRestoration()
    - Test correctly calculates available stock with restoration
    - Test returns errors when new quantity exceeds available stock
    - Test returns empty array when validation passes
    - Test handles products not in original sale
    - Test handles multiple products with varying stock levels
    - _Requirements: 2.8, 2.9, 2.10_

  - [ ] 7.5 Test EditSale::beforeSave()
    - Test validates stock with restoration for completed sales
    - Test shows notification and halts on validation failure
    - Test allows save when validation passes
    - Test does not interfere with non-completed sales
    - _Requirements: 2.8, 2.9, 2.10_

  - [ ] 7.6 Test EditPurchase::beforeSave()
    - Test validates account balance for cash purchase increases
    - Test shows notification and halts when balance insufficient
    - Test allows save when balance sufficient
    - Test does not interfere with credit purchases
    - Test does not interfere when total decreases
    - _Requirements: 2.17, 2.18, 2.19_

- [ ] 8. Write integration tests for full transaction lifecycles

  - [ ] 8.1 Test full expense lifecycle
    - Create expense with payment
    - Verify payment created and account balance reduced
    - Delete expense
    - Verify payment deleted and account balance restored
    - _Requirements: 2.1, 2.2, 2.3, 3.1_

  - [ ] 8.2 Test full cash purchase lifecycle
    - Create cash purchase with payment
    - Verify payment created and account balance reduced
    - Edit to increase total
    - Verify payment amount updated and account balance adjusted
    - Edit to decrease total
    - Verify payment amount updated and account balance adjusted
    - _Requirements: 2.4, 2.5, 2.6, 2.7, 3.2_

  - [ ] 8.3 Test full credit purchase lifecycle
    - Create credit purchase with payable
    - Verify payable created with correct amount and remaining_balance
    - Edit to increase total
    - Verify payable amount and remaining_balance updated
    - Make partial payment
    - Verify remaining_balance reduced
    - Edit total again
    - Verify payable amount and remaining_balance adjusted correctly
    - _Requirements: 2.11, 2.12, 2.13, 3.10_

  - [ ] 8.4 Test full credit sale lifecycle
    - Create credit sale with receivable
    - Verify receivable created with correct amount and remaining_balance
    - Edit to increase total
    - Verify receivable amount and remaining_balance updated
    - Receive partial payment
    - Verify remaining_balance reduced
    - Edit total again
    - Verify receivable amount and remaining_balance adjusted correctly
    - _Requirements: 2.14, 2.15, 2.16, 3.11_

  - [ ] 8.5 Test full sale stock validation lifecycle
    - Create completed sale with 29 units (stock: 30 → 1)
    - Edit to 30 units
    - Verify validation recognizes 1 + 29 = 30 available and allows edit
    - Verify stock movements updated correctly
    - Edit to 31 units
    - Verify validation fails with appropriate error message
    - _Requirements: 2.8, 2.9, 2.10, 3.5_

  - [ ] 8.6 Test purchase balance validation error scenarios
    - Create cash purchase ETB 1000 (account balance: ETB 1300 → ETB 300)
    - Attempt to edit to ETB 1500 (needs additional ETB 500)
    - Verify edit prevented with error message showing available and required amounts
    - Verify purchase total remains at ETB 1000
    - Verify account balance remains at ETB 300
    - _Requirements: 2.17, 2.18, 2.19_

- [ ] 9. Write property-based tests for comprehensive coverage

  - [ ] 9.1 Property test: Expense deletion always cleans up correctly
    - Generate random expense amounts and account balances
    - For each scenario, create expense with payment, delete it
    - Assert payment deleted and account balance restored for all cases
    - _Requirements: 2.1, 2.2, 2.3_

  - [ ] 9.2 Property test: Purchase payment synchronization always works
    - Generate random purchase edits with varying deltas (positive and negative)
    - For each scenario, verify payment amount matches new total
    - Verify account balance adjusted by correct delta
    - _Requirements: 2.4, 2.5, 2.6, 2.7_

  - [ ] 9.3 Property test: Sale stock validation with restoration always works
    - Generate random sale edits with varying stock levels and quantities
    - For each scenario, verify validation uses restoration formula
    - Verify legitimate edits allowed and invalid edits prevented
    - _Requirements: 2.8, 2.9, 2.10_

  - [ ] 9.4 Property test: Credit purchase/sale synchronization always works
    - Generate random credit transaction edits with varying deltas
    - For each scenario, verify payable/receivable amounts match new totals
    - Verify remaining_balance adjusted correctly
    - _Requirements: 2.11, 2.12, 2.13, 2.14, 2.15, 2.16_

  - [ ] 9.5 Property test: Transaction creation preservation
    - Generate random transaction creations with payments
    - Verify behavior identical to original code for all scenarios
    - Test expenses, cash purchases, cash sales, credit purchases, credit sales
    - _Requirements: 3.1, 3.2, 3.3_

  - [ ] 9.6 Property test: Status change preservation
    - Generate random status changes (PENDING → COMPLETED, COMPLETED → CANCELLED)
    - Verify stock movements triggered correctly for all scenarios
    - Verify behavior identical to original code
    - _Requirements: 3.6, 3.7, 3.8, 3.9_

- [ ] 10. Checkpoint - Ensure all tests pass
  - Run full test suite: `php artisan test --compact`
  - Verify all bug condition tests pass (bugs are fixed)
  - Verify all preservation tests pass (no regressions)
  - Verify all unit tests pass
  - Verify all integration tests pass
  - Verify all property-based tests pass
  - Run Laravel Pint to format code: `vendor/bin/pint --dirty --format agent`
  - Ensure all tests pass, ask the user if questions arise
