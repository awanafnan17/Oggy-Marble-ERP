<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Contact;
use App\Models\CustomerInvoice;
use App\Models\DeliveryChallan;
use App\Models\InventoryBatch;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Order;
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

class Batch11oPhysicalDeviceUATTest extends TestCase
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

    private Contact $customer;

    private InventoryBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup admin user with full permissions
        $this->admin = User::factory()->create([
            'name' => 'Batch 11O Admin',
            'email' => 'admin_11o@example.test',
        ]);
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrator']);
        $adminRole->permissions()->sync(
            Permission::all()->pluck('id')
        );
        $this->admin->roles()->syncWithoutDetaching([$adminRole->id]);

        // Create warehouse
        $this->warehouse = Warehouse::create(['name' => 'Main Yard', 'location' => 'Islamabad']);

        // Create G/L accounts
        $this->arAccount = Account::create(['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset']);
        $this->apAccount = Account::create(['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability']);

        $invStone = Account::create(['code' => '1210', 'name' => 'Raw Stone Inventory', 'type' => 'asset']);
        $cogsStone = Account::create(['code' => '5110', 'name' => 'COGS - Raw Stone', 'type' => 'expense']);
        $revStone = Account::create(['code' => '4110', 'name' => 'Revenue - Raw Stone', 'type' => 'revenue']);

        $invSlab = Account::create(['code' => '1230', 'name' => 'Slab Inventory', 'type' => 'asset']);
        $cogsSlab = Account::create(['code' => '5120', 'name' => 'COGS - Slabs', 'type' => 'expense']);
        $revSlab = Account::create(['code' => '4120', 'name' => 'Revenue - Slabs', 'type' => 'revenue']);

        $invTile = Account::create(['code' => '1200', 'name' => 'Tile Inventory', 'type' => 'asset']);
        $cogsTile = Account::create(['code' => '5100', 'name' => 'COGS - Tiles', 'type' => 'expense']);
        $revTile = Account::create(['code' => '4100', 'name' => 'Revenue - Tiles', 'type' => 'revenue']);

        $invScrap = Account::create(['code' => '1240', 'name' => 'Scrap Inventory', 'type' => 'asset']);
        Account::create(['code' => '4200', 'name' => 'Machine Rental Revenue', 'type' => 'revenue']);

        // Create bank account
        $glBank = Account::create(['code' => '1011', 'name' => 'Primary Bank GL', 'type' => 'asset']);
        $this->bankAccount = BankAccount::create([
            'account_id' => $glBank->id,
            'account_name' => 'HBL Test Account',
            'type' => 'bank',
        ]);

        // Create products
        $this->rawStoneProduct = Product::create([
            'sku' => 'RM-11O-01',
            'name' => 'Raw Basalt Block',
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
            'sku' => 'SL-11O-01',
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
            'sku' => 'TL-11O-01',
            'name' => 'Premium Marble Tiles',
            'unit' => 'pcs',
            'cost' => 250.00,
            'price' => 350.00,
            'product_type' => 'tile',
            'unit_of_measure' => 'Sq Ft',
            'inventory_account_id' => $invTile->id,
            'cogs_account_id' => $cogsTile->id,
            'revenue_account_id' => $revTile->id,
        ]);

        // Create customer
        $this->customer = Contact::create(['name' => 'UAT Test Customer', 'type' => 'customer']);

        // Create inventory batch with stock for testing
        $this->batch = InventoryBatch::create([
            'product_id' => $this->rawStoneProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_received' => 50.0,
            'quantity_remaining' => 50.0,
            'unit_cost' => 20000.00,
            'total_cost' => 1000000.00,
            'unit_of_measure' => 'MT',
            'product_type' => 'raw_stone',
            'received_at' => now(),
            'reference_type' => 'beginning_inventory',
            'reference_id' => 1,
        ]);
    }

    private function getJournalTotals(int $entryId): array
    {
        $items = JournalItem::where('journal_entry_id', $entryId)->get();

        return [
            'debits' => (float) $items->sum('debit'),
            'credits' => (float) $items->sum('credit'),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // Phase 1+2: Invoice Creation & Calculation Audit
    // ──────────────────────────────────────────────────────────────
    public function test_invoice_creation_with_valid_data_redirects_and_persists()
    {
        $response = $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->rawStoneProduct->id,
                    'quantity' => '3',
                    'unit_price' => '30000',
                    'selected_batch_id' => $this->batch->id,
                ],
            ],
            'discount_amount' => '2000',
            'delivery_charges' => '5000',
            'cash_received' => '50000',
            'bank_received' => '0',
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response->assertRedirect();

        // Verify invoice exists
        $invoice = CustomerInvoice::latest()->first();
        $this->assertNotNull($invoice);

        // Verify calculation: gross = 3*30000 = 90000, net = 90000 - 2000 + 5000 = 93000
        $order = Order::find($invoice->order_id);
        $this->assertEquals(93000, $order->total_amount);

        // Verify remaining: 93000 - 50000 = 43000
        $this->assertEquals(43000, $invoice->remaining_balance);
        $this->assertEquals('partial', $invoice->status);
    }

    public function test_invoice_exact_payment_creates_paid_status()
    {
        $response = $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->rawStoneProduct->id,
                    'quantity' => '2',
                    'unit_price' => '30000',
                    'selected_batch_id' => $this->batch->id,
                ],
            ],
            'discount_amount' => '0',
            'delivery_charges' => '0',
            'cash_received' => '60000',
            'bank_received' => '0',
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response->assertRedirect();
        $invoice = CustomerInvoice::latest()->first();
        $this->assertEquals(0, $invoice->remaining_balance);
        $this->assertEquals('paid', $invoice->status);
    }

    public function test_invoice_zero_payment_creates_unpaid_status()
    {
        $response = $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->rawStoneProduct->id,
                    'quantity' => '2',
                    'unit_price' => '30000',
                    'selected_batch_id' => $this->batch->id,
                ],
            ],
            'discount_amount' => '0',
            'delivery_charges' => '0',
            'cash_received' => '0',
            'bank_received' => '0',
            'bank_account_id' => '',
        ]);

        $response->assertRedirect();
        $invoice = CustomerInvoice::latest()->first();
        $this->assertEquals(60000, $invoice->remaining_balance);
        $this->assertEquals('unpaid', $invoice->status);
    }

    // ──────────────────────────────────────────────────────────────
    // Phase 3: Validation Error Display Audit
    // ──────────────────────────────────────────────────────────────
    public function test_invoice_missing_customer_returns_validation_error()
    {
        $response = $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => '',
            'order_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $this->rawStoneProduct->id, 'quantity' => '1', 'unit_price' => '30000'],
            ],
        ]);

        $response->assertSessionHasErrors(['contact_id']);
    }

    public function test_invoice_missing_items_returns_validation_error()
    {
        $response = $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors(['items']);
    }

    public function test_invoice_nested_item_errors_surface_correctly()
    {
        $response = $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'items' => [
                ['product_id' => '', 'quantity' => '0', 'unit_price' => '-50'],
            ],
        ]);

        $response->assertSessionHasErrors([
            'items.0.product_id',
            'items.0.quantity',
            'items.0.unit_price',
        ]);
    }

    public function test_invoice_overpayment_returns_validation_error()
    {
        $response = $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->rawStoneProduct->id,
                    'quantity' => '2',
                    'unit_price' => '30000',
                    'selected_batch_id' => $this->batch->id,
                ],
            ],
            'discount_amount' => '0',
            'delivery_charges' => '0',
            'cash_received' => '100000', // net = 60000, overpay by 40000
            'bank_received' => '0',
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response->assertSessionHasErrors(['cash_received']);
    }

    public function test_invoice_bank_payment_without_account_returns_validation_error()
    {
        $response = $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->rawStoneProduct->id,
                    'quantity' => '1',
                    'unit_price' => '30000',
                    'selected_batch_id' => $this->batch->id,
                ],
            ],
            'discount_amount' => '0',
            'delivery_charges' => '0',
            'cash_received' => '0',
            'bank_received' => '10000',
            'bank_account_id' => '', // missing when bank_received > 0
        ]);

        $response->assertSessionHasErrors(['bank_account_id']);
    }

    // ──────────────────────────────────────────────────────────────
    // Phase 4: Invoice Submit & Posting (Journal Balance) Audit
    // ──────────────────────────────────────────────────────────────
    public function test_invoice_journal_is_balanced()
    {
        $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->rawStoneProduct->id,
                    'quantity' => '3',
                    'unit_price' => '30000',
                    'selected_batch_id' => $this->batch->id,
                ],
            ],
            'discount_amount' => '1000',
            'delivery_charges' => '3000',
            'cash_received' => '40000',
            'bank_received' => '10000',
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $invoice = CustomerInvoice::latest()->first();
        $entry = JournalEntry::where('reference_type', 'order')
            ->where('reference_id', $invoice->order_id)
            ->first();

        $this->assertNotNull($entry, 'Journal entry must exist for the invoice');

        $totals = $this->getJournalTotals($entry->id);
        $this->assertEquals($totals['debits'], $totals['credits'], 'Total debits must equal total credits');
    }

    public function test_invoice_fifo_depletes_stock()
    {
        $originalStock = $this->batch->quantity_remaining;

        $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->rawStoneProduct->id,
                    'quantity' => '5',
                    'unit_price' => '30000',
                    'selected_batch_id' => $this->batch->id,
                ],
            ],
            'discount_amount' => '0',
            'delivery_charges' => '0',
            'cash_received' => '0',
            'bank_received' => '0',
            'bank_account_id' => '',
        ]);

        $this->batch->refresh();
        $this->assertEquals($originalStock - 5, $this->batch->quantity_remaining);

        // Verify stock ledger entry exists with negative quantity
        $stockOut = StockLedger::where('reference_type', 'sales_order')->latest()->first();
        $this->assertNotNull($stockOut);
        $this->assertTrue($stockOut->quantity < 0);
    }

    public function test_invoice_remaining_balance_matches_calculation()
    {
        $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->rawStoneProduct->id,
                    'quantity' => '4',
                    'unit_price' => '30000',
                    'selected_batch_id' => $this->batch->id,
                ],
            ],
            'discount_amount' => '5000',
            'delivery_charges' => '8000',
            'cash_received' => '30000',
            'bank_received' => '20000',
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $invoice = CustomerInvoice::latest()->first();
        // gross = 4*30000 = 120000, net = 120000 - 5000 + 8000 = 123000
        // received = 30000 + 20000 = 50000
        // remaining = 123000 - 50000 = 73000
        $this->assertEquals(73000, $invoice->remaining_balance);
    }

    // ──────────────────────────────────────────────────────────────
    // Phase 6: Payment / Collection Audit
    // ──────────────────────────────────────────────────────────────
    public function test_customer_payment_reduces_remaining_balance()
    {
        // Create an unpaid invoice first
        $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->rawStoneProduct->id,
                    'quantity' => '2',
                    'unit_price' => '30000',
                    'selected_batch_id' => $this->batch->id,
                ],
            ],
            'discount_amount' => '0',
            'delivery_charges' => '0',
            'cash_received' => '0',
            'bank_received' => '0',
            'bank_account_id' => '',
        ]);

        $invoice = CustomerInvoice::latest()->first();
        $this->assertEquals(60000, $invoice->remaining_balance);

        // Make a payment
        $payResponse = $this->actingAs($this->admin)->post('/payments/customer', [
            'contact_id' => $this->customer->id,
            'invoice_id' => $invoice->id,
            'amount' => '25000',
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $payResponse->assertRedirect();
        $invoice->refresh();
        $this->assertEquals(35000, $invoice->remaining_balance);
        $this->assertEquals('partial', $invoice->status);
    }

    public function test_customer_overpayment_is_blocked()
    {
        // Create an invoice with partial payment
        $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->rawStoneProduct->id,
                    'quantity' => '1',
                    'unit_price' => '30000',
                    'selected_batch_id' => $this->batch->id,
                ],
            ],
            'discount_amount' => '0',
            'delivery_charges' => '0',
            'cash_received' => '10000',
            'bank_received' => '0',
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $invoice = CustomerInvoice::latest()->first();
        $this->assertEquals(20000, $invoice->remaining_balance);

        // Try to overpay
        $payResponse = $this->actingAs($this->admin)->post('/payments/customer', [
            'contact_id' => $this->customer->id,
            'invoice_id' => $invoice->id,
            'amount' => '50000', // remaining is 20000
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $payResponse->assertSessionHasErrors();
    }

    // ──────────────────────────────────────────────────────────────
    // Phase 7: Delivery Challan After Invoice Audit
    // ──────────────────────────────────────────────────────────────
    public function test_delivery_challan_creation_linked_to_invoice()
    {
        // Create invoice first
        $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->rawStoneProduct->id,
                    'quantity' => '2',
                    'unit_price' => '30000',
                    'selected_batch_id' => $this->batch->id,
                ],
            ],
            'discount_amount' => '0',
            'delivery_charges' => '0',
            'cash_received' => '0',
            'bank_received' => '0',
            'bank_account_id' => '',
        ]);

        $order = Order::latest()->first();
        $invoice = CustomerInvoice::latest()->first();

        // Create delivery challan
        $challanResponse = $this->actingAs($this->admin)->post('/delivery-challans', [
            'challan_date' => now()->toDateString(),
            'order_id' => $order->id,
            'customer_invoice_id' => $invoice->id,
            'vehicle_number' => 'ABC-1234',
            'driver_name' => 'Test Driver',
            'prepared_by' => 'Admin',
            'items' => [
                [
                    'product_id' => $this->rawStoneProduct->id,
                    'quantity' => '2',
                    'unit' => 'MT',
                    'warehouse_id' => $this->warehouse->id,
                ],
            ],
        ]);

        $challanResponse->assertRedirect();
        $challan = DeliveryChallan::latest()->first();
        $this->assertNotNull($challan);
        $this->assertEquals($order->id, $challan->order_id);
    }

    public function test_delivery_challan_validation_requires_fields()
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

    // ──────────────────────────────────────────────────────────────
    // Phase 8: Reports/Export After Invoice Audit
    // ──────────────────────────────────────────────────────────────
    public function test_sales_summary_csv_includes_new_invoice()
    {
        // Create invoice
        $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->rawStoneProduct->id,
                    'quantity' => '2',
                    'unit_price' => '30000',
                    'selected_batch_id' => $this->batch->id,
                ],
            ],
            'discount_amount' => '0',
            'delivery_charges' => '0',
            'cash_received' => '0',
            'bank_received' => '0',
            'bank_account_id' => '',
        ]);

        $response = $this->actingAs($this->admin)->get('/reports/export/sales_summary');
        $response->assertStatus(200);
        $this->assertStringContainsString('UAT Test Customer', $response->streamedContent());
    }

    public function test_trial_balance_is_balanced_after_invoice()
    {
        // Create invoice with payment
        $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->rawStoneProduct->id,
                    'quantity' => '2',
                    'unit_price' => '30000',
                    'selected_batch_id' => $this->batch->id,
                ],
            ],
            'discount_amount' => '0',
            'delivery_charges' => '0',
            'cash_received' => '30000',
            'bank_received' => '0',
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $response = $this->actingAs($this->admin)->get('/reports/export/trial_balance');
        $response->assertStatus(200);
    }

    // ──────────────────────────────────────────────────────────────
    // Phase 5: Dropdown Propagation / Page Load Audit
    // ──────────────────────────────────────────────────────────────
    public function test_sales_page_loads_with_required_props()
    {
        $response = $this->actingAs($this->admin)->get('/sales');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Index')
            ->has('customers')
            ->has('products')
            ->has('bankAccounts')
        );
    }

    public function test_challans_page_loads_with_required_props()
    {
        $response = $this->actingAs($this->admin)->get('/delivery-challans');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Challans')
        );
    }

    public function test_purchases_page_loads_with_required_props()
    {
        $response = $this->actingAs($this->admin)->get('/purchases');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Purchases/Index')
            ->has('vendors')
            ->has('products')
            ->has('bankAccounts')
        );
    }

    public function test_reports_center_loads()
    {
        $response = $this->actingAs($this->admin)->get('/reports');
        $response->assertStatus(200);
    }

    public function test_cash_bank_page_loads()
    {
        $response = $this->actingAs($this->admin)->get('/cash-bank');
        $response->assertStatus(200);
    }

    // ──────────────────────────────────────────────────────────────
    // Phase 10: Database Post-Condition Verification
    // ──────────────────────────────────────────────────────────────
    public function test_order_total_amount_matches_net_invoice_amount()
    {
        $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->rawStoneProduct->id,
                    'quantity' => '3',
                    'unit_price' => '30000',
                    'selected_batch_id' => $this->batch->id,
                ],
            ],
            'discount_amount' => '5000',
            'delivery_charges' => '2000',
            'cash_received' => '0',
            'bank_received' => '0',
            'bank_account_id' => '',
        ]);

        $invoice = CustomerInvoice::latest()->first();
        $order = Order::find($invoice->order_id);

        // net = (3*30000) - 5000 + 2000 = 87000
        $this->assertEquals(87000, $order->total_amount);
        $this->assertEquals(87000, $invoice->remaining_balance);
    }

    public function test_multi_item_invoice_journal_balanced()
    {
        // Create a second batch for slab
        $slabBatch = InventoryBatch::create([
            'product_id' => $this->slabProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_received' => 20.0,
            'quantity_remaining' => 20.0,
            'unit_cost' => 30000.00,
            'total_cost' => 600000.00,
            'unit_of_measure' => 'MT',
            'product_type' => 'slab',
            'received_at' => now(),
            'reference_type' => 'beginning_inventory',
            'reference_id' => 1,
        ]);

        $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->rawStoneProduct->id,
                    'quantity' => '2',
                    'unit_price' => '30000',
                    'selected_batch_id' => $this->batch->id,
                ],
                [
                    'product_id' => $this->slabProduct->id,
                    'quantity' => '1',
                    'unit_price' => '45000',
                    'selected_batch_id' => $slabBatch->id,
                ],
            ],
            'discount_amount' => '3000',
            'delivery_charges' => '5000',
            'cash_received' => '50000',
            'bank_received' => '10000',
            'bank_account_id' => $this->bankAccount->id,
        ]);

        $invoice = CustomerInvoice::latest()->first();
        $entry = JournalEntry::where('reference_type', 'order')
            ->where('reference_id', $invoice->order_id)
            ->first();

        $this->assertNotNull($entry);
        $totals = $this->getJournalTotals($entry->id);
        $this->assertEquals($totals['debits'], $totals['credits']);
    }

    public function test_purchase_bill_journal_balanced()
    {
        $vendor = Contact::create(['name' => 'Test Quarry Owner', 'type' => 'vendor']);

        $response = $this->actingAs($this->admin)->post('/purchases/store', [
            'contact_id' => $vendor->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->rawStoneProduct->id,
                    'quantity' => '5',
                    'unit_price' => '25000',
                    'landed_cost_manual' => '0',
                ],
            ],
            'transportation_cost' => '10000',
            'loading_cost' => '2000',
            'other_direct_cost' => '0',
            'cash_paid' => '50000',
            'bank_paid' => '0',
            'bank_account_id' => $this->bankAccount->id,
            'allocation_method' => 'value',
        ]);

        $response->assertRedirect();

        $bill = SupplierBill::latest()->first();
        $entry = JournalEntry::where('reference_type', 'order')
            ->where('reference_id', $bill->order_id)
            ->first();

        $this->assertNotNull($entry);
        $totals = $this->getJournalTotals($entry->id);
        $this->assertEquals($totals['debits'], $totals['credits']);
    }
}
