# Requirements Document

## Introduction

This document specifies requirements for adding payment management functionality to Customer and Supplier detail pages. The feature enables users to view sales/purchases and their associated receivables/payables, and record payments directly from the customer/supplier context without navigating to separate pages.

## Glossary

- **Customer_Detail_Page**: The Filament resource page displaying a single customer's information
- **Supplier_Detail_Page**: The Filament resource page displaying a single supplier's information
- **Sales_Relation_Manager**: A Filament relation manager component displaying all sales for a customer
- **Purchases_Relation_Manager**: A Filament relation manager component displaying all purchases for a supplier
- **Receivables_Relation_Manager**: A Filament relation manager component displaying all receivables for a customer
- **Payables_Relation_Manager**: A Filament relation manager component displaying all payables for a supplier
- **Record_Payment_Action**: A Filament table action that opens a modal form to record a payment
- **Payment_Form**: A modal form collecting payment details (account, amount, payment method, date, notes)
- **Payment_System**: The backend system that processes and stores payment records
- **Receivable**: A record representing money owed by a customer from a credit sale
- **Payable**: A record representing money owed to a supplier from a credit purchase
- **Remaining_Balance**: The unpaid amount on a receivable or payable
- **Payment_Record**: A polymorphic record linking a payment to either a receivable or payable
- **Account_Balance**: The current balance of a financial account used for payments

## Requirements

### Requirement 1: Display Customer Sales

**User Story:** As a user, I want to view all sales for a specific customer on their detail page, so that I can see their complete purchase history.

#### Acceptance Criteria

1. WHEN a user navigates to the Customer_Detail_Page, THE Sales_Relation_Manager SHALL display all sales associated with that customer
2. THE Sales_Relation_Manager SHALL display the sale date for each sale
3. THE Sales_Relation_Manager SHALL display the total amount for each sale
4. THE Sales_Relation_Manager SHALL display the payment type (cash or credit) for each sale
5. THE Sales_Relation_Manager SHALL display the status for each sale
6. THE Sales_Relation_Manager SHALL support sorting by sale date, amount, and status
7. THE Sales_Relation_Manager SHALL support searching by sale attributes

### Requirement 2: Display Customer Receivables

**User Story:** As a user, I want to view all receivables for a specific customer on their detail page, so that I can see what payments are outstanding.

#### Acceptance Criteria

1. WHEN a user navigates to the Customer_Detail_Page, THE Receivables_Relation_Manager SHALL display all receivables associated with that customer
2. THE Receivables_Relation_Manager SHALL display the original amount for each receivable
3. THE Receivables_Relation_Manager SHALL display the remaining balance for each receivable
4. THE Receivables_Relation_Manager SHALL display the due date for each receivable
5. THE Receivables_Relation_Manager SHALL display a status indicator showing whether the receivable is paid, pending, or overdue
6. WHEN a receivable has a remaining balance of zero, THE Receivables_Relation_Manager SHALL display the status as "Paid"
7. WHEN a receivable has a remaining balance greater than zero and the due date is in the future, THE Receivables_Relation_Manager SHALL display the status as "Pending"
8. WHEN a receivable has a remaining balance greater than zero and the due date is in the past, THE Receivables_Relation_Manager SHALL display the status as "Overdue"
9. THE Receivables_Relation_Manager SHALL support sorting by due date, amount, and remaining balance
10. THE Receivables_Relation_Manager SHALL support filtering by status (paid, pending, overdue)

### Requirement 3: Record Payment Against Customer Receivable

**User Story:** As a user, I want to record a payment against a customer receivable from the customer detail page, so that I can update the receivable balance without navigating away.

#### Acceptance Criteria

1. WHEN a user views the Receivables_Relation_Manager, THE Record_Payment_Action SHALL be available for each receivable with a remaining balance greater than zero
2. WHEN a user clicks the Record_Payment_Action, THE Payment_Form SHALL open in a modal
3. THE Payment_Form SHALL require the user to select an account from available accounts
4. THE Payment_Form SHALL require the user to enter a payment amount
5. THE Payment_Form SHALL require the user to select a payment method
6. THE Payment_Form SHALL require the user to select a payment date
7. THE Payment_Form SHALL allow the user to optionally enter notes
8. WHEN the payment amount exceeds the remaining balance, THE Payment_Form SHALL display a validation error stating "Payment amount cannot exceed remaining balance"
9. WHEN the payment amount is zero or negative, THE Payment_Form SHALL display a validation error stating "Payment amount must be greater than zero"
10. WHEN the user submits a valid Payment_Form, THE Payment_System SHALL create a Payment_Record linked to the receivable
11. WHEN a Payment_Record is created, THE Payment_System SHALL reduce the receivable's remaining balance by the payment amount
12. WHEN a Payment_Record is created, THE Payment_System SHALL update the account balance by adding the payment amount
13. WHEN a Payment_Record is successfully created, THE Payment_Form SHALL close and display a success notification

### Requirement 4: Display Supplier Purchases

**User Story:** As a user, I want to view all purchases from a specific supplier on their detail page, so that I can see our complete purchase history with them.

#### Acceptance Criteria

1. WHEN a user navigates to the Supplier_Detail_Page, THE Purchases_Relation_Manager SHALL display all purchases associated with that supplier
2. THE Purchases_Relation_Manager SHALL display the purchase date for each purchase
3. THE Purchases_Relation_Manager SHALL display the total amount for each purchase
4. THE Purchases_Relation_Manager SHALL display the payment type (cash or credit) for each purchase
5. THE Purchases_Relation_Manager SHALL display the status for each purchase
6. THE Purchases_Relation_Manager SHALL support sorting by purchase date, amount, and status
7. THE Purchases_Relation_Manager SHALL support searching by purchase attributes

### Requirement 5: Display Supplier Payables

**User Story:** As a user, I want to view all payables for a specific supplier on their detail page, so that I can see what payments we owe.

#### Acceptance Criteria

1. WHEN a user navigates to the Supplier_Detail_Page, THE Payables_Relation_Manager SHALL display all payables associated with that supplier
2. THE Payables_Relation_Manager SHALL display the original amount for each payable
3. THE Payables_Relation_Manager SHALL display the remaining balance for each payable
4. THE Payables_Relation_Manager SHALL display the due date for each payable
5. THE Payables_Relation_Manager SHALL display a status indicator showing whether the payable is paid, pending, or overdue
6. WHEN a payable has a remaining balance of zero, THE Payables_Relation_Manager SHALL display the status as "Paid"
7. WHEN a payable has a remaining balance greater than zero and the due date is in the future, THE Payables_Relation_Manager SHALL display the status as "Pending"
8. WHEN a payable has a remaining balance greater than zero and the due date is in the past, THE Payables_Relation_Manager SHALL display the status as "Overdue"
9. THE Payables_Relation_Manager SHALL support sorting by due date, amount, and remaining balance
10. THE Payables_Relation_Manager SHALL support filtering by status (paid, pending, overdue)

### Requirement 6: Record Payment Against Supplier Payable

**User Story:** As a user, I want to record a payment against a supplier payable from the supplier detail page, so that I can update the payable balance without navigating away.

#### Acceptance Criteria

1. WHEN a user views the Payables_Relation_Manager, THE Record_Payment_Action SHALL be available for each payable with a remaining balance greater than zero
2. WHEN a user clicks the Record_Payment_Action, THE Payment_Form SHALL open in a modal
3. THE Payment_Form SHALL require the user to select an account from available accounts
4. THE Payment_Form SHALL require the user to enter a payment amount
5. THE Payment_Form SHALL require the user to select a payment method
6. THE Payment_Form SHALL require the user to select a payment date
7. THE Payment_Form SHALL allow the user to optionally enter notes
8. WHEN the payment amount exceeds the remaining balance, THE Payment_Form SHALL display a validation error stating "Payment amount cannot exceed remaining balance"
9. WHEN the payment amount is zero or negative, THE Payment_Form SHALL display a validation error stating "Payment amount must be greater than zero"
10. WHEN the user submits a valid Payment_Form, THE Payment_System SHALL create a Payment_Record linked to the payable
11. WHEN a Payment_Record is created, THE Payment_System SHALL reduce the payable's remaining balance by the payment amount
12. WHEN a Payment_Record is created, THE Payment_System SHALL update the account balance by subtracting the payment amount
13. WHEN a Payment_Record is successfully created, THE Payment_Form SHALL close and display a success notification

### Requirement 7: Display Payment History

**User Story:** As a user, I want to view all payments made against a receivable or payable, so that I can track the payment history.

#### Acceptance Criteria

1. WHEN a user views a receivable in the Receivables_Relation_Manager, THE Receivables_Relation_Manager SHALL provide access to view all Payment_Records associated with that receivable
2. WHEN a user views a payable in the Payables_Relation_Manager, THE Payables_Relation_Manager SHALL provide access to view all Payment_Records associated with that payable
3. FOR ALL Payment_Records displayed, THE system SHALL show the payment date, amount, payment method, account used, and notes
4. THE Payment_Records SHALL be sorted by payment date in descending order (most recent first)

### Requirement 8: Validate Account Selection

**User Story:** As a user, I want to ensure payments are recorded against valid accounts, so that financial records remain accurate.

#### Acceptance Criteria

1. WHEN the Payment_Form is displayed, THE Payment_Form SHALL only show accounts that are active and available for transactions
2. WHEN no valid accounts exist, THE Record_Payment_Action SHALL be disabled with a message stating "No accounts available for payment"
3. THE Payment_Form SHALL display the account name and current balance for each selectable account

### Requirement 9: Handle Concurrent Payment Scenarios

**User Story:** As a user, I want the system to handle situations where multiple payments might be recorded simultaneously, so that the remaining balance stays accurate.

#### Acceptance Criteria

1. WHEN a Payment_Record is being created, THE Payment_System SHALL lock the associated receivable or payable record to prevent concurrent modifications
2. WHEN a payment would cause the remaining balance to become negative due to concurrent payments, THE Payment_System SHALL reject the payment and display an error stating "Payment cannot be processed because the remaining balance has changed"
3. WHEN a payment is rejected due to concurrent modification, THE Payment_Form SHALL remain open with the entered data so the user can adjust the amount

### Requirement 10: Maintain Audit Trail

**User Story:** As a user, I want to know who recorded each payment and when, so that I can maintain accountability.

#### Acceptance Criteria

1. WHEN a Payment_Record is created, THE Payment_System SHALL record the user who created the payment
2. WHEN a Payment_Record is created, THE Payment_System SHALL record the timestamp of creation
3. WHEN viewing Payment_Records, THE system SHALL display the user who recorded the payment and the timestamp

### Requirement 11: Support Partial Payments

**User Story:** As a user, I want to record partial payments against receivables and payables, so that I can track incremental payments over time.

#### Acceptance Criteria

1. WHEN a user records a payment with an amount less than the remaining balance, THE Payment_System SHALL accept the payment
2. WHEN a partial payment is recorded, THE Payment_System SHALL reduce the remaining balance by the payment amount
3. WHEN a partial payment is recorded, THE receivable or payable SHALL remain in "Pending" or "Overdue" status until the remaining balance reaches zero
4. FOR ALL receivables and payables, THE system SHALL support multiple Payment_Records until the remaining balance reaches zero

### Requirement 12: Prevent Overpayment

**User Story:** As a user, I want the system to prevent me from recording payments that exceed the remaining balance, so that I don't create incorrect financial records.

#### Acceptance Criteria

1. WHEN a user enters a payment amount in the Payment_Form, THE Payment_Form SHALL display the current remaining balance
2. WHEN the payment amount exceeds the remaining balance, THE Payment_Form SHALL prevent form submission
3. THE Payment_Form SHALL suggest the maximum allowable payment amount equal to the remaining balance

### Requirement 13: Link Payments to Source Transactions

**User Story:** As a user, I want to see which sale or purchase a receivable or payable originated from, so that I can trace payments back to their source.

#### Acceptance Criteria

1. WHEN viewing a receivable in the Receivables_Relation_Manager, THE Receivables_Relation_Manager SHALL display a link to the associated sale
2. WHEN viewing a payable in the Payables_Relation_Manager, THE Payables_Relation_Manager SHALL display a link to the associated purchase
3. WHEN a user clicks the sale or purchase link, THE system SHALL navigate to the sale or purchase detail page
