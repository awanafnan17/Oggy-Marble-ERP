<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Contact;
use App\Models\CustomerInvoice;
use App\Models\InventoryBatch;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockLedger;
use App\Models\SupplierBill;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Batch11rPostedEntryReversalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $unauthorizedUser;

    private Warehouse $warehouse;

    private BankAccount $bankAccount;

    private Product $tileProduct;

    private Contact $customer;

    private Contact $supplier;

    private Account $invAccount;

    private Account $cogsAccount;

    private Account $revAccount;

    private Account $arAccount;

    private Account $apAccount;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Seed Permissions & Roles
        $permissions = [
            'manage_sales',
            'manage_purchases',
            'manage_accounting',
            'view_sales',
            'view_purchases',
        ];
        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p], ['display_name' => $p]);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrator']);
        $adminRole->permissions()->sync(Permission::all()->pluck('id'));

        $viewerRole = Role::firstOrCreate(['name' => 'viewer'], ['display_name' => 'Viewer']);
        $viewerRole->permissions()->sync(Permission::where('name', 'view_sales')->pluck('id'));

        // 2. Users
        $this->admin = User::factory()->create(['name' => 'Admin User', 'email' => 'admin@erp.test']);
        $this->admin->roles()->sync([$adminRole->id]);

        $this->unauthorizedUser = User::factory()->create(['name' => 'Viewer User', 'email' => 'viewer@erp.test']);
        $this->unauthorizedUser->roles()->sync([$viewerRole->id]);

        // 3. Setup basic accounts
        $this->arAccount = Account::firstOrCreate(['code' => '1100'], ['name' => 'Accounts Receivable', 'type' => 'asset']);
        $this->apAccount = Account::firstOrCreate(['code' => '2100'], ['name' => 'Accounts Payable', 'type' => 'liability']);
        $this->invAccount = Account::firstOrCreate(['code' => '1200'], ['name' => 'Tile Inventory (Sq Ft)', 'type' => 'asset']);
        $this->cogsAccount = Account::firstOrCreate(['code' => '5100'], ['name' => 'COGS - Tiles', 'type' => 'expense']);
        $this->revAccount = Account::firstOrCreate(['code' => '4100'], ['name' => 'Sales Revenue - Tiles', 'type' => 'revenue']);

        $cashLedgerAccount = Account::firstOrCreate(['code' => '1010'], ['name' => 'Cash in Hand', 'type' => 'asset']);

        // 4. Warehouse & BankAccount
        $this->warehouse = Warehouse::create(['name' => 'Main Warehouse', 'location' => 'Islamabad']);
        $this->bankAccount = BankAccount::create([
            'account_id' => $cashLedgerAccount->id,
            'account_name' => 'Cash Account',
            'type' => 'cash',
        ]);

        // 5. Product
        $this->tileProduct = Product::create([
            'sku' => 'TL-TEST-01',
            'name' => 'Test Polish Tile',
            'product_type' => 'tile',
            'unit_of_measure' => 'Sq Ft',
            'inventory_account_id' => $this->invAccount->id,
            'cogs_account_id' => $this->cogsAccount->id,
            'revenue_account_id' => $this->revAccount->id,
            'cost' => 100.0,
            'price' => 200.0,
        ]);

        // 6. Contacts
        $this->customer = Contact::create(['name' => 'Test Customer', 'type' => 'customer']);
        $this->supplier = Contact::create(['name' => 'Test Supplier', 'type' => 'vendor']);
    }

    /**
     * Helper to create a batch of stock.
     */
    private function seedStock(float $qty, float $cost = 100.0): InventoryBatch
    {
        $batch = InventoryBatch::create([
            'product_id' => $this->tileProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'reference_type' => 'beginning_inventory',
            'reference_id' => 1,
            'quantity_received' => $qty,
            'quantity_remaining' => $qty,
            'unit_cost' => $cost,
            'total_cost' => $qty * $cost,
            'unit_of_measure' => 'Sq Ft',
            'product_type' => 'tile',
            'received_at' => now(),
        ]);

        StockLedger::create([
            'product_id' => $this->tileProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_id' => $batch->id,
            'quantity' => $qty,
            'unit_cost' => $cost,
            'type' => 'IN',
            'reference_type' => 'beginning_inventory',
            'reference_id' => 1,
            'notes' => 'Test stock ingest',
        ]);

        return $batch;
    }

    // 1. Sales invoice void reverses stock.
    public function test_sales_invoice_void_reverses_stock()
    {
        $batch = $this->seedStock(100);

        $this->actingAs($this->admin);

        // Post a sales invoice
        $postData = [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->tileProduct->id,
                    'quantity' => 10,
                    'unit_price' => 200.0,
                    'selected_batch_id' => $batch->id,
                ],
            ],
            'discount_amount' => 0,
            'delivery_charges' => 0,
            'cash_received' => 0,
            'bank_received' => 0,
            'bank_account_id' => $this->bankAccount->id,
        ];

        $response = $this->post('/sales/store', $postData);
        $response->assertSessionHasNoErrors();

        $invoice = CustomerInvoice::first();
        $this->assertEquals(90, InventoryBatch::find($batch->id)->quantity_remaining);

        // Void the invoice
        $response = $this->post("/sales/invoice/{$invoice->id}/void", [
            'void_reason' => 'Duplicate invoice entry',
        ]);

        $response->assertStatus(302);
        $invoice->refresh();

        $this->assertEquals('voided', $invoice->status);
        $this->assertEquals(100, InventoryBatch::find($batch->id)->quantity_remaining);

        // Assert reversal stock ledger entry
        $this->assertTrue(StockLedger::where('reference_type', 'sales_order')
            ->where('notes', 'like', '%Reversal%')
            ->where('quantity', '>', 0)
            ->exists());
    }

    // 2. Sales invoice void creates reversal journal.
    public function test_sales_invoice_void_creates_reversal_journal()
    {
        $batch = $this->seedStock(100);
        $this->actingAs($this->admin);

        $postData = [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->tileProduct->id,
                    'quantity' => 10,
                    'unit_price' => 200.0,
                    'selected_batch_id' => $batch->id,
                ],
            ],
            'discount_amount' => 0,
            'delivery_charges' => 0,
            'cash_received' => 0,
            'bank_received' => 0,
            'bank_account_id' => $this->bankAccount->id,
        ];

        $this->post('/sales/store', $postData);
        $invoice = CustomerInvoice::first();

        $this->post("/sales/invoice/{$invoice->id}/void", [
            'void_reason' => 'Test void reason',
        ]);

        $orig = JournalEntry::where('reference_type', 'order')->whereNull('reversal_journal_entry_id')->first();
        $rev = JournalEntry::where('reference_type', 'order')->whereNotNull('reversal_journal_entry_id')->first();

        $this->assertNotNull($orig);
        $this->assertNotNull($rev);

        $this->assertEquals($orig->id, $rev->reversal_journal_entry_id);
        $this->assertEquals($rev->id, $orig->reversed_by_entry_id);

        // Swapped check
        $origDebits = $orig->items->sum('debit');
        $revCredits = $rev->items->sum('credit');
        $this->assertEquals($origDebits, $revCredits);
    }

    // 3. Sales invoice void restores customer ledger.
    public function test_sales_invoice_void_restores_customer_ledger()
    {
        $batch = $this->seedStock(100);
        $this->actingAs($this->admin);

        $postData = [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->tileProduct->id,
                    'quantity' => 10,
                    'unit_price' => 200.0,
                    'selected_batch_id' => $batch->id,
                ],
            ],
            'discount_amount' => 0,
            'delivery_charges' => 0,
            'cash_received' => 0,
            'bank_received' => 0,
            'bank_account_id' => $this->bankAccount->id,
        ];

        $this->post('/sales/store', $postData);
        $invoice = CustomerInvoice::first();

        // Check customer ledger has invoice debit
        $response = $this->get("/party-ledgers?contact_id={$this->customer->id}");
        $response->assertInertia(fn (Assert $page) => $page
            ->has('ledgerData', 1)
        );

        // Void the invoice
        $this->post("/sales/invoice/{$invoice->id}/void", ['void_reason' => 'Void reason']);

        // Check customer ledger excludes voided
        $response = $this->get("/party-ledgers?contact_id={$this->customer->id}");
        $response->assertInertia(fn (Assert $page) => $page
            ->has('ledgerData', 0)
        );
    }

    // 4. Sales invoice cannot be voided twice.
    public function test_sales_invoice_cannot_be_voided_twice()
    {
        $batch = $this->seedStock(100);
        $this->actingAs($this->admin);

        $postData = [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->tileProduct->id,
                    'quantity' => 10,
                    'unit_price' => 200.0,
                    'selected_batch_id' => $batch->id,
                ],
            ],
            'discount_amount' => 0,
            'delivery_charges' => 0,
            'cash_received' => 0,
            'bank_received' => 0,
            'bank_account_id' => $this->bankAccount->id,
        ];

        $this->post('/sales/store', $postData);
        $invoice = CustomerInvoice::first();

        $this->post("/sales/invoice/{$invoice->id}/void", ['void_reason' => 'Void reason']);

        $response = $this->post("/sales/invoice/{$invoice->id}/void", ['void_reason' => 'Second void attempt']);
        $response->assertSessionHasErrors('error');
    }

    // 5. Sales invoice void blocked if linked payment exists.
    public function test_sales_invoice_void_blocked_if_linked_payment_exists()
    {
        $batch = $this->seedStock(100);
        $this->actingAs($this->admin);

        $postData = [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->tileProduct->id,
                    'quantity' => 10,
                    'unit_price' => 200.0,
                    'selected_batch_id' => $batch->id,
                ],
            ],
            'discount_amount' => 0,
            'delivery_charges' => 0,
            'cash_received' => 0,
            'bank_received' => 0,
            'bank_account_id' => $this->bankAccount->id,
        ];

        $this->post('/sales/store', $postData);
        $invoice = CustomerInvoice::first();

        // Record a linked payment
        $this->post('/payments/customer', [
            'contact_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'amount' => 500,
            'payment_method' => 'cash',
            'bank_account_id' => $this->bankAccount->id,
            'invoice_id' => $invoice->id,
            'notes' => 'Deposit received',
        ]);

        // Attempt voiding invoice
        $response = $this->post("/sales/invoice/{$invoice->id}/void", ['void_reason' => 'Void reason']);
        $response->assertSessionHasErrors('error');
    }

    // 6. Purchase bill void reverses inward stock if untouched.
    public function test_purchase_bill_void_reverses_inward_stock_if_untouched()
    {
        $this->actingAs($this->admin);

        $postData = [
            'contact_id' => $this->supplier->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->tileProduct->id,
                    'quantity' => 50,
                    'unit_price' => 100.0,
                ],
            ],
            'transportation_cost' => 0,
            'loading_cost' => 0,
            'other_direct_cost' => 0,
            'allocation_method' => 'quantity',
            'cash_paid' => 0,
            'bank_paid' => 0,
            'bank_account_id' => $this->bankAccount->id,
        ];

        $response = $this->post('/purchases/store', $postData);
        $response->assertSessionHasNoErrors();

        $bill = SupplierBill::first();
        $batch = InventoryBatch::where('reference_type', 'purchase')->first();

        $this->assertNotNull($batch);
        $this->assertEquals(50, $batch->quantity_remaining);

        // Void purchase bill
        $response = $this->post("/purchases/bill/{$bill->id}/void", ['void_reason' => 'Wrong bill entries']);
        $response->assertStatus(302);

        $batch->refresh();
        $this->assertEquals(0, $batch->quantity_remaining);
    }

    // 7. Purchase bill void blocked if stock already used.
    public function test_purchase_bill_void_blocked_if_stock_already_used()
    {
        $this->actingAs($this->admin);

        $postData = [
            'contact_id' => $this->supplier->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->tileProduct->id,
                    'quantity' => 50,
                    'unit_price' => 100.0,
                ],
            ],
            'transportation_cost' => 0,
            'loading_cost' => 0,
            'other_direct_cost' => 0,
            'allocation_method' => 'quantity',
            'cash_paid' => 0,
            'bank_paid' => 0,
            'bank_account_id' => $this->bankAccount->id,
        ];

        $this->post('/purchases/store', $postData);

        $bill = SupplierBill::first();
        $batch = InventoryBatch::where('reference_type', 'purchase')->first();

        // Simulate stock usage
        $batch->quantity_remaining = 40;
        $batch->save();

        // Attempt voiding purchase bill
        $response = $this->post("/purchases/bill/{$bill->id}/void", ['void_reason' => 'Void reason']);
        $response->assertSessionHasErrors('error');
    }

    // 8. Customer payment void restores invoice balance.
    public function test_customer_payment_void_restores_invoice_balance()
    {
        $batch = $this->seedStock(100);
        $this->actingAs($this->admin);

        $postData = [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->tileProduct->id,
                    'quantity' => 10,
                    'unit_price' => 200.0,
                    'selected_batch_id' => $batch->id,
                ],
            ],
            'discount_amount' => 0,
            'delivery_charges' => 0,
            'cash_received' => 0,
            'bank_received' => 0,
            'bank_account_id' => $this->bankAccount->id,
        ];

        $this->post('/sales/store', $postData);
        $invoice = CustomerInvoice::first();

        $this->post('/payments/customer', [
            'contact_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'amount' => 500,
            'payment_method' => 'cash',
            'bank_account_id' => $this->bankAccount->id,
            'invoice_id' => $invoice->id,
            'notes' => 'Partial receipt',
        ]);

        $payment = Payment::first();
        $invoice->refresh();
        $this->assertEquals(1500, $invoice->remaining_balance);
        $this->assertEquals('partial', $invoice->status);

        // Void the payment
        $response = $this->post("/payments/{$payment->id}/void", ['void_reason' => 'Returned payment check']);
        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertEquals(2000, $invoice->remaining_balance);
        $this->assertEquals('unpaid', $invoice->status);
    }

    // 9. Supplier payment void restores bill balance.
    public function test_supplier_payment_void_restores_bill_balance()
    {
        $this->actingAs($this->admin);

        $postData = [
            'contact_id' => $this->supplier->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->tileProduct->id,
                    'quantity' => 10,
                    'unit_price' => 100.0,
                ],
            ],
            'transportation_cost' => 0,
            'loading_cost' => 0,
            'other_direct_cost' => 0,
            'allocation_method' => 'quantity',
            'cash_paid' => 0,
            'bank_paid' => 0,
            'bank_account_id' => $this->bankAccount->id,
        ];

        $this->post('/purchases/store', $postData);
        $bill = SupplierBill::first();

        $this->post('/payments/supplier', [
            'contact_id' => $this->supplier->id,
            'payment_date' => now()->toDateString(),
            'amount' => 400,
            'payment_method' => 'cash',
            'bank_account_id' => $this->bankAccount->id,
            'bill_id' => $bill->id,
            'notes' => 'Partial payment to vendor',
        ]);

        $payment = Payment::first();
        $bill->refresh();
        $this->assertEquals(600, $bill->remaining_balance);

        // Void the payment
        $this->post("/payments/{$payment->id}/void", ['void_reason' => 'Void reason']);
        $bill->refresh();
        $this->assertEquals(1000, $bill->remaining_balance);
    }

    // 10. Bank transaction reversal works.
    public function test_bank_transaction_reversal_works()
    {
        $batch = $this->seedStock(100);
        $this->actingAs($this->admin);

        $postData = [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->tileProduct->id,
                    'quantity' => 10,
                    'unit_price' => 200.0,
                    'selected_batch_id' => $batch->id,
                ],
            ],
            'discount_amount' => 0,
            'delivery_charges' => 0,
            'cash_received' => 0,
            'bank_received' => 0,
            'bank_account_id' => $this->bankAccount->id,
        ];

        $this->post('/sales/store', $postData);
        $invoice = CustomerInvoice::first();

        $this->post('/payments/customer', [
            'contact_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'amount' => 500,
            'payment_method' => 'cash',
            'bank_account_id' => $this->bankAccount->id,
            'invoice_id' => $invoice->id,
            'notes' => 'Partial receipt',
        ]);

        $payment = Payment::first();
        $btOriginal = BankTransaction::where('reference_type', 'payment')->where('reference_id', $payment->id)->first();
        $this->assertNotNull($btOriginal);

        $response = $this->post("/payments/{$payment->id}/void", ['void_reason' => 'Reversal reason']);
        $response->assertSessionHasNoErrors();

        $btReversal = BankTransaction::where('reference_type', 'payment')
            ->where('reference_id', $payment->id)
            ->where('type', 'withdrawal')
            ->first();

        $this->assertNotNull($btReversal);
        $this->assertEquals($btOriginal->amount, $btReversal->amount);
    }

    // 11. Payment allocation history is preserved, not deleted.
    public function test_payment_allocation_history_is_preserved_not_deleted()
    {
        $batch = $this->seedStock(100);
        $this->actingAs($this->admin);

        $postData = [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->tileProduct->id,
                    'quantity' => 10,
                    'unit_price' => 200.0,
                    'selected_batch_id' => $batch->id,
                ],
            ],
            'discount_amount' => 0,
            'delivery_charges' => 0,
            'cash_received' => 0,
            'bank_received' => 0,
            'bank_account_id' => $this->bankAccount->id,
        ];

        $this->post('/sales/store', $postData);
        $invoice = CustomerInvoice::first();

        $this->post('/payments/customer', [
            'contact_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'amount' => 500,
            'payment_method' => 'cash',
            'bank_account_id' => $this->bankAccount->id,
            'invoice_id' => $invoice->id,
            'notes' => 'Partial receipt',
        ]);

        $payment = Payment::first();
        $this->assertCount(1, PaymentAllocation::all());

        // Void payment
        $this->post("/payments/{$payment->id}/void", ['void_reason' => 'Void reason']);

        // Check allocation is still in database but marked as voided/reversed
        $alloc = PaymentAllocation::first();
        $this->assertNotNull($alloc);
        $this->assertNotNull($alloc->voided_at);
        $this->assertEquals('Void reason', $alloc->void_reason);
    }

    // 12. Closed period blocks reversal.
    public function test_closed_period_blocks_reversal()
    {
        $batch = $this->seedStock(100);
        $this->actingAs($this->admin);

        $postData = [
            'contact_id' => $this->customer->id,
            'order_date' => '2026-06-01',
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->tileProduct->id,
                    'quantity' => 10,
                    'unit_price' => 200.0,
                    'selected_batch_id' => $batch->id,
                ],
            ],
            'discount_amount' => 0,
            'delivery_charges' => 0,
            'cash_received' => 0,
            'bank_received' => 0,
            'bank_account_id' => $this->bankAccount->id,
        ];

        $this->post('/sales/store', $postData);
        $invoice = CustomerInvoice::first();

        // Close the accounting period for June 2026
        AccountingPeriod::create([
            'period_name' => 'June 2026',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'is_closed' => true,
        ]);

        // Attempt to void the invoice in closed period
        $response = $this->post("/sales/invoice/{$invoice->id}/void", ['void_reason' => 'Void reason']);
        $response->assertSessionHasErrors('error');
    }

    // 13. Void reason is required.
    public function test_void_reason_is_required()
    {
        $batch = $this->seedStock(100);
        $this->actingAs($this->admin);

        $postData = [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->tileProduct->id,
                    'quantity' => 10,
                    'unit_price' => 200.0,
                    'selected_batch_id' => $batch->id,
                ],
            ],
            'discount_amount' => 0,
            'delivery_charges' => 0,
            'cash_received' => 0,
            'bank_received' => 0,
            'bank_account_id' => $this->bankAccount->id,
        ];

        $this->post('/sales/store', $postData);
        $invoice = CustomerInvoice::first();

        // Void without a reason or with too short reason
        $response = $this->post("/sales/invoice/{$invoice->id}/void", ['void_reason' => '']);
        $response->assertSessionHasErrors('void_reason');
    }

    // 14. Permission/RBAC blocks unauthorized void.
    public function test_permission_blocks_unauthorized_void()
    {
        $batch = $this->seedStock(100);

        // Post invoice as admin
        $this->actingAs($this->admin);
        $postData = [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->tileProduct->id,
                    'quantity' => 10,
                    'unit_price' => 200.0,
                    'selected_batch_id' => $batch->id,
                ],
            ],
            'discount_amount' => 0,
            'delivery_charges' => 0,
            'cash_received' => 0,
            'bank_received' => 0,
            'bank_account_id' => $this->bankAccount->id,
        ];
        $this->post('/sales/store', $postData);
        $invoice = CustomerInvoice::first();

        // Act as unauthorized user
        $this->actingAs($this->unauthorizedUser);
        $response = $this->post("/sales/invoice/{$invoice->id}/void", ['void_reason' => 'Correct reason length']);

        // Since it uses permission middleware which returns 403 or throws exception
        $response->assertStatus(403);
    }

    // 15. Voided print/report status appears.
    public function test_voided_print_report_status_appears()
    {
        $batch = $this->seedStock(100);
        $this->actingAs($this->admin);

        $postData = [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->tileProduct->id,
                    'quantity' => 10,
                    'unit_price' => 200.0,
                    'selected_batch_id' => $batch->id,
                ],
            ],
            'discount_amount' => 0,
            'delivery_charges' => 0,
            'cash_received' => 0,
            'bank_received' => 0,
            'bank_account_id' => $this->bankAccount->id,
        ];

        $this->post('/sales/store', $postData);
        $invoice = CustomerInvoice::first();

        $this->post("/sales/invoice/{$invoice->id}/void", ['void_reason' => 'Reason for void']);

        $response = $this->get("/sales/invoice/{$invoice->id}/print");
        $response->assertStatus(200);

        // Verify status propagates to print
        $response->assertInertia(fn (Assert $page) => $page
            ->where('invoice.status', 'voided')
        );
    }
}
