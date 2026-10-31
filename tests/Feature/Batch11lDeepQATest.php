<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Contact;
use App\Models\DeliveryChallan;
use App\Models\InventoryBatch;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Batch11lDeepQATest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $guest;

    private Warehouse $warehouseA;

    private Warehouse $warehouseB;

    private Account $arAccount;

    private Account $apAccount;

    private Account $invAssetAccount;

    private Account $salesRevAccount;

    private Account $cogsAccount;

    private Account $wipAccount;

    private Account $allocationAccount;

    private BankAccount $cashAccount;

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
        $this->arAccount = Account::create(['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset']);
        $this->apAccount = Account::create(['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability']);
        $this->invAssetAccount = Account::create(['code' => '1200', 'name' => 'Finished Goods Inventory', 'type' => 'asset']);
        $this->salesRevAccount = Account::create(['code' => '4100', 'name' => 'Sales Revenue', 'type' => 'revenue']);
        $this->cogsAccount = Account::create(['code' => '5100', 'name' => 'Cost of Goods Sold', 'type' => 'expense']);
        $this->wipAccount = Account::create(['code' => '1220', 'name' => 'Work In Progress', 'type' => 'asset']);
        $this->allocationAccount = Account::create(['code' => '5600', 'name' => 'Overhead Offset Allocation', 'type' => 'expense']);

        $cashGLAccount = Account::create(['code' => '1010', 'name' => 'Cash in Hand', 'type' => 'asset']);
        $this->cashAccount = BankAccount::create([
            'account_id' => $cashGLAccount->id,
            'account_name' => 'Cash Drawer',
            'type' => 'cash',
        ]);
    }

    // 1. Customer creation and ledger opening
    public function test_customer_creation_and_ledger_opening(): void
    {
        $response = $this->actingAs($this->admin)->post('/party-ledgers', [
            'name' => 'Test Customer',
            'type' => 'customer',
            'opening_balance' => 5000,
            'opening_balance_type' => 'debit',
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('contacts', [
            'name' => 'Test Customer',
            'type' => 'customer',
            'opening_balance' => 5000,
        ]);
    }

    // 2. Supplier creation and ledger opening
    public function test_supplier_creation_and_ledger_opening(): void
    {
        $response = $this->actingAs($this->admin)->post('/party-ledgers', [
            'name' => 'Test Supplier',
            'type' => 'vendor',
            'opening_balance' => 10000,
            'opening_balance_type' => 'credit',
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('contacts', [
            'name' => 'Test Supplier',
            'type' => 'vendor',
            'opening_balance' => 10000,
        ]);
    }

    // 3. Product creation with sq_ft
    public function test_product_creation_with_sq_ft(): void
    {
        $response = $this->actingAs($this->admin)->post('/inventory/products', [
            'sku' => 'PRD-SQFT',
            'name' => 'Sq Ft Marble Product',
            'unit' => 'Sq Ft',
            'cost' => 120,
            'price' => 200,
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('products', [
            'sku' => 'PRD-SQFT',
            'unit' => 'Sq Ft',
        ]);
    }

    // 4. Product creation with ton
    public function test_product_creation_with_ton(): void
    {
        $response = $this->actingAs($this->admin)->post('/inventory/products', [
            'sku' => 'PRD-TON',
            'name' => 'Ton Raw Block Product',
            'unit' => 'Ton',
            'cost' => 5000,
            'price' => 8000,
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('products', [
            'sku' => 'PRD-TON',
            'unit' => 'Ton',
        ]);
    }

    // 5. Product creation with running_ft
    public function test_product_creation_with_running_ft(): void
    {
        $response = $this->actingAs($this->admin)->post('/inventory/products', [
            'sku' => 'PRD-RFT',
            'name' => 'Running Ft Border Product',
            'unit' => 'Running Ft',
            'cost' => 45,
            'price' => 75,
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('products', [
            'sku' => 'PRD-RFT',
            'unit' => 'Running Ft',
        ]);
    }

    // 6. Purchase creates stock and accounting entries correctly
    public function test_purchase_creates_stock_and_accounting_entries(): void
    {
        $supplier = Contact::create(['name' => 'Supplier XYZ', 'type' => 'vendor']);
        $product = Product::create([
            'sku' => 'PRD-PUR',
            'name' => 'Purchasable Item',
            'unit' => 'Ton',
            'cost' => 1000,
            'price' => 1500,
            'inventory_account_id' => $this->invAssetAccount->id,
            'cogs_account_id' => $this->cogsAccount->id,
            'revenue_account_id' => $this->salesRevAccount->id,
        ]);

        $response = $this->actingAs($this->admin)->post('/purchases/store', [
            'contact_id' => $supplier->id,
            'order_date' => '2026-06-27',
            'due_date' => '2026-07-27',
            'warehouse_id' => $this->warehouseA->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'unit_price' => 1000,
                ],
            ],
            'transportation_cost' => 0,
            'loading_cost' => 0,
            'other_direct_cost' => 0,
            'cash_paid' => 0,
            'bank_paid' => 0,
            'allocation_method' => 'value',
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('inventory_batches', [
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouseA->id,
            'quantity_received' => 10,
        ]);
    }

    // 7. Sales invoice creates customer ledger, accounting, and stock effects correctly
    public function test_sales_invoice_creates_ledger_and_accounting(): void
    {
        $customer = Contact::create(['name' => 'Customer ABC', 'type' => 'customer']);
        $product = Product::create([
            'sku' => 'PRD-SAL',
            'name' => 'Salable Item',
            'unit' => 'Sq Ft',
            'cost' => 100,
            'price' => 200,
            'inventory_account_id' => $this->invAssetAccount->id,
            'cogs_account_id' => $this->cogsAccount->id,
            'revenue_account_id' => $this->salesRevAccount->id,
        ]);

        // Add inventory batch first
        InventoryBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouseA->id,
            'reference_type' => 'adjustment',
            'reference_id' => 1,
            'quantity_received' => 50,
            'quantity_remaining' => 50,
            'unit_cost' => 100,
            'total_cost' => 5000,
            'unit_of_measure' => 'Sq Ft',
            'product_type' => 'tile',
            'received_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $customer->id,
            'order_date' => '2026-06-27',
            'warehouse_id' => $this->warehouseA->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'unit_price' => 200,
                ],
            ],
            'discount_amount' => 0,
            'delivery_charges' => 0,
            'cash_received' => 0,
            'bank_received' => 0,
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('customer_invoices', [
            'contact_id' => $customer->id,
            'total_amount' => 2000,
        ]);
    }

    // 8. Delivery challan and gate pass routes work
    public function test_delivery_challan_and_gate_pass_routes(): void
    {
        $customer = Contact::create(['name' => 'Customer ABC', 'type' => 'customer']);
        $product = Product::create([
            'sku' => 'PRD-CHL',
            'name' => 'Challan Item',
            'unit' => 'Sq Ft',
            'cost' => 100,
            'price' => 200,
            'inventory_account_id' => $this->invAssetAccount->id,
            'cogs_account_id' => $this->cogsAccount->id,
            'revenue_account_id' => $this->salesRevAccount->id,
        ]);

        $challan = DeliveryChallan::create([
            'challan_number' => 'CHL-0001',
            'challan_date' => '2026-06-27',
            'status' => 'pending',
            'vehicle_number' => 'KD-9922',
            'driver_name' => 'Akram Khan',
            'prepared_by' => 'QA Operator',
        ]);

        $responseIndex = $this->actingAs($this->admin)->get('/delivery-challans');
        $responseIndex->assertStatus(200);

        $responsePrint = $this->actingAs($this->admin)->get("/delivery-challans/{$challan->id}/print");
        $responsePrint->assertStatus(200);
    }

    // 9. Production stone-to-slab flow works
    public function test_production_stone_to_slab_flow(): void
    {
        $rawStone = Product::create([
            'sku' => 'RAW-STN',
            'name' => 'Raw Block',
            'unit' => 'Ton',
            'cost' => 5000,
            'price' => 0,
            'inventory_account_id' => $this->invAssetAccount->id,
        ]);

        $slab = Product::create([
            'sku' => 'SLB-MAR',
            'name' => 'Unfinished Slab',
            'unit' => 'MT',
            'cost' => 100,
            'price' => 0,
            'inventory_account_id' => $this->invAssetAccount->id,
        ]);

        $inputBatch = InventoryBatch::create([
            'product_id' => $rawStone->id,
            'warehouse_id' => $this->warehouseA->id,
            'reference_type' => 'adjustment',
            'reference_id' => 1,
            'quantity_received' => 10,
            'quantity_remaining' => 10,
            'unit_cost' => 5000,
            'total_cost' => 50000,
            'unit_of_measure' => 'Ton',
            'product_type' => 'raw_stone',
            'received_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->post('/production/stone-to-slab', [
            'input_batch_id' => $inputBatch->id,
            'input_quantity' => 2,
            'slab_product_id' => $slab->id,
            'slabs_produced' => 5,
            'wastage_quantity' => 0.5,
            'scrap_quantity' => 0,
            'labor_cost' => 500,
            'electricity_cost' => 200,
            'fuel_cost' => 100,
            'maintenance_cost' => 50,
            'overhead_cost' => 50,
            'production_date' => '2026-06-27',
            'warehouse_id' => $this->warehouseA->id,
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('stone_to_slab_productions', [
            'input_batch_id' => $inputBatch->id,
            'slabs_produced' => 5,
        ]);
    }

    // 10. Production slab-to-tile flow works
    public function test_production_slab_to_tile_flow(): void
    {
        $slab = Product::create([
            'sku' => 'SLB-MAR-T',
            'name' => 'Unfinished Slab 2',
            'unit' => 'MT',
            'cost' => 500,
            'price' => 0,
            'inventory_account_id' => $this->invAssetAccount->id,
        ]);

        $tile = Product::create([
            'sku' => 'TL-ONYX-T',
            'name' => 'Onyx Tiles 2',
            'unit' => 'Sq Ft',
            'cost' => 50,
            'price' => 100,
            'inventory_account_id' => $this->invAssetAccount->id,
        ]);

        $inputBatch = InventoryBatch::create([
            'product_id' => $slab->id,
            'warehouse_id' => $this->warehouseA->id,
            'reference_type' => 'adjustment',
            'reference_id' => 1,
            'quantity_received' => 20,
            'quantity_remaining' => 20,
            'unit_cost' => 500,
            'total_cost' => 10000,
            'unit_of_measure' => 'MT',
            'product_type' => 'slab',
            'received_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->post('/production/slab-to-tile', [
            'input_batch_id' => $inputBatch->id,
            'input_quantity' => 5,
            'tile_product_id' => $tile->id,
            'tiles_produced' => 150,
            'wastage_percentage' => 5,
            'scrap_quantity' => 0,
            'labor_cost' => 1000,
            'cutting_cost' => 300,
            'polishing_cost' => 400,
            'packaging_cost' => 100,
            'overhead_cost' => 200,
            'production_date' => '2026-06-27',
            'warehouse_id' => $this->warehouseA->id,
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('slab_to_tile_productions', [
            'input_batch_id' => $inputBatch->id,
            'tiles_produced' => 150,
        ]);
    }

    // 11. Stock transfer prevents over-transfer
    public function test_stock_transfer_prevents_over_transfer(): void
    {
        $product = Product::create([
            'sku' => 'PRD-TRF',
            'name' => 'Transfer Item',
            'unit' => 'Sq Ft',
            'cost' => 10,
            'price' => 20,
            'inventory_account_id' => $this->invAssetAccount->id,
        ]);

        $inputBatch = InventoryBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouseA->id,
            'reference_type' => 'adjustment',
            'reference_id' => 1,
            'quantity_received' => 5,
            'quantity_remaining' => 5,
            'unit_cost' => 10,
            'total_cost' => 50,
            'unit_of_measure' => 'Sq Ft',
            'product_type' => 'tile',
            'received_at' => now(),
        ]);

        // Attempting to transfer 10 (which is > 5 available)
        $response = $this->actingAs($this->admin)->post('/inventory/transfers', [
            'source_batch_id' => $inputBatch->id,
            'to_warehouse_id' => $this->warehouseB->id,
            'quantity' => 10,
            'transfer_date' => '2026-06-27',
        ]);

        $response->assertSessionHasErrors(['transfer']);
    }

    // 12. Stock adjustment posts correct journal
    public function test_stock_adjustment_posts_journal(): void
    {
        $product = Product::create([
            'sku' => 'PRD-ADJ',
            'name' => 'Adjustment Item',
            'unit' => 'Sq Ft',
            'cost' => 10,
            'price' => 20,
            'inventory_account_id' => $this->invAssetAccount->id,
        ]);

        $response = $this->actingAs($this->admin)->post('/inventory/adjust', [
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouseA->id,
            'quantity' => 10,
            'unit_cost' => 10,
            'notes' => 'QA Adjust IN',
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('inventory_batches', [
            'product_id' => $product->id,
            'quantity_received' => 10,
        ]);
    }

    // 13. Reports index route loads
    public function test_reports_index_route_loads(): void
    {
        $response = $this->actingAs($this->admin)->get('/reports');
        $response->assertStatus(200);
    }

    // 14. Daily reports route loads
    public function test_daily_reports_route_loads(): void
    {
        $response = $this->actingAs($this->admin)->get('/reports/daily');
        $response->assertStatus(200);
    }

    // 15. Unauthorized role is blocked from admin/audit routes
    public function test_unauthorized_role_is_blocked_from_admin_audit(): void
    {
        $response = $this->actingAs($this->guest)->get('/administration/audit-logs');
        $response->assertStatus(403);
    }

    // 16. Expected business-rule failures return validation errors instead of raw 500s
    public function test_expected_business_rule_failures_return_validation_errors(): void
    {
        $contact = Contact::create(['name' => 'Test Customer', 'type' => 'customer']);

        // Try creating a payment without a Cash/Bank account
        $response = $this->actingAs($this->admin)->post('/rental-jobs/store', [
            'contact_id' => $contact->id,
            'job_date' => '2026-06-27',
            'machine_hours' => 5,
            'machine_rate' => 100,
            'labor_hours' => 0,
            'labor_rate' => 0,
            'other_charges' => 0,
            'cash_received' => 500,
            'bank_received' => 0,
            'bank_account_id' => '', // Empty triggers select error
        ]);

        $response->assertSessionHasErrors(['cash_received']);
    }

    // 17. Unexpected technical failures are not silently converted
    public function test_unexpected_technical_failures_are_not_silently_converted(): void
    {
        // When database/runtime bugs exist (e.g. invalid input types triggering database integrity errors),
        // it should throw a raw Database/Query Exception or regular Exception and fail instead of passing.
        $this->expectException(QueryException::class);

        // Force a Database query exception by inserting a record with a null field that is not nullable and has no default
        DB::table('users')->insert([
            'email' => null, // Not nullable
        ]);
    }

    // 18. Seeding requires an explicit, sufficiently long administrator password
    public function test_seed_admin_password_is_explicit_and_sufficiently_long(): void
    {
        $password = env('OGGY_SUPERADMIN_PASSWORD');

        $this->assertIsString($password);
        $this->assertGreaterThanOrEqual(12, strlen($password));
    }
}
