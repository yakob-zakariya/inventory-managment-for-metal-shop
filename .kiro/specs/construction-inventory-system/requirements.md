# Requirements Document

## Introduction

This document specifies the requirements for a comprehensive construction shop inventory management system. The system manages products, suppliers, customers, purchases, sales, stock movements, financial accounts, payments, expenses, and credit tracking. It provides role-based access control and maintains detailed audit trails for all transactions.

## Glossary

- **System**: The construction inventory management system
- **User**: A person with authenticated access to the System
- **Product**: An item tracked in inventory (materials, tools, equipment)
- **Category**: A classification group for Products
- **Unit**: A measurement type (kg, piece, m3, bag, liter)
- **Supplier**: A vendor from whom Products are purchased
- **Customer**: A person or entity who purchases Products
- **Purchase**: A transaction recording Products acquired from a Supplier
- **Sale**: A transaction recording Products sold to a Customer
- **Stock_Movement**: A record of inventory quantity changes
- **Account**: A financial account (cash, bank, mobile money)
- **Payment**: A financial transaction for Sales, Purchases, or Expenses
- **Expense**: A business cost not directly related to inventory purchases
- **Receivable**: Money owed to the business by a Customer
- **Payable**: Money owed by the business to a Supplier
- **Walk_In_Customer**: A Customer making a one-time purchase without registration
- **Permission**: An authorization to perform specific actions
- **Role**: A collection of Permissions assigned to Users

## Requirements

### Requirement 1: User Authentication and Authorization

**User Story:** As a business owner, I want role-based access control, so that different staff members have appropriate permissions.

#### Acceptance Criteria

1. THE System SHALL integrate with Spatie Laravel Permission package for role and permission management
2. WHEN a User attempts to access a protected resource, THE System SHALL verify the User has the required Permission
3. THE System SHALL support multiple Roles with configurable Permissions
4. WHEN a User is created, THE System SHALL assign at least one Role to the User
5. THE System SHALL track which User created each Purchase, Sale, Expense, and Payment record

### Requirement 2: Product and Category Management

**User Story:** As an inventory manager, I want to organize products by categories with specific units, so that I can track different types of construction materials.

#### Acceptance Criteria

1. THE System SHALL store Products with name, description, Category, Unit, and minimum stock level
2. THE System SHALL support Units including kg, piece, m3, bag, and liter
3. WHEN a Product's current stock falls below its minimum stock level, THE System SHALL flag the Product for reorder alert
4. THE System SHALL organize Products into Categories
5. THE System SHALL allow multiple Products to share the same Category
6. WHEN a Product is created, THE System SHALL require a valid Unit and Category

### Requirement 3: Supplier and Customer Management

**User Story:** As a sales manager, I want to track suppliers and customers with their contact information, so that I can manage business relationships.

#### Acceptance Criteria

1. THE System SHALL store Supplier information including name, contact details, address, and city
2. THE System SHALL store Customer information including name, contact details, address, and city
3. THE System SHALL allow Customers to be optional for Sales (Walk_In_Customer support)
4. WHEN a Supplier or Customer is created, THE System SHALL require at minimum a name
5. THE System SHALL maintain a list of all Suppliers and Customers for selection during transactions

### Requirement 4: Purchase Order Management

**User Story:** As a procurement officer, I want to record purchases with expected and received dates, so that I can track order fulfillment.

#### Acceptance Criteria

1. WHEN a Purchase is created, THE System SHALL record the Supplier, purchase_date, expected_date, payment type, and status
2. THE System SHALL support Purchase statuses: pending, completed, and cancelled
3. THE System SHALL support payment types: cash and credit for Purchases
4. WHEN a Purchase is received, THE System SHALL record the received_date
5. THE System SHALL link each Purchase to the User who created it
6. WHEN a Purchase contains multiple Products, THE System SHALL store each as a separate Purchase Item with quantity, unit_price, and total_price
7. FOR ALL Purchase Items in a Purchase, THE System SHALL calculate total_price as quantity multiplied by unit_price

### Requirement 5: Sales Order Management

**User Story:** As a sales clerk, I want to record sales with customer information and payment details, so that I can process customer orders.

#### Acceptance Criteria

1. WHEN a Sale is created, THE System SHALL record the Customer (or null for Walk_In_Customer), sale_date, payment type, status, and notes
2. THE System SHALL support Sale statuses: draft, completed, and cancelled
3. THE System SHALL support payment types: cash and credit for Sales
4. WHERE a Customer is not registered, THE System SHALL allow Sale creation with null customer_id
5. THE System SHALL link each Sale to the User who created it
6. WHEN a Sale contains multiple Products, THE System SHALL store each as a separate Sale Item with quantity, unit_price, and total_price
7. FOR ALL Sale Items in a Sale, THE System SHALL calculate total_price as quantity multiplied by unit_price

### Requirement 6: Stock Movement Tracking

**User Story:** As an inventory manager, I want to track all stock changes with reasons and references, so that I can audit inventory levels.

#### Acceptance Criteria

1. WHEN a Purchase is completed, THE System SHALL create a Stock_Movement record with type "purchase" and quantity_in
2. WHEN a Sale is completed, THE System SHALL create a Stock_Movement record with type "sale" and quantity_out
3. THE System SHALL support Stock_Movement types: purchase, sale, adjustment, and damage
4. WHEN a Stock_Movement is created, THE System SHALL record the Product, transaction_date, type, quantity_in or quantity_out, and reference to source transaction
5. THE System SHALL maintain reference tracking linking Stock_Movements to their originating Purchase or Sale
6. WHERE a manual adjustment is made, THE System SHALL create a Stock_Movement with type "adjustment"
7. WHERE damaged inventory is recorded, THE System SHALL create a Stock_Movement with type "damage" and quantity_out

### Requirement 7: Financial Account Management

**User Story:** As a financial manager, I want to track multiple accounts with current balances, so that I can manage business finances.

#### Acceptance Criteria

1. THE System SHALL support Account types: cash, bank, and mobile_money
2. THE System SHALL store each Account with name, type, and current balance
3. WHEN an Account is created, THE System SHALL initialize balance to zero
4. THE System SHALL maintain separate balances for each Account
5. WHEN a Payment is processed, THE System SHALL update the associated Account balance

### Requirement 8: Payment Processing

**User Story:** As a cashier, I want to record payments for sales, purchases, and expenses, so that I can track all money movements.

#### Acceptance Criteria

1. THE System SHALL support payment methods: cash, bank_transfer, mobile_money, and check
2. WHEN a Payment is created, THE System SHALL record the amount, payment_method, payment_date, and associated Account
3. THE System SHALL link Payments to Sales, Purchases, or Expenses
4. WHEN a Payment is recorded, THE System SHALL create a corresponding Cash_Movement record
5. THE System SHALL track which User created each Payment
6. WHEN a credit Sale is paid, THE System SHALL update the associated Receivable
7. WHEN a credit Purchase is paid, THE System SHALL update the associated Payable

### Requirement 9: Cash Movement Tracking

**User Story:** As an accountant, I want to track all cash inflows and outflows, so that I can reconcile accounts.

#### Acceptance Criteria

1. WHEN a Payment is received, THE System SHALL create a Cash_Movement with type "in" and update Account balance
2. WHEN a Payment is made, THE System SHALL create a Cash_Movement with type "out" and update Account balance
3. THE System SHALL link each Cash_Movement to its originating Payment
4. THE System SHALL record transaction_date for each Cash_Movement
5. FOR ALL Cash_Movements linked to an Account, THE System SHALL maintain accurate running balance

### Requirement 10: Expense Management

**User Story:** As a business manager, I want to record business expenses with categories, so that I can track operating costs.

#### Acceptance Criteria

1. WHEN an Expense is created, THE System SHALL record the category, amount, expense_date, and description
2. THE System SHALL link each Expense to the User who created it
3. THE System SHALL allow Expenses to be paid through the Payment system
4. THE System SHALL support multiple expense categories
5. WHEN an Expense is paid, THE System SHALL link the Payment to the Expense record

### Requirement 11: Receivables Management

**User Story:** As a credit manager, I want to track customer credit with due dates, so that I can manage collections.

#### Acceptance Criteria

1. WHEN a Sale is created with payment type "credit", THE System SHALL create a Receivable record
2. THE System SHALL record the Customer, amount, due_date, and remaining balance for each Receivable
3. WHEN a Payment is applied to a Receivable, THE System SHALL reduce the remaining balance
4. THE System SHALL link each Receivable to its originating Sale
5. WHEN a Receivable balance reaches zero, THE System SHALL mark it as fully paid
6. THE System SHALL track partial payments against Receivables

### Requirement 12: Payables Management

**User Story:** As an accounts payable clerk, I want to track supplier credit with due dates, so that I can manage payment obligations.

#### Acceptance Criteria

1. WHEN a Purchase is created with payment type "credit", THE System SHALL create a Payable record
2. THE System SHALL record the Supplier, amount, due_date, and remaining balance for each Payable
3. WHEN a Payment is applied to a Payable, THE System SHALL reduce the remaining balance
4. THE System SHALL link each Payable to its originating Purchase
5. WHEN a Payable balance reaches zero, THE System SHALL mark it as fully paid
6. THE System SHALL track partial payments against Payables

### Requirement 13: Audit Trail and Timestamps

**User Story:** As an auditor, I want to see who created records and when transactions occurred, so that I can verify business activities.

#### Acceptance Criteria

1. THE System SHALL record created_at and updated_at timestamps for all records
2. THE System SHALL distinguish between transaction_date (business date) and created_at (system timestamp)
3. WHEN a Purchase is created, THE System SHALL record the purchase_date as the business transaction date
4. WHEN a Sale is created, THE System SHALL record the sale_date as the business transaction date
5. WHEN an Expense is created, THE System SHALL record the expense_date as the business transaction date
6. THE System SHALL link Purchases, Sales, Expenses, and Payments to the creating User

### Requirement 14: Inventory Reporting

**User Story:** As an inventory manager, I want to view current stock levels and movement history, so that I can make informed purchasing decisions.

#### Acceptance Criteria

1. THE System SHALL calculate current stock level for each Product based on all Stock_Movements
2. WHEN stock level is requested for a Product, THE System SHALL sum all quantity_in and subtract all quantity_out
3. THE System SHALL display Products with stock below minimum stock level
4. THE System SHALL provide Stock_Movement history for each Product
5. THE System SHALL show the source transaction reference for each Stock_Movement

### Requirement 15: Transaction Status Management

**User Story:** As a transaction manager, I want to update transaction statuses, so that I can reflect the current state of orders.

#### Acceptance Criteria

1. WHEN a Purchase status changes to "completed", THE System SHALL require a received_date
2. WHEN a Purchase status changes to "cancelled", THE System SHALL not create Stock_Movements
3. WHEN a Sale status changes to "completed", THE System SHALL create Stock_Movements
4. WHEN a Sale status changes to "cancelled", THE System SHALL reverse any Stock_Movements
5. THE System SHALL prevent status changes that would create inconsistent data

### Requirement 16: Data Validation and Integrity

**User Story:** As a system administrator, I want data validation rules enforced, so that the database maintains integrity.

#### Acceptance Criteria

1. WHEN a quantity is entered, THE System SHALL require it to be greater than zero
2. WHEN a price is entered, THE System SHALL require it to be greater than or equal to zero
3. WHEN a date is entered, THE System SHALL validate it is a valid date format
4. THE System SHALL prevent deletion of records referenced by other records
5. WHEN a Payment amount exceeds the outstanding balance, THE System SHALL reject the Payment
6. THE System SHALL ensure Account balances cannot become negative unless explicitly allowed
7. WHEN a Stock_Movement would result in negative inventory, THE System SHALL reject the movement
