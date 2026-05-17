# Implementation Plan: Customer/Supplier Payment Management

## Overview

This implementation plan breaks down the customer/supplier payment management feature into discrete coding tasks. The feature adds payment management capabilities to Customer and Supplier detail pages using Filament Relation Managers, enabling users to view sales/purchases, receivables/payables, and record payments with proper validation and transaction safety.

**Technology Stack**: Laravel v13, Filament v5, Livewire v4, PHP 8.4

**Key Components**:
- PaymentService with transaction safety
- Custom exceptions for validation and concurrency
- Four relation managers (Sales, Receivables, Purchases, Payables)
- Reusable payment form schema
- Comprehensive test coverage

## Tasks

- [x] 1. Create custom exception classes
  - Create `PaymentValidationException` in `app/Exceptions/`
  - Create `ConcurrentModificationException` in `app/Exceptions/`
  - Add static factory methods for common error scenarios
  - _Requirements: 3.8, 3.9, 6.8, 6.9, 9.2_

- [x] 2. Implement PaymentService with transaction safety
  - [x] 2.1 Create PaymentService class structure
    - Create `app/Services/PaymentService.php`
    - Add constructor with dependency injection if needed
    - Define method signatures for `recordPayment()`, `validatePaymentAmount()`, and `getAvailableAccounts()`
    - _Requirements: 3.10, 3.11, 3.12, 6.10, 6.11, 6.12_
  
  - [x] 2.2 Implement recordPayment method with database transactions
    - Use `DB::transaction()` to wrap all operations
    - Lock receivable/payable record using `lockForUpdate()`
    - Validate payment amount against current remaining balance
    - Create Payment record with all required fields including user_id
    - Update remaining_balance on receivable/payable using `decrement()`
    - Update Account balance (increment for receivables, decrement for payables)
    - Return created Payment instance
    - _Requirements: 3.10, 3.11, 3.12, 6.10, 6.11, 6.12, 9.1, 10.1, 10.2_
  
  - [x] 2.3 Implement validation and error handling
    - Throw `PaymentValidationException` when amount exceeds remaining balance
    - Throw `PaymentValidationException` when amount is zero or negative
    - Throw `ConcurrentModificationException` when balance changes during transaction
    - Ensure transaction rollback on any error
    - _Requirements: 3.8, 3.9, 6.8, 6.9, 9.2, 9.3_
  
  - [x] 2.4 Implement getAvailableAccounts method
    - Query active accounts suitable for transactions
    - Return collection of accounts
    - _Requirements: 8.1, 8.2_
  
  - [ ]* 2.5 Write unit tests for PaymentService
    - Test successful payment recording for receivables
    - Test successful payment recording for payables
    - Test validation errors (amount exceeds balance, zero amount, negative amount)
    - Test remaining balance updates correctly
    - Test account balance updates correctly (add for receivables, subtract for payables)
    - Test user_id is recorded correctly
    - Test partial payments work correctly
    - Test overpayment prevention
    - Test transaction rollback on errors
    - _Requirements: 3.8, 3.9, 3.10, 3.11, 3.12, 6.8, 6.9, 6.10, 6.11, 6.12, 11.1, 11.2, 12.1, 12.2_

- [x] 3. Create reusable PaymentFormSchema component
  - Create `app/Filament/Resources/Shared/Schemas/PaymentFormSchema.php`
  - Implement static `make()` method accepting receivable/payable record
  - Add account selection field with balance display using `Select::make('account_id')`
  - Add amount field with validation using `TextInput::make('amount')`
  - Add payment method selection using `Select::make('payment_method')`
  - Add payment date field using `DatePicker::make('payment_date')`
  - Add optional notes field using `Textarea::make('notes')`
  - Set max_amount hidden field to remaining_balance
  - Add helper text showing maximum allowable amount
  - Configure live validation on amount field
  - _Requirements: 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7, 8.3, 12.1, 12.3_

- [x] 4. Implement SalesRelationManager for customers
  - [x] 4.1 Create SalesRelationManager class
    - Use `php artisan make:filament-relation-manager CustomerResource sales sale_id --no-interaction`
    - Move to `app/Filament/Resources/Customers/RelationManagers/`
    - Configure relationship to Customer model
    - _Requirements: 1.1_
  
  - [x] 4.2 Configure table columns and features
    - Add `sale_date` column (sortable)
    - Add `total_amount` column (sortable, computed from sale items)
    - Add `payment_type` column with badge styling
    - Add `status` column with badge styling and colors
    - Add `notes` column with truncation
    - Set default sort to `sale_date DESC`
    - Enable search functionality
    - Add link to sale detail page
    - _Requirements: 1.2, 1.3, 1.4, 1.5, 1.6, 1.7_
  
  - [ ]* 4.3 Write feature tests for SalesRelationManager
    - Test displays sales for customer
    - Test sorting by date, amount, and status
    - Test search functionality
    - Test link to sale detail page
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7_

- [x] 5. Implement ReceivablesRelationManager for customers
  - [x] 5.1 Create ReceivablesRelationManager class
    - Use `php artisan make:filament-relation-manager CustomerResource receivables customer_id --no-interaction`
    - Move to `app/Filament/Resources/Customers/RelationManagers/`
    - Configure relationship to Customer model
    - _Requirements: 2.1_
  
  - [x] 5.2 Configure table columns with status logic
    - Add `sale_id` column with link to sale
    - Add `amount` column (sortable)
    - Add `remaining_balance` column (sortable, highlighted)
    - Add `due_date` column (sortable)
    - Add `status` column with badge using computed state (Paid/Pending/Overdue)
    - Implement status color logic (green for Paid, yellow for Pending, red for Overdue)
    - _Requirements: 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 2.8, 13.1_
  
  - [x] 5.3 Add status filter
    - Create SelectFilter for status (Paid, Pending, Overdue)
    - Configure filter query logic
    - _Requirements: 2.10_
  
  - [x] 5.4 Implement RecordPaymentAction
    - Create table action `recordPayment` with icon and color
    - Set visibility condition: `remaining_balance > 0`
    - Use PaymentFormSchema for modal form
    - Implement action handler calling PaymentService
    - Add success notification
    - Add error handling with notifications
    - Keep modal open on validation errors
    - _Requirements: 3.1, 3.2, 3.8, 3.9, 3.13, 9.3_
  
  - [x] 5.5 Implement ViewPaymentsAction
    - Create table action `viewPayments` to display payment history
    - Load payments with relationships (account, user)
    - Sort by payment_date descending
    - Display payment date, amount, method, account, notes, user, and timestamp
    - Configure modal with close button only
    - _Requirements: 7.1, 7.3, 7.4, 10.3_
  
  - [ ]* 5.6 Write feature tests for ReceivablesRelationManager
    - Test displays receivables for customer
    - Test status badges display correctly (Paid, Pending, Overdue)
    - Test status filter works
    - Test sorting by due date, amount, remaining balance
    - Test RecordPaymentAction visible only for unpaid receivables
    - Test payment modal opens with correct form
    - Test payment form validation errors
    - Test successful payment recording
    - Test success notification displays
    - Test table refreshes after payment
    - Test ViewPaymentsAction displays payment history
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 2.8, 2.9, 2.10, 3.1, 3.2, 3.8, 3.13, 7.1, 7.3, 7.4_

- [x] 6. Checkpoint - Ensure all tests pass for customer components
  - Run tests for PaymentService, SalesRelationManager, and ReceivablesRelationManager
  - Ensure all tests pass, ask the user if questions arise

- [x] 7. Implement PurchasesRelationManager for suppliers
  - [x] 7.1 Create PurchasesRelationManager class
    - Use `php artisan make:filament-relation-manager SupplierResource purchases supplier_id --no-interaction`
    - Move to `app/Filament/Resources/Suppliers/RelationManagers/`
    - Configure relationship to Supplier model
    - _Requirements: 4.1_
  
  - [x] 7.2 Configure table columns and features
    - Add `purchase_date` column (sortable)
    - Add `total_amount` column (sortable, computed from purchase items)
    - Add `payment_type` column with badge styling
    - Add `status` column with badge styling and colors
    - Add `notes` column with truncation
    - Set default sort to `purchase_date DESC`
    - Enable search functionality
    - Add link to purchase detail page
    - _Requirements: 4.2, 4.3, 4.4, 4.5, 4.6, 4.7_
  
  - [ ]* 7.3 Write feature tests for PurchasesRelationManager
    - Test displays purchases for supplier
    - Test sorting by date, amount, and status
    - Test search functionality
    - Test link to purchase detail page
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7_

- [x] 8. Implement PayablesRelationManager for suppliers
  - [x] 8.1 Create PayablesRelationManager class
    - Use `php artisan make:filament-relation-manager SupplierResource payables supplier_id --no-interaction`
    - Move to `app/Filament/Resources/Suppliers/RelationManagers/`
    - Configure relationship to Supplier model
    - _Requirements: 5.1_
  
  - [x] 8.2 Configure table columns with status logic
    - Add `purchase_id` column with link to purchase
    - Add `amount` column (sortable)
    - Add `remaining_balance` column (sortable, highlighted)
    - Add `due_date` column (sortable)
    - Add `status` column with badge using computed state (Paid/Pending/Overdue)
    - Implement status color logic (green for Paid, yellow for Pending, red for Overdue)
    - _Requirements: 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 5.8, 13.2_
  
  - [x] 8.3 Add status filter
    - Create SelectFilter for status (Paid, Pending, Overdue)
    - Configure filter query logic
    - _Requirements: 5.10_
  
  - [x] 8.4 Implement RecordPaymentAction
    - Create table action `recordPayment` with icon and color
    - Set visibility condition: `remaining_balance > 0`
    - Use PaymentFormSchema for modal form
    - Implement action handler calling PaymentService
    - Add success notification
    - Add error handling with notifications
    - Keep modal open on validation errors
    - _Requirements: 6.1, 6.2, 6.8, 6.9, 6.13, 9.3_
  
  - [x] 8.5 Implement ViewPaymentsAction
    - Create table action `viewPayments` to display payment history
    - Load payments with relationships (account, user)
    - Sort by payment_date descending
    - Display payment date, amount, method, account, notes, user, and timestamp
    - Configure modal with close button only
    - _Requirements: 7.2, 7.3, 7.4, 10.3_
  
  - [ ]* 8.6 Write feature tests for PayablesRelationManager
    - Test displays payables for supplier
    - Test status badges display correctly (Paid, Pending, Overdue)
    - Test status filter works
    - Test sorting by due date, amount, remaining balance
    - Test RecordPaymentAction visible only for unpaid payables
    - Test payment modal opens with correct form
    - Test payment form validation errors
    - Test successful payment recording
    - Test success notification displays
    - Test table refreshes after payment
    - Test ViewPaymentsAction displays payment history
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 5.8, 5.9, 5.10, 6.1, 6.2, 6.8, 6.13, 7.2, 7.3, 7.4_

- [x] 9. Register relation managers in resources
  - [x] 9.1 Register relation managers in CustomerResource
    - Open `app/Filament/Resources/Customers/CustomerResource.php`
    - Add `SalesRelationManager::class` to `getRelations()` array
    - Add `ReceivablesRelationManager::class` to `getRelations()` array
    - _Requirements: 1.1, 2.1_
  
  - [x] 9.2 Register relation managers in SupplierResource
    - Open `app/Filament/Resources/Suppliers/SupplierResource.php`
    - Add `PurchasesRelationManager::class` to `getRelations()` array
    - Add `PayablesRelationManager::class` to `getRelations()` array
    - _Requirements: 4.1, 5.1_

- [ ]* 10. Write integration tests for payment workflows
  - Test concurrent payment handling with multiple simultaneous payments
  - Test transaction rollback on errors
  - Test account balance consistency after multiple payments
  - Test multiple partial payments until fully paid
  - Test payment audit trail (user_id and timestamps)
  - Test status changes from Pending to Paid after full payment
  - Test status remains Pending/Overdue after partial payment
  - _Requirements: 9.1, 9.2, 9.3, 11.1, 11.2, 11.3, 11.4_

- [x] 11. Final checkpoint and verification
  - Run full test suite with `php artisan test --compact`
  - Run Laravel Pint to format code: `vendor/bin/pint --dirty --format agent`
  - Verify all relation managers display correctly in Filament UI
  - Verify payment recording works for both receivables and payables
  - Verify account balances update correctly
  - Verify status badges display with correct colors
  - Verify payment history displays correctly
  - Ensure all tests pass, ask the user if questions arise

## Notes

- Tasks marked with `*` are optional test tasks and can be skipped for faster MVP delivery
- Each task references specific requirements from the requirements document for traceability
- The design document provides detailed implementation guidance for each component
- All database tables and models already exist - no migrations needed
- Use Filament v5 Artisan commands to generate relation managers
- Follow Laravel Boost guidelines for code style and conventions
- PaymentService uses database transactions with row-level locking for concurrency safety
- Status logic is computed dynamically based on remaining_balance and due_date
- Payment form is reusable across both receivables and payables contexts
- All payment operations record user_id and timestamps for audit trail
- Checkpoints ensure incremental validation and allow for user feedback

## Implementation Sequence

The tasks are ordered to build foundational components first (exceptions, service, form schema), then implement customer-facing features (sales, receivables), validate with checkpoint, then implement supplier-facing features (purchases, payables), and finally integrate and test the complete system.

This approach allows for:
1. Early validation of core payment logic
2. Parallel development of customer and supplier features after checkpoint
3. Incremental testing and feedback opportunities
4. Clear separation between implementation and optional testing tasks
