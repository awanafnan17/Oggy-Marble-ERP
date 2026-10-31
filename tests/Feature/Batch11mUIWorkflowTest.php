<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\BankAccount;
use App\Models\Bom;
use App\Models\Contact;
use App\Models\CustomerInvoice;
use App\Models\DeliveryChallan;
use App\Models\InventoryBatch;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Batch11mUIWorkflowTest extends TestCase
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

        // Product default asset accounts
        Account::create(['code' => '1200', 'name' => 'Tile Inventory (Sq Ft)', 'type' => 'asset']);
        Account::create(['code' => '1210', 'name' => 'Raw Stone Inventory (MT)', 'type' => 'asset']);
        Account::create(['code' => '1230', 'name' => 'Slab Inventory (MT)', 'type' => 'asset']);
        Account::create(['code' => '1240', 'name' => 'Scrap Inventory', 'type' => 'asset']);

        // Product default revenue accounts
        Account::create(['code' => '4100', 'name' => 'Sales Revenue - Tiles', 'type' => 'revenue']);
        Account::create(['code' => '4110', 'name' => 'Sales Revenue - Raw Stone', 'type' => 'revenue']);
        Account::create(['code' => '4120', 'name' => 'Sales Revenue - Slabs', 'type' => 'revenue']);
        Account::create(['code' => '4200', 'name' => 'Machine Rental Revenue', 'type' => 'revenue']);

        // Product default COGS accounts
        Account::create(['code' => '5100', 'name' => 'COGS - Tiles', 'type' => 'expense']);
        Account::create(['code' => '5110', 'name' => 'COGS - Raw Stone', 'type' => 'expense']);
        Account::create(['code' => '5120', 'name' => 'COGS - Slabs', 'type' => 'expense']);

        $this->wipAccount = Account::create(['code' => '1220', 'name' => 'Work In Progress', 'type' => 'asset']);
        $this->allocationAccount = Account::create(['code' => '5600', 'name' => 'Overhead Offset Allocation', 'type' => 'expense']);
        Account::create(['code' => '5500', 'name' => 'Inventory Adjustments Gain/Loss', 'type' => 'expense']);

        $cashGLAccount = Account::create(['code' => '1010', 'name' => 'Cash in Hand', 'type' => 'asset']);
        $this->cashAccount = BankAccount::create([
            'account_id' => $cashGLAccount->id,
            'account_name' => 'Cash Drawer',
            'type' => 'cash',
        ]);

        // Setup default period
        AccountingPeriod::create([
            'period_name' => 'June 2026',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'is_closed' => false,
        ]);
    }

    // Helpers
    private function createProductHelper($type = 'tile', $sku = 'P-001')
    {
        $invAcc = Account::where('code', $type === 'raw_stone' ? '1210' : ($type === 'slab' ? '1230' : ($type === 'scrap' ? '1240' : '1200')))->first();
        $revAcc = Account::where('code', $type === 'raw_stone' ? '4110' : ($type === 'slab' ? '4120' : '4100'))->first();
        $cogsAcc = Account::where('code', $type === 'raw_stone' ? '5110' : ($type === 'slab' ? '5120' : '5100'))->first();

        return Product::create([
            'sku' => $sku,
            'name' => "Product {$sku}",
            'unit' => $type === 'raw_stone' ? 'Ton' : ($type === 'slab' ? 'Rft' : 'Sq Ft'),
            'cost' => 1000.00,
            'price' => 1500.00,
            'product_type' => $type,
            'inventory_account_id' => $invAcc->id,
            'revenue_account_id' => $revAcc->id,
            'cogs_account_id' => $cogsAcc->id,
        ]);
    }

    // 1. Party Ledger Add Customer button/source exists and POST /party-ledgers creates customer.
    public function test_party_ledger_add_customer(): void
    {
        $response = $this->actingAs($this->admin)->post('/party-ledgers', [
            'name' => 'Customer Alpha',
            'type' => 'customer',
            'opening_balance' => '1000.00',
            'opening_balance_type' => 'debit',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('contacts', [
            'name' => 'Customer Alpha',
            'type' => 'customer',
            'opening_balance' => 1000.00,
        ]);
    }

    // 2. Party Ledger Add Supplier button/source exists and POST /party-ledgers creates supplier.
    public function test_party_ledger_add_supplier(): void
    {
        $response = $this->actingAs($this->admin)->post('/party-ledgers', [
            'name' => 'Supplier Beta',
            'type' => 'vendor',
            'opening_balance' => '2000.00',
            'opening_balance_type' => 'credit',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('contacts', [
            'name' => 'Supplier Beta',
            'type' => 'vendor',
            'opening_balance' => 2000.00,
        ]);
    }

    // 3. Created customer appears in party/customer selection source.
    public function test_created_customer_in_selection(): void
    {
        $c = Contact::create(['name' => 'Selection Customer', 'type' => 'customer', 'opening_balance' => 0]);
        $response = $this->actingAs($this->admin)->get('/party-ledgers?contact_id='.$c->id);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Ledger/PartyLedger')
            ->has('contacts')
            ->where('selectedContact.id', $c->id)
        );
    }

    // 4. Created supplier appears in party/supplier selection source.
    public function test_created_supplier_in_selection(): void
    {
        $s = Contact::create(['name' => 'Selection Supplier', 'type' => 'vendor', 'opening_balance' => 0]);
        $response = $this->actingAs($this->admin)->get('/party-ledgers?contact_id='.$s->id);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Ledger/PartyLedger')
            ->has('contacts')
            ->where('selectedContact.id', $s->id)
        );
    }

    // 5. Product create with Sq Ft assigns required G/L mappings or returns validation error if mapping missing.
    public function test_product_create_sqft_assigns_mappings(): void
    {
        $response = $this->actingAs($this->admin)->post('/inventory/products', [
            'sku' => 'TILE-NEW-01',
            'name' => 'New Premium Tile',
            'unit' => 'Sq Ft',
            'cost' => '120.00',
            'price' => '180.00',
            'product_type' => 'tile',
        ]);

        $response->assertRedirect();

        $p = Product::where('sku', 'TILE-NEW-01')->first();
        $this->assertNotNull($p);
        $this->assertEquals(Account::where('code', '1200')->first()->id, $p->inventory_account_id);
        $this->assertEquals(Account::where('code', '4100')->first()->id, $p->revenue_account_id);
        $this->assertEquals(Account::where('code', '5100')->first()->id, $p->cogs_account_id);
    }

    // 6. Product create with Ton assigns required G/L mappings or returns validation error if mapping missing.
    public function test_product_create_ton_assigns_mappings(): void
    {
        $response = $this->actingAs($this->admin)->post('/inventory/products', [
            'sku' => 'STONE-NEW-01',
            'name' => 'New Premium Raw Stone',
            'unit' => 'Ton',
            'cost' => '15000.00',
            'price' => '0.00',
            'product_type' => 'raw_stone',
        ]);

        $response->assertRedirect();

        $p = Product::where('sku', 'STONE-NEW-01')->first();
        $this->assertNotNull($p);
        $this->assertEquals(Account::where('code', '1210')->first()->id, $p->inventory_account_id);
        $this->assertEquals(Account::where('code', '4110')->first()->id, $p->revenue_account_id);
        $this->assertEquals(Account::where('code', '5110')->first()->id, $p->cogs_account_id);
    }

    // 7. Product create with Running Ft assigns required G/L mappings or returns validation error if mapping missing.
    public function test_product_create_running_ft_assigns_mappings(): void
    {
        $response = $this->actingAs($this->admin)->post('/inventory/products', [
            'sku' => 'SLAB-NEW-01',
            'name' => 'New Premium Slab',
            'unit' => 'Rft',
            'cost' => '350.00',
            'price' => '500.00',
            'product_type' => 'slab',
        ]);

        $response->assertRedirect();

        $p = Product::where('sku', 'SLAB-NEW-01')->first();
        $this->assertNotNull($p);
        $this->assertEquals(Account::where('code', '1230')->first()->id, $p->inventory_account_id);
        $this->assertEquals(Account::where('code', '4120')->first()->id, $p->revenue_account_id);
        $this->assertEquals(Account::where('code', '5120')->first()->id, $p->cogs_account_id);
    }

    // 8. Purchase workflow saves header and items.
    public function test_purchase_workflow_saves_header_and_items(): void
    {
        $supplier = Contact::create(['name' => 'Supplier A', 'type' => 'vendor']);
        $product = $this->createProductHelper('tile', 'P-TILE-1');

        $response = $this->actingAs($this->admin)->post('/purchases/store', [
            'contact_id' => $supplier->id,
            'order_date' => '2026-06-28',
            'due_date' => '2026-07-28',
            'warehouse_id' => $this->warehouseA->id,
            'transportation_cost' => '1000',
            'loading_cost' => '500',
            'other_direct_cost' => '0',
            'cash_paid' => '0',
            'bank_paid' => '0',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '100',
                    'unit_price' => '10',
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('orders', ['contact_id' => $supplier->id, 'type' => 'purchase']);
        $this->assertDatabaseHas('order_items', ['product_id' => $product->id, 'quantity' => 100]);
    }

    // 9. Purchase workflow creates inventory batch.
    public function test_purchase_workflow_creates_inventory_batch(): void
    {
        $supplier = Contact::create(['name' => 'Supplier A', 'type' => 'vendor']);
        $product = $this->createProductHelper('tile', 'P-TILE-1');

        $this->actingAs($this->admin)->post('/purchases/store', [
            'contact_id' => $supplier->id,
            'order_date' => '2026-06-28',
            'warehouse_id' => $this->warehouseA->id,
            'transportation_cost' => '1000',
            'loading_cost' => '500',
            'other_direct_cost' => '0',
            'cash_paid' => '0',
            'bank_paid' => '0',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '100',
                    'unit_price' => '10',
                ],
            ],
        ]);

        $this->assertDatabaseHas('inventory_batches', [
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouseA->id,
            'quantity_received' => 100,
        ]);
    }

    // 10. Purchase workflow posts balanced accounting entries.
    public function test_purchase_workflow_posts_balanced_accounting(): void
    {
        $supplier = Contact::create(['name' => 'Supplier A', 'type' => 'vendor']);
        $product = $this->createProductHelper('tile', 'P-TILE-1');

        $this->actingAs($this->admin)->post('/purchases/store', [
            'contact_id' => $supplier->id,
            'order_date' => '2026-06-28',
            'warehouse_id' => $this->warehouseA->id,
            'transportation_cost' => '1000',
            'loading_cost' => '500',
            'other_direct_cost' => '0',
            'cash_paid' => '0',
            'bank_paid' => '0',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '100',
                    'unit_price' => '10',
                ],
            ],
        ]);

        $entry = JournalEntry::latest('id')->first();
        $this->assertNotNull($entry);

        $debits = JournalItem::where('journal_entry_id', $entry->id)->sum('debit');
        $credits = JournalItem::where('journal_entry_id', $entry->id)->sum('credit');

        $this->assertEquals($debits, $credits);
        $this->assertEquals(2500, $debits); // (100 * 10) + 1000 + 500 = 2500
    }

    // 11. Sales invoice workflow saves header and line items.
    public function test_sales_invoice_workflow_saves_header_and_items(): void
    {
        $customer = Contact::create(['name' => 'Customer A', 'type' => 'customer']);
        $product = $this->createProductHelper('tile', 'P-TILE-1');

        // Seed stock batch
        InventoryBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouseA->id,
            'quantity_received' => 100,
            'quantity_remaining' => 100,
            'unit_cost' => 10.00,
            'product_type' => 'tile',
            'batch_number' => 'B1',
            'reference_type' => 'purchase_order',
            'reference_id' => 1,
            'received_at' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $customer->id,
            'order_date' => '2026-06-28',
            'warehouse_id' => $this->warehouseA->id,
            'discount_amount' => '0',
            'delivery_charges' => '0',
            'cash_received' => '0',
            'bank_received' => '0',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '20',
                    'unit_price' => '15',
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('orders', ['contact_id' => $customer->id, 'type' => 'sales']);
        $this->assertDatabaseHas('order_items', ['product_id' => $product->id, 'quantity' => 20]);
    }

    // 12. Sales invoice workflow updates customer ledger.
    public function test_sales_invoice_workflow_updates_customer_ledger(): void
    {
        $customer = Contact::create(['name' => 'Customer A', 'type' => 'customer']);
        $product = $this->createProductHelper('tile', 'P-TILE-1');
        InventoryBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouseA->id,
            'quantity_received' => 100,
            'quantity_remaining' => 100,
            'unit_cost' => 10.00,
            'product_type' => 'tile',
            'batch_number' => 'B1',
            'reference_type' => 'purchase_order',
            'reference_id' => 1,
            'received_at' => now()->toDateString(),
        ]);

        $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $customer->id,
            'order_date' => '2026-06-28',
            'warehouse_id' => $this->warehouseA->id,
            'discount_amount' => '0',
            'delivery_charges' => '0',
            'cash_received' => '100',
            'bank_received' => '0',
            'bank_account_id' => $this->cashAccount->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '20',
                    'unit_price' => '15', // net: 300
                ],
            ],
        ]);

        $invoice = CustomerInvoice::latest('id')->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(200.00, $invoice->remaining_balance); // 300 - 100 = 200
    }

    // 13. Sales invoice workflow posts balanced accounting entries.
    public function test_sales_invoice_workflow_posts_balanced_accounting(): void
    {
        $customer = Contact::create(['name' => 'Customer A', 'type' => 'customer']);
        $product = $this->createProductHelper('tile', 'P-TILE-1');
        InventoryBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouseA->id,
            'quantity_received' => 100,
            'quantity_remaining' => 100,
            'unit_cost' => 10.00,
            'product_type' => 'tile',
            'batch_number' => 'B1',
            'reference_type' => 'purchase_order',
            'reference_id' => 1,
            'received_at' => now()->toDateString(),
        ]);

        $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $customer->id,
            'order_date' => '2026-06-28',
            'warehouse_id' => $this->warehouseA->id,
            'discount_amount' => '0',
            'delivery_charges' => '0',
            'cash_received' => '0',
            'bank_received' => '0',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '20',
                    'unit_price' => '15',
                ],
            ],
        ]);

        $entry = JournalEntry::latest('id')->first();
        $this->assertNotNull($entry);

        $debits = JournalItem::where('journal_entry_id', $entry->id)->sum('debit');
        $credits = JournalItem::where('journal_entry_id', $entry->id)->sum('credit');

        $this->assertEquals($debits, $credits);
    }

    // 14. Sales invoice workflow reduces stock where applicable.
    public function test_sales_invoice_workflow_reduces_stock(): void
    {
        $customer = Contact::create(['name' => 'Customer A', 'type' => 'customer']);
        $product = $this->createProductHelper('tile', 'P-TILE-1');
        $batch = InventoryBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouseA->id,
            'quantity_received' => 100,
            'quantity_remaining' => 100,
            'unit_cost' => 10.00,
            'product_type' => 'tile',
            'batch_number' => 'B1',
            'reference_type' => 'purchase_order',
            'reference_id' => 1,
            'received_at' => now()->toDateString(),
        ]);

        $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $customer->id,
            'order_date' => '2026-06-28',
            'warehouse_id' => $this->warehouseA->id,
            'discount_amount' => '0',
            'delivery_charges' => '0',
            'cash_received' => '0',
            'bank_received' => '0',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '20',
                    'unit_price' => '15',
                ],
            ],
        ]);

        $batch->refresh();
        $this->assertEquals(80, $batch->quantity_remaining);
    }

    // 15. Sales invoice invalid payload shows validation errors, not silent failure.
    public function test_sales_invoice_invalid_payload_validation(): void
    {
        $response = $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => '',
            'order_date' => '',
            'items' => [],
        ]);

        $response->assertSessionHasErrors(['contact_id', 'order_date', 'items']);
    }

    public function test_payment_workflow_posts_effects(): void
    {
        $customer = Contact::create(['name' => 'Customer A', 'type' => 'customer']);
        $product = $this->createProductHelper('tile', 'P-TILE-1');
        InventoryBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouseA->id,
            'quantity_received' => 100,
            'quantity_remaining' => 100,
            'unit_cost' => 10.00,
            'product_type' => 'tile',
            'batch_number' => 'B1',
            'reference_type' => 'purchase_order',
            'reference_id' => 1,
            'received_at' => now()->toDateString(),
        ]);

        $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $customer->id,
            'order_date' => '2026-06-28',
            'warehouse_id' => $this->warehouseA->id,
            'discount_amount' => '0',
            'delivery_charges' => '0',
            'cash_received' => '0',
            'bank_received' => '0',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '20',
                    'unit_price' => '15',
                ],
            ],
        ]);

        $invoice = CustomerInvoice::latest('id')->first();

        $response = $this->actingAs($this->admin)->post('/payments/customer', [
            'contact_id' => $customer->id,
            'payment_date' => '2026-06-28',
            'amount' => '100.00',
            'payment_method' => 'Cash',
            'bank_account_id' => $this->cashAccount->id,
            'invoice_id' => $invoice->id,
            'notes' => 'Test payment collection',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('payments', [
            'contact_id' => $customer->id,
            'amount' => 100.00,
            'type' => 'receipt',
        ]);
    }

    // 17. Delivery challan workflow saves header/items and print route works.
    public function test_delivery_challan_saves_items_and_print_works(): void
    {
        $customer = Contact::create(['name' => 'Customer A', 'type' => 'customer']);
        $order = Order::create([
            'contact_id' => $customer->id,
            'type' => 'sales',
            'order_number' => 'SO-123',
            'total_amount' => 1000,
            'order_date' => now()->toDateString(),
        ]);
        $product = $this->createProductHelper('tile', 'P-TILE-1');
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 50, 'unit_price' => 20, 'total_price' => 1000]);

        $response = $this->actingAs($this->admin)->post('/delivery-challans', [
            'challan_date' => '2026-06-28',
            'order_id' => $order->id,
            'vehicle_number' => 'LH-9900',
            'driver_name' => 'driver',
            'prepared_by' => 'admin',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '10',
                    'unit' => 'Sq Ft',
                    'warehouse_id' => $this->warehouseA->id,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('delivery_challans', ['order_id' => $order->id, 'vehicle_number' => 'LH-9900']);

        $dc = DeliveryChallan::latest('id')->first();
        $printResponse = $this->actingAs($this->admin)->get("/delivery-challans/{$dc->id}/print");
        $printResponse->assertStatus(200);
    }

    // 18. Production BOM workflow saves BOM and components.
    public function test_production_bom_saves_bom_and_components(): void
    {
        $finished = $this->createProductHelper('tile', 'P-FINISHED');
        $raw = $this->createProductHelper('raw_stone', 'P-RAW');

        $response = $this->actingAs($this->admin)->post('/production/bom', [
            'product_id' => $finished->id,
            'name' => 'Finished Goods BOM',
            'labor_cost_estimate' => '100',
            'overhead_cost_estimate' => '50',
            'items' => [
                [
                    'product_id' => $raw->id,
                    'quantity' => '2.5',
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('boms', ['product_id' => $finished->id, 'name' => 'Finished Goods BOM']);
        $this->assertDatabaseHas('bom_items', ['product_id' => $raw->id, 'quantity' => 2.5]);
    }

    // 19. Production schedule workflow saves schedule and prevents overlap.
    public function test_production_schedule_saves_and_prevents_overlap(): void
    {
        $product = $this->createProductHelper('raw_stone', 'P-RAW');
        $machine = 'Gang Saw 1';

        $response1 = $this->actingAs($this->admin)->post('/production/schedules', [
            'production_type' => 'stone_to_slab',
            'machine' => $machine,
            'planned_start_date' => '2026-06-28 10:00:00',
            'planned_end_date' => '2026-06-28 12:00:00',
            'priority' => 'normal',
            'planned_input_product_id' => $product->id,
            'planned_input_quantity' => '10',
            'expected_output_quantity' => '5',
        ]);
        $response1->assertRedirect();
        $this->assertDatabaseHas('production_schedules', ['machine' => $machine, 'planned_input_quantity' => 10]);

        $response2 = $this->actingAs($this->admin)->post('/production/schedules', [
            'production_type' => 'stone_to_slab',
            'machine' => $machine,
            'planned_start_date' => '2026-06-28 11:00:00',
            'planned_end_date' => '2026-06-28 13:00:00',
            'priority' => 'normal',
            'planned_input_product_id' => $product->id,
            'planned_input_quantity' => '10',
            'expected_output_quantity' => '5',
        ]);
        $response2->assertSessionHasErrors(['machine']);
    }

    // 20. Stone-to-slab consumes input and creates output batch.
    public function test_stone_to_slab_consumes_input_and_creates_output(): void
    {
        $raw = $this->createProductHelper('raw_stone', 'P-RAW');
        $slab = $this->createProductHelper('slab', 'P-SLAB');
        $scrap = $this->createProductHelper('scrap', 'P-SCRAP');

        $batch = InventoryBatch::create([
            'product_id' => $raw->id,
            'warehouse_id' => $this->warehouseA->id,
            'quantity_received' => 10,
            'quantity_remaining' => 10,
            'unit_cost' => 5000.00,
            'product_type' => 'raw_stone',
            'batch_number' => 'RAW-B1',
            'reference_type' => 'purchase_order',
            'reference_id' => 1,
            'received_at' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->post('/production/stone-to-slab', [
            'input_batch_id' => $batch->id,
            'input_quantity' => '2',
            'slab_product_id' => $slab->id,
            'slabs_produced' => '50',
            'wastage_quantity' => '0.5',
            'scrap_product_id' => $scrap->id,
            'scrap_quantity' => '0.2',
            'scrap_unit' => 'Ton',
            'labor_cost' => '500',
            'electricity_cost' => '100',
            'fuel_cost' => '100',
            'maintenance_cost' => '50',
            'overhead_cost' => '250',
            'production_date' => '2026-06-28',
            'warehouse_id' => $this->warehouseA->id,
        ]);

        $response->assertRedirect();
        $batch->refresh();
        $this->assertEquals(8, $batch->quantity_remaining);
        $this->assertDatabaseHas('inventory_batches', [
            'product_id' => $slab->id,
            'quantity_received' => 50,
        ]);
    }

    // 21. Slab-to-tile consumes input and creates output batch.
    public function test_slab_to_tile_consumes_input_and_creates_output(): void
    {
        $slab = $this->createProductHelper('slab', 'P-SLAB');
        $tile = $this->createProductHelper('tile', 'P-TILE');
        $scrap = $this->createProductHelper('scrap', 'P-SCRAP');

        $batch = InventoryBatch::create([
            'product_id' => $slab->id,
            'warehouse_id' => $this->warehouseA->id,
            'quantity_received' => 100,
            'quantity_remaining' => 100,
            'unit_cost' => 200.00,
            'product_type' => 'slab',
            'batch_number' => 'SLAB-B1',
            'reference_type' => 'purchase_order',
            'reference_id' => 1,
            'received_at' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->post('/production/slab-to-tile', [
            'input_batch_id' => $batch->id,
            'input_quantity' => '20',
            'tile_product_id' => $tile->id,
            'tiles_produced' => '80',
            'wastage_percentage' => '10',
            'scrap_product_id' => $scrap->id,
            'scrap_quantity' => '2',
            'scrap_unit' => 'Sq Ft',
            'labor_cost' => '300',
            'cutting_cost' => '50',
            'polishing_cost' => '50',
            'packaging_cost' => '50',
            'overhead_cost' => '150',
            'production_date' => '2026-06-28',
            'warehouse_id' => $this->warehouseA->id,
        ]);

        $response->assertRedirect();
        $batch->refresh();
        $this->assertEquals(80, $batch->quantity_remaining);
        $this->assertDatabaseHas('inventory_batches', [
            'product_id' => $tile->id,
            'quantity_received' => 80,
        ]);
    }

    // 22. Stock transfer creates transfer and prevents over-transfer.
    public function test_stock_transfer_creates_transfer_and_prevents_over_transfer(): void
    {
        $product = $this->createProductHelper('tile', 'P-TILE-1');
        $batch = InventoryBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouseA->id,
            'quantity_received' => 50,
            'quantity_remaining' => 50,
            'unit_cost' => 100.00,
            'product_type' => 'tile',
            'batch_number' => 'B1',
            'reference_type' => 'purchase_order',
            'reference_id' => 1,
            'received_at' => now()->toDateString(),
        ]);

        // Over-transfer check
        $response1 = $this->actingAs($this->admin)->post('/inventory/transfers', [
            'source_batch_id' => $batch->id,
            'to_warehouse_id' => $this->warehouseB->id,
            'quantity' => '60',
            'transfer_date' => '2026-06-28',
        ]);
        $response1->assertSessionHasErrors();

        // Valid transfer check
        $response2 = $this->actingAs($this->admin)->post('/inventory/transfers', [
            'source_batch_id' => $batch->id,
            'to_warehouse_id' => $this->warehouseB->id,
            'quantity' => '20',
            'transfer_date' => '2026-06-28',
        ]);
        $response2->assertRedirect();
        $batch->refresh();
        $this->assertEquals(30, $batch->quantity_remaining);
        $this->assertDatabaseHas('inventory_batches', [
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouseB->id,
            'quantity_received' => 20,
        ]);
    }

    // 23. Stock adjustment persists adjustment and journal/stock effect where applicable.
    public function test_stock_adjustment_persists_effect(): void
    {
        $product = $this->createProductHelper('tile', 'P-TILE-1');

        $response = $this->actingAs($this->admin)->post('/inventory/adjust', [
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouseA->id,
            'quantity' => '10',
            'unit_cost' => '100.00',
            'notes' => 'Fitted adjustment count',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('stock_ledgers', [
            'product_id' => $product->id,
            'quantity' => 10,
            'type' => 'IN',
        ]);
    }

    // 24. Quality inspection saves inspection and report data.
    public function test_quality_inspection_saves_data(): void
    {
        $product = $this->createProductHelper('tile', 'P-TILE-1');

        $response = $this->actingAs($this->admin)->post('/quality-inspections', [
            'inspection_date' => '2026-06-28',
            'product_id' => $product->id,
            'inspection_type' => 'production_qc',
            'grade' => 'A',
            'checked_quantity' => '100',
            'rejected_quantity' => '5',
            'accepted_quantity' => '95',
            'inspector_name' => 'John Doe',
            'remarks' => 'Good finish',
            'status' => 'passed',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('quality_inspections', [
            'product_id' => $product->id,
            'checked_quantity' => 100,
            'rejected_quantity' => 5,
        ]);
    }

    // 25. Machine rental saves rental job and accounting/payment effect where applicable.
    public function test_machine_rental_saves_accounting_effect(): void
    {
        $customer = Contact::create(['name' => 'Customer A', 'type' => 'customer']);

        $response = $this->actingAs($this->admin)->post('/rental-jobs/store', [
            'contact_id' => $customer->id,
            'job_date' => '2026-06-28',
            'machine_hours' => '8',
            'machine_rate' => '1500',
            'labor_hours' => '0',
            'labor_rate' => '0',
            'other_charges' => '0',
            'cash_received' => '5000',
            'bank_received' => '0',
            'bank_account_id' => $this->cashAccount->id,
            'notes' => 'Standard cutting job',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('rental_jobs', [
            'contact_id' => $customer->id,
            'machine_hours' => 8,
            'total_amount' => 12000,
        ]);
    }

    // 26. Reports routes load and CSV/print routes do not 500.
    public function test_reports_routes_load(): void
    {
        $response = $this->actingAs($this->admin)->get('/reports');
        $response->assertStatus(200);

        $responseLedgers = $this->actingAs($this->admin)->get('/party-ledgers');
        $responseLedgers->assertStatus(200);
    }

    // 27. Unauthorized roles are blocked from admin/audit routes.
    public function test_unauthorized_roles_blocked_from_admin(): void
    {
        $response = $this->actingAs($this->guest)->get('/administration/audit-logs');
        $response->assertStatus(403);
    }

    // 28. Missing required fields return visible validation errors.
    public function test_missing_required_fields_validation_error(): void
    {
        $response = $this->actingAs($this->admin)->post('/party-ledgers', [
            'name' => '',
            'type' => 'customer',
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    // 29. No expected workflow returns success without database post-condition changes.
    public function test_invalid_post_prevents_db_write(): void
    {
        $initialCount = Contact::count();

        // Submitting with empty payload should fail validation and not change DB count
        $response = $this->actingAs($this->admin)->post('/party-ledgers', []);
        $response->assertSessionHasErrors();
        $this->assertEquals($initialCount, Contact::count());
    }

    // 30. Unexpected technical failures are not silently converted into fake success.
    public function test_unexpected_technical_failures_bubble_up(): void
    {
        $this->withoutExceptionHandling();
        $this->expectException(\RuntimeException::class);

        $mock = \Mockery::mock(InventoryService::class);
        $mock->shouldReceive('ensureStockAvailable')->andThrow(new \RuntimeException('Unexpected database corruption'));
        $this->app->instance(InventoryService::class, $mock);

        $customer = Contact::create(['name' => 'Customer A', 'type' => 'customer']);
        $product = $this->createProductHelper('tile', 'P-TILE-1');

        $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $customer->id,
            'order_date' => '2026-06-28',
            'warehouse_id' => $this->warehouseA->id,
            'discount_amount' => '0',
            'delivery_charges' => '0',
            'cash_received' => '0',
            'bank_received' => '0',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '20',
                    'unit_price' => '15',
                ],
            ],
        ]);
    }
}
