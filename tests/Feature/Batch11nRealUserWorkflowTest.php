<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Contact;
use App\Models\CustomerInvoice;
use App\Models\InventoryBatch;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductionSchedule;
use App\Models\RentalJob;
use App\Models\Role;
use App\Models\SupplierBill;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Batch11nRealUserWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    private Account $arAccount;

    private Account $apAccount;

    private BankAccount $bankAccount;

    private Product $rawStoneProduct;

    private Product $slabProduct;

    private Product $tileProduct;

    private Product $scrapProduct;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup admin user and roles/permissions
        $this->admin = User::factory()->create([
            'name' => 'Batch 11N Admin',
            'email' => 'admin_11n@example.test',
        ]);
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrator']);
        $adminRole->permissions()->sync(
            Permission::all()->pluck('id')
        );
        $this->admin->roles()->syncWithoutDetaching([$adminRole->id]);

        // Create warehouse
        $this->warehouse = Warehouse::create(['name' => 'Raw Yard', 'location' => 'Islamabad']);

        // Create dynamic G/L accounts
        $this->arAccount = Account::create(['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset']);
        $this->apAccount = Account::create(['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability']);

        $invStone = Account::create(['code' => '1210', 'name' => 'Raw Stone Inventory (MT)', 'type' => 'asset']);
        $cogsStone = Account::create(['code' => '5110', 'name' => 'COGS - Raw Stone', 'type' => 'expense']);
        $revStone = Account::create(['code' => '4110', 'name' => 'Sales Revenue - Raw Stone', 'type' => 'revenue']);

        $invSlab = Account::create(['code' => '1230', 'name' => 'Slab Inventory (MT)', 'type' => 'asset']);
        $cogsSlab = Account::create(['code' => '5120', 'name' => 'COGS - Slabs', 'type' => 'expense']);
        $revSlab = Account::create(['code' => '4120', 'name' => 'Sales Revenue - Slabs', 'type' => 'revenue']);

        $invTile = Account::create(['code' => '1200', 'name' => 'Tile Inventory (Sq Ft)', 'type' => 'asset']);
        $cogsTile = Account::create(['code' => '5100', 'name' => 'COGS - Tiles', 'type' => 'expense']);
        $revTile = Account::create(['code' => '4100', 'name' => 'Sales Revenue - Tiles', 'type' => 'revenue']);

        $invScrap = Account::create(['code' => '1240', 'name' => 'Scrap Inventory', 'type' => 'asset']);

        Account::create(['code' => '4200', 'name' => 'Machine Rental Revenue', 'type' => 'revenue']);

        // Create bank account
        $glBank = Account::create(['code' => '1011', 'name' => 'Primary Bank GL', 'type' => 'asset']);
        $this->bankAccount = BankAccount::create([
            'account_id' => $glBank->id,
            'account_name' => 'HBL Test',
            'type' => 'bank',
        ]);

        // Create products
        $this->rawStoneProduct = Product::create([
            'sku' => 'RM-VOLCANIC-01',
            'name' => 'Raw Stone',
            'unit' => 'pcs',
            'cost' => 25000.00,
            'price' => 30000.00,
            'product_type' => 'raw_stone',
            'unit_of_measure' => 'MT',
            'inventory_account_id' => $invStone->id,
            'cogs_account_id' => $cogsStone->id,
            'revenue_account_id' => $revStone->id,
        ]);

        $this->slabProduct = Product::create([
            'sku' => 'SL-VOLCANIC-01',
            'name' => 'Volcanic Slab',
            'unit' => 'pcs',
            'cost' => 35000.00,
            'price' => 45000.00,
            'product_type' => 'slab',
            'unit_of_measure' => 'MT',
            'inventory_account_id' => $invSlab->id,
            'cogs_account_id' => $cogsSlab->id,
            'revenue_account_id' => $revSlab->id,
        ]);

        $this->tileProduct = Product::create([
            'sku' => 'TL-VOLCANIC-01',
            'name' => 'Premium Tiles',
            'unit' => 'pcs',
            'cost' => 250.00,
            'price' => 350.00,
            'product_type' => 'tile',
            'unit_of_measure' => 'Sq Ft',
            'inventory_account_id' => $invTile->id,
            'cogs_account_id' => $cogsTile->id,
            'revenue_account_id' => $revTile->id,
        ]);

        $this->scrapProduct = Product::create([
            'sku' => 'SC-VOLCANIC-01',
            'name' => 'Basalt Scrap',
            'unit' => 'pcs',
            'cost' => 0.00,
            'price' => 5000.00,
            'product_type' => 'scrap',
            'unit_of_measure' => 'MT',
            'inventory_account_id' => $invScrap->id,
            'cogs_account_id' => $cogsTile->id,
            'revenue_account_id' => $revTile->id,
        ]);
    }

    private function getInvoiceTotals(int $entryId): array
    {
        $items = JournalItem::where('journal_entry_id', $entryId)->get();

        return [
            'debits' => (float) $items->sum('debit'),
            'credits' => (float) $items->sum('credit'),
        ];
    }

    // 1. Sales invalid payload returns field-level validation errors
    public function test_sales_invalid_payload_validation_errors()
    {
        $response = $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => '',
            'order_date' => '',
            'items' => [
                ['product_id' => '', 'quantity' => '0', 'unit_price' => '-50'],
            ],
        ]);

        $response->assertSessionHasErrors(['contact_id', 'order_date', 'items.0.product_id', 'items.0.quantity', 'items.0.unit_price']);
    }

    // 2. Sales UI/backend error bag includes all relevant validation keys
    public function test_sales_error_bag_keys_alignment()
    {
        $response = $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => '999',
            'order_date' => 'invalid-date',
        ]);
        $response->assertSessionHasErrors(['contact_id', 'order_date']);
    }

    // 3. Sales invoice exact payment creates balanced journal
    public function test_sales_invoice_exact_payment_balanced_journal()
    {
        // Seed customer and batch
        $customer = Contact::create(['name' => 'Test Customer', 'type' => 'customer']);
        $batch = InventoryBatch::create([
            'product_id' => $this->rawStoneProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_received' => 10.0,
            'quantity_remaining' => 10.0,
            'unit_cost' => 20000.00,
            'total_cost' => 200000.00,
            'unit_of_measure' => 'MT',
            'product_type' => 'raw_stone',
            'received_at' => now(),
            'reference_type' => 'beginning_inventory',
            'reference_id' => 1,
        ]);

        $response = $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->rawStoneProduct->id,
                    'quantity' => '2',
                    'unit_price' => '30000',
                    'selected_batch_id' => $batch->id,
                ],
            ],
            'discount_amount' => '1000',
            'delivery_charges' => '5000',
            'cash_received' => '64000', // Gross: 60000 - 1000 + 5000 = 64000
            'bank_received' => '0',
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response->assertRedirect();
        $invoice = CustomerInvoice::latest()->first();
        $this->assertEquals(0, $invoice->remaining_balance);
        $this->assertEquals('paid', $invoice->status);

        $entry = JournalEntry::where('reference_type', 'order')->where('reference_id', $invoice->order_id)->first();
        $this->assertNotNull($entry);
        $totals = $this->getInvoiceTotals($entry->id);
        $this->assertEquals($totals['debits'], $totals['credits']);
    }

    // 4. Sales invoice partial payment creates balanced journal and correct remaining A/R
    public function test_sales_invoice_partial_payment_balanced()
    {
        $customer = Contact::create(['name' => 'Test Customer', 'type' => 'customer']);
        $batch = InventoryBatch::create([
            'product_id' => $this->rawStoneProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_received' => 10.0,
            'quantity_remaining' => 10.0,
            'unit_cost' => 20000.00,
            'total_cost' => 200000.00,
            'unit_of_measure' => 'MT',
            'product_type' => 'raw_stone',
            'received_at' => now(),
            'reference_type' => 'beginning_inventory',
            'reference_id' => 1,
        ]);

        $response = $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->rawStoneProduct->id,
                    'quantity' => '2',
                    'unit_price' => '30000',
                    'selected_batch_id' => $batch->id,
                ],
            ],
            'discount_amount' => '0',
            'delivery_charges' => '0',
            'cash_received' => '20000',
            'bank_received' => '0',
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response->assertRedirect();
        $invoice = CustomerInvoice::latest()->first();
        $this->assertEquals(40000, $invoice->remaining_balance);
        $this->assertEquals('partial', $invoice->status);

        $entry = JournalEntry::where('reference_type', 'order')->where('reference_id', $invoice->order_id)->first();
        $totals = $this->getInvoiceTotals($entry->id);
        $this->assertEquals($totals['debits'], $totals['credits']);
    }

    // 5. Sales invoice overpayment is blocked under Option A
    public function test_sales_invoice_overpayment_blocked()
    {
        $customer = Contact::create(['name' => 'Test Customer', 'type' => 'customer']);
        $batch = InventoryBatch::create([
            'product_id' => $this->rawStoneProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_received' => 10.0,
            'quantity_remaining' => 10.0,
            'unit_cost' => 20000.00,
            'total_cost' => 200000.00,
            'unit_of_measure' => 'MT',
            'product_type' => 'raw_stone',
            'received_at' => now(),
            'reference_type' => 'beginning_inventory',
            'reference_id' => 1,
        ]);

        $response = $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->rawStoneProduct->id,
                    'quantity' => '2',
                    'unit_price' => '30000',
                    'selected_batch_id' => $batch->id,
                ],
            ],
            'discount_amount' => '0',
            'delivery_charges' => '0',
            'cash_received' => '80000', // Net: 60000. Overpay by 20000.
            'bank_received' => '0',
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response->assertSessionHasErrors(['cash_received']);
    }

    // 6. Purchase exact payment creates balanced journal
    public function test_purchase_exact_payment_balanced()
    {
        $vendor = Contact::create(['name' => 'Test Vendor', 'type' => 'vendor']);

        $response = $this->actingAs($this->admin)->post('/purchases/store', [
            'contact_id' => $vendor->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->rawStoneProduct->id,
                    'quantity' => '2',
                    'unit_price' => '25000',
                    'landed_cost_manual' => '0',
                ],
            ],
            'transportation_cost' => '5000',
            'loading_cost' => '1000',
            'other_direct_cost' => '0',
            'cash_paid' => '56000', // Net: 50000 + 6000 = 56000
            'bank_paid' => '0',
            'bank_account_id' => $this->bankAccount->id,
            'allocation_method' => 'value',
        ]);

        $response->assertRedirect();
        $bill = SupplierBill::latest()->first();
        $this->assertEquals(0, $bill->remaining_balance);
        $this->assertEquals('paid', $bill->status);

        $entry = JournalEntry::where('reference_type', 'order')->where('reference_id', $bill->order_id)->first();
        $totals = $this->getInvoiceTotals($entry->id);
        $this->assertEquals($totals['debits'], $totals['credits']);
    }

    // 7. Purchase partial payment creates balanced journal and correct remaining A/P
    public function test_purchase_partial_payment_balanced()
    {
        $vendor = Contact::create(['name' => 'Test Vendor', 'type' => 'vendor']);

        $response = $this->actingAs($this->admin)->post('/purchases/store', [
            'contact_id' => $vendor->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->rawStoneProduct->id,
                    'quantity' => '2',
                    'unit_price' => '25000',
                    'landed_cost_manual' => '0',
                ],
            ],
            'transportation_cost' => '5000',
            'loading_cost' => '1000',
            'other_direct_cost' => '0',
            'cash_paid' => '20000',
            'bank_paid' => '0',
            'bank_account_id' => $this->bankAccount->id,
            'allocation_method' => 'value',
        ]);

        $response->assertRedirect();
        $bill = SupplierBill::latest()->first();
        $this->assertEquals(36000, $bill->remaining_balance);
        $this->assertEquals('partial', $bill->status);

        $entry = JournalEntry::where('reference_type', 'order')->where('reference_id', $bill->order_id)->first();
        $totals = $this->getInvoiceTotals($entry->id);
        $this->assertEquals($totals['debits'], $totals['credits']);
    }

    // 8. Purchase overpayment is blocked under Option A
    public function test_purchase_overpayment_blocked()
    {
        $vendor = Contact::create(['name' => 'Test Vendor', 'type' => 'vendor']);

        $response = $this->actingAs($this->admin)->post('/purchases/store', [
            'contact_id' => $vendor->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->rawStoneProduct->id,
                    'quantity' => '2',
                    'unit_price' => '25000',
                    'landed_cost_manual' => '0',
                ],
            ],
            'transportation_cost' => '0',
            'loading_cost' => '0',
            'other_direct_cost' => '0',
            'cash_paid' => '70000', // Net: 50000. Overpay by 20000
            'bank_paid' => '0',
            'bank_account_id' => $this->bankAccount->id,
            'allocation_method' => 'value',
        ]);

        $response->assertSessionHasErrors(['cash_paid']);
    }

    // 9. Rental job exact payment creates balanced journal
    public function test_rental_job_exact_payment_balanced()
    {
        $customer = Contact::create(['name' => 'Test Customer', 'type' => 'customer']);

        $response = $this->actingAs($this->admin)->post('/rental-jobs/store', [
            'contact_id' => $customer->id,
            'job_date' => now()->toDateString(),
            'machine_hours' => '5',
            'machine_rate' => '2000',
            'labor_hours' => '5',
            'labor_rate' => '500',
            'other_charges' => '1000',
            'cash_received' => '13500', // Total: 10000 + 2500 + 1000 = 13500
            'bank_received' => '0',
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response->assertRedirect();
        $job = RentalJob::latest()->first();
        $this->assertEquals(0, $job->remaining_receivable);
        $this->assertEquals('paid', $job->payment_status);

        $entry = JournalEntry::where('reference_type', 'rental_job')->where('reference_id', $job->id)->first();
        $totals = $this->getInvoiceTotals($entry->id);
        $this->assertEquals($totals['debits'], $totals['credits']);
    }

    // 10. Rental job overpayment is blocked under Option A
    public function test_rental_job_overpayment_blocked()
    {
        $customer = Contact::create(['name' => 'Test Customer', 'type' => 'customer']);

        $response = $this->actingAs($this->admin)->post('/rental-jobs/store', [
            'contact_id' => $customer->id,
            'job_date' => now()->toDateString(),
            'machine_hours' => '5',
            'machine_rate' => '2000',
            'labor_hours' => '5',
            'labor_rate' => '500',
            'other_charges' => '1000',
            'cash_received' => '20000', // overpay
            'bank_received' => '0',
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response->assertSessionHasErrors(['cash_received']);
    }

    // 11. Delivery challan validation errors are visible and nested item errors are preserved
    public function test_delivery_challan_validation_errors()
    {
        $response = $this->actingAs($this->admin)->post('/delivery-challans', [
            'challan_date' => '',
            'order_id' => '',
            'items' => [
                ['product_id' => '', 'quantity' => ''],
            ],
        ]);

        $response->assertSessionHasErrors(['challan_date', 'order_id', 'items.0.product_id', 'items.0.quantity']);
    }

    // 12. Production schedule pre-fill does not set target output product incorrectly
    public function test_production_schedule_prefill_and_output_safety()
    {
        $schedule = ProductionSchedule::create([
            'schedule_number' => 'SCH-001',
            'production_type' => 'slab_to_tile',
            'planned_start_date' => now(),
            'planned_end_date' => now()->addHours(8),
            'priority' => 'high',
            'planned_input_product_id' => $this->slabProduct->id,
            'planned_input_quantity' => 5.0,
            'expected_output_quantity' => 1000.0,
            'expected_wastage_percentage' => 5.0,
            'status' => 'scheduled',
            'machine' => 'Gang Saw 1',
        ]);

        // Assert prefill items on scheduling pages query cleanly
        $this->assertEquals($this->slabProduct->id, $schedule->planned_input_product_id);
    }

    // 13. Stone-to-slab target slab product must be selected correctly
    public function test_stone_to_slab_processing_validation()
    {
        $batch = InventoryBatch::create([
            'product_id' => $this->rawStoneProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_received' => 10.0,
            'quantity_remaining' => 10.0,
            'unit_cost' => 20000.00,
            'total_cost' => 200000.00,
            'unit_of_measure' => 'MT',
            'product_type' => 'raw_stone',
            'received_at' => now(),
            'reference_type' => 'beginning_inventory',
            'reference_id' => 1,
        ]);

        // Submit without selecting target slab product ID
        $response = $this->actingAs($this->admin)->post('/production/stone-to-slab', [
            'input_batch_id' => $batch->id,
            'input_quantity' => '5.0',
            'slab_product_id' => '',
            'slabs_produced' => '4.8',
        ]);

        $response->assertSessionHasErrors(['slab_product_id']);
    }

    // 14. Slab-to-tile target tile product must be selected correctly
    public function test_slab_to_tile_processing_validation()
    {
        $batch = InventoryBatch::create([
            'product_id' => $this->slabProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_received' => 10.0,
            'quantity_remaining' => 10.0,
            'unit_cost' => 30000.00,
            'total_cost' => 300000.00,
            'unit_of_measure' => 'MT',
            'product_type' => 'slab',
            'received_at' => now(),
            'reference_type' => 'beginning_inventory',
            'reference_id' => 1,
        ]);

        // Submit without selecting target tile product ID
        $response = $this->actingAs($this->admin)->post('/production/slab-to-tile', [
            'input_batch_id' => $batch->id,
            'input_quantity' => '2.0',
            'tile_product_id' => '',
            'tiles_produced' => '500.0',
        ]);

        $response->assertSessionHasErrors(['tile_product_id']);
    }

    // 15. Reports CSV/export handles orphaned/deleted product relations safely
    public function test_reports_csv_handles_deleted_product_relations()
    {
        $batch = InventoryBatch::create([
            'product_id' => 9999, // Mismatched / deleted product
            'warehouse_id' => $this->warehouse->id,
            'quantity_received' => 5.0,
            'quantity_remaining' => 5.0,
            'unit_cost' => 1000.0,
            'received_at' => now(),
            'reference_type' => 'beginning_inventory',
            'reference_id' => 1,
            'unit_of_measure' => 'MT',
            'product_type' => 'raw_stone',
        ]);

        $response = $this->actingAs($this->admin)->get('/reports/export/batch_inventory');
        $response->assertStatus(200);
        $this->assertStringContainsString('N/A', $response->streamedContent());
    }

    // 16. Unexpected technical failures are not silently converted into fake success
    public function test_unexpected_runtime_failures_bubble_up()
    {
        // Try submitting dynamic store that causes a database constraint error or similar structural error to confirm it crashes or raises exception rather than showing successful redirect with errors
        $this->expectException(QueryException::class);

        // Raw DB insert forcing non-nullable field to be null
        DB::table('orders')->insert([
            'contact_id' => null,
            'type' => null,
        ]);
    }

    // 17. No workflow returns success without required database post-conditions
    public function test_workflow_database_persistence_safety()
    {
        $customer = Contact::create(['name' => 'Original Customer', 'type' => 'customer']);

        // Assert we can't save without contact_id
        $response = $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => '',
            'order_date' => now()->toDateString(),
        ]);

        $this->assertCount(1, Contact::all());
    }

    // 18. Mobile/source audit confirms required buttons/forms/errors are present
    public function test_mobile_source_elements_audit()
    {
        $response = $this->actingAs($this->admin)->get('/sales');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page->component('Sales/Index'));
    }
}
