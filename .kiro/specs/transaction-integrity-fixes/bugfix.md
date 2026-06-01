# Bugfix Requirements Document

## Introduction

This document addresses critical data integrity issues in the transaction management system where editing or deleting transactions (Expenses, Purchases, Sales) fails to properly synchronize related records. The system creates side effects (Payment records, Account balance changes, Stock movements) when transactions are created, but does not clean up or update these side effects when transactions are edited or deleted, resulting in orphaned records and incorrect financial/inventory data.

**Impact:** Financial records show incorrect balances, payments exist for non-existent transactions, and stock validation fails for legitimate operations.

## Bug Analysis

### Current Behavior (Defect)

#### Bug 1: Expense Deletion Leaves Orphaned Payments

1.1 WHEN an Expense with an associated Payment is deleted THEN the system leaves the Payment record in the database

1.2 WHEN an Expense with an associated Payment is deleted THEN the system does NOT restore the Account balance that was reduced when the Payment was created

1.3 WHEN an Expense with an associated Payment is deleted THEN the system creates data inconsistency where money appears to be gone but the expense doesn't exist

#### Bug 2: Purchase Editing Doesn't Update Payment Amount

1.4 WHEN a Purchase with payment_type=CASH is edited and the total_amount changes (due to product changes in items) THEN the system does NOT update the associated Payment amount to match the new total

1.5 WHEN a Purchase with payment_type=CASH is edited and the total_amount changes THEN the system does NOT adjust the Account balance to reflect the difference between old and new payment amounts

1.6 WHEN a Purchase with payment_type=CASH is edited and the total_amount changes THEN the system creates data inconsistency where Payment amount doesn't match Purchase total_amount

#### Bug 3: Sales Editing Stock Validation Fails Incorrectly

1.7 WHEN a completed Sale is edited to increase the quantity of a SaleItem THEN the system validates stock availability without first restoring the original quantity that was deducted

1.8 WHEN a completed Sale with 29 units is edited to 30 units and only 1 unit remains in stock THEN the system incorrectly reports "only 1 available" instead of recognizing that 29 units would be restored first

1.9 WHEN a completed Sale is edited to change product quantities THEN the system prevents legitimate edits due to incorrect stock validation logic

#### Bug 4: Credit Purchase Editing Doesn't Update Payable Amount

1.10 WHEN a Purchase with payment_type=CREDIT is edited and the total_amount changes (due to product changes in items) THEN the system does NOT update the associated Payable amount to match the new total

1.11 WHEN a Purchase with payment_type=CREDIT is edited and the total_amount changes THEN the system does NOT update the Payable remaining_balance to reflect the new amount

1.12 WHEN a Purchase with payment_type=CREDIT is edited and the total_amount changes THEN the system creates data inconsistency where Payable amount doesn't match Purchase total_amount

#### Bug 5: Credit Sale Editing Doesn't Update Receivable Amount

1.13 WHEN a Sale with payment_type=CREDIT is edited and the total_amount changes (due to product changes in items) THEN the system does NOT update the associated Receivable amount to match the new total

1.14 WHEN a Sale with payment_type=CREDIT is edited and the total_amount changes THEN the system does NOT update the Receivable remaining_balance to reflect the new amount

1.15 WHEN a Sale with payment_type=CREDIT is edited and the total_amount changes THEN the system creates data inconsistency where Receivable amount doesn't match Sale total_amount

#### Bug 6: Purchase Editing Doesn't Validate Account Balance

1.16 WHEN a Purchase with payment_type=CASH is edited and the total_amount increases THEN the system does NOT validate that the Account has sufficient balance for the additional amount

1.17 WHEN a Purchase with payment_type=CASH is edited from ETB 1000 to ETB 1500 and the Account only has ETB 300 remaining THEN the system allows the edit and creates a negative account balance

1.18 WHEN a Purchase with payment_type=CASH is edited to increase the total THEN the system creates data inconsistency where Account balance becomes negative

### Expected Behavior (Correct)

#### Bug 1: Expense Deletion Should Clean Up Payments and Restore Balances

2.1 WHEN an Expense with an associated Payment is deleted THEN the system SHALL delete the associated Payment record(s) to prevent orphaned data

2.2 WHEN an Expense with an associated Payment is deleted THEN the system SHALL restore the Account balance by adding back the payment amount that was originally deducted

2.3 WHEN an Expense with an associated Payment is deleted THEN the system SHALL maintain data integrity where all related records are properly cleaned up

#### Bug 2: Purchase Editing Should Update Payment Amounts

2.4 WHEN a Purchase with payment_type=CASH is edited and the total_amount changes THEN the system SHALL update the associated Payment amount to match the new total_amount

2.5 WHEN a Purchase with payment_type=CASH is edited and the total_amount increases THEN the system SHALL reduce the Account balance by the difference (new_total - old_total)

2.6 WHEN a Purchase with payment_type=CASH is edited and the total_amount decreases THEN the system SHALL increase the Account balance by the difference (old_total - new_total)

2.7 WHEN a Purchase with payment_type=CASH is edited and the total_amount changes THEN the system SHALL maintain data integrity where Payment amount always matches Purchase total_amount

#### Bug 3: Sales Editing Should Validate Stock After Restoring Original Quantities

2.8 WHEN a completed Sale is edited to change SaleItem quantities THEN the system SHALL first restore the original quantities to stock before validating the new quantities

2.9 WHEN a completed Sale with 29 units is edited to 30 units and only 1 unit remains in stock THEN the system SHALL recognize that restoring 29 units gives 30 total available and allow the edit

2.10 WHEN a completed Sale is edited to change product quantities THEN the system SHALL use the formula: available_stock = current_stock + original_sale_quantity, then validate new_sale_quantity <= available_stock

#### Bug 4: Credit Purchase Editing Should Update Payable Amounts

2.11 WHEN a Purchase with payment_type=CREDIT is edited and the total_amount changes THEN the system SHALL update the associated Payable amount to match the new total_amount

2.12 WHEN a Purchase with payment_type=CREDIT is edited and the total_amount changes THEN the system SHALL update the Payable remaining_balance by adjusting it with the difference (new_total - old_total)

2.13 WHEN a Purchase with payment_type=CREDIT is edited and the total_amount changes THEN the system SHALL maintain data integrity where Payable amount always matches Purchase total_amount

#### Bug 5: Credit Sale Editing Should Update Receivable Amounts

2.14 WHEN a Sale with payment_type=CREDIT is edited and the total_amount changes THEN the system SHALL update the associated Receivable amount to match the new total_amount

2.15 WHEN a Sale with payment_type=CREDIT is edited and the total_amount changes THEN the system SHALL update the Receivable remaining_balance by adjusting it with the difference (new_total - old_total)

2.16 WHEN a Sale with payment_type=CREDIT is edited and the total_amount changes THEN the system SHALL maintain data integrity where Receivable amount always matches Sale total_amount

#### Bug 6: Purchase Editing Should Validate Account Balance

2.17 WHEN a Purchase with payment_type=CASH is edited and the total_amount increases THEN the system SHALL validate that the Account has sufficient balance for the additional amount (new_total - old_total)

2.18 WHEN a Purchase with payment_type=CASH is edited from ETB 1000 to ETB 1500 and the Account only has ETB 300 remaining THEN the system SHALL prevent the edit and show an error message

2.19 WHEN a Purchase with payment_type=CASH is edited to increase the total THEN the system SHALL prevent negative account balances by validating before applying the change

### Unchanged Behavior (Regression Prevention)

#### Transaction Creation Should Continue Working

3.1 WHEN a new Expense is created with a Payment THEN the system SHALL CONTINUE TO create the Payment record and reduce the Account balance

3.2 WHEN a new Purchase with payment_type=CASH is created THEN the system SHALL CONTINUE TO create the Payment record and reduce the Account balance

3.3 WHEN a new Sale with payment_type=CASH is created THEN the system SHALL CONTINUE TO create the Payment record and increase the Account balance

#### Stock Movements for New Transactions Should Continue Working

3.4 WHEN a new completed Purchase is created THEN the system SHALL CONTINUE TO create stock movements that increase inventory

3.5 WHEN a new completed Sale is created THEN the system SHALL CONTINUE TO create stock movements that decrease inventory and validate stock availability

#### Status Changes Should Continue Working

3.6 WHEN a Purchase status changes from PENDING to COMPLETED THEN the system SHALL CONTINUE TO create stock movements via PurchaseService::completePurchase()

3.7 WHEN a Purchase status changes from COMPLETED to CANCELLED THEN the system SHALL CONTINUE TO reverse stock movements via PurchaseService::cancelPurchase()

3.8 WHEN a Sale status changes from DRAFT to COMPLETED THEN the system SHALL CONTINUE TO create stock movements via SaleService::completeSale()

3.9 WHEN a Sale status changes from COMPLETED to CANCELLED THEN the system SHALL CONTINUE TO reverse stock movements via SaleService::cancelSale()

#### Transactions Without Payments Should Continue Working

3.10 WHEN a Purchase with payment_type=CREDIT is edited and items change THEN the system SHALL update the Payable amount accordingly (this is now a fix, not unchanged behavior)

3.11 WHEN a Sale with payment_type=CREDIT is edited and items change THEN the system SHALL update the Receivable amount accordingly (this is now a fix, not unchanged behavior)

3.12 WHEN an Expense without any associated Payments is deleted THEN the system SHALL CONTINUE TO delete successfully without errors

#### Validation for New Transactions Should Continue Working

3.13 WHEN creating a new Sale with insufficient stock THEN the system SHALL CONTINUE TO prevent the sale and show appropriate error messages

3.14 WHEN creating a new Purchase with payment_type=CASH and insufficient account balance THEN the system SHALL CONTINUE TO prevent the purchase and show appropriate error messages
