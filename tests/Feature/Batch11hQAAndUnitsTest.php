<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Contact;
use App\Models\CustomerInvoice;
use App\Models\InventoryBatch;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Batch11hQAAndUnitsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $guest;

    private Warehouse $warehouseA;

    private Warehouse $warehouseB;

    private Contact $customer;

    private Product $tileProduct;

    private BankAccount $cashAccount;

    private Account $arAccount;

    private Account $invAssetAccount;

    private Account $salesRevAccount;

    private Account $cogsAccount;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Users
        $this->admin = User::factory()->create([
            'name' => 'QA Admin',
            'email' => 'qa_admin@example.test',
        ]);
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrator']);
        $permissions = [
            'view_dashboard', 'view_reports', 'view_accounting', 'manage_accounting',
            'view_inventory', 'manage_inventory', 'view_production', 'manage_production',
            'view_sales', 'manage_sales', 'view_purchases', 'manage_purchases',
            'view_quality', 'manage_quality', 'view_dispatch', 'manage_dispatch',
            'manage_machines', 'manage_backup', 'view_audit_logs',
        ];
        foreach ($permissions as $p) {
            $perm = Permission::firstOrCreate(['name' => $p], ['display_name' => $p]);
            $adminRole->permissions()->syncWithoutDetaching([$perm->id]);
        }
        $this->admin->roles()->syncWithoutDetaching([$adminRole->id]);

        $this->guest = User::factory()->create([
            'name' => 'QA Guest',
            'email' => 'qa_guest@example.test',
        ]);

        // 2. Create Warehouses
        $this->warehouseA = Warehouse::create(['name' => 'Warehouse Alpha', 'location' => 'SITE']);
        $this->warehouseB = Warehouse::create(['name' => 'Warehouse Beta', 'location' => 'Korangi']);

        // 3. Create Accounts
        $this->arAccount = Account::firstOrCreate(['code' => '1100'], ['name' => 'Accounts Receivable', 'type' => 'asset']);
        $this->invAssetAccount = Account::firstOrCreate(['code' => '1200'], ['name' => 'Finished Goods Inventory', 'type' => 'asset']);
        $this->salesRevAccount = Account::firstOrCreate(['code' => '4100'], ['name' => 'Sales Revenue', 'type' => 'revenue']);
        $this->cogsAccount = Account::firstOrCreate(['code' => '5100'], ['name' => 'Cost of Goods Sold', 'type' => 'expense']);

        $cashGLAccount = Account::firstOrCreate(['code' => '1010'], ['name' => 'Cash in Hand', 'type' => 'asset']);
        $this->cashAccount = BankAccount::create([
            'account_id' => $cashGLAccount->id,
            'account_name' => 'Cash Drawer',
            'type' => 'cash',
        ]);

        // 4. Create Product with UOM
        $this->tileProduct = Product::create([
            'sku' => 'TL-ONYX-01',
            'name' => 'Onyx Tiles 60x60',
            'unit' => 'pcs',
            'cost' => 100.00,
            'price' => 250.00,
            'product_type' => 'tile',
            'unit_of_measure' => 'Sq Ft',
            'inventory_account_id' => $this->invAssetAccount->id,
            'cogs_account_id' => $this->cogsAccount->id,
            'revenue_account_id' => $this->salesRevAccount->id,
        ]);

        // 5. Create Customer
        $this->customer = Contact::create(['name' => 'Siddique Marble Traders', 'type' => 'customer']);
    }

    /**
     * Test sales page loads for admin and redirects for guest
     */
    public function test_sales_page_auth_guard(): void
    {
        $response = $this->actingAs($this->admin)->get('/sales');
        $response->assertStatus(200);

        $responseGuest = $this->actingAs($this->guest)->get('/sales');
        $responseGuest->assertStatus(403);
    }

    /**
     * Test validation fails when customer is missing
     */
    public function test_missing_customer_returns_validation_error(): void
    {
        $response = $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => '',
            'order_date' => '2026-06-27',
            'warehouse_id' => $this->warehouseA->id,
            'items' => [
                ['product_id' => $this->tileProduct->id, 'quantity' => 10, 'unit_price' => 250.00],
            ],
            'discount_amount' => 0,
            'delivery_charges' => 0,
            'cash_received' => 0,
            'bank_received' => 0,
        ]);

        $response->assertSessionHasErrors(['contact_id']);
    }

    /**
     * Test validation fails when payment is received but bank account is missing
     */
    public function test_missing_bank_account_for_payments_returns_validation_error(): void
    {
        InventoryBatch::create([
            'product_id' => $this->tileProduct->id,
            'warehouse_id' => $this->warehouseA->id,
            'reference_type' => 'adjustment',
            'reference_id' => 1,
            'quantity_received' => 50,
            'quantity_remaining' => 50,
            'unit_cost' => 100.00,
            'total_cost' => 5000.00,
            'unit_of_measure' => 'Sq Ft',
            'product_type' => 'tile',
            'received_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $this->customer->id,
            'order_date' => '2026-06-27',
            'warehouse_id' => $this->warehouseA->id,
            'items' => [
                ['product_id' => $this->tileProduct->id, 'quantity' => 10, 'unit_price' => 250.00],
            ],
            'discount_amount' => 0,
            'delivery_charges' => 0,
            'cash_received' => 1000,
            'bank_received' => 0,
            'bank_account_id' => '',
        ]);

        $response->assertSessionHasErrors(['bank_account_id']);
    }

    /**
     * Test insufficient stock returns validation error
     */
    public function test_insufficient_stock_returns_validation_error(): void
    {
        // No stock exists currently
        $response = $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $this->customer->id,
            'order_date' => '2026-06-27',
            'warehouse_id' => $this->warehouseA->id,
            'items' => [
                ['product_id' => $this->tileProduct->id, 'quantity' => 10, 'unit_price' => 250.00],
            ],
            'discount_amount' => 0,
            'delivery_charges' => 0,
            'cash_received' => 0,
            'bank_received' => 0,
        ]);

        $response->assertSessionHasErrors(['items']);
    }

    /**
     * Test warehouse-specific stock validation
     */
    public function test_warehouse_specific_stock_validation(): void
    {
        // Add 50 units in Warehouse B only
        InventoryBatch::create([
            'product_id' => $this->tileProduct->id,
            'warehouse_id' => $this->warehouseB->id,
            'reference_type' => 'adjustment',
            'reference_id' => 1,
            'quantity_received' => 50,
            'quantity_remaining' => 50,
            'unit_cost' => 100.00,
            'total_cost' => 5000.00,
            'unit_of_measure' => 'Sq Ft',
            'product_type' => 'tile',
            'received_at' => now(),
        ]);

        // Attempting to sell from Warehouse A should fail
        $response = $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $this->customer->id,
            'order_date' => '2026-06-27',
            'warehouse_id' => $this->warehouseA->id,
            'items' => [
                ['product_id' => $this->tileProduct->id, 'quantity' => 10, 'unit_price' => 250.00],
            ],
            'discount_amount' => 0,
            'delivery_charges' => 0,
            'cash_received' => 0,
            'bank_received' => 0,
        ]);

        $response->assertSessionHasErrors(['items']);

        // Selling from Warehouse B should succeed
        $responseSuccess = $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $this->customer->id,
            'order_date' => '2026-06-27',
            'warehouse_id' => $this->warehouseB->id,
            'items' => [
                ['product_id' => $this->tileProduct->id, 'quantity' => 10, 'unit_price' => 250.00],
            ],
            'discount_amount' => 0,
            'delivery_charges' => 0,
            'cash_received' => 0,
            'bank_received' => 0,
        ]);

        $responseSuccess->assertSessionHasNoErrors();
    }

    /**
     * Test batch-specific stock validation
     */
    public function test_batch_specific_stock_validation(): void
    {
        // Batch 1 has 5 units in Warehouse B
        $batch1 = InventoryBatch::create([
            'product_id' => $this->tileProduct->id,
            'warehouse_id' => $this->warehouseB->id,
            'reference_type' => 'adjustment',
            'reference_id' => 1,
            'quantity_received' => 5,
            'quantity_remaining' => 5,
            'unit_cost' => 100.00,
            'total_cost' => 500.00,
            'unit_of_measure' => 'Sq Ft',
            'product_type' => 'tile',
            'received_at' => now(),
        ]);

        // Batch 2 has 50 units in Warehouse B
        $batch2 = InventoryBatch::create([
            'product_id' => $this->tileProduct->id,
            'warehouse_id' => $this->warehouseB->id,
            'reference_type' => 'adjustment',
            'reference_id' => 2,
            'quantity_received' => 50,
            'quantity_remaining' => 50,
            'unit_cost' => 100.00,
            'total_cost' => 5000.00,
            'unit_of_measure' => 'Sq Ft',
            'product_type' => 'tile',
            'received_at' => now(),
        ]);

        // Attempting to sell 10 units from Batch 1 should fail
        $response = $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $this->customer->id,
            'order_date' => '2026-06-27',
            'warehouse_id' => $this->warehouseB->id,
            'items' => [
                ['product_id' => $this->tileProduct->id, 'quantity' => 10, 'unit_price' => 250.00, 'selected_batch_id' => $batch1->id],
            ],
            'discount_amount' => 0,
            'delivery_charges' => 0,
            'cash_received' => 0,
            'bank_received' => 0,
        ]);

        $response->assertSessionHasErrors(['items']);
    }

    /**
     * Test ledger postings, totals calculation, and invoice print route
     */
    public function test_invoice_creation_ledger_and_print(): void
    {
        // Setup stock
        InventoryBatch::create([
            'product_id' => $this->tileProduct->id,
            'warehouse_id' => $this->warehouseA->id,
            'reference_type' => 'adjustment',
            'reference_id' => 1,
            'quantity_received' => 10,
            'quantity_remaining' => 10,
            'unit_cost' => 100.00,
            'total_cost' => 1000.00,
            'unit_of_measure' => 'Sq Ft',
            'product_type' => 'tile',
            'received_at' => now(),
        ]);

        // Create sale invoice for 5 Sq Ft of tiles
        $response = $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $this->customer->id,
            'order_date' => '2026-06-27',
            'warehouse_id' => $this->warehouseA->id,
            'items' => [
                ['product_id' => $this->tileProduct->id, 'quantity' => 5, 'unit_price' => 250.00],
            ],
            'discount_amount' => 50,
            'delivery_charges' => 100,
            'cash_received' => 500,
            'bank_received' => 0,
            'bank_account_id' => $this->cashAccount->id,
        ]);

        $response->assertSessionHasNoErrors();

        // 1. Verify invoice details & totals: gross = 1250, net = 1250 - 50 + 100 = 1300, remaining = 1300 - 500 = 800
        $invoice = CustomerInvoice::first();
        $this->assertNotNull($invoice);
        $this->assertEquals(1250.00, $invoice->gross_amount);
        $this->assertEquals(50.00, $invoice->discount_amount);
        $this->assertEquals(100.00, $invoice->delivery_charges);
        $this->assertEquals(1300.00, $invoice->net_amount);
        $this->assertEquals(800.00, $invoice->remaining_balance);

        // 2. Verify ledger postings are fully balanced and correct G/L values recorded
        // Accounts Receivable (1100) -> Debit $1300 (before payment) -> net receivables after payment is $800
        $this->assertEquals(800.00, app(AccountService::class)->getBalance($this->arAccount->id));
        // Sales Revenue (4100) -> Credit $1300 (gross 1250 - discount 50 + delivery charges 100)
        $this->assertEquals(1300.00, app(AccountService::class)->getBalance($this->salesRevAccount->id));
        // COGS (5100) -> Debit $500 (5 Sq Ft @ 100.00 cost)
        $this->assertEquals(500.00, app(AccountService::class)->getBalance($this->cogsAccount->id));
        // Cash Account -> Debit $500
        $this->assertEquals(500.00, app(AccountService::class)->getBalance($this->cashAccount->account_id));

        // 3. Verify print route does not crash and renders view
        $printResponse = $this->actingAs($this->admin)->get("/sales/invoice/{$invoice->id}/print");
        $printResponse->assertStatus(200);
    }
}
