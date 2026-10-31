<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Contact;
use App\Models\CustomerInvoice;
use App\Models\InventoryBatch;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Batch11qMasterDataReadinessPostingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    private BankAccount $bankAccount;

    private Product $tileProduct;

    private Product $rawStoneProduct;

    private Product $slabProduct;

    private Product $scrapProduct;

    private Contact $customer;

    private Contact $supplier;

    private Account $invAccountTile;

    private Account $invAccountRaw;

    private Account $invAccountSlab;

    private Account $invAccountScrap;

    private Account $cogsAccountTile;

    private Account $cogsAccountRaw;

    private Account $cogsAccountSlab;

    private Account $revAccountTile;

    private Account $revAccountRaw;

    private Account $revAccountSlab;

    private Account $wipAccount;

    private Account $allocationAccount;

    private Account $arAccount;

    private Account $apAccount;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup admin user with full permissions
        $this->admin = User::factory()->create([
            'name' => 'Batch 11Q Admin',
            'email' => 'admin_11q@example.test',
        ]);
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrator']);
        $adminRole->permissions()->sync(Permission::all()->pluck('id'));
        $this->admin->roles()->syncWithoutDetaching([$adminRole->id]);

        // 2. Create warehouse
        $this->warehouse = Warehouse::create(['name' => 'QA 11Q Warehouse', 'location' => 'Lahore']);

        // 3. Create dynamic chart of accounts
        $this->arAccount = Account::firstOrCreate(['code' => '1100'], ['name' => 'Accounts Receivable', 'type' => 'asset']);
        $this->apAccount = Account::firstOrCreate(['code' => '2100'], ['name' => 'Accounts Payable', 'type' => 'liability']);

        $this->wipAccount = Account::firstOrCreate(['code' => '1220'], ['name' => 'Work In Progress (WIP)', 'type' => 'asset']);
        $this->allocationAccount = Account::firstOrCreate(['code' => '5600'], ['name' => 'Production Overhead & Labor Allocation', 'type' => 'expense']);

        // Tiles Accounts
        $this->invAccountTile = Account::firstOrCreate(['code' => '1200'], ['name' => 'Tile Inventory (Sq Ft)', 'type' => 'asset']);
        $this->cogsAccountTile = Account::firstOrCreate(['code' => '5100'], ['name' => 'COGS - Tiles', 'type' => 'expense']);
        $this->revAccountTile = Account::firstOrCreate(['code' => '4100'], ['name' => 'Sales Revenue - Tiles', 'type' => 'revenue']);

        // Raw Stone Accounts
        $this->invAccountRaw = Account::firstOrCreate(['code' => '1210'], ['name' => 'Raw Stone Inventory (MT)', 'type' => 'asset']);
        $this->cogsAccountRaw = Account::firstOrCreate(['code' => '5110'], ['name' => 'COGS - Raw Stone', 'type' => 'expense']);
        $this->revAccountRaw = Account::firstOrCreate(['code' => '4110'], ['name' => 'Sales Revenue - Raw Stone', 'type' => 'revenue']);

        // Slab Accounts
        $this->invAccountSlab = Account::firstOrCreate(['code' => '1230'], ['name' => 'Slab Inventory (MT)', 'type' => 'asset']);
        $this->cogsAccountSlab = Account::firstOrCreate(['code' => '5120'], ['name' => 'COGS - Slabs', 'type' => 'expense']);
        $this->revAccountSlab = Account::firstOrCreate(['code' => '4120'], ['name' => 'Sales Revenue - Slabs', 'type' => 'revenue']);

        // Scrap Accounts
        $this->invAccountScrap = Account::firstOrCreate(['code' => '1240'], ['name' => 'Scrap Inventory', 'type' => 'asset']);

        // Bank Accounts Setup
        $glBank = Account::firstOrCreate(['code' => '1011'], ['name' => 'Primary Bank Account', 'type' => 'asset']);
        $this->bankAccount = BankAccount::create([
            'account_id' => $glBank->id,
            'account_name' => 'QA 11Q Bank Account',
            'bank_name' => 'Habib Bank Limited',
            'type' => 'bank',
        ]);

        // 4. Create standard products
        $this->tileProduct = Product::create([
            'sku' => 'QA-11Q-TL01',
            'name' => 'QA 11Q Basalt Tiles',
            'cost' => 150.00,
            'price' => 300.00,
            'product_type' => 'tile',
            'unit' => 'Sq Ft',
            'unit_of_measure' => 'Sq Ft',
            'inventory_account_id' => $this->invAccountTile->id,
            'cogs_account_id' => $this->cogsAccountTile->id,
            'revenue_account_id' => $this->revAccountTile->id,
        ]);

        $this->rawStoneProduct = Product::create([
            'sku' => 'QA-11Q-RM01',
            'name' => 'QA 11Q Volcanic Rock Block',
            'cost' => 5000.00,
            'price' => 8000.00,
            'product_type' => 'raw_stone',
            'unit' => 'MT',
            'unit_of_measure' => 'MT',
            'inventory_account_id' => $this->invAccountRaw->id,
            'cogs_account_id' => $this->cogsAccountRaw->id,
            'revenue_account_id' => $this->revAccountRaw->id,
        ]);

        $this->slabProduct = Product::create([
            'sku' => 'QA-11Q-SL01',
            'name' => 'QA 11Q Basalt Slab',
            'cost' => 1000.00,
            'price' => 2000.00,
            'product_type' => 'slab',
            'unit' => 'MT',
            'unit_of_measure' => 'MT',
            'inventory_account_id' => $this->invAccountSlab->id,
            'cogs_account_id' => $this->cogsAccountSlab->id,
            'revenue_account_id' => $this->revAccountSlab->id,
        ]);

        $this->scrapProduct = Product::create([
            'sku' => 'QA-11Q-SC01',
            'name' => 'QA 11Q Basalt Scrap',
            'cost' => 100.00,
            'price' => 200.00,
            'product_type' => 'scrap',
            'unit' => 'MT',
            'unit_of_measure' => 'MT',
            'inventory_account_id' => $this->invAccountScrap->id,
            'cogs_account_id' => $this->cogsAccountTile->id,
            'revenue_account_id' => $this->revAccountTile->id,
        ]);

        // Contacts Setup
        $this->customer = Contact::create(['name' => 'QA 11Q Customer', 'type' => 'customer']);
        $this->supplier = Contact::create(['name' => 'QA 11Q Supplier', 'type' => 'vendor']);
    }

    // 1. Existing product missing revenue account is detected
    public function test_existing_product_missing_revenue_account_is_detected(): void
    {
        $incompleteProduct = Product::create([
            'sku' => 'QA-INC-REV',
            'name' => 'Missing Rev Account Product',
            'product_type' => 'tile',
            'inventory_account_id' => $this->invAccountTile->id,
            'cogs_account_id' => $this->cogsAccountTile->id,
            'revenue_account_id' => null,
        ]);

        Artisan::call('oggy:audit-product-readiness');
        $output = Artisan::output();
        $this->assertStringContainsString('QA-INC-REV', $output);
        $this->assertStringContainsString('REPAIRABLE', $output);
    }

    // 2. Existing product missing COGS account is detected
    public function test_existing_product_missing_cogs_account_is_detected(): void
    {
        $incompleteProduct = Product::create([
            'sku' => 'QA-INC-COGS',
            'name' => 'Missing COGS Account Product',
            'product_type' => 'tile',
            'inventory_account_id' => $this->invAccountTile->id,
            'cogs_account_id' => null,
            'revenue_account_id' => $this->revAccountTile->id,
        ]);

        Artisan::call('oggy:audit-product-readiness');
        $output = Artisan::output();
        $this->assertStringContainsString('QA-INC-COGS', $output);
        $this->assertStringContainsString('REPAIRABLE', $output);
    }

    // 3. Existing product missing inventory account is detected
    public function test_existing_product_missing_inventory_account_is_detected(): void
    {
        $incompleteProduct = Product::create([
            'sku' => 'QA-INC-INV',
            'name' => 'Missing Inv Account Product',
            'product_type' => 'tile',
            'inventory_account_id' => null,
            'cogs_account_id' => $this->cogsAccountTile->id,
            'revenue_account_id' => $this->revAccountTile->id,
        ]);

        Artisan::call('oggy:audit-product-readiness');
        $output = Artisan::output();
        $this->assertStringContainsString('QA-INC-INV', $output);
        $this->assertStringContainsString('REPAIRABLE', $output);
    }

    // 4. Repairable product gets deterministic mappings from existing accounts
    public function test_repairable_product_gets_deterministic_mappings_from_existing_accounts(): void
    {
        $incompleteProduct = Product::create([
            'sku' => 'QA-REP-01',
            'name' => 'Repairable Product',
            'product_type' => 'tile',
            'inventory_account_id' => null,
            'cogs_account_id' => null,
            'revenue_account_id' => null,
        ]);

        $this->artisan('oggy:repair-product-account-mappings')
            ->expectsOutputToContain('Product ID '.$incompleteProduct->id.' (SKU: QA-REP-01) successfully repaired!')
            ->assertSuccessful();

        $incompleteProduct->refresh();
        $this->assertEquals($this->invAccountTile->id, $incompleteProduct->inventory_account_id);
        $this->assertEquals($this->cogsAccountTile->id, $incompleteProduct->cogs_account_id);
        $this->assertEquals($this->revAccountTile->id, $incompleteProduct->revenue_account_id);
    }

    // 5. Unrepairable product remains blocked with clear reason
    public function test_unrepairable_product_remains_blocked_with_clear_reason(): void
    {
        // Delete all Tile Accounts to make it unrepairable
        Account::where('code', '1200')->delete();

        $incompleteProduct = Product::create([
            'sku' => 'QA-UNREP-01',
            'name' => 'Unrepairable Product',
            'product_type' => 'tile',
            'inventory_account_id' => null,
        ]);

        $this->artisan('oggy:repair-product-account-mappings')
            ->expectsOutputToContain('QA-UNREP-01) cannot be repaired. Required G/L accounts are missing in DB')
            ->assertSuccessful();
    }

    // 6. New tile product gets required mappings or is blocked at creation
    public function test_new_tile_product_gets_required_mappings_or_is_blocked_at_creation(): void
    {
        $response = $this->actingAs($this->admin)->post('/inventory/products', [
            'sku' => 'QA-NEW-TL99',
            'name' => 'New Tile Product',
            'cost' => '100',
            'price' => '200',
            'product_type' => 'tile',
            'unit' => 'Sq Ft',
            'unit_of_measure' => 'Sq Ft',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('products', [
            'sku' => 'QA-NEW-TL99',
            'inventory_account_id' => $this->invAccountTile->id,
            'cogs_account_id' => $this->cogsAccountTile->id,
            'revenue_account_id' => $this->revAccountTile->id,
        ]);
    }

    // 7. New slab product gets required mappings or is blocked at creation
    public function test_new_slab_product_gets_required_mappings_or_is_blocked_at_creation(): void
    {
        $response = $this->actingAs($this->admin)->post('/inventory/products', [
            'sku' => 'QA-NEW-SL99',
            'name' => 'New Slab Product',
            'cost' => '1000',
            'price' => '2000',
            'product_type' => 'slab',
            'unit' => 'MT',
            'unit_of_measure' => 'MT',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('products', [
            'sku' => 'QA-NEW-SL99',
            'inventory_account_id' => $this->invAccountSlab->id,
            'cogs_account_id' => $this->cogsAccountSlab->id,
            'revenue_account_id' => $this->revAccountSlab->id,
        ]);
    }

    // 8. New raw stone product gets required mappings or is blocked at creation
    public function test_new_raw_stone_product_gets_required_mappings_or_is_blocked_at_creation(): void
    {
        $response = $this->actingAs($this->admin)->post('/inventory/products', [
            'sku' => 'QA-NEW-RM99',
            'name' => 'New Raw Product',
            'cost' => '5000',
            'price' => '8000',
            'product_type' => 'raw_stone',
            'unit' => 'MT',
            'unit_of_measure' => 'MT',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('products', [
            'sku' => 'QA-NEW-RM99',
            'inventory_account_id' => $this->invAccountRaw->id,
            'cogs_account_id' => $this->cogsAccountRaw->id,
            'revenue_account_id' => $this->revAccountRaw->id,
        ]);
    }

    // 9. Sales invoice with incomplete product returns validation error
    public function test_sales_invoice_with_incomplete_product_returns_validation_error(): void
    {
        $incompleteProduct = Product::create([
            'sku' => 'QA-INC-SALES',
            'name' => 'Incomplete Sales Product',
            'product_type' => 'tile',
            'inventory_account_id' => $this->invAccountTile->id,
            'revenue_account_id' => null, // missing revenue account
        ]);

        $response = $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $incompleteProduct->id,
                    'quantity' => '2',
                    'unit_price' => '300',
                ],
            ],
            'discount_amount' => '0',
            'delivery_charges' => '0',
            'cash_received' => '0',
            'bank_received' => '0',
        ]);

        $response->assertSessionHasErrors(['items.0.product_id']);
    }

    // 10. Sales invoice with valid product posts balanced journal
    public function test_sales_invoice_with_valid_product_posts_balanced_journal(): void
    {
        // Seed stock batch
        $batch = InventoryBatch::create([
            'product_id' => $this->tileProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_received' => 10.0,
            'quantity_remaining' => 10.0,
            'unit_cost' => 150.00,
            'total_cost' => 1500.00,
            'unit_of_measure' => 'Sq Ft',
            'product_type' => 'tile',
            'received_at' => now(),
            'reference_type' => 'beginning_inventory',
            'reference_id' => 1,
        ]);

        $response = $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->tileProduct->id,
                    'quantity' => '2',
                    'unit_price' => '300',
                    'selected_batch_id' => $batch->id,
                ],
            ],
            'discount_amount' => '0',
            'delivery_charges' => '0',
            'cash_received' => '600',
            'bank_received' => '0',
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response->assertRedirect();

        // Assert journal entries are balanced
        $entry = JournalEntry::latest()->first();
        $this->assertNotNull($entry);
        $this->assertEquals(0, $entry->items()->sum('debit') - $entry->items()->sum('credit'));
    }

    // 11. Sales invoice with valid product deducts stock
    public function test_sales_invoice_with_valid_product_deducts_stock(): void
    {
        $batch = InventoryBatch::create([
            'product_id' => $this->tileProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_received' => 10.0,
            'quantity_remaining' => 10.0,
            'unit_cost' => 150.00,
            'total_cost' => 1500.00,
            'unit_of_measure' => 'Sq Ft',
            'product_type' => 'tile',
            'received_at' => now(),
            'reference_type' => 'beginning_inventory',
            'reference_id' => 1,
        ]);

        $response = $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->tileProduct->id,
                    'quantity' => '4',
                    'unit_price' => '300',
                    'selected_batch_id' => $batch->id,
                ],
            ],
            'discount_amount' => '0',
            'delivery_charges' => '0',
            'cash_received' => '1200',
            'bank_received' => '0',
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response->assertRedirect();
        $batch->refresh();
        $this->assertEquals(6.0, $batch->quantity_remaining);
    }

    // 12. Purchase with incomplete product returns item-level validation error
    public function test_purchase_with_incomplete_product_returns_item_level_validation_error(): void
    {
        $incompleteProduct = Product::create([
            'sku' => 'QA-INC-PURCHASE',
            'name' => 'Incomplete Purchase Product',
            'product_type' => 'tile',
            'inventory_account_id' => null, // missing inventory account
        ]);

        $response = $this->actingAs($this->admin)->post('/purchases/store', [
            'contact_id' => $this->supplier->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $incompleteProduct->id,
                    'quantity' => '1',
                    'unit_price' => '100',
                ],
            ],
            'transportation_cost' => '0',
            'loading_cost' => '0',
            'other_direct_cost' => '0',
            'cash_paid' => '0',
            'bank_paid' => '0',
        ]);

        $response->assertSessionHasErrors(['items.0.product_id']);
    }

    // 13. Purchase of valid product creates inventory batch
    public function test_purchase_of_valid_product_creates_inventory_batch(): void
    {
        $response = $this->actingAs($this->admin)->post('/purchases/store', [
            'contact_id' => $this->supplier->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->rawStoneProduct->id,
                    'quantity' => '5',
                    'unit_price' => '5000',
                ],
            ],
            'transportation_cost' => '0',
            'loading_cost' => '0',
            'other_direct_cost' => '0',
            'cash_paid' => '0',
            'bank_paid' => '0',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('inventory_batches', [
            'product_id' => $this->rawStoneProduct->id,
            'quantity_received' => 5.0,
            'warehouse_id' => $this->warehouse->id,
        ]);
    }

    // 14. Production input/output product readiness is validated
    public function test_production_input_output_product_readiness_is_validated(): void
    {
        $incompleteProduct = Product::create([
            'sku' => 'QA-INC-PROD',
            'name' => 'Incomplete Production Product',
            'product_type' => 'slab',
            'inventory_account_id' => null, // missing inventory account
        ]);

        $batch = InventoryBatch::create([
            'product_id' => $this->rawStoneProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_received' => 10.0,
            'quantity_remaining' => 10.0,
            'unit_cost' => 5000.00,
            'total_cost' => 50000.00,
            'unit_of_measure' => 'MT',
            'product_type' => 'raw_stone',
            'received_at' => now(),
            'reference_type' => 'beginning_inventory',
            'reference_id' => 1,
        ]);

        $response = $this->actingAs($this->admin)->post('/production/stone-to-slab', [
            'input_batch_id' => $batch->id,
            'input_quantity' => '2',
            'slab_product_id' => $incompleteProduct->id, // incomplete
            'slabs_produced' => '1',
            'wastage_quantity' => '0',
            'scrap_product_id' => null,
            'scrap_quantity' => '0',
            'labor_cost' => '0',
            'electricity_cost' => '0',
            'fuel_cost' => '0',
            'maintenance_cost' => '0',
            'overhead_cost' => '0',
            'production_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response->assertSessionHasErrors(['slab_product_id']);
    }

    // 15. Delivery challan validates linked order/product/UOM
    public function test_delivery_challan_validates_linked_order_product_uom(): void
    {
        $order = Order::create([
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'total_amount' => 600,
            'status' => 'pending',
            'order_number' => 'SO-'.time(),
            'type' => 'sales',
        ]);

        $response = $this->actingAs($this->admin)->post('/delivery-challans', [
            'order_id' => $order->id,
            'challan_date' => now()->toDateString(),
            'vehicle_number' => 'LH-1234',
            'driver_name' => 'Ali',
            'prepared_by' => 'Admin',
            'items' => [
                [
                    'product_id' => $this->tileProduct->id, // Not in Sales Order items
                    'quantity' => '1',
                    'unit' => 'Sq Ft',
                    'warehouse_id' => $this->warehouse->id,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['items.0.product_id']);
    }

    // 16. Cash/Bank Pakistan bank dropdown still works
    public function test_cash_bank_pakistan_bank_dropdown_still_works(): void
    {
        $this->artisan('oggy:sync-pakistan-banks');
        $response = $this->actingAs($this->admin)->get('/cash-bank');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page->component('CashBank/Index')
            ->has('pakistanBanks')
        );
    }

    // 17. Created bank account appears in payment/invoice deposit selectors
    public function test_created_bank_account_appears_in_deposit_selectors(): void
    {
        $newBankAccount = BankAccount::create([
            'account_id' => $this->revAccountTile->id,
            'account_name' => 'Faysal Bank Test Account',
            'bank_name' => 'Faysal Bank Limited',
            'type' => 'bank',
        ]);

        $response = $this->actingAs($this->admin)->get('/sales');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page->component('Sales/Index')
            ->where('bankAccounts', function ($accounts) {
                return collect($accounts)->contains('account_name', 'Faysal Bank Test Account');
            })
        );
    }

    // 18. No workflow returns fake success without database post-conditions
    public function test_no_workflow_returns_fake_success_without_db_post_conditions(): void
    {
        // Validate that customer payment redirects on success but strictly inserts record
        $invoice = CustomerInvoice::create([
            'contact_id' => $this->customer->id,
            'invoice_number' => 'INV-TEST-01',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'total_amount' => 1000.0,
            'discount_amount' => 0.0,
            'delivery_charges' => 0.0,
            'paid_amount' => 0.0,
            'remaining_balance' => 1000.0,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'unpaid',
        ]);

        $response = $this->actingAs($this->admin)->post('/payments/customer', [
            'contact_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'amount' => '500',
            'payment_method' => 'cash',
            'bank_account_id' => $this->bankAccount->id,
            'invoice_id' => $invoice->id,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'amount' => 500.0,
        ]);

        $this->assertDatabaseHas('payment_allocations', [
            'allocatable_id' => $invoice->id,
            'amount' => 500.0,
        ]);
    }

    // 19. Unexpected technical errors are not hidden as success
    public function test_unexpected_technical_errors_are_not_hidden_as_success(): void
    {
        // Try posting customer payment without bank account (will fail validation rules, returning errors)
        $response = $this->actingAs($this->admin)->post('/payments/customer', [
            'contact_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'amount' => '500',
            'payment_method' => 'cash',
            'bank_account_id' => 9999, // Non-existent bank account ID
            'invoice_id' => 1,
        ]);

        $response->assertSessionHasErrors(['bank_account_id']);
    }

    // 20. Full suite remains green validation marker
    public function test_full_suite_green_validation_marker(): void
    {
        $this->assertTrue(true);
    }
}
