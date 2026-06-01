<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Enums\PurchaseStatus;
use App\Enums\UnitType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Payable;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exploratory Bug Condition Tests for Purchase Integrity Issues
 *
 * CRITICAL: These tests MUST FAIL on unfixed code - failure confirms the bugs exist.
 * DO NOT attempt to fix the tests or the code when they fail.
 * These tests encode the expected behavior - they will validate the fixes when they pass after implementation.
 *
 * Tests Bug 2, Bug 4, and Bug 6 from the bugfix requirements.
 */
class PurchaseIntegrityBugTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Account $account;

    protected Supplier $supplier;

    protected Product $product;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->user = User::factory()->create();

        $this->account = Account::create([
            'name' => 'Test Account',
            'type' => AccountType::CASH,
            'balance' => 5000.00,
            'description' => 'Test account for purchase tests',
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Test Supplier',
            'phone' => '1234567890',
            'email' => 'supplier@test.com',
        ]);

        $this->category = Category::create([
            'name' => 'Test Category',
            'description' => 'Test category for purchase tests',
        ]);

        $this->product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Test Product',
            'unit' => UnitType::PIECE,
            'purchase_price' => 100.00,
            'selling_price' => 150.00,
            'current_stock' => 0,
            'minimum_stock_alert' => 10,
        ]);
    }

    /**
     * Bug 2: Cash Purchase Editing Doesn't Update Payment Amount
     *
     * EXPECTED TO FAIL on unfixed code - this confirms Bug 2 exists.
     *
     * Scenario:
     * 1. Create cash purchase for ETB 1000 (payment created, account balance: 5000 → 4000)
     * 2. Edit purchase to change items, new total is ETB 1500
     * 3. BUG: Payment amount stays at ETB 1000, account balance stays at ETB 4000
     * 4. EXPECTED: Payment amount should be ETB 1500, account balance should be ETB 3500
     *
     * Requirements: 1.4, 1.5, 1.6 (Current Behavior - Defect)
     * Expected Fix: 2.4, 2.5, 2.6, 2.7 (Expected Behavior - Correct)
     */
    public function test_bug2_cash_purchase_editing_does_not_update_payment_amount(): void
    {
        // Step 1: Create cash purchase for ETB 1000
        $purchase = Purchase::create([
            'supplier_id' => $this->supplier->id,
            'user_id' => $this->user->id,
            'purchase_date' => now(),
            'payment_type' => PaymentType::CASH,
            'status' => PurchaseStatus::PENDING,
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 100.00,
            'total_price' => 1000.00,
        ]);

        // Create payment (simulating what CreatePurchase does)
        $payment = Payment::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'payable_type' => Purchase::class,
            'payable_id' => $purchase->id,
            'amount' => 1000.00,
            'payment_method' => PaymentMethod::CASH,
            'payment_date' => now(),
            'notes' => 'Cash payment for purchase',
        ]);

        // Reduce account balance
        $this->account->balance -= 1000.00;
        $this->account->save();

        // Verify initial state
        $this->assertEquals(1000.00, $payment->fresh()->amount);
        $this->assertEquals(4000.00, $this->account->fresh()->balance);

        // Step 2: Edit purchase to change total to ETB 1500
        $purchase->items()->delete(); // Remove old items
        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->product->id,
            'quantity' => 15,
            'unit_price' => 100.00,
            'total_price' => 1500.00,
        ]);

        // Update the purchase to trigger the observer (update timestamps)
        $purchase->update(['notes' => $purchase->notes ?? 'Updated']);

        // Step 3: Assert expected behavior (WILL FAIL on unfixed code)
        $payment->refresh();
        $this->account->refresh();

        // Expected: Payment amount should be updated to ETB 1500
        $this->assertEquals(
            1500.00,
            $payment->amount,
            'Bug 2: Payment amount should be updated to match new purchase total (ETB 1500), but it remains at ETB 1000'
        );

        // Expected: Account balance should be adjusted by delta (5000 - 1500 = 3500)
        $this->assertEquals(
            3500.00,
            $this->account->balance,
            'Bug 2: Account balance should be adjusted to ETB 3500 (5000 - 1500), but it remains at ETB 4000'
        );
    }

    /**
     * Bug 2 (Decrease Scenario): Cash Purchase Editing Should Restore Balance When Total Decreases
     *
     * EXPECTED TO FAIL on unfixed code.
     *
     * Scenario:
     * 1. Create cash purchase for ETB 1000 (account balance: 5000 → 4000)
     * 2. Edit purchase to decrease total to ETB 600
     * 3. BUG: Payment amount stays at ETB 1000, account balance stays at ETB 4000
     * 4. EXPECTED: Payment amount should be ETB 600, account balance should be ETB 4400
     *
     * Requirements: 2.6 (Expected Behavior - Correct)
     */
    public function test_bug2_cash_purchase_editing_should_restore_balance_when_total_decreases(): void
    {
        // Create cash purchase for ETB 1000
        $purchase = Purchase::create([
            'supplier_id' => $this->supplier->id,
            'user_id' => $this->user->id,
            'purchase_date' => now(),
            'payment_type' => PaymentType::CASH,
            'status' => PurchaseStatus::PENDING,
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 100.00,
            'total_price' => 1000.00,
        ]);

        $payment = Payment::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'payable_type' => Purchase::class,
            'payable_id' => $purchase->id,
            'amount' => 1000.00,
            'payment_method' => PaymentMethod::CASH,
            'payment_date' => now(),
        ]);

        $this->account->balance -= 1000.00;
        $this->account->save();

        // Edit purchase to decrease total to ETB 600
        $purchase->items()->delete();
        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->product->id,
            'quantity' => 6,
            'unit_price' => 100.00,
            'total_price' => 600.00,
        ]);

        $purchase->update(['notes' => $purchase->notes ?? 'Updated']);

        // Assert expected behavior (WILL FAIL on unfixed code)
        $payment->refresh();
        $this->account->refresh();

        $this->assertEquals(
            600.00,
            $payment->amount,
            'Bug 2: Payment amount should be decreased to ETB 600'
        );

        $this->assertEquals(
            4400.00,
            $this->account->balance,
            'Bug 2: Account balance should be restored by ETB 400 (4000 + 400 = 4400)'
        );
    }

    /**
     * Bug 4: Credit Purchase Editing Doesn't Update Payable Amount
     *
     * EXPECTED TO FAIL on unfixed code - this confirms Bug 4 exists.
     *
     * Scenario:
     * 1. Create completed credit purchase for ETB 2000 (payable created with amount=2000, remaining=2000)
     * 2. Edit purchase to change items, new total is ETB 2500
     * 3. BUG: Payable amount stays at ETB 2000, remaining_balance stays at ETB 2000
     * 4. EXPECTED: Payable amount should be ETB 2500, remaining_balance should be ETB 2500
     *
     * Requirements: 1.10, 1.11, 1.12 (Current Behavior - Defect)
     * Expected Fix: 2.11, 2.12, 2.13 (Expected Behavior - Correct)
     */
    public function test_bug4_credit_purchase_editing_does_not_update_payable_amount(): void
    {
        // Step 1: Create completed credit purchase for ETB 2000
        $purchase = Purchase::create([
            'supplier_id' => $this->supplier->id,
            'user_id' => $this->user->id,
            'purchase_date' => now(),
            'payment_type' => PaymentType::CREDIT,
            'status' => PurchaseStatus::COMPLETED,
            'received_date' => now(),
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->product->id,
            'quantity' => 20,
            'unit_price' => 100.00,
            'total_price' => 2000.00,
        ]);

        // Create payable (simulating what PurchaseService::completePurchase does)
        $payable = Payable::create([
            'purchase_id' => $purchase->id,
            'supplier_id' => $this->supplier->id,
            'amount' => 2000.00,
            'remaining_balance' => 2000.00,
            'due_date' => now()->addDays(30),
        ]);

        // Verify initial state
        $this->assertEquals(2000.00, $payable->fresh()->amount);
        $this->assertEquals(2000.00, $payable->fresh()->remaining_balance);

        // Step 2: Edit purchase to change total to ETB 2500
        $purchase->items()->delete();
        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->product->id,
            'quantity' => 25,
            'unit_price' => 100.00,
            'total_price' => 2500.00,
        ]);

        $purchase->update(['notes' => $purchase->notes ?? 'Updated']); // Trigger update

        // Step 3: Assert expected behavior (WILL FAIL on unfixed code)
        $payable->refresh();

        // Expected: Payable amount should be updated to ETB 2500
        $this->assertEquals(
            2500.00,
            $payable->amount,
            'Bug 4: Payable amount should be updated to match new purchase total (ETB 2500), but it remains at ETB 2000'
        );

        // Expected: Payable remaining_balance should be adjusted by delta (2000 + 500 = 2500)
        $this->assertEquals(
            2500.00,
            $payable->remaining_balance,
            'Bug 4: Payable remaining_balance should be adjusted to ETB 2500, but it remains at ETB 2000'
        );
    }

    /**
     * Bug 4 (With Partial Payment): Credit Purchase Editing Should Adjust Remaining Balance Correctly
     *
     * EXPECTED TO FAIL on unfixed code.
     *
     * Scenario:
     * 1. Create credit purchase for ETB 2000 (payable: amount=2000, remaining=2000)
     * 2. Make partial payment of ETB 800 (remaining=1200)
     * 3. Edit purchase to increase total to ETB 2500
     * 4. BUG: Payable amount stays at ETB 2000, remaining stays at ETB 1200
     * 5. EXPECTED: Payable amount should be ETB 2500, remaining should be ETB 1700 (1200 + 500)
     *
     * Requirements: 2.12 (Expected Behavior - Correct)
     */
    public function test_bug4_credit_purchase_editing_with_partial_payment_adjusts_remaining_balance(): void
    {
        // Create credit purchase for ETB 2000
        $purchase = Purchase::create([
            'supplier_id' => $this->supplier->id,
            'user_id' => $this->user->id,
            'purchase_date' => now(),
            'payment_type' => PaymentType::CREDIT,
            'status' => PurchaseStatus::COMPLETED,
            'received_date' => now(),
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->product->id,
            'quantity' => 20,
            'unit_price' => 100.00,
            'total_price' => 2000.00,
        ]);

        $payable = Payable::create([
            'purchase_id' => $purchase->id,
            'supplier_id' => $this->supplier->id,
            'amount' => 2000.00,
            'remaining_balance' => 2000.00,
            'due_date' => now()->addDays(30),
        ]);

        // Make partial payment of ETB 800
        Payment::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'payable_type' => Payable::class,
            'payable_id' => $payable->id,
            'amount' => 800.00,
            'payment_method' => PaymentMethod::CASH,
            'payment_date' => now(),
        ]);

        $payable->remaining_balance -= 800.00;
        $payable->save();

        $this->assertEquals(1200.00, $payable->fresh()->remaining_balance);

        // Edit purchase to increase total to ETB 2500
        $purchase->items()->delete();
        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->product->id,
            'quantity' => 25,
            'unit_price' => 100.00,
            'total_price' => 2500.00,
        ]);

        $purchase->update(['notes' => $purchase->notes ?? 'Updated']);

        // Assert expected behavior (WILL FAIL on unfixed code)
        $payable->refresh();

        $this->assertEquals(
            2500.00,
            $payable->amount,
            'Bug 4: Payable amount should be updated to ETB 2500'
        );

        $this->assertEquals(
            1700.00,
            $payable->remaining_balance,
            'Bug 4: Payable remaining_balance should be adjusted to ETB 1700 (1200 + 500 delta)'
        );
    }

    /**
     * Bug 6: Purchase Editing Doesn't Validate Account Balance
     *
     * EXPECTED TO FAIL on unfixed code - this confirms Bug 6 exists.
     *
     * Scenario:
     * 1. Create cash purchase for ETB 1000 (account balance: 5000 → 4000)
     * 2. Withdraw ETB 3700 from account (balance: 4000 → 300)
     * 3. Attempt to edit purchase to increase total to ETB 1500 (needs additional ETB 500)
     * 4. BUG: Edit is allowed, account balance becomes negative (-200)
     * 5. EXPECTED: Edit should be prevented with error message
     *
     * Requirements: 1.16, 1.17, 1.18 (Current Behavior - Defect)
     * Expected Fix: 2.17, 2.18, 2.19 (Expected Behavior - Correct)
     */
    public function test_bug6_purchase_editing_does_not_validate_account_balance(): void
    {
        // Step 1: Create cash purchase for ETB 1000
        $purchase = Purchase::create([
            'supplier_id' => $this->supplier->id,
            'user_id' => $this->user->id,
            'purchase_date' => now(),
            'payment_type' => PaymentType::CASH,
            'status' => PurchaseStatus::PENDING,
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 100.00,
            'total_price' => 1000.00,
        ]);

        $payment = Payment::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'payable_type' => Purchase::class,
            'payable_id' => $purchase->id,
            'amount' => 1000.00,
            'payment_method' => PaymentMethod::CASH,
            'payment_date' => now(),
        ]);

        $this->account->balance -= 1000.00;
        $this->account->save();

        // Step 2: Withdraw ETB 3700 (simulating other expenses)
        $this->account->balance -= 3700.00;
        $this->account->save();

        $this->assertEquals(300.00, $this->account->fresh()->balance);

        // Step 3: Attempt to edit purchase to increase total to ETB 1500
        // This requires additional ETB 500, but account only has ETB 300
        $purchase->items()->delete();
        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->product->id,
            'quantity' => 15,
            'unit_price' => 100.00,
            'total_price' => 1500.00,
        ]);

        // In the fixed version, this should throw an exception
        // For now, we'll just save and check the result
        try {
            $purchase->update(['notes' => $purchase->notes ?? 'Updated']);
            $editWasAllowed = true;
        } catch (\Exception $e) {
            $editWasAllowed = false;
        }

        // Step 4: Assert expected behavior (WILL FAIL on unfixed code)
        // Expected: Edit should be prevented (exception thrown)
        $this->assertFalse(
            $editWasAllowed,
            'Bug 6: Purchase edit should be prevented when account has insufficient balance (needs ETB 500, has ETB 300)'
        );

        // If edit was somehow allowed, account balance should NOT be negative
        $this->account->refresh();
        $this->assertGreaterThanOrEqual(
            0,
            $this->account->balance,
            'Bug 6: Account balance should never become negative, but it is: '.$this->account->balance
        );
    }

    /**
     * Stock Movement Test: Editing PENDING Purchase Should NOT Update Stock
     *
     * Scenario:
     * 1. Create PENDING purchase with Product A (qty 5)
     * 2. Edit purchase to add Product B (qty 2)
     * 3. EXPECTED: Both products should have stock = 0 (purchase not received yet)
     * 4. After marking as received, stock should be updated correctly
     */
    public function test_editing_pending_purchase_does_not_update_stock(): void
    {
        // Create two products with initial stock = 0
        $productA = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Product A',
            'unit' => UnitType::PIECE,
            'purchase_price' => 100.00,
            'selling_price' => 150.00,
            'current_stock' => 0,
            'minimum_stock_alert' => 10,
        ]);

        $productB = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Product B',
            'unit' => UnitType::PIECE,
            'purchase_price' => 100.00,
            'selling_price' => 150.00,
            'current_stock' => 0,
            'minimum_stock_alert' => 10,
        ]);

        // Step 1: Create PENDING cash purchase with Product A (qty 5)
        $purchase = Purchase::create([
            'supplier_id' => $this->supplier->id,
            'user_id' => $this->user->id,
            'purchase_date' => now(),
            'payment_type' => PaymentType::CASH,
            'status' => PurchaseStatus::PENDING,
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $productA->id,
            'quantity' => 5,
            'unit_price' => 100.00,
            'total_price' => 500.00,
        ]);

        // Create payment
        Payment::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'payable_type' => Purchase::class,
            'payable_id' => $purchase->id,
            'amount' => 500.00,
            'payment_method' => PaymentMethod::CASH,
            'payment_date' => now(),
        ]);

        $this->account->balance -= 500.00;
        $this->account->save();

        // Verify stock is still 0 (purchase is PENDING)
        $this->assertEquals(0, $productA->fresh()->current_stock, 'Product A stock should be 0 while purchase is PENDING');
        $this->assertEquals(0, $productB->fresh()->current_stock, 'Product B stock should be 0 initially');

        // Step 2: Edit purchase to add Product B (qty 2)
        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $productB->id,
            'quantity' => 2,
            'unit_price' => 100.00,
            'total_price' => 200.00,
        ]);

        // Trigger observer by updating purchase
        $purchase->update(['notes' => $purchase->notes ?? 'Updated']);

        // Step 3: Verify stock is STILL 0 for both products (purchase is still PENDING)
        $this->assertEquals(0, $productA->fresh()->current_stock, 'Product A stock should remain 0 while purchase is PENDING');
        $this->assertEquals(0, $productB->fresh()->current_stock, 'Product B stock should remain 0 while purchase is PENDING');

        // Verify no stock movements exist yet
        $this->assertEquals(0, $purchase->stockMovements()->count(), 'No stock movements should exist for PENDING purchase');

        // Step 4: Mark purchase as received (complete it)
        $purchaseService = app(PurchaseService::class);
        $purchaseService->completePurchase($purchase);

        // Step 5: NOW stock should be updated correctly
        $this->assertEquals(5, $productA->fresh()->current_stock, 'Product A stock should be 5 after marking as received');
        $this->assertEquals(2, $productB->fresh()->current_stock, 'Product B stock should be 2 after marking as received');

        // Verify stock movements were created
        $this->assertEquals(2, $purchase->stockMovements()->count(), 'Two stock movements should exist after marking as received');
    }

    /**
     * Stock Movement Test: Editing COMPLETED Purchase Should Update Stock Correctly
     *
     * This test matches the user's reported scenario:
     * 1. Create PENDING purchase with Product A (qty 5)
     * 2. Mark as received (stock: Product A = 5)
     * 3. Edit purchase to add Product B (qty 2)
     * 4. EXPECTED: Product A stock = 5, Product B stock = 2
     */
    public function test_editing_completed_purchase_updates_stock_correctly(): void
    {
        // Create two products with initial stock = 0
        $productA = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Product A',
            'unit' => UnitType::PIECE,
            'purchase_price' => 100.00,
            'selling_price' => 150.00,
            'current_stock' => 0,
            'minimum_stock_alert' => 10,
        ]);

        $productB = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Product B',
            'unit' => UnitType::PIECE,
            'purchase_price' => 100.00,
            'selling_price' => 150.00,
            'current_stock' => 0,
            'minimum_stock_alert' => 10,
        ]);

        // Step 1: Create PENDING purchase with Product A (qty 5)
        $purchase = Purchase::create([
            'supplier_id' => $this->supplier->id,
            'user_id' => $this->user->id,
            'purchase_date' => now(),
            'payment_type' => PaymentType::CASH,
            'status' => PurchaseStatus::PENDING,
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $productA->id,
            'quantity' => 5,
            'unit_price' => 100.00,
            'total_price' => 500.00,
        ]);

        // Create payment
        Payment::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'payable_type' => Purchase::class,
            'payable_id' => $purchase->id,
            'amount' => 500.00,
            'payment_method' => PaymentMethod::CASH,
            'payment_date' => now(),
        ]);

        $this->account->balance -= 500.00;
        $this->account->save();

        // Step 2: Mark as received
        $purchaseService = app(PurchaseService::class);
        $purchaseService->completePurchase($purchase);

        // Verify stock after marking as received
        $this->assertEquals(5, $productA->fresh()->current_stock, 'Product A stock should be 5 after marking as received');
        $this->assertEquals(0, $productB->fresh()->current_stock, 'Product B stock should still be 0');

        // Step 3: Edit purchase to add Product B (qty 2)
        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $productB->id,
            'quantity' => 2,
            'unit_price' => 100.00,
            'total_price' => 200.00,
        ]);

        // Trigger observer by updating purchase (simulating what EditPurchase::afterSave() does)
        // We need to actually change something to trigger the updated event
        $purchase->notes = ($purchase->notes ?? '').' '; // Add a space to make it dirty
        $purchase->save();

        // Step 4: Verify stock is correct for both products
        $this->assertEquals(5, $productA->fresh()->current_stock, 'Product A stock should remain 5 after adding Product B');
        $this->assertEquals(2, $productB->fresh()->current_stock, 'Product B stock should be 2 after adding it to completed purchase');

        // Verify stock movements
        $this->assertEquals(2, $purchase->stockMovements()->count(), 'Two stock movements should exist');
    }
}
