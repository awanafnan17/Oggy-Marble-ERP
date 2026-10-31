<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankTransaction;
use App\Models\Bom;
use App\Models\BomItem;
use App\Models\Contact;
use App\Models\CustomerInvoice;
use App\Models\DeliveryChallan;
use App\Models\InventoryAdjustment;
use App\Models\InventoryBatch;
use App\Models\InventoryTransfer;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Machine;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionSchedule;
use App\Models\QualityInspection;
use App\Models\Role;
use App\Models\SlabToTileProduction;
use App\Models\StockLedger;
use App\Models\StoneToSlabProduction;
use App\Models\SupplierBill;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AccountService;
use App\Services\BankReconciliationService;
use App\Services\CashBankService;
use App\Services\DashboardService;
use App\Services\InventoryService;
use App\Services\ProductionVarianceService;
use App\Services\ReconciliationService;
use App\Services\SubledgerService;
use App\Services\TransferService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ErpCoreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    private Account $arAccount;

    private Account $apAccount;

    private Account $invAssetAccount;

    private Account $rawAssetAccount;

    private Account $wipAssetAccount;

    private Account $salesRevAccount;

    private Account $cogsAccount;

    private Account $adjAccount;

    private Account $allocationAccount;

    // Additional accounts/bank accounts for marble specific flow
    private Account $cashAccount;

    private Account $bankAccount;

    private Account $slabAssetAccount;

    private Account $scrapAssetAccount;

    private Account $rawStoneRevenue;

    private Account $slabRevenue;

    private Account $cogsRawStone;

    private Account $cogsSlabs;

    private BankAccount $cashBankAccount;

    private BankAccount $hblBankAccount;

    // Reconciliation accounts
    private Account $shrinkageAccount;

    private Account $gainAccount;

    // Production variance accounts
    private Account $varianceExpenseAccount;

    private Account $varianceGainAccount;

    private AccountService $accountService;

    private InventoryService $inventoryService;

    private SubledgerService $subledgerService;

    private CashBankService $cashBankService;

    private ReconciliationService $reconciliationService;

    private ProductionVarianceService $productionVarianceService;

    private BankReconciliationService $bankReconciliationService;

    private TransferService $transferService;

    protected function setUp(): void
    {
        parent::setUp();

        // Instantiate Services
        $this->accountService = app(AccountService::class);
        $this->inventoryService = app(InventoryService::class);
        $this->subledgerService = app(SubledgerService::class);
        $this->cashBankService = app(CashBankService::class);
        $this->reconciliationService = app(ReconciliationService::class);
        $this->productionVarianceService = app(ProductionVarianceService::class);
        $this->bankReconciliationService = app(BankReconciliationService::class);
        $this->transferService = app(TransferService::class);

        // 1. Seed base user
        $this->admin = User::factory()->create([
            'name' => 'Test Admin',
            'email' => 'admin@example.test',
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

        $this->actingAs($this->admin);

        // 2. Seed chart of accounts (No balance columns are saved)
        $this->arAccount = Account::firstOrCreate(['code' => '1100'], ['name' => 'Accounts Receivable', 'type' => 'asset']);
        $this->invAssetAccount = Account::firstOrCreate(['code' => '1200'], ['name' => 'Finished Goods Inventory', 'type' => 'asset']);
        $this->rawAssetAccount = Account::firstOrCreate(['code' => '1210'], ['name' => 'Raw Materials Inventory', 'type' => 'asset']);
        $this->wipAssetAccount = Account::firstOrCreate(['code' => '1220'], ['name' => 'Work In Progress (WIP)', 'type' => 'asset']);
        $this->apAccount = Account::firstOrCreate(['code' => '2100'], ['name' => 'Accounts Payable', 'type' => 'liability']);
        $this->salesRevAccount = Account::firstOrCreate(['code' => '4100'], ['name' => 'Sales Revenue', 'type' => 'revenue']);
        $this->cogsAccount = Account::firstOrCreate(['code' => '5100'], ['name' => 'Cost of Goods Sold', 'type' => 'expense']);
        $this->adjAccount = Account::firstOrCreate(['code' => '5500'], ['name' => 'Inventory Adjustments Gain/Loss', 'type' => 'expense']);
        $this->allocationAccount = Account::firstOrCreate(['code' => '5600'], ['name' => 'Production Overhead/Labor Allocation', 'type' => 'expense']);

        // Extra accounts for validations
        $this->cashAccount = Account::firstOrCreate(['code' => '1010'], ['name' => 'Cash Account (Hand)', 'type' => 'asset']);
        $this->bankAccount = Account::firstOrCreate(['code' => '1011'], ['name' => 'Primary Bank Account', 'type' => 'asset']);
        $this->slabAssetAccount = Account::firstOrCreate(['code' => '1230'], ['name' => 'Slab Inventory', 'type' => 'asset']);
        $this->scrapAssetAccount = Account::firstOrCreate(['code' => '1240'], ['name' => 'Scrap Inventory', 'type' => 'asset']);
        $this->rawStoneRevenue = Account::firstOrCreate(['code' => '4110'], ['name' => 'Sales Revenue - Raw Stone', 'type' => 'revenue']);
        $this->slabRevenue = Account::firstOrCreate(['code' => '4120'], ['name' => 'Sales Revenue - Slabs', 'type' => 'revenue']);
        $this->cogsRawStone = Account::firstOrCreate(['code' => '5110'], ['name' => 'COGS - Raw Stone', 'type' => 'expense']);
        $this->cogsSlabs = Account::firstOrCreate(['code' => '5120'], ['name' => 'COGS - Slabs', 'type' => 'expense']);

        // Reconciliation accounts
        $this->shrinkageAccount = Account::firstOrCreate(['code' => '5800'], ['name' => 'Inventory Shrinkage Expense', 'type' => 'expense']);
        $this->gainAccount = Account::firstOrCreate(['code' => '4800'], ['name' => 'Inventory Gain Adjustment', 'type' => 'revenue']);

        // Production variance accounts
        $this->varianceExpenseAccount = Account::firstOrCreate(['code' => '5900'], ['name' => 'Production Variance Expense', 'type' => 'expense']);
        $this->varianceGainAccount = Account::firstOrCreate(['code' => '4900'], ['name' => 'Production Variance Gain', 'type' => 'revenue']);

        // Extra bank accounts
        $this->cashBankAccount = BankAccount::create([
            'account_id' => $this->cashAccount->id,
            'account_name' => 'Cash Account',
            'type' => 'cash',
        ]);
        $this->hblBankAccount = BankAccount::create([
            'account_id' => $this->bankAccount->id,
            'account_name' => 'Primary Bank Account',
            'type' => 'bank',
        ]);

        // 3. Seed warehouse
        $this->warehouse = Warehouse::create(['name' => 'Test Warehouse', 'location' => 'Zone T']);

        // Auto-configure accounting mapping for newly created products in tests
        Product::creating(function ($product) {
            if (empty($product->inventory_account_id)) {
                if (str_starts_with(strtoupper($product->sku), 'RM')) {
                    $product->inventory_account_id = $this->rawAssetAccount->id;
                    $product->cogs_account_id = $this->cogsRawStone->id;
                    $product->revenue_account_id = $this->rawStoneRevenue->id;
                } elseif (str_starts_with(strtoupper($product->sku), 'SL-')) {
                    $product->inventory_account_id = $this->slabAssetAccount->id;
                    $product->cogs_account_id = $this->cogsSlabs->id;
                    $product->revenue_account_id = $this->slabRevenue->id;
                } else {
                    $product->inventory_account_id = $this->invAssetAccount->id;
                    $product->cogs_account_id = $this->cogsAccount->id;
                    $product->revenue_account_id = $this->salesRevAccount->id;
                }
            }
        });
    }

    /**
     * Test 1: Double-Entry Enforcement Logic
     */
    public function test_unbalanced_journal_entry_fails_validation(): void
    {
        $response = $this->post('/accounting/journal', [
            'date' => '2026-06-16',
            'description' => 'Unbalanced Entry',
            'items' => [
                ['account_id' => $this->arAccount->id, 'debit' => 100.00, 'credit' => 0.00],
                ['account_id' => $this->salesRevAccount->id, 'debit' => 0.00, 'credit' => 90.00], // Unbalanced by $10
            ],
        ]);

        $response->assertSessionHasErrors(['items']);
        $this->assertEquals(0, JournalEntry::count());
    }

    public function test_balanced_journal_entry_succeeds_and_updates_ledger(): void
    {
        $response = $this->post('/accounting/journal', [
            'date' => '2026-06-16',
            'description' => 'Balanced Entry',
            'items' => [
                ['account_id' => $this->arAccount->id, 'debit' => 100.00, 'credit' => 0.00],
                ['account_id' => $this->salesRevAccount->id, 'debit' => 0.00, 'credit' => 100.00],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals(1, JournalEntry::count());
        $this->assertEquals(2, JournalItem::count());

        // Assert account balance updates (Assets increase with Debit, Revenue increases with Credit)
        $this->assertEquals(100.00, $this->accountService->getBalance($this->arAccount->id));
        $this->assertEquals(100.00, $this->accountService->getBalance($this->salesRevAccount->id));
    }

    /**
     * Test 2: Inventory Adjustments and FIFO Batch Tracking
     */
    public function test_inventory_adjustment_updates_stock_ledger_and_standard_cost(): void
    {
        $product = Product::create([
            'sku' => 'FG-TILE',
            'name' => 'Obsidian Tile',
            'unit' => 'pcs',
            'cost' => 10.00,
            'price' => 25.00,
        ]);

        $response = $this->post('/inventory/adjust', [
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 50,
            'unit_cost' => 12.50, // Standard cost increases from 10.00 to 12.50
            'notes' => 'Initial stock audit count',
        ]);

        $response->assertSessionHasNoErrors();

        // Check stock ledger quantity
        $this->assertEquals(50, StockLedger::where('product_id', $product->id)->sum('quantity'));

        // Check cached standard cost on product
        $this->assertEquals(12.50, $product->fresh()->cost);

        // Check automated journal entry creation for Inventory Valuation (Debit Inventory Asset, Credit Adjustments)
        $this->assertEquals(1, JournalEntry::count());
        // Debit Finished Goods Inventory by $625
        $this->assertEquals(625.00, $this->accountService->getBalance($this->invAssetAccount->id));
        // Credit Inventory Adjustments by $625 (credit to expense = negative balance)
        $this->assertEquals(-625.00, $this->accountService->getBalance($this->adjAccount->id));

        // Check that a batch has been created
        $batch = InventoryBatch::where('product_id', $product->id)->first();
        $this->assertNotNull($batch);
        $this->assertEquals(50, $batch->quantity_remaining);
        $this->assertEquals(12.50, $batch->unit_cost);
    }

    /**
     * Test 3: FIFO Depletion of Multiple Batches
     */
    public function test_fifo_depletion_multiple_batches(): void
    {
        $product = Product::create([
            'sku' => 'FG-SLAB-FIFO',
            'name' => 'Marble Slab FIFO',
            'unit' => 'pcs',
            'cost' => 10.00,
            'price' => 30.00,
        ]);

        // Batch 1: 5 units at $10.00
        $this->post('/inventory/adjust', [
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 5,
            'unit_cost' => 10.00,
            'notes' => 'First Batch',
        ]);

        // Batch 2: 5 units at $15.00
        $this->post('/inventory/adjust', [
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 5,
            'unit_cost' => 15.00,
            'notes' => 'Second Batch',
        ]);

        // Sell 7 units: expected COGS is 5 * $10.00 + 2 * $15.00 = $80.00
        $customer = Contact::create(['name' => 'Customer B', 'type' => 'customer']);
        $response = $this->post('/sales/store', [
            'contact_id' => $customer->id,
            'order_date' => '2026-06-16',
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 7, 'unit_price' => 30.00],
            ],
            'discount_amount' => 0.0,
            'delivery_charges' => 0.0,
            'cash_received' => 0.0,
            'bank_received' => 0.0,
        ]);

        $response->assertSessionHasNoErrors();

        // Verify remaining stock is 3 units
        $this->assertEquals(3, $this->inventoryService->getTotalStock($product->id));

        // Verify G/L Costings: COGS is $80, Inventory asset is credited by $80.
        // Batch 1 initial value = $50, Batch 2 initial value = $75. Total initial asset = $125.
        // Balance after sale should be $125 - $80 = $45.
        $this->assertEquals(80.00, $this->accountService->getBalance($this->cogsAccount->id));
        $this->assertEquals(45.00, $this->accountService->getBalance($this->invAssetAccount->id));
    }

    /**
     * Test 4: Cost of Goods Sold (COGS) Calculation and Sales Invoicing
     */
    public function test_sales_order_depletes_inventory_calculates_cogs_and_posts_accounting(): void
    {
        $product = Product::create([
            'sku' => 'FG-SLAB',
            'name' => 'Marble Slab',
            'unit' => 'pcs',
            'cost' => 100.00,
            'price' => 300.00,
        ]);

        // Establish stock of 5 units at cost $100 using Adjustment endpoint
        $this->post('/inventory/adjust', [
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 5,
            'unit_cost' => 100.00,
            'notes' => 'Setup Stock',
        ]);

        $customer = Contact::create(['name' => 'Customer A', 'type' => 'customer']);

        // Sell 2 units at $300 each (Total Sales = $600, Expected COGS = $200)
        $response = $this->post('/sales/store', [
            'contact_id' => $customer->id,
            'order_date' => '2026-06-16',
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 300.00],
            ],
            'discount_amount' => 0.0,
            'delivery_charges' => 0.0,
            'cash_received' => 0.0,
            'bank_received' => 0.0,
        ]);

        $response->assertSessionHasNoErrors();

        // 1. Verify stock reduction: 5 - 2 = 3 units left
        $this->assertEquals(3, StockLedger::where('product_id', $product->id)->sum('quantity'));

        // 2. Verify G/L Journal entry created
        // Accounts Receivable (1100) -> Debit $600
        // Sales Revenue (4100) -> Credit $600
        // Cost of Goods Sold (5100) -> Debit $200
        // Finished Goods Inventory (1200) -> Credit $200 ($500 initial - $200 = $300)
        $this->assertEquals(600.00, $this->accountService->getBalance($this->arAccount->id));
        $this->assertEquals(600.00, $this->accountService->getBalance($this->salesRevAccount->id));
        $this->assertEquals(200.00, $this->accountService->getBalance($this->cogsAccount->id));
        $this->assertEquals(300.00, $this->accountService->getBalance($this->invAssetAccount->id));

        // 3. Verify sub-ledger CustomerInvoice was created
        $invoice = CustomerInvoice::where('contact_id', $customer->id)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(600.00, $invoice->total_amount);
        $this->assertEquals(600.00, $invoice->remaining_balance);
        $this->assertEquals('unpaid', $invoice->status);
    }

    /**
     * Test 5: Production BOM Cost Allocation and WIP flow
     */
    public function test_production_order_consumption_capitalization_and_costing(): void
    {
        // 1. Setup raw materials
        $rawStone = Product::create(['sku' => 'RM-STONE', 'name' => 'Raw Stone Block', 'unit' => 'pcs', 'cost' => 20.00]);
        $rawResin = Product::create(['sku' => 'RM-RESIN', 'name' => 'Epoxy Resin', 'unit' => 'ltrs', 'cost' => 5.00]);

        // Setup finished product
        $finishedTile = Product::create(['sku' => 'FG-TILE', 'name' => 'Obsidian Finished Tile', 'unit' => 'pcs', 'cost' => 0]);

        // Establish raw materials stock
        $this->post('/inventory/adjust', [
            'product_id' => $rawStone->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 20,
            'unit_cost' => 20.00,
            'notes' => 'Raw stone stock',
        ]);
        $this->post('/inventory/adjust', [
            'product_id' => $rawResin->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 10,
            'unit_cost' => 5.00,
            'notes' => 'Raw resin stock',
        ]);

        // 2. Create BOM recipe: To make 1 finished tile requires 2 RM-STONE and 1 RM-RESIN.
        $bom = Bom::create([
            'product_id' => $finishedTile->id,
            'name' => 'Onyx Tile Recipe',
            'labor_cost_estimate' => 10.00,
            'overhead_cost_estimate' => 5.00,
        ]);
        BomItem::create(['bom_id' => $bom->id, 'product_id' => $rawStone->id, 'quantity' => 2]);
        BomItem::create(['bom_id' => $bom->id, 'product_id' => $rawResin->id, 'quantity' => 1]);

        // 3. Create production order for 5 tiles
        $order = ProductionOrder::create([
            'bom_id' => $bom->id,
            'quantity' => 5,
            'status' => 'pending',
        ]);

        // 4. Complete production order
        // Actual direct labor: $50 ($10 estimate), Actual overhead: $25 ($5 estimate)
        // Total Actual Cost = $225 materials + $50 labor + $25 overhead = $300.
        // Calculated finished goods unit cost = $300 / 5 = $60.00.
        $response = $this->post("/production/order/{$order->id}/complete", [
            'warehouse_id' => $this->warehouse->id,
            'actual_labor_cost' => 50.00,
            'actual_overhead_cost' => 25.00,
        ]);

        $response->assertSessionHasNoErrors();

        // A. Verify raw stock depletions:
        // Stone: 20 - 10 = 10 units left
        $this->assertEquals(10, $this->inventoryService->getTotalStock($rawStone->id));
        // Resin: 10 - 5 = 5 units left
        $this->assertEquals(5, $this->inventoryService->getTotalStock($rawResin->id));

        // B. Verify finished stock addition: 5 units
        $this->assertEquals(5, $this->inventoryService->getTotalStock($finishedTile->id));

        // C. Verify finished good standard cost capitalization: $60.00 per unit
        $this->assertEquals(60.00, $finishedTile->fresh()->cost);

        // D. Verify general ledger journal entries & WIP flow
        // WIP balance should be 0 because it was fully capitalized to Finished Goods
        $this->assertEquals(0.00, $this->accountService->getBalance($this->wipAssetAccount->id));

        // Finished Goods should have +$300 from the production completion
        $this->assertEquals(300.00, $this->accountService->getBalance($this->invAssetAccount->id));

        // Raw Materials inventory should have decreased by $225 (Initially $450, now $225)
        $this->assertEquals(225.00, $this->accountService->getBalance($this->rawAssetAccount->id));

        // Labor/Overhead offset allocation account should have $75.00 (credit to expense = negative balance)
        $this->assertEquals(-75.00, $this->accountService->getBalance($this->allocationAccount->id));
    }

    /**
     * Test 6: Sub-ledger Aging & Payments
     */
    public function test_subledger_aging_and_payments(): void
    {
        $customer = Contact::create(['name' => 'Customer Subledger', 'type' => 'customer']);

        // 1. Manually create a CustomerInvoice
        $invoice = CustomerInvoice::create([
            'order_id' => null,
            'contact_id' => $customer->id,
            'invoice_number' => 'INV-TEST-001',
            'issue_date' => now()->subDays(45), // Will fall in the 31-60 days bucket
            'due_date' => now()->subDays(15),
            'total_amount' => 1000.00,
            'remaining_balance' => 1000.00,
            'status' => 'unpaid',
        ]);

        // 2. Check the initial aging report
        $report = $this->subledgerService->getAgingReport('customer');
        $this->assertEquals(1000.00, $report['summary']['thirty']); // 31-60 days bucket
        $this->assertEquals(0.00, $report['summary']['current']);

        // 3. Create a Payment
        $payment = Payment::create([
            'payment_number' => 'PAY-TEST-001',
            'contact_id' => $customer->id,
            'payment_date' => now()->toDateString(),
            'amount' => 400.00,
            'type' => 'receipt',
            'payment_method' => 'bank_transfer',
        ]);

        // 4. Record Invoice Payment Allocation ($400)
        $this->subledgerService->recordInvoicePayment($payment->id, $invoice->id, 400.00);

        // 5. Verify partial payment update
        $invoice->refresh();
        $this->assertEquals(600.00, $invoice->remaining_balance);
        $this->assertEquals('partial', $invoice->status);

        // 6. Verify updated aging report
        $report2 = $this->subledgerService->getAgingReport('customer');
        $this->assertEquals(600.00, $report2['summary']['thirty']);

        // 7. Complete the payment ($600)
        $payment2 = Payment::create([
            'payment_number' => 'PAY-TEST-002',
            'contact_id' => $customer->id,
            'payment_date' => now()->toDateString(),
            'amount' => 600.00,
            'type' => 'receipt',
            'payment_method' => 'bank_transfer',
        ]);
        $this->subledgerService->recordInvoicePayment($payment2->id, $invoice->id, 600.00);

        // 8. Verify fully paid status
        $invoice->refresh();
        $this->assertEquals(0.00, $invoice->remaining_balance);
        $this->assertEquals('paid', $invoice->status);

        // Aging report should now show 0 balance
        $report3 = $this->subledgerService->getAgingReport('customer');
        $this->assertEquals(0.00, $report3['summary']['thirty']);
    }

    /**
     * Test 7: End-to-End Marble ERP Flow (Step 3 validation checks)
     */
    public function test_end_to_end_marble_erp_flow(): void
    {
        // 1. Create raw stone purchase with base cost, freight, loading, and partial cash payment.
        $rawStoneProduct = Product::create([
            'sku' => 'RM-STONE-TEST',
            'name' => 'Raw Stone Test Block',
            'unit' => 'pcs',
            'cost' => 0.0,
            'price' => 0.0,
            'product_type' => 'raw_stone',
            'unit_of_measure' => 'MT',
        ]);
        $supplier = Contact::create(['name' => 'Supplier Balochistan', 'type' => 'vendor']);

        $purchaseResponse = $this->post('/purchases/store', [
            'contact_id' => $supplier->id,
            'order_date' => '2026-06-16',
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                ['product_id' => $rawStoneProduct->id, 'quantity' => 10, 'unit_price' => 20000.00],
            ],
            'transportation_cost' => 15000.00,
            'loading_cost' => 5000.00,
            'other_direct_cost' => 0.00,
            'cash_paid' => 50000.00,
            'bank_paid' => 0.00,
            'bank_account_id' => $this->cashBankAccount->id,
        ]);
        $purchaseResponse->assertSessionHasNoErrors();

        // Confirm FIFO batch created with correct landed cost per MT
        $batch = InventoryBatch::where('product_id', $rawStoneProduct->id)->first();
        $this->assertNotNull($batch);
        $this->assertEquals(10, $batch->quantity_remaining);
        $this->assertEquals(22000.00, $batch->unit_cost);

        // Confirm Supplier bill created
        $bill = SupplierBill::where('contact_id', $supplier->id)->first();
        $this->assertNotNull($bill);
        $this->assertEquals(220000.00, $bill->total_amount);
        $this->assertEquals(170000.00, $bill->remaining_balance);

        // Confirm Journal entries balanced (Dr Inventory Asset, Cr Cash, Cr Accounts Payable)
        $this->assertEquals(220000.00, $this->accountService->getBalance($this->rawAssetAccount->id));
        $this->assertEquals(-50000.00, $this->accountService->getBalance($this->cashAccount->id));
        $this->assertEquals(170000.00, $this->accountService->getBalance($this->apAccount->id));

        // 2. Run Stone-to-Slab production: Consume FIFO raw batch, Add overheads.
        $slabProduct = Product::create([
            'sku' => 'SL-TEST-01',
            'name' => 'Slab Test Product',
            'unit' => 'pcs',
            'cost' => 0.0,
            'price' => 0.0,
            'product_type' => 'slab',
            'unit_of_measure' => 'MT',
        ]);

        $stoneToSlabResponse = $this->post('/production/stone-to-slab', [
            'input_batch_id' => $batch->id,
            'input_quantity' => 6,
            'slab_product_id' => $slabProduct->id,
            'slabs_produced' => 5,
            'wastage_quantity' => 1,
            'scrap_product_id' => null,
            'scrap_quantity' => 0,
            'scrap_unit' => 'MT',
            'labor_cost' => 10000.00,
            'electricity_cost' => 5000.00,
            'fuel_cost' => 3000.00,
            'maintenance_cost' => 2000.00,
            'overhead_cost' => 1000.00,
            'production_date' => '2026-06-16',
            'warehouse_id' => $this->warehouse->id,
        ]);
        $stoneToSlabResponse->assertSessionHasNoErrors();

        // Confirm WIP postings correct (balanced and fully capitalized)
        $this->assertEquals(0.00, $this->accountService->getBalance($this->wipAssetAccount->id));
        $this->assertEquals(-21000.00, $this->accountService->getBalance($this->allocationAccount->id));

        // Confirm Slab batch created with cost and yield
        $slabBatch = InventoryBatch::where('product_id', $slabProduct->id)->first();
        $this->assertNotNull($slabBatch);
        $this->assertEquals(5, $slabBatch->quantity_remaining);
        $this->assertEquals(30600.00, $slabBatch->unit_cost);
        $this->assertEquals(153000.00, $this->accountService->getBalance($this->slabAssetAccount->id));

        // Confirm Yield calculated
        $stsProduction = StoneToSlabProduction::where('slab_product_id', $slabProduct->id)->first();
        $this->assertNotNull($stsProduction);
        $this->assertEquals(83.3333, round($stsProduction->yield_percentage, 4));
        $this->assertEquals(153000.00, $stsProduction->total_cost);

        // Confirm Raw batch quantity reduced
        $batch->refresh();
        $this->assertEquals(4, $batch->quantity_remaining);
        $this->assertEquals(88000.00, $this->accountService->getBalance($this->rawAssetAccount->id));

        // 3. Run Slab-to-Tile production: Confirm conversion ratio correct, Tile batch created.
        $tileProduct = Product::create([
            'sku' => 'TL-TEST-01',
            'name' => 'Tile Test Product',
            'unit' => 'pcs',
            'cost' => 0.0,
            'price' => 0.0,
            'product_type' => 'tile',
            'unit_of_measure' => 'Sq Ft',
        ]);

        $slabToTileResponse = $this->post('/production/slab-to-tile', [
            'input_batch_id' => $slabBatch->id,
            'input_quantity' => 4,
            'tile_product_id' => $tileProduct->id,
            'tiles_produced' => 2000,
            'wastage_percentage' => 5,
            'scrap_product_id' => null,
            'scrap_quantity' => 0,
            'scrap_unit' => 'Sq Ft',
            'labor_cost' => 5000.00,
            'cutting_cost' => 3000.00,
            'polishing_cost' => 4000.00,
            'packaging_cost' => 2000.00,
            'overhead_cost' => 1000.00,
            'production_date' => '2026-06-16',
            'warehouse_id' => $this->warehouse->id,
        ]);
        $slabToTileResponse->assertSessionHasNoErrors();

        // Confirm conversion ratio correct
        $sttProduction = SlabToTileProduction::where('tile_product_id', $tileProduct->id)->first();
        $this->assertNotNull($sttProduction);
        $this->assertEquals(500.00, $sttProduction->conversion_ratio);
        $this->assertEquals(137400.00, $sttProduction->total_cost);

        // Confirm Tile batch created and Slab batch reduced
        $tileBatch = InventoryBatch::where('product_id', $tileProduct->id)->first();
        $this->assertNotNull($tileBatch);
        $this->assertEquals(2000, $tileBatch->quantity_remaining);
        $this->assertEquals(68.70, $tileBatch->unit_cost);

        $slabBatch->refresh();
        $this->assertEquals(1, $slabBatch->quantity_remaining);
        $this->assertEquals(30600.00, $this->accountService->getBalance($this->slabAssetAccount->id));
        $this->assertEquals(137400.00, $this->accountService->getBalance($this->invAssetAccount->id));

        // 4. Run Tile sale: FIFO depletion works, COGS equals actual batch cost, Correct accounts hit.
        $customer = Contact::create(['name' => 'Customer Balochistan', 'type' => 'customer']);

        $saleResponse = $this->post('/sales/store', [
            'contact_id' => $customer->id,
            'order_date' => '2026-06-16',
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                ['product_id' => $tileProduct->id, 'quantity' => 1500, 'unit_price' => 150.00],
            ],
            'discount_amount' => 0.0,
            'delivery_charges' => 0.0,
            'cash_received' => 0.0,
            'bank_received' => 0.0,
        ]);
        $saleResponse->assertSessionHasNoErrors();

        // Confirm FIFO depletion works
        $tileBatch->refresh();
        $this->assertEquals(500, $tileBatch->quantity_remaining);

        // Confirm COGS equals actual batch cost
        $this->assertEquals(103050.00, $this->accountService->getBalance($this->cogsAccount->id));

        // Confirm correct revenue & COGS accounts hit
        $this->assertEquals(225000.00, $this->accountService->getBalance($this->salesRevAccount->id));
        $this->assertEquals(225000.00, $this->accountService->getBalance($this->arAccount->id));
        $this->assertEquals(34350.00, $this->accountService->getBalance($this->invAssetAccount->id));

        // 5. Run customer payment: Cash/Bank updated, AR reduced, Subledger updated.
        $payment = Payment::create([
            'payment_number' => 'PAY-STS-001',
            'contact_id' => $customer->id,
            'payment_date' => '2026-06-16',
            'amount' => 200000.00,
            'type' => 'receipt',
            'payment_method' => 'bank_transfer',
            'bank_account_id' => $this->hblBankAccount->id,
        ]);

        // Allocate payment to invoice
        $invoice = CustomerInvoice::where('contact_id', $customer->id)->first();
        $this->assertNotNull($invoice);
        $this->subledgerService->recordInvoicePayment($payment->id, $invoice->id, 200000.00);

        // Record BankTransaction subledger deposit
        $this->cashBankService->deposit(
            $this->hblBankAccount->id,
            200000.00,
            '2026-06-16',
            'payment',
            $payment->id,
            'Collection from customer invoice'
        );

        // Record G/L Journal entry
        $paymentEntry = JournalEntry::create([
            'entry_number' => 'JV-PAY-'.time().'-'.rand(100, 999),
            'date' => '2026-06-16',
            'description' => 'Collection from customer invoice',
        ]);
        JournalItem::create([
            'journal_entry_id' => $paymentEntry->id,
            'account_id' => $this->bankAccount->id,
            'debit' => 200000.00,
            'credit' => 0.00,
        ]);
        JournalItem::create([
            'journal_entry_id' => $paymentEntry->id,
            'account_id' => $this->arAccount->id,
            'debit' => 0.00,
            'credit' => 200000.00,
        ]);

        // Confirm AR reduced, Cash/Bank updated, subledger updated
        $invoice->refresh();
        $this->assertEquals(25000.00, $invoice->remaining_balance);
        $this->assertEquals('partial', $invoice->status);
        $this->assertEquals(25000.00, $this->accountService->getBalance($this->arAccount->id));
        $this->assertEquals(200000.00, $this->accountService->getBalance($this->bankAccount->id));
        $this->assertEquals(200000.00, $this->cashBankService->getBalance($this->hblBankAccount->id));
    }

    /**
     * Test 8: Stock limit guard (Step 6.1)
     */
    public function test_cannot_sell_more_than_available_stock(): void
    {
        $product = Product::create([
            'sku' => 'TEST-STOCK-LIMIT',
            'name' => 'Stock Limit Test',
            'unit' => 'pcs',
            'cost' => 10.00,
            'price' => 20.00,
            'product_type' => 'tile',
            'unit_of_measure' => 'Sq Ft',
            'inventory_account_id' => $this->invAssetAccount->id,
            'cogs_account_id' => $this->cogsAccount->id,
            'revenue_account_id' => $this->salesRevAccount->id,
        ]);

        $this->inventoryService->createBatch($product->id, $this->warehouse->id, 'adjustment', 1, 10.0, 10.0);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Insufficient stock/');

        $this->inventoryService->depleteStock($product->id, $this->warehouse->id, 15.0, 'test', 1);
    }

    /**
     * Test 9: Concurrency lock prevents negative stock (Step 6.2)
     */
    public function test_concurrent_fifo_locking_prevents_negative_stock(): void
    {
        $product = Product::create([
            'sku' => 'TEST-CONCURRENCY',
            'name' => 'Concurrency Test',
            'unit' => 'pcs',
            'cost' => 10.00,
            'price' => 20.00,
            'product_type' => 'tile',
            'unit_of_measure' => 'Sq Ft',
            'inventory_account_id' => $this->invAssetAccount->id,
            'cogs_account_id' => $this->cogsAccount->id,
            'revenue_account_id' => $this->salesRevAccount->id,
        ]);

        $batch = $this->inventoryService->createBatch($product->id, $this->warehouse->id, 'adjustment', 1, 10.0, 10.0);

        // Attempt 1: Deplete 6 units (should succeed)
        $cogs1 = $this->inventoryService->depleteStock($product->id, $this->warehouse->id, 6.0, 'test', 1);
        $this->assertEquals(60.0, $cogs1);
        $this->assertEquals(4.0, $batch->fresh()->quantity_remaining);

        // Attempt 2: Deplete 6 units (should fail due to insufficient stock, and not result in negative stock)
        $thrown = false;
        try {
            $this->inventoryService->depleteStock($product->id, $this->warehouse->id, 6.0, 'test', 2);
        } catch (\Exception $e) {
            $thrown = true;
        }

        $this->assertTrue($thrown, 'Expected depletion to throw an exception');
        // The stock must not be negative
        $this->assertEquals(4.0, $batch->fresh()->quantity_remaining);
    }

    /**
     * Test 10: Customer payments updates Cash/Bank and AR (Step 6.3)
     */
    public function test_customer_payment_updates_bank_and_ar(): void
    {
        $customer = Contact::create(['name' => 'Customer C', 'type' => 'customer']);

        // Create a CustomerInvoice manually
        $invoice = CustomerInvoice::create([
            'order_id' => null,
            'contact_id' => $customer->id,
            'invoice_number' => 'INV-PAY-TEST-99',
            'issue_date' => '2026-06-16',
            'due_date' => '2026-06-30',
            'total_amount' => 5000.00,
            'remaining_balance' => 5000.00,
            'status' => 'unpaid',
            'gross_amount' => 5000.00,
            'discount_amount' => 0.00,
            'delivery_charges' => 0.00,
            'net_amount' => 5000.00,
            'cash_received' => 0.00,
            'bank_received' => 0.00,
        ]);

        // Post AR journal entry first so ledger matches the subledger invoice
        $arEntry = JournalEntry::create([
            'entry_number' => 'JV-AR-TEST-99',
            'date' => '2026-06-16',
            'description' => 'Initial AR Posting',
        ]);
        JournalItem::create([
            'journal_entry_id' => $arEntry->id,
            'account_id' => $this->arAccount->id,
            'debit' => 5000.00,
            'credit' => 0.00,
        ]);
        JournalItem::create([
            'journal_entry_id' => $arEntry->id,
            'account_id' => $this->salesRevAccount->id,
            'debit' => 0.00,
            'credit' => 5000.00,
        ]);

        // Call the new payment endpoint
        $response = $this->post('/payments/customer', [
            'contact_id' => $customer->id,
            'payment_date' => '2026-06-16',
            'amount' => 3000.00,
            'payment_method' => 'bank_transfer',
            'bank_account_id' => $this->hblBankAccount->id,
            'invoice_id' => $invoice->id,
            'notes' => 'Partial collection',
        ]);

        $response->assertSessionHasNoErrors();

        // Assert subledger invoice updated
        $invoice->refresh();
        $this->assertEquals(2000.00, $invoice->remaining_balance);
        $this->assertEquals('partial', $invoice->status);

        // Assert G/L balances updated
        // AR reduced: 5000 - 3000 = 2000
        $this->assertEquals(2000.00, $this->accountService->getBalance($this->arAccount->id));
        // Bank increased: 0 + 3000 = 3000
        $this->assertEquals(3000.00, $this->accountService->getBalance($this->bankAccount->id));
        // Cash bank subledger matches
        $this->assertEquals(3000.00, $this->cashBankService->getBalance($this->hblBankAccount->id));
    }

    /**
     * Test 11: Cannot post transaction in a closed accounting period
     */
    public function test_cannot_post_transaction_in_closed_period(): void
    {
        // Create a closed accounting period covering the transaction date
        AccountingPeriod::create([
            'period_name' => 'June 2026',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'is_closed' => true,
            'closed_by' => $this->admin->id,
            'closed_at' => now(),
        ]);

        // Create product and stock for sale attempt
        $product = Product::create([
            'sku' => 'TEST-PERIOD-CLOSED',
            'name' => 'Period Lock Test',
            'unit' => 'pcs',
            'cost' => 10.00,
            'price' => 20.00,
        ]);

        $this->post('/inventory/adjust', [
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 10,
            'unit_cost' => 10.00,
            'notes' => 'Stock setup',
            'date' => '2026-05-15', // Use a date outside the closed period for setup
        ]);

        $customer = Contact::create(['name' => 'Customer Period Test', 'type' => 'customer']);

        // Attempt to post a sale in the closed period
        $response = $this->post('/sales/store', [
            'contact_id' => $customer->id,
            'order_date' => '2026-06-16', // Within the closed period
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 20.00],
            ],
            'discount_amount' => 0.0,
            'delivery_charges' => 0.0,
            'cash_received' => 0.0,
            'bank_received' => 0.0,
        ]);

        $response->assertSessionHasErrors(['period']);
    }

    /**
     * Test 12: Can post transaction in an open accounting period
     */
    public function test_can_post_transaction_in_open_period(): void
    {
        // Create an open accounting period
        AccountingPeriod::create([
            'period_name' => 'June 2026',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'is_closed' => false,
        ]);

        $product = Product::create([
            'sku' => 'TEST-PERIOD-OPEN',
            'name' => 'Period Open Test',
            'unit' => 'pcs',
            'cost' => 10.00,
            'price' => 20.00,
        ]);

        $this->post('/inventory/adjust', [
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 10,
            'unit_cost' => 10.00,
            'notes' => 'Stock setup',
        ]);

        $customer = Contact::create(['name' => 'Customer Open Period', 'type' => 'customer']);

        $response = $this->post('/sales/store', [
            'contact_id' => $customer->id,
            'order_date' => '2026-06-16', // Within the open period
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 20.00],
            ],
            'discount_amount' => 0.0,
            'delivery_charges' => 0.0,
            'cash_received' => 0.0,
            'bank_received' => 0.0,
        ]);

        $response->assertSessionHasNoErrors();
    }

    /**
     * Test 13: Period overlap validation
     */
    public function test_period_overlap_validation(): void
    {
        // Create an existing period
        AccountingPeriod::create([
            'period_name' => 'June 2026',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'is_closed' => false,
        ]);

        // Attempt to create an overlapping period
        $response = $this->post('/accounting/periods', [
            'period_name' => 'Mid June 2026',
            'start_date' => '2026-06-15',
            'end_date' => '2026-07-15',
        ]);

        $response->assertSessionHasErrors(['start_date']);

        // Ensure only 1 period exists
        $this->assertEquals(1, AccountingPeriod::count());
    }

    /**
     * Test 14: Audit log created on model create
     */
    public function test_audit_log_created_on_model_create(): void
    {
        $productCount = Product::count();
        $auditCount = AuditLog::count();

        $product = Product::create([
            'sku' => 'TEST-AUDIT-CREATE',
            'name' => 'Audit Create Test',
            'unit' => 'pcs',
            'cost' => 10.00,
            'price' => 20.00,
        ]);

        $this->assertEquals($productCount + 1, Product::count());
        $this->assertEquals($auditCount + 1, AuditLog::count());

        $log = AuditLog::latest('id')->first();
        $this->assertEquals('created', $log->action);
        $this->assertEquals(Product::class, $log->model_type);
        $this->assertEquals($product->id, $log->model_id);
        $this->assertNull($log->before_data);
        $this->assertNotNull($log->after_data);
        $this->assertEquals('TEST-AUDIT-CREATE', $log->after_data['sku']);
    }

    /**
     * Test 15: Audit log created on model update
     */
    public function test_audit_log_created_on_model_update(): void
    {
        $product = Product::create([
            'sku' => 'TEST-AUDIT-UPDATE',
            'name' => 'Audit Update Test',
            'unit' => 'pcs',
            'cost' => 10.00,
            'price' => 20.00,
        ]);

        $auditCount = AuditLog::count();

        $product->update([
            'price' => 30.00,
            'name' => 'Audit Update Tested',
        ]);

        $this->assertEquals($auditCount + 1, AuditLog::count());

        $log = AuditLog::latest('id')->first();
        $this->assertEquals('updated', $log->action);
        $this->assertEquals(Product::class, $log->model_type);
        $this->assertEquals($product->id, $log->model_id);

        $this->assertNotNull($log->before_data);
        $this->assertNotNull($log->after_data);

        // Assert only dirty fields are tracked in before_data
        $this->assertArrayHasKey('price', $log->before_data);
        // The DB returns decimal columns as strings with correct precision, e.g. '20.0000'
        $this->assertEquals('20.0000', $log->before_data['price']);
        $this->assertEquals('30.0000', $log->after_data['price']);
    }

    /**
     * Test 16: Audit log created on model delete
     */
    public function test_audit_log_created_on_model_delete(): void
    {
        $product = Product::create([
            'sku' => 'TEST-AUDIT-DELETE',
            'name' => 'Audit Delete Test',
            'unit' => 'pcs',
            'cost' => 10.00,
            'price' => 20.00,
        ]);

        $auditCount = AuditLog::count();

        $product->delete();

        $this->assertEquals($auditCount + 1, AuditLog::count());

        $log = AuditLog::latest('id')->first();
        $this->assertEquals('deleted', $log->action);
        $this->assertEquals(Product::class, $log->model_type);
        $this->assertEquals($product->id, $log->model_id);

        $this->assertNotNull($log->before_data);
        $this->assertNull($log->after_data);
        $this->assertEquals('TEST-AUDIT-DELETE', $log->before_data['sku']);
    }

    /**
     * Test 17: Journal entries cannot be deleted
     */
    public function test_journal_entry_cannot_be_deleted(): void
    {
        $entry = JournalEntry::create([
            'entry_number' => 'JV-TEST-DELETE',
            'date' => '2026-06-16',
            'description' => 'Test entry for deletion',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Journal entries cannot be deleted.');

        $entry->delete();
    }

    // =========================================================
    // PHASE 3: INVENTORY RECONCILIATION TESTS
    // =========================================================

    /**
     * Test 18: Batch reconciliation creates adjustment record
     */
    public function test_batch_reconciliation_creates_adjustment_record(): void
    {
        $product = Product::create([
            'sku' => 'FG-RECON-01',
            'name' => 'Reconciliation Test Product',
            'unit' => 'pcs',
            'cost' => 100.00,
            'price' => 200.00,
        ]);

        // Create a batch with 50 units
        $batch = $this->inventoryService->createBatch(
            $product->id,
            $this->warehouse->id,
            'adjustment',
            1,
            50.0,
            100.00
        );

        $auditCountBefore = AuditLog::count();

        // Physical count shows 45 units (shrinkage of 5)
        $adjustment = $this->reconciliationService->reconcileBatch(
            $batch->id,
            45.0,
            'Breakage during handling',
            '2026-06-16'
        );

        // 1. Assert adjustment record created
        $this->assertNotNull($adjustment);
        $this->assertInstanceOf(InventoryAdjustment::class, $adjustment);
        $this->assertEquals($batch->id, $adjustment->inventory_batch_id);
        $this->assertEquals($product->id, $adjustment->product_id);
        $this->assertEquals($this->warehouse->id, $adjustment->warehouse_id);
        $this->assertEquals(50.0, (float) $adjustment->system_quantity);
        $this->assertEquals(45.0, (float) $adjustment->physical_quantity);
        $this->assertEquals(-5.0, (float) $adjustment->difference);
        $this->assertEquals('Breakage during handling', $adjustment->reason);

        // 2. Assert batch quantity updated
        $batch->refresh();
        $this->assertEquals(45.0, (float) $batch->quantity_remaining);

        // 3. Assert journal entry created and balanced
        $this->assertNotNull($adjustment->journal_entry_id);
        $journalEntry = JournalEntry::find($adjustment->journal_entry_id);
        $this->assertNotNull($journalEntry);
        $items = $journalEntry->items;
        $totalDebit = $items->sum('debit');
        $totalCredit = $items->sum('credit');
        $this->assertTrue(abs($totalDebit - $totalCredit) < 0.0001, 'Journal entry must be balanced');

        // 4. Assert audit log was created (InventoryAdjustment + InventoryBatch update + StockLedger + JE + JI)
        $this->assertGreaterThan($auditCountBefore, AuditLog::count());
    }

    /**
     * Test 19: Stock shrinkage posts correct journal (Dr Shrinkage, Cr Inventory)
     */
    public function test_stock_shrinkage_posts_correct_journal(): void
    {
        $product = Product::create([
            'sku' => 'FG-SHRINK-01',
            'name' => 'Shrinkage Test Product',
            'unit' => 'pcs',
            'cost' => 50.00,
            'price' => 100.00,
        ]);

        $batch = $this->inventoryService->createBatch(
            $product->id,
            $this->warehouse->id,
            'adjustment',
            1,
            20.0,
            50.00
        );

        // Record the initial inventory balance from the batch creation journal
        $initialInvBalance = $this->accountService->getBalance($this->invAssetAccount->id);

        // Physical count: 15 units (shrinkage of 5 units at $50 each = $250)
        $adjustment = $this->reconciliationService->reconcileBatch(
            $batch->id,
            15.0,
            'Water damage',
            '2026-06-16'
        );

        $this->assertNotNull($adjustment);

        // Shrinkage value = 5 * 50.00 = 250.00
        // Dr Inventory Shrinkage Expense (5800) = 250
        $this->assertEquals(250.00, $this->accountService->getBalance($this->shrinkageAccount->id));

        // Cr Inventory Asset = reduced by 250
        $expectedInvBalance = $initialInvBalance - 250.00;
        $this->assertEquals($expectedInvBalance, $this->accountService->getBalance($this->invAssetAccount->id));

        // Verify correct accounts were hit in journal items
        $journalEntry = JournalEntry::find($adjustment->journal_entry_id);
        $items = $journalEntry->items;

        // Should have exactly 2 journal items
        $this->assertCount(2, $items);

        // Debit to shrinkage account
        $debitItem = $items->where('account_id', $this->shrinkageAccount->id)->first();
        $this->assertNotNull($debitItem);
        $this->assertEquals(250.00, (float) $debitItem->debit);
        $this->assertEquals(0.00, (float) $debitItem->credit);

        // Credit to inventory asset account
        $creditItem = $items->where('account_id', $this->invAssetAccount->id)->first();
        $this->assertNotNull($creditItem);
        $this->assertEquals(0.00, (float) $creditItem->debit);
        $this->assertEquals(250.00, (float) $creditItem->credit);
    }

    /**
     * Test 20: Stock gain posts correct journal (Dr Inventory, Cr Gain)
     */
    public function test_stock_gain_posts_correct_journal(): void
    {
        $product = Product::create([
            'sku' => 'FG-GAIN-01',
            'name' => 'Gain Test Product',
            'unit' => 'pcs',
            'cost' => 75.00,
            'price' => 150.00,
        ]);

        $batch = $this->inventoryService->createBatch(
            $product->id,
            $this->warehouse->id,
            'adjustment',
            1,
            10.0,
            75.00
        );

        $initialInvBalance = $this->accountService->getBalance($this->invAssetAccount->id);

        // Physical count: 13 units (gain of 3 units at $75 each = $225)
        $adjustment = $this->reconciliationService->reconcileBatch(
            $batch->id,
            13.0,
            'Found uncounted stock',
            '2026-06-16'
        );

        $this->assertNotNull($adjustment);
        $this->assertEquals(3.0, (float) $adjustment->difference);

        // Gain value = 3 * 75.00 = 225.00
        // Dr Inventory Asset = increased by 225
        $expectedInvBalance = $initialInvBalance + 225.00;
        $this->assertEquals($expectedInvBalance, $this->accountService->getBalance($this->invAssetAccount->id));

        // Cr Inventory Gain Account (4800) = 225 (revenue account, balance = credit - debit)
        $this->assertEquals(225.00, $this->accountService->getBalance($this->gainAccount->id));

        // Verify correct accounts were hit in journal items
        $journalEntry = JournalEntry::find($adjustment->journal_entry_id);
        $items = $journalEntry->items;
        $this->assertCount(2, $items);

        // Debit to inventory asset account
        $debitItem = $items->where('account_id', $this->invAssetAccount->id)->first();
        $this->assertNotNull($debitItem);
        $this->assertEquals(225.00, (float) $debitItem->debit);

        // Credit to gain account
        $creditItem = $items->where('account_id', $this->gainAccount->id)->first();
        $this->assertNotNull($creditItem);
        $this->assertEquals(225.00, (float) $creditItem->credit);

        // Batch should now show 13 remaining
        $batch->refresh();
        $this->assertEquals(13.0, (float) $batch->quantity_remaining);
    }

    /**
     * Test 21: Reconciliation respects period lock
     */
    public function test_reconciliation_respects_period_lock(): void
    {
        // Create a closed accounting period for June 2026
        AccountingPeriod::create([
            'period_name' => 'June 2026 (Recon)',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'is_closed' => true,
            'closed_by' => $this->admin->id,
            'closed_at' => now(),
        ]);

        $product = Product::create([
            'sku' => 'FG-PERIOD-RECON',
            'name' => 'Period Lock Recon Test',
            'unit' => 'pcs',
            'cost' => 10.00,
            'price' => 20.00,
        ]);

        $batch = $this->inventoryService->createBatch(
            $product->id,
            $this->warehouse->id,
            'adjustment',
            1,
            100.0,
            10.00
        );

        // Attempt to reconcile within the closed period
        $response = $this->post('/inventory/reconciliation', [
            'inventory_batch_id' => $batch->id,
            'physical_quantity' => 90.0,
            'adjustment_date' => '2026-06-16', // Within closed period
            'reason' => 'Should be blocked',
        ]);

        $response->assertSessionHasErrors(['period']);

        // Batch should not have been modified
        $batch->refresh();
        $this->assertEquals(100.0, (float) $batch->quantity_remaining);

        // No adjustment record should exist
        $this->assertEquals(0, InventoryAdjustment::count());
    }

    // =========================================================
    // PHASE 4: PRODUCTION VARIANCE TESTS
    // =========================================================

    /**
     * Test 22: Production variance calculation is correct
     */
    public function test_production_variance_calculation_correct(): void
    {
        // Create raw stone product
        $rawStone = Product::create([
            'sku' => 'RS-VAR-01',
            'name' => 'Variance Test Raw Stone',
            'unit' => 'MT',
            'cost' => 500.00,
            'price' => 0,
            'product_type' => 'raw_stone',
            'inventory_account_id' => $this->rawAssetAccount->id,
            'cogs_account_id' => $this->cogsRawStone->id,
            'revenue_account_id' => $this->rawStoneRevenue->id,
        ]);

        // Create slab product
        $slab = Product::create([
            'sku' => 'SL-VAR-01',
            'name' => 'Variance Test Slab',
            'unit' => 'MT',
            'cost' => 0,
            'price' => 1000.00,
            'product_type' => 'slab',
            'inventory_account_id' => $this->slabAssetAccount->id,
            'cogs_account_id' => $this->cogsSlabs->id,
            'revenue_account_id' => $this->slabRevenue->id,
        ]);

        // Create BOM with standard labor=100, overhead=50 per MT
        $bom = Bom::create([
            'product_id' => $slab->id,
            'name' => 'Variance Slab BOM',
            'labor_cost_estimate' => 100.00,
            'overhead_cost_estimate' => 50.00,
        ]);
        BomItem::create(['bom_id' => $bom->id, 'product_id' => $rawStone->id, 'quantity' => 1]);

        // Create raw stone batch (10 MT @ 500/MT)
        $batch = $this->inventoryService->createBatch(
            $rawStone->id,
            $this->warehouse->id,
            'purchase',
            1,
            10.0,
            500.00,
            'MT',
            'raw_stone',
            'RS-BATCH-VAR'
        );

        // Run stone-to-slab production: 5MT input, 4MT slabs, 1MT wastage
        // Actual labor=200, actual overhead components total 800
        $response = $this->post('/production/stone-to-slab', [
            'input_batch_id' => $batch->id,
            'input_quantity' => 5.0,
            'slab_product_id' => $slab->id,
            'slabs_produced' => 4.0,
            'wastage_quantity' => 1.0,
            'scrap_product_id' => null,
            'scrap_quantity' => 0,
            'scrap_unit' => null,
            'labor_cost' => 200.00,
            'electricity_cost' => 300.00,
            'fuel_cost' => 100.00,
            'maintenance_cost' => 100.00,
            'overhead_cost' => 100.00,
            'production_date' => '2026-06-17',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response->assertSessionHasNoErrors();

        // Find the created production run
        $run = StoneToSlabProduction::latest()->first();
        $this->assertNotNull($run);

        // Actual total = material(5×500=2500) + additionalCosts(200+300+100+100+100=800) = 3300
        $this->assertEquals(3300.0, (float) $run->total_cost);

        // Expected material = consumed cost = 2500 (same as actual for direct conversion)
        // Expected labor = BOM labor_est × input_qty = 100 × 5 = 500
        // Expected overhead = BOM overhead_est × input_qty = 50 × 5 = 250
        // Expected total = 2500 + 500 + 250 = 3250
        $this->assertEquals(2500.0, (float) $run->expected_material_cost);
        $this->assertEquals(500.0, (float) $run->expected_labor_cost);
        $this->assertEquals(250.0, (float) $run->expected_overhead_cost);
        $this->assertEquals(3250.0, (float) $run->expected_total_cost);

        // Variance = actual(3300) - expected(3250) = 50 (unfavorable)
        // Note: actual is material(2500) + additionalCosts(800) + 0 = 3300
        // But the service receives: actualMaterial=2500, actualLabor=800, actualOverhead=0 → total=3300
        $actualTotal = 2500.0 + 800.0 + 0.0;
        $expectedTotal = 2500.0 + 500.0 + 250.0;
        $expectedVariance = $actualTotal - $expectedTotal; // 3300 - 3250 = 50

        $this->assertEquals($expectedVariance, (float) $run->variance_amount);
        $this->assertFalse((bool) $run->is_favorable); // positive variance = unfavorable
    }

    /**
     * Test 23: Unfavorable variance posts to expense account
     */
    public function test_unfavorable_variance_posts_expense_account(): void
    {
        $rawStone = Product::create([
            'sku' => 'RS-UNFAV-01',
            'name' => 'Unfav Variance Raw Stone',
            'unit' => 'MT',
            'cost' => 300.00,
            'price' => 0,
            'product_type' => 'raw_stone',
            'inventory_account_id' => $this->rawAssetAccount->id,
            'cogs_account_id' => $this->cogsRawStone->id,
            'revenue_account_id' => $this->rawStoneRevenue->id,
        ]);

        $slab = Product::create([
            'sku' => 'SL-UNFAV-01',
            'name' => 'Unfav Variance Slab',
            'unit' => 'MT',
            'cost' => 0,
            'price' => 800.00,
            'product_type' => 'slab',
            'inventory_account_id' => $this->slabAssetAccount->id,
            'cogs_account_id' => $this->cogsSlabs->id,
            'revenue_account_id' => $this->slabRevenue->id,
        ]);

        // BOM with low standards: labor=10, overhead=5 per unit
        $bom = Bom::create([
            'product_id' => $slab->id,
            'name' => 'Low Standard BOM',
            'labor_cost_estimate' => 10.00,
            'overhead_cost_estimate' => 5.00,
        ]);
        BomItem::create(['bom_id' => $bom->id, 'product_id' => $rawStone->id, 'quantity' => 1]);

        $batch = $this->inventoryService->createBatch(
            $rawStone->id,
            $this->warehouse->id,
            'purchase',
            1,
            10.0,
            300.00,
            'MT',
            'raw_stone',
            'RS-UNFAV-BATCH'
        );

        // High actual costs → unfavorable
        $response = $this->post('/production/stone-to-slab', [
            'input_batch_id' => $batch->id,
            'input_quantity' => 2.0,
            'slab_product_id' => $slab->id,
            'slabs_produced' => 1.5,
            'wastage_quantity' => 0.5,
            'scrap_product_id' => null,
            'scrap_quantity' => 0,
            'scrap_unit' => null,
            'labor_cost' => 500.00,    // Way over BOM standard of 10×2=20
            'electricity_cost' => 200.00,
            'fuel_cost' => 100.00,
            'maintenance_cost' => 50.00,
            'overhead_cost' => 50.00,
            'production_date' => '2026-06-17',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response->assertSessionHasNoErrors();

        $run = StoneToSlabProduction::latest()->first();
        $this->assertNotNull($run);

        // Variance should be unfavorable (positive)
        $this->assertGreaterThan(0, (float) $run->variance_amount);
        $this->assertFalse((bool) $run->is_favorable);

        // Variance journal entry should exist
        $this->assertNotNull($run->variance_journal_entry_id);
        $varianceJE = JournalEntry::find($run->variance_journal_entry_id);
        $this->assertNotNull($varianceJE);

        // Should be balanced
        $items = $varianceJE->items;
        $totalDebit = $items->sum('debit');
        $totalCredit = $items->sum('credit');
        $this->assertTrue(abs($totalDebit - $totalCredit) < 0.0001, 'Variance journal must be balanced');

        // Dr Production Variance Expense (5900)
        $debitItem = $items->where('account_id', $this->varianceExpenseAccount->id)->first();
        $this->assertNotNull($debitItem, 'Debit to variance expense account expected');
        $this->assertGreaterThan(0, (float) $debitItem->debit);

        // Cr WIP
        $creditItem = $items->where('account_id', $this->wipAssetAccount->id)->first();
        $this->assertNotNull($creditItem, 'Credit to WIP account expected');
        $this->assertGreaterThan(0, (float) $creditItem->credit);
    }

    /**
     * Test 24: Favorable variance posts to gain account
     */
    public function test_favorable_variance_posts_gain_account(): void
    {
        $rawStone = Product::create([
            'sku' => 'RS-FAV-01',
            'name' => 'Fav Variance Raw Stone',
            'unit' => 'MT',
            'cost' => 100.00,
            'price' => 0,
            'product_type' => 'raw_stone',
            'inventory_account_id' => $this->rawAssetAccount->id,
            'cogs_account_id' => $this->cogsRawStone->id,
            'revenue_account_id' => $this->rawStoneRevenue->id,
        ]);

        $slab = Product::create([
            'sku' => 'SL-FAV-01',
            'name' => 'Fav Variance Slab',
            'unit' => 'MT',
            'cost' => 0,
            'price' => 500.00,
            'product_type' => 'slab',
            'inventory_account_id' => $this->slabAssetAccount->id,
            'cogs_account_id' => $this->cogsSlabs->id,
            'revenue_account_id' => $this->slabRevenue->id,
        ]);

        // BOM with HIGH standards: labor=1000, overhead=500 per unit
        $bom = Bom::create([
            'product_id' => $slab->id,
            'name' => 'High Standard BOM',
            'labor_cost_estimate' => 1000.00,
            'overhead_cost_estimate' => 500.00,
        ]);
        BomItem::create(['bom_id' => $bom->id, 'product_id' => $rawStone->id, 'quantity' => 1]);

        $batch = $this->inventoryService->createBatch(
            $rawStone->id,
            $this->warehouse->id,
            'purchase',
            1,
            10.0,
            100.00,
            'MT',
            'raw_stone',
            'RS-FAV-BATCH'
        );

        // Low actual costs → favorable
        $response = $this->post('/production/stone-to-slab', [
            'input_batch_id' => $batch->id,
            'input_quantity' => 3.0,
            'slab_product_id' => $slab->id,
            'slabs_produced' => 2.5,
            'wastage_quantity' => 0.5,
            'scrap_product_id' => null,
            'scrap_quantity' => 0,
            'scrap_unit' => null,
            'labor_cost' => 50.00,     // Way under BOM standard of 1000×3=3000
            'electricity_cost' => 10.00,
            'fuel_cost' => 5.00,
            'maintenance_cost' => 5.00,
            'overhead_cost' => 10.00,
            'production_date' => '2026-06-17',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response->assertSessionHasNoErrors();

        $run = StoneToSlabProduction::latest()->first();
        $this->assertNotNull($run);

        // Variance should be favorable (negative)
        $this->assertLessThan(0, (float) $run->variance_amount);
        $this->assertTrue((bool) $run->is_favorable);

        // Variance journal should exist
        $this->assertNotNull($run->variance_journal_entry_id);
        $varianceJE = JournalEntry::find($run->variance_journal_entry_id);
        $this->assertNotNull($varianceJE);

        // Should be balanced
        $items = $varianceJE->items;
        $totalDebit = $items->sum('debit');
        $totalCredit = $items->sum('credit');
        $this->assertTrue(abs($totalDebit - $totalCredit) < 0.0001, 'Variance journal must be balanced');

        // Dr WIP (favorable = Dr WIP)
        $debitItem = $items->where('account_id', $this->wipAssetAccount->id)->first();
        $this->assertNotNull($debitItem, 'Debit to WIP account expected');
        $this->assertGreaterThan(0, (float) $debitItem->debit);

        // Cr Production Variance Gain (4900)
        $creditItem = $items->where('account_id', $this->varianceGainAccount->id)->first();
        $this->assertNotNull($creditItem, 'Credit to variance gain account expected');
        $this->assertGreaterThan(0, (float) $creditItem->credit);
    }

    /**
     * Test 25: Production variance respects period lock
     */
    public function test_variance_respects_period_lock(): void
    {
        // Close the period
        AccountingPeriod::create([
            'period_name' => 'June 2026 (Variance)',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'is_closed' => true,
            'closed_by' => $this->admin->id,
            'closed_at' => now(),
        ]);

        $rawStone = Product::create([
            'sku' => 'RS-LOCK-VAR',
            'name' => 'Period Lock Variance Stone',
            'unit' => 'MT',
            'cost' => 100.00,
            'price' => 0,
            'product_type' => 'raw_stone',
            'inventory_account_id' => $this->rawAssetAccount->id,
            'cogs_account_id' => $this->cogsRawStone->id,
            'revenue_account_id' => $this->rawStoneRevenue->id,
        ]);

        $slab = Product::create([
            'sku' => 'SL-LOCK-VAR',
            'name' => 'Period Lock Variance Slab',
            'unit' => 'MT',
            'cost' => 0,
            'price' => 100.00,
            'product_type' => 'slab',
            'inventory_account_id' => $this->slabAssetAccount->id,
            'cogs_account_id' => $this->cogsSlabs->id,
            'revenue_account_id' => $this->slabRevenue->id,
        ]);

        $batch = $this->inventoryService->createBatch(
            $rawStone->id,
            $this->warehouse->id,
            'purchase',
            1,
            10.0,
            100.00,
            'MT',
            'raw_stone',
            'RS-LOCK-BATCH'
        );

        // Try to post production in closed period
        $response = $this->post('/production/stone-to-slab', [
            'input_batch_id' => $batch->id,
            'input_quantity' => 2.0,
            'slab_product_id' => $slab->id,
            'slabs_produced' => 1.5,
            'wastage_quantity' => 0.5,
            'scrap_product_id' => null,
            'scrap_quantity' => 0,
            'scrap_unit' => null,
            'labor_cost' => 50.00,
            'electricity_cost' => 10.00,
            'fuel_cost' => 5.00,
            'maintenance_cost' => 5.00,
            'overhead_cost' => 10.00,
            'production_date' => '2026-06-17',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response->assertSessionHasErrors(['period']);

        // No production run should have been created
        $this->assertEquals(0, StoneToSlabProduction::count());
    }

    // =========================================================
    // PHASE 5: BANK RECONCILIATION TESTS
    // =========================================================

    /**
     * Test 26: Open bank reconciliation calculates difference correctly
     */
    public function test_open_bank_reconciliation_calculates_difference(): void
    {
        // Add some transactions to the bank account
        BankTransaction::create([
            'bank_account_id' => $this->hblBankAccount->id,
            'date' => '2026-06-10',
            'type' => 'deposit',
            'amount' => 1000.00,
            'reference_type' => 'manual',
            'reference_id' => 1,
            'description' => 'Initial deposit',
        ]);
        BankTransaction::create([
            'bank_account_id' => $this->hblBankAccount->id,
            'date' => '2026-06-12',
            'type' => 'withdrawal',
            'amount' => -200.00,
            'reference_type' => 'manual',
            'reference_id' => 2,
            'description' => 'Vendor payment',
        ]);

        // System balance should be 800.00
        $response = $this->post('/cash-bank/reconciliation/open', [
            'bank_account_id' => $this->hblBankAccount->id,
            'statement_date' => '2026-06-15',
            'statement_balance' => 800.00,
        ]);

        $response->assertSessionHasNoErrors();

        $recon = BankReconciliation::first();
        $this->assertNotNull($recon);
        $this->assertEquals(800.00, (float) $recon->system_balance);
        $this->assertEquals(0.00, (float) $recon->difference);
        $this->assertFalse((bool) $recon->is_closed);
    }

    /**
     * Test 27: Bulk reconcile marks transactions through the statement date
     */
    public function test_bulk_reconcile_to_date_marks_transactions(): void
    {
        BankTransaction::create([
            'bank_account_id' => $this->hblBankAccount->id,
            'date' => '2026-06-10',
            'type' => 'deposit',
            'amount' => 500.00,
            'reference_type' => 'manual',
            'reference_id' => 1,
            'description' => 'Deposit 1',
        ]);

        $recon = $this->bankReconciliationService->openReconciliation(
            $this->hblBankAccount->id,
            '2026-06-15',
            500.00
        );

        $response = $this->post('/cash-bank/reconciliation/bulk-reconcile-to-date', [
            'reconciliation_id' => $recon->id,
            'statement_date' => '2026-06-15',
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertEquals(1, BankTransaction::where('is_reconciled', true)->count());
        $recon->refresh();
        $this->assertEquals(0.00, (float) $recon->difference);
    }

    /**
     * Test 28: Cannot close reconciliation if difference not zero
     */
    public function test_cannot_close_reconciliation_if_difference_not_zero(): void
    {
        BankTransaction::create([
            'bank_account_id' => $this->hblBankAccount->id,
            'date' => '2026-06-10',
            'type' => 'deposit',
            'amount' => 500.00,
            'reference_type' => 'manual',
            'reference_id' => 1,
            'description' => 'Deposit',
        ]);

        $recon = $this->bankReconciliationService->openReconciliation(
            $this->hblBankAccount->id,
            '2026-06-15',
            600.00 // Statement is 600, system is 500
        );

        $this->bankReconciliationService->bulkReconcileToStatementDate($recon->id);

        $response = $this->post('/cash-bank/reconciliation/close', [
            'reconciliation_id' => $recon->id,
            'statement_date' => '2026-06-15',
        ]);

        $response->assertSessionHasErrors(['reconciliation']);

        $recon->refresh();
        $this->assertFalse((bool) $recon->is_closed);
    }

    /**
     * Test 29: Close reconciliation when balanced
     */
    public function test_close_reconciliation_when_balanced(): void
    {
        BankTransaction::create([
            'bank_account_id' => $this->hblBankAccount->id,
            'date' => '2026-06-10',
            'type' => 'deposit',
            'amount' => 500.00,
            'reference_type' => 'manual',
            'reference_id' => 1,
            'description' => 'Deposit',
        ]);

        $recon = $this->bankReconciliationService->openReconciliation(
            $this->hblBankAccount->id,
            '2026-06-15',
            500.00
        );

        $this->bankReconciliationService->bulkReconcileToStatementDate($recon->id);

        $response = $this->post('/cash-bank/reconciliation/close', [
            'reconciliation_id' => $recon->id,
            'statement_date' => '2026-06-15',
        ]);

        $response->assertSessionHasNoErrors();

        $recon->refresh();
        $this->assertTrue((bool) $recon->is_closed);
        $this->assertNotNull($recon->closed_by);
        $this->assertNotNull($recon->closed_at);
    }

    /**
     * Test 30: Reconciliation respects period lock
     */
    public function test_bank_reconciliation_respects_period_lock(): void
    {
        AccountingPeriod::create([
            'period_name' => 'June 2026',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'is_closed' => true,
            'closed_by' => $this->admin->id,
            'closed_at' => now(),
        ]);

        $response = $this->post('/cash-bank/reconciliation/open', [
            'bank_account_id' => $this->hblBankAccount->id,
            'statement_date' => '2026-06-15',
            'statement_balance' => 800.00,
        ]);

        $response->assertSessionHasErrors(['period']);
        $this->assertEquals(0, BankReconciliation::count());
    }

    // =========================================================
    // PHASE 6: MULTI-WAREHOUSE TRANSFERS TESTS
    // =========================================================

    /**
     * Test 31: Partial batch transfer creates new batch
     */
    public function test_partial_batch_transfer_creates_new_batch(): void
    {
        // Add a new warehouse
        $newWarehouse = Warehouse::create([
            'name' => 'Secondary Warehouse',
            'location' => 'Zone B',
        ]);

        $stoneProduct = Product::create([
            'sku' => 'RM-STONE',
            'name' => 'Raw Marble Stone',
            'unit' => 'tons',
            'cost' => 500.00,
            'price' => 800.00,
        ]);

        $sourceBatch = InventoryBatch::create([
            'product_id' => $stoneProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'reference_type' => 'adjustment',
            'reference_id' => 1,
            'quantity_received' => 100,
            'quantity_remaining' => 100,
            'unit_cost' => 500.00,
            'total_cost' => 50000.00,
            'received_at' => now(),
            'unit_of_measure' => 'tons',
            'product_type' => 'raw',
        ]);

        $initialQty = (float) $sourceBatch->quantity_remaining;
        $transferQty = 2.0;

        $response = $this->post('/inventory/transfers', [
            'source_batch_id' => $sourceBatch->id,
            'to_warehouse_id' => $newWarehouse->id,
            'quantity' => $transferQty,
            'transfer_date' => '2026-06-17',
        ]);

        $response->assertSessionHasNoErrors();

        $sourceBatch->refresh();
        $this->assertEquals($initialQty - $transferQty, (float) $sourceBatch->quantity_remaining);

        // Verify destination batch
        $destBatch = InventoryBatch::where('product_id', $stoneProduct->id)
            ->where('warehouse_id', $newWarehouse->id)
            ->first();

        $this->assertNotNull($destBatch);
        $this->assertEquals($transferQty, (float) $destBatch->quantity_remaining);
        $this->assertEquals((float) $sourceBatch->unit_cost, (float) $destBatch->unit_cost);

        $transfer = InventoryTransfer::first();
        $this->assertNotNull($transfer);
        $this->assertEquals($sourceBatch->id, $transfer->source_batch_id);
        $this->assertEquals($destBatch->id, $transfer->destination_batch_id);
        $this->assertEquals($newWarehouse->id, $transfer->to_warehouse_id);
    }

    /**
     * Test 32: Transfer creates stock ledger entries
     */
    public function test_transfer_creates_stock_ledger_entries(): void
    {
        $newWarehouse = Warehouse::create([
            'name' => 'Secondary Warehouse',
            'location' => 'Zone B',
        ]);

        $stoneProduct = Product::create([
            'sku' => 'RM-STONE',
            'name' => 'Raw Marble Stone',
            'unit' => 'tons',
            'cost' => 500.00,
            'price' => 800.00,
        ]);

        $sourceBatch = InventoryBatch::create([
            'product_id' => $stoneProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'reference_type' => 'adjustment',
            'reference_id' => 1,
            'quantity_received' => 100,
            'quantity_remaining' => 100,
            'unit_cost' => 500.00,
            'total_cost' => 50000.00,
            'received_at' => now(),
            'unit_of_measure' => 'tons',
            'product_type' => 'raw',
        ]);

        $this->post('/inventory/transfers', [
            'source_batch_id' => $sourceBatch->id,
            'to_warehouse_id' => $newWarehouse->id,
            'quantity' => 1.0,
            'transfer_date' => '2026-06-17',
        ]);

        $transfer = InventoryTransfer::first();

        // Check OUT
        $outLedger = StockLedger::where('reference_type', 'transfer')
            ->where('reference_id', $transfer->id)
            ->where('type', 'OUT')
            ->first();

        $this->assertNotNull($outLedger);
        $this->assertEquals(-1.0, (float) $outLedger->quantity);
        $this->assertEquals($sourceBatch->warehouse_id, $outLedger->warehouse_id);

        // Check IN
        $inLedger = StockLedger::where('reference_type', 'transfer')
            ->where('reference_id', $transfer->id)
            ->where('type', 'IN')
            ->first();

        $this->assertNotNull($inLedger);
        $this->assertEquals(1.0, (float) $inLedger->quantity);
        $this->assertEquals($newWarehouse->id, $inLedger->warehouse_id);
    }

    /**
     * Test 33: Transfer does not post journal entry
     */
    public function test_transfer_does_not_post_journal_entry(): void
    {
        $newWarehouse = Warehouse::create([
            'name' => 'Secondary Warehouse',
            'location' => 'Zone B',
        ]);

        $stoneProduct = Product::create([
            'sku' => 'RM-STONE',
            'name' => 'Raw Marble Stone',
            'unit' => 'tons',
            'cost' => 500.00,
            'price' => 800.00,
        ]);

        $sourceBatch = InventoryBatch::create([
            'product_id' => $stoneProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'reference_type' => 'adjustment',
            'reference_id' => 1,
            'quantity_received' => 100,
            'quantity_remaining' => 100,
            'unit_cost' => 500.00,
            'total_cost' => 50000.00,
            'received_at' => now(),
            'unit_of_measure' => 'tons',
            'product_type' => 'raw',
        ]);

        $initialJournalCount = JournalEntry::count();

        $this->post('/inventory/transfers', [
            'source_batch_id' => $sourceBatch->id,
            'to_warehouse_id' => $newWarehouse->id,
            'quantity' => 1.0,
            'transfer_date' => '2026-06-17',
        ]);

        // Journals should remain unchanged
        $this->assertEquals($initialJournalCount, JournalEntry::count());
    }

    /**
     * Test 34: Transfer respects period lock
     */
    public function test_transfer_respects_period_lock(): void
    {
        AccountingPeriod::create([
            'period_name' => 'June 2026',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'is_closed' => true,
            'closed_by' => $this->admin->id,
            'closed_at' => now(),
        ]);

        $newWarehouse = Warehouse::create([
            'name' => 'Secondary Warehouse',
            'location' => 'Zone B',
        ]);

        $stoneProduct = Product::create([
            'sku' => 'RM-STONE',
            'name' => 'Raw Marble Stone',
            'unit' => 'tons',
            'cost' => 500.00,
            'price' => 800.00,
        ]);

        $sourceBatch = InventoryBatch::create([
            'product_id' => $stoneProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'reference_type' => 'adjustment',
            'reference_id' => 1,
            'quantity_received' => 100,
            'quantity_remaining' => 100,
            'unit_cost' => 500.00,
            'total_cost' => 50000.00,
            'received_at' => now(),
            'unit_of_measure' => 'tons',
            'product_type' => 'raw',
        ]);

        $response = $this->post('/inventory/transfers', [
            'source_batch_id' => $sourceBatch->id,
            'to_warehouse_id' => $newWarehouse->id,
            'quantity' => 1.0,
            'transfer_date' => '2026-06-17',
        ]);

        $response->assertSessionHasErrors(['period']);
        $this->assertEquals(0, InventoryTransfer::count());
    }

    /**
     * Test 35: Transfer prevents over quantity
     */
    public function test_transfer_prevents_over_quantity(): void
    {
        $newWarehouse = Warehouse::create([
            'name' => 'Secondary Warehouse',
            'location' => 'Zone B',
        ]);

        $stoneProduct = Product::create([
            'sku' => 'RM-STONE',
            'name' => 'Raw Marble Stone',
            'unit' => 'tons',
            'cost' => 500.00,
            'price' => 800.00,
        ]);

        $sourceBatch = InventoryBatch::create([
            'product_id' => $stoneProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'reference_type' => 'adjustment',
            'reference_id' => 1,
            'quantity_received' => 100,
            'quantity_remaining' => 100,
            'unit_cost' => 500.00,
            'total_cost' => 50000.00,
            'received_at' => now(),
            'unit_of_measure' => 'tons',
            'product_type' => 'raw',
        ]);

        $overQty = (float) $sourceBatch->quantity_remaining + 10;

        $response = $this->post('/inventory/transfers', [
            'source_batch_id' => $sourceBatch->id,
            'to_warehouse_id' => $newWarehouse->id,
            'quantity' => $overQty,
            'transfer_date' => '2026-06-17',
        ]);

        $response->assertSessionHasErrors(['transfer']);
        $this->assertEquals(0, InventoryTransfer::count());
    }

    /**
     * Test 36: Dashboard Financial KPIs
     */
    public function test_dashboard_financial_kpis_correct(): void
    {
        $service = app(DashboardService::class);

        // Record some dummy revenue
        $je = JournalEntry::create([
            'date' => '2026-06-17',
            'entry_number' => 'JE-9999',
            'reference' => 'TEST-REV',
            'description' => 'Test Revenue',
            'created_by' => $this->admin->id,
        ]);
        JournalItem::create([
            'journal_entry_id' => $je->id,
            'account_id' => $this->arAccount->id,
            'debit' => 1000,
            'credit' => 0,
        ]);
        JournalItem::create([
            'journal_entry_id' => $je->id,
            'account_id' => $this->salesRevAccount->id,
            'debit' => 0,
            'credit' => 1000,
        ]);

        $kpis = $service->getFinancialKPIs(null, null);

        $this->assertEquals(1000, $kpis['totalRevenue']);
        $this->assertEquals(0, $kpis['totalCOGS']);
        $this->assertEquals(1000, $kpis['grossProfit']);
        $this->assertEquals(100, $kpis['grossMargin']);
        $this->assertEquals(1000, $kpis['netProfit']);
    }

    /**
     * Test 37: Dashboard Inventory Valuation
     */
    public function test_dashboard_inventory_valuation_correct(): void
    {
        $service = app(DashboardService::class);

        $stoneProduct = Product::create([
            'sku' => 'RM-STONE-2',
            'name' => 'Raw Marble Stone 2',
            'unit' => 'tons',
            'cost' => 500.00,
            'price' => 800.00,
            'product_type' => 'raw',
        ]);

        InventoryBatch::create([
            'product_id' => $stoneProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'reference_type' => 'adjustment',
            'reference_id' => 999,
            'quantity_received' => 10,
            'quantity_remaining' => 10,
            'unit_cost' => 500.00,
            'total_cost' => 5000.00,
            'received_at' => now(),
            'unit_of_measure' => 'tons',
            'product_type' => 'raw',
        ]);

        $val = $service->getInventoryValuation();

        $this->assertGreaterThanOrEqual(5000, $val['totalInventoryValue']);

        $hasWarehouse = false;
        foreach ($val['valuationByWarehouse'] as $w) {
            if ($w['name'] === $this->warehouse->name && $w['value'] >= 5000) {
                $hasWarehouse = true;
            }
        }
        $this->assertTrue($hasWarehouse);
    }

    /**
     * Test 38: Dashboard Production Metrics
     */
    public function test_dashboard_production_metrics_correct(): void
    {
        $service = app(DashboardService::class);

        $metrics = $service->getProductionMetrics(null, null);

        $this->assertArrayHasKey('totalProductionRuns', $metrics);
        $this->assertArrayHasKey('avgYield', $metrics);
        $this->assertArrayHasKey('totalVarianceAmount', $metrics);
    }

    /**
     * Test 39: Dashboard Respects Date Filter
     */
    public function test_dashboard_respects_date_filter(): void
    {
        $service = app(DashboardService::class);

        // Date range that excludes the TEST-REV from test 36 (2026-06-17)
        $kpis = $service->getFinancialKPIs('2026-06-18', '2026-06-19');

        $this->assertEquals(0, $kpis['totalRevenue']);
    }

    /**
     * Test 40: Landed Cost Allocated by Quantity
     */
    public function test_landed_cost_allocated_by_quantity(): void
    {
        $productA = Product::create([
            'sku' => 'LC-QTY-A',
            'name' => 'Basalt Block A',
            'unit' => 'pcs',
            'cost' => 100.00,
            'price' => 0.00,
            'product_type' => 'raw_stone',
            'unit_of_measure' => 'MT',
            'inventory_account_id' => $this->invAssetAccount->id,
            'cogs_account_id' => $this->cogsAccount->id,
            'revenue_account_id' => $this->salesRevAccount->id,
        ]);

        $productB = Product::create([
            'sku' => 'LC-QTY-B',
            'name' => 'Basalt Block B',
            'unit' => 'pcs',
            'cost' => 200.00,
            'price' => 0.00,
            'product_type' => 'raw_stone',
            'unit_of_measure' => 'MT',
            'inventory_account_id' => $this->invAssetAccount->id,
            'cogs_account_id' => $this->cogsAccount->id,
            'revenue_account_id' => $this->salesRevAccount->id,
        ]);

        // Quantities: Product A = 2, Product B = 8. Total Qty = 10.
        // Overheads: 10,000 PKR.
        // Qty ratio: A = 0.2, B = 0.8.
        // Allocated overhead: A = 2,000 PKR (1,000 per unit), B = 8,000 PKR (1,000 per unit).
        $response = $this->post('/purchases/store', [
            'contact_id' => Contact::create(['name' => 'Supplier LC Qty', 'type' => 'vendor'])->id,
            'order_date' => '2026-06-18',
            'warehouse_id' => $this->warehouse->id,
            'allocation_method' => 'quantity',
            'items' => [
                ['product_id' => $productA->id, 'quantity' => 2, 'unit_price' => 1000.00],
                ['product_id' => $productB->id, 'quantity' => 8, 'unit_price' => 2000.00],
            ],
            'transportation_cost' => 7000.00,
            'loading_cost' => 2000.00,
            'other_direct_cost' => 1000.00,
            'cash_paid' => 0.00,
            'bank_paid' => 0.00,
        ]);

        $response->assertSessionHasNoErrors();

        // Unit cost increases correctly:
        // Product A: 1000 base + 1000 LC = 2000
        $this->assertEquals(2000.00, $productA->fresh()->cost);
        // Product B: 2000 base + 1000 LC = 3000
        $this->assertEquals(3000.00, $productB->fresh()->cost);
    }

    /**
     * Test 41: Landed Cost Allocated by Value
     */
    public function test_landed_cost_allocated_by_value(): void
    {
        $productA = Product::create([
            'sku' => 'LC-VAL-A',
            'name' => 'Basalt Block C',
            'unit' => 'pcs',
            'cost' => 100.00,
            'price' => 0.00,
            'product_type' => 'raw_stone',
            'unit_of_measure' => 'MT',
            'inventory_account_id' => $this->invAssetAccount->id,
            'cogs_account_id' => $this->cogsAccount->id,
            'revenue_account_id' => $this->salesRevAccount->id,
        ]);

        $productB = Product::create([
            'sku' => 'LC-VAL-B',
            'name' => 'Basalt Block D',
            'unit' => 'pcs',
            'cost' => 200.00,
            'price' => 0.00,
            'product_type' => 'raw_stone',
            'unit_of_measure' => 'MT',
            'inventory_account_id' => $this->invAssetAccount->id,
            'cogs_account_id' => $this->cogsAccount->id,
            'revenue_account_id' => $this->salesRevAccount->id,
        ]);

        // Base price: A = 1 * 2,000 = 2,000. B = 1 * 8,000 = 8,000. Total = 10,000 PKR.
        // Overheads: 5,000 PKR.
        // Value ratio: A = 0.2, B = 0.8.
        // Allocated overhead: A = 1,000 PKR (1,000 per unit), B = 4,000 PKR (4,000 per unit).
        $response = $this->post('/purchases/store', [
            'contact_id' => Contact::create(['name' => 'Supplier LC Val', 'type' => 'vendor'])->id,
            'order_date' => '2026-06-18',
            'warehouse_id' => $this->warehouse->id,
            'allocation_method' => 'value',
            'items' => [
                ['product_id' => $productA->id, 'quantity' => 1, 'unit_price' => 2000.00],
                ['product_id' => $productB->id, 'quantity' => 1, 'unit_price' => 8000.00],
            ],
            'transportation_cost' => 3000.00,
            'loading_cost' => 1500.00,
            'other_direct_cost' => 500.00,
            'cash_paid' => 0.00,
            'bank_paid' => 0.00,
        ]);

        $response->assertSessionHasNoErrors();

        // Unit cost increases correctly:
        // Product A: 2000 base + 1000 LC = 3000
        $this->assertEquals(3000.00, $productA->fresh()->cost);
        // Product B: 8000 base + 4000 LC = 12000
        $this->assertEquals(12000.00, $productB->fresh()->cost);
    }

    /**
     * Test 42: Period Lock Blocks Landed Cost Posting
     */
    public function test_period_lock_blocks_landed_cost_posting(): void
    {
        $product = Product::create([
            'sku' => 'LC-LOCK',
            'name' => 'Basalt Block Lock',
            'unit' => 'pcs',
            'cost' => 100.00,
            'price' => 0.00,
            'product_type' => 'raw_stone',
            'unit_of_measure' => 'MT',
            'inventory_account_id' => $this->invAssetAccount->id,
            'cogs_account_id' => $this->cogsAccount->id,
            'revenue_account_id' => $this->salesRevAccount->id,
        ]);

        // Create a closed period for June 2026
        AccountingPeriod::create([
            'period_name' => 'June 2026',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'is_closed' => true,
        ]);

        // Try to post on 2026-06-15 (closed period)
        $response = $this->post('/purchases/store', [
            'contact_id' => Contact::create(['name' => 'Supplier Locked', 'type' => 'vendor'])->id,
            'order_date' => '2026-06-15',
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000.00],
            ],
            'transportation_cost' => 500.00,
            'loading_cost' => 0.00,
            'other_direct_cost' => 0.00,
            'cash_paid' => 0.00,
            'bank_paid' => 0.00,
        ]);

        $response->assertSessionHasErrors(['period']);
    }

    /**
     * Test 43: Invalid Negative Landed Cost Fails Validation
     */
    public function test_invalid_negative_landed_cost_fails_validation(): void
    {
        $product = Product::create([
            'sku' => 'LC-NEG',
            'name' => 'Basalt Block Neg',
            'unit' => 'pcs',
            'cost' => 100.00,
            'price' => 0.00,
            'product_type' => 'raw_stone',
            'unit_of_measure' => 'MT',
            'inventory_account_id' => $this->invAssetAccount->id,
            'cogs_account_id' => $this->cogsAccount->id,
            'revenue_account_id' => $this->salesRevAccount->id,
        ]);

        $response = $this->post('/purchases/store', [
            'contact_id' => Contact::create(['name' => 'Supplier Neg', 'type' => 'vendor'])->id,
            'order_date' => '2026-06-18',
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000.00],
            ],
            'transportation_cost' => -100.00, // Negative transportation cost
            'loading_cost' => 0.00,
            'other_direct_cost' => 0.00,
            'cash_paid' => 0.00,
            'bank_paid' => 0.00,
        ]);

        $response->assertSessionHasErrors(['transportation_cost']);
    }

    /**
     * Test 44: Gate Pass Route Exists and Renders for Transfer
     */
    public function test_gate_pass_route_exists_and_renders(): void
    {
        $product = Product::create([
            'sku' => 'GP-TEST-P',
            'name' => 'Basalt Block GP',
            'unit' => 'pcs',
            'cost' => 100.00,
            'price' => 0.00,
            'product_type' => 'raw_stone',
            'unit_of_measure' => 'MT',
            'inventory_account_id' => $this->invAssetAccount->id,
            'cogs_account_id' => $this->cogsAccount->id,
            'revenue_account_id' => $this->salesRevAccount->id,
        ]);

        $batch = $this->inventoryService->createBatch(
            $product->id,
            $this->warehouse->id,
            'adjustment',
            1,
            10.0,
            100.0,
            'MT',
            'raw_stone'
        );

        $destWarehouse = Warehouse::create(['name' => 'Depot B', 'location' => 'SITE Karachi']);

        $transfer = $this->transferService->transferBatch(
            $batch->id,
            $destWarehouse->id,
            5.0,
            '2026-06-18',
            [
                'vehicle_number' => 'LES-1234',
                'driver_name' => 'Muhammad Ali',
                'receiver_name' => 'Siddique',
                'authorized_by_name' => 'Manager',
                'remarks' => 'Internal transfer',
            ]
        );

        $response = $this->get("/inventory/transfers/{$transfer->id}/gate-pass");
        $response->assertStatus(200);
    }

    /**
     * Test 45: Period Close Blocked by Unbalanced Journal Entry
     */
    public function test_period_close_blocked_by_unbalanced_journal(): void
    {
        $period = AccountingPeriod::create([
            'period_name' => 'July 2026',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'is_closed' => false,
        ]);

        // Create an unbalanced journal entry directly bypassing service validation for testing
        $je = JournalEntry::create([
            'entry_number' => 'JV-UNBAL-99',
            'date' => '2026-07-15',
            'description' => 'Unbalanced Entry',
        ]);
        JournalItem::create([
            'journal_entry_id' => $je->id,
            'account_id' => $this->arAccount->id,
            'debit' => 100.00,
            'credit' => 0.00,
        ]);
        JournalItem::create([
            'journal_entry_id' => $je->id,
            'account_id' => $this->salesRevAccount->id,
            'debit' => 0.00,
            'credit' => 90.00, // Unbalanced by $10
        ]);

        $response = $this->post("/accounting/periods/{$period->id}/close");
        $response->assertSessionHasErrors(['pre_close_checks']);
        $this->assertFalse($period->fresh()->is_closed);
    }

    /**
     * Test 46: Clean Period Can Close Successfully
     */
    public function test_clean_period_can_close_successfully(): void
    {
        $period = AccountingPeriod::create([
            'period_name' => 'August 2026',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'is_closed' => false,
        ]);

        $response = $this->post("/accounting/periods/{$period->id}/close");
        $response->assertSessionHasNoErrors();
        $this->assertTrue($period->fresh()->is_closed);
    }

    /**
     * Test 47: Production Schedule Date and Machine Validation
     */
    public function test_schedule_creation_date_and_machine_validation(): void
    {
        $product = Product::create([
            'sku' => 'RM-BLOCK-SCH-1',
            'name' => 'Basalt Block',
            'unit' => 'pcs',
            'cost' => 100.00,
            'price' => 0.00,
            'product_type' => 'raw_stone',
            'unit_of_measure' => 'MT',
        ]);

        // 1. Rejects if end date is equal or before start date
        $response = $this->post('/production/schedules', [
            'production_type' => 'stone_to_slab',
            'machine' => 'Gang Saw 1',
            'planned_start_date' => '2026-06-25 10:00:00',
            'planned_end_date' => '2026-06-25 09:00:00', // invalid
            'priority' => 'normal',
            'planned_input_product_id' => $product->id,
            'planned_input_quantity' => 10.0,
            'expected_output_quantity' => 7.0,
        ]);
        $response->assertSessionHasErrors(['planned_end_date']);

        // 2. Rejects empty machine name after trim
        $response = $this->post('/production/schedules', [
            'production_type' => 'stone_to_slab',
            'machine' => '   ', // empty after trim
            'planned_start_date' => '2026-06-25 10:00:00',
            'planned_end_date' => '2026-06-25 12:00:00',
            'priority' => 'normal',
            'planned_input_product_id' => $product->id,
            'planned_input_quantity' => 10.0,
            'expected_output_quantity' => 7.0,
        ]);
        $response->assertSessionHasErrors(['machine']);

        // 3. Succeeds with valid input and normalizes machine name
        $response = $this->post('/production/schedules', [
            'production_type' => 'stone_to_slab',
            'machine' => '  Gang Saw 1   ',
            'planned_start_date' => '2026-06-25 10:00:00',
            'planned_end_date' => '2026-06-25 12:00:00',
            'priority' => 'normal',
            'planned_input_product_id' => $product->id,
            'planned_input_quantity' => 10.0,
            'expected_output_quantity' => 7.0,
        ]);
        $response->assertSessionHasNoErrors();

        $schedule = ProductionSchedule::first();
        $this->assertEquals('Gang Saw 1', $schedule->machine);
        $this->assertEquals('planned', $schedule->status);
    }

    /**
     * Test 48: Machine Booking Overlap Cases Validation
     */
    public function test_machine_booking_overlap_validation(): void
    {
        $product = Product::create([
            'sku' => 'RM-BLOCK-SCH-2',
            'name' => 'Basalt Block 2',
            'unit' => 'pcs',
            'cost' => 100.00,
            'price' => 0.00,
            'product_type' => 'raw_stone',
            'unit_of_measure' => 'MT',
        ]);

        // Create initial schedule: Gang Saw 1, 10:00 - 12:00
        $sched = ProductionSchedule::create([
            'schedule_number' => 'SCH-BASE',
            'production_type' => 'stone_to_slab',
            'machine' => 'Gang Saw 1',
            'planned_start_date' => '2026-06-25 10:00:00',
            'planned_end_date' => '2026-06-25 12:00:00',
            'priority' => 'normal',
            'planned_input_product_id' => $product->id,
            'planned_input_quantity' => 10.0,
            'expected_output_quantity' => 7.0,
            'status' => 'planned',
        ]);

        // Case 1: New start inside existing schedule (11:00 - 13:00)
        $response = $this->post('/production/schedules', [
            'production_type' => 'stone_to_slab',
            'machine' => 'Gang Saw 1',
            'planned_start_date' => '2026-06-25 11:00:00',
            'planned_end_date' => '2026-06-25 13:00:00',
            'priority' => 'normal',
            'planned_input_product_id' => $product->id,
            'planned_input_quantity' => 10.0,
            'expected_output_quantity' => 7.0,
        ]);
        $response->assertSessionHasErrors(['machine']);

        // Case 2: New end inside existing schedule (09:00 - 11:00)
        $response = $this->post('/production/schedules', [
            'production_type' => 'stone_to_slab',
            'machine' => 'Gang Saw 1',
            'planned_start_date' => '2026-06-25 09:00:00',
            'planned_end_date' => '2026-06-25 11:00:00',
            'priority' => 'normal',
            'planned_input_product_id' => $product->id,
            'planned_input_quantity' => 10.0,
            'expected_output_quantity' => 7.0,
        ]);
        $response->assertSessionHasErrors(['machine']);

        // Case 3: New schedule surrounds existing schedule (09:00 - 13:00)
        $response = $this->post('/production/schedules', [
            'production_type' => 'stone_to_slab',
            'machine' => 'Gang Saw 1',
            'planned_start_date' => '2026-06-25 09:00:00',
            'planned_end_date' => '2026-06-25 13:00:00',
            'priority' => 'normal',
            'planned_input_product_id' => $product->id,
            'planned_input_quantity' => 10.0,
            'expected_output_quantity' => 7.0,
        ]);
        $response->assertSessionHasErrors(['machine']);

        // Case 4: Existing schedule surrounds new schedule (10:30 - 11:30)
        $response = $this->post('/production/schedules', [
            'production_type' => 'stone_to_slab',
            'machine' => 'Gang Saw 1',
            'planned_start_date' => '2026-06-25 10:30:00',
            'planned_end_date' => '2026-06-25 11:30:00',
            'priority' => 'normal',
            'planned_input_product_id' => $product->id,
            'planned_input_quantity' => 10.0,
            'expected_output_quantity' => 7.0,
        ]);
        $response->assertSessionHasErrors(['machine']);

        // Case 5: Overlap is ALLOWED if the existing schedule is completed or cancelled
        $sched->update(['status' => 'completed']);
        $response = $this->post('/production/schedules', [
            'production_type' => 'stone_to_slab',
            'machine' => 'Gang Saw 1',
            'planned_start_date' => '2026-06-25 10:30:00',
            'planned_end_date' => '2026-06-25 11:30:00',
            'priority' => 'normal',
            'planned_input_product_id' => $product->id,
            'planned_input_quantity' => 10.0,
            'expected_output_quantity' => 7.0,
        ]);
        $response->assertSessionHasNoErrors();
    }

    /**
     * Test 49: Production Schedule Status Transitions
     */
    public function test_schedule_status_transitions_and_edit_locks(): void
    {
        $product = Product::create([
            'sku' => 'RM-BLOCK-SCH-3',
            'name' => 'Basalt Block 3',
            'unit' => 'pcs',
            'cost' => 100.00,
            'price' => 0.00,
            'product_type' => 'raw_stone',
            'unit_of_measure' => 'MT',
        ]);

        $schedule = ProductionSchedule::create([
            'schedule_number' => 'SCH-STATUS',
            'production_type' => 'stone_to_slab',
            'machine' => 'Gang Saw 2',
            'planned_start_date' => '2026-06-25 10:00:00',
            'planned_end_date' => '2026-06-25 12:00:00',
            'priority' => 'normal',
            'planned_input_product_id' => $product->id,
            'planned_input_quantity' => 10.0,
            'expected_output_quantity' => 7.0,
            'status' => 'planned',
        ]);

        // 1. Block: in_progress directly from planned
        $response = $this->patch("/production/schedules/{$schedule->id}/status", ['status' => 'in_progress']);
        $response->assertSessionHasErrors(['status']);

        // 2. Allow: planned -> queued
        $response = $this->patch("/production/schedules/{$schedule->id}/status", ['status' => 'queued']);
        $response->assertSessionHasNoErrors();
        $this->assertEquals('queued', $schedule->fresh()->status);

        // 3. Block: queued -> completed
        $response = $this->patch("/production/schedules/{$schedule->id}/status", ['status' => 'completed']);
        $response->assertSessionHasErrors(['status']);

        // 4. Allow: queued -> in_progress
        $response = $this->patch("/production/schedules/{$schedule->id}/status", ['status' => 'in_progress']);
        $response->assertSessionHasNoErrors();
        $this->assertEquals('in_progress', $schedule->fresh()->status);

        // 5. Block: in_progress -> queued
        $response = $this->patch("/production/schedules/{$schedule->id}/status", ['status' => 'queued']);
        $response->assertSessionHasErrors(['status']);

        // 6. Allow: in_progress -> completed
        $response = $this->patch("/production/schedules/{$schedule->id}/status", ['status' => 'completed']);
        $response->assertSessionHasNoErrors();
        $this->assertEquals('completed', $schedule->fresh()->status);

        // 7. Block: completed -> planned
        $response = $this->patch("/production/schedules/{$schedule->id}/status", ['status' => 'planned']);
        $response->assertSessionHasErrors(['status']);

        // 8. Block: completed -> cancelled
        $response = $this->patch("/production/schedules/{$schedule->id}/status", ['status' => 'cancelled']);
        $response->assertSessionHasErrors(['status']);

        // 9. Block: Editing completed schedule
        $response = $this->put("/production/schedules/{$schedule->id}", [
            'production_type' => 'stone_to_slab',
            'machine' => 'Gang Saw 2',
            'planned_start_date' => '2026-06-25 10:00:00',
            'planned_end_date' => '2026-06-25 13:00:00',
            'priority' => 'high',
            'planned_input_product_id' => $product->id,
            'planned_input_quantity' => 12.0,
            'expected_output_quantity' => 8.0,
        ]);
        $response->assertSessionHasErrors(['status']);
    }

    /**
     * Test 50: Schedule creation is planning only
     */
    public function test_schedule_is_planning_only_and_does_not_consume_inventory(): void
    {
        $product = Product::create([
            'sku' => 'RM-BLOCK-SCH-4',
            'name' => 'Basalt Block 4',
            'unit' => 'pcs',
            'cost' => 100.00,
            'price' => 0.00,
            'product_type' => 'raw_stone',
            'unit_of_measure' => 'MT',
        ]);

        $initialLedgerCount = StockLedger::count();
        $initialBatchCount = InventoryBatch::count();
        $initialJournalCount = JournalEntry::count();

        $response = $this->post('/production/schedules', [
            'production_type' => 'stone_to_slab',
            'machine' => 'Gang Saw 1',
            'planned_start_date' => '2026-06-25 10:00:00',
            'planned_end_date' => '2026-06-25 12:00:00',
            'priority' => 'normal',
            'planned_input_product_id' => $product->id,
            'planned_input_quantity' => 10.0,
            'expected_output_quantity' => 7.0,
        ]);
        $response->assertSessionHasNoErrors();

        $this->assertEquals($initialLedgerCount, StockLedger::count());
        $this->assertEquals($initialBatchCount, InventoryBatch::count());
        $this->assertEquals($initialJournalCount, JournalEntry::count());
    }

    /**
     * Test 51: Waste Logging Reasons Validation
     */
    public function test_waste_logging_validation_and_reasons(): void
    {
        $inputProduct = Product::create([
            'sku' => 'RM-BLOCK-SCH-5',
            'name' => 'Basalt Block 5',
            'unit' => 'pcs',
            'cost' => 100.00,
            'price' => 0.00,
            'product_type' => 'raw_stone',
            'unit_of_measure' => 'MT',
        ]);

        $batch = $this->inventoryService->createBatch(
            $inputProduct->id,
            $this->warehouse->id,
            'adjustment',
            1,
            20.0,
            100.0,
            'MT',
            'raw_stone'
        );

        $outputProduct = Product::create([
            'sku' => 'SL-BASALT-5',
            'name' => 'Basalt Slab 5',
            'unit' => 'pcs',
            'cost' => 0.00,
            'price' => 0.00,
            'product_type' => 'slab',
            'unit_of_measure' => 'MT',
        ]);

        // 1. Rejects invalid waste reason
        $response = $this->post('/production/stone-to-slab', [
            'input_batch_id' => $batch->id,
            'input_quantity' => 10.0,
            'slab_product_id' => $outputProduct->id,
            'slabs_produced' => 7.0,
            'wastage_quantity' => 3.0,
            'scrap_product_id' => null,
            'scrap_quantity' => 0,
            'labor_cost' => 1000,
            'electricity_cost' => 500,
            'fuel_cost' => 300,
            'maintenance_cost' => 200,
            'overhead_cost' => 100,
            'production_date' => '2026-06-25',
            'warehouse_id' => $this->warehouse->id,
            'waste_reason' => 'invalid_reason_string', // invalid
            'waste_operator_name' => 'John Doe',
        ]);
        $response->assertSessionHasErrors(['waste_reason']);

        // 2. Succeeds with valid waste reason and operator
        $response = $this->post('/production/stone-to-slab', [
            'input_batch_id' => $batch->id,
            'input_quantity' => 10.0,
            'slab_product_id' => $outputProduct->id,
            'slabs_produced' => 7.0,
            'wastage_quantity' => 3.0,
            'scrap_product_id' => null,
            'scrap_quantity' => 0,
            'labor_cost' => 1000,
            'electricity_cost' => 500,
            'fuel_cost' => 300,
            'maintenance_cost' => 200,
            'overhead_cost' => 100,
            'production_date' => '2026-06-25',
            'warehouse_id' => $this->warehouse->id,
            'waste_reason' => 'breakage', // valid
            'waste_operator_name' => 'Siddique Operator',
        ]);
        $response->assertSessionHasNoErrors();

        $run = StoneToSlabProduction::first();
        $this->assertEquals('breakage', $run->waste_reason);
        $this->assertEquals('Siddique Operator', $run->waste_operator_name);
        $this->assertEquals(30.0, $run->wastage_percentage);
    }

    /**
     * Test 52: Schedule completion inside same database transaction
     */
    public function test_schedule_completion_inside_production_transaction(): void
    {
        $inputProduct = Product::create([
            'sku' => 'RM-BLOCK-SCH-6',
            'name' => 'Basalt Block 6',
            'unit' => 'pcs',
            'cost' => 100.00,
            'price' => 0.00,
            'product_type' => 'raw_stone',
            'unit_of_measure' => 'MT',
        ]);

        $batch = $this->inventoryService->createBatch(
            $inputProduct->id,
            $this->warehouse->id,
            'adjustment',
            1,
            5.0, // Only 5.0 MT in stock!
            100.0,
            'MT',
            'raw_stone'
        );

        $outputProduct = Product::create([
            'sku' => 'SL-BASALT-6',
            'name' => 'Basalt Slab 6',
            'unit' => 'pcs',
            'cost' => 0.00,
            'price' => 0.00,
            'product_type' => 'slab',
            'unit_of_measure' => 'MT',
        ]);

        $schedule = ProductionSchedule::create([
            'schedule_number' => 'SCH-TX-1',
            'production_type' => 'stone_to_slab',
            'machine' => 'Gang Saw 1',
            'planned_start_date' => '2026-06-25 10:00:00',
            'planned_end_date' => '2026-06-25 12:00:00',
            'priority' => 'normal',
            'planned_input_product_id' => $inputProduct->id,
            'planned_input_quantity' => 10.0,
            'expected_output_quantity' => 7.0,
            'status' => 'planned',
        ]);

        // 1. Run production requiring 10.0 MT (stock is only 5.0 MT). This will fail and rollback!
        $response = $this->post('/production/stone-to-slab', [
            'input_batch_id' => $batch->id,
            'input_quantity' => 10.0, // greater than 5.0 MT stock
            'slab_product_id' => $outputProduct->id,
            'slabs_produced' => 7.0,
            'wastage_quantity' => 3.0,
            'scrap_product_id' => null,
            'scrap_quantity' => 0,
            'labor_cost' => 1000,
            'electricity_cost' => 500,
            'fuel_cost' => 300,
            'maintenance_cost' => 200,
            'overhead_cost' => 100,
            'production_date' => '2026-06-25',
            'warehouse_id' => $this->warehouse->id,
            'production_schedule_id' => $schedule->id,
            'waste_reason' => 'cutting_loss',
            'waste_operator_name' => 'Operator A',
        ]);

        $response->assertSessionHasErrors(['items']);

        // Check schedule status remains 'planned' (has not changed because of rollback)
        $this->assertEquals('planned', $schedule->fresh()->status);

        // 2. Re-stock or request within limits: 4.0 MT (which is available)
        $response = $this->post('/production/stone-to-slab', [
            'input_batch_id' => $batch->id,
            'input_quantity' => 4.0, // within 5.0 MT stock
            'slab_product_id' => $outputProduct->id,
            'slabs_produced' => 3.0,
            'wastage_quantity' => 1.0,
            'scrap_product_id' => null,
            'scrap_quantity' => 0,
            'labor_cost' => 1000,
            'electricity_cost' => 500,
            'fuel_cost' => 300,
            'maintenance_cost' => 200,
            'overhead_cost' => 100,
            'production_date' => '2026-06-25',
            'warehouse_id' => $this->warehouse->id,
            'production_schedule_id' => $schedule->id,
            'waste_reason' => 'cutting_loss',
            'waste_operator_name' => 'Operator A',
        ]);

        $response->assertSessionHasNoErrors();
        // Check schedule status becomes 'completed'
        $this->assertEquals('completed', $schedule->fresh()->status);
    }

    /**
     * Batch 4 Test: Quality Inspections Math and Validation
     */
    public function test_quality_inspections_math_and_validation(): void
    {
        $product = Product::create([
            'sku' => 'QC-PROD-1',
            'name' => 'QC Test Product',
            'unit' => 'pcs',
            'cost' => 10.00,
        ]);

        $batch = $this->inventoryService->createBatch(
            $product->id,
            $this->warehouse->id,
            'adjustment',
            1,
            50.0,
            10.0
        );

        $schedule = ProductionSchedule::create([
            'schedule_number' => 'SCH-QC-1',
            'production_type' => 'stone_to_slab',
            'machine' => 'Gang Saw 1',
            'planned_start_date' => '2026-06-25 10:00:00',
            'planned_end_date' => '2026-06-25 12:00:00',
            'priority' => 'normal',
            'planned_input_product_id' => $product->id,
            'planned_input_quantity' => 10.0,
            'expected_output_quantity' => 7.0,
            'status' => 'planned',
        ]);

        // 1. Invalid rejected quantity > checked quantity
        $response = $this->post('/quality-inspections', [
            'inspection_date' => '2026-06-25',
            'product_id' => $product->id,
            'inventory_batch_id' => $batch->id,
            'production_schedule_id' => $schedule->id,
            'inspection_type' => 'production_qc',
            'grade' => 'A',
            'defect_type' => 'cracks',
            'checked_quantity' => 10.0,
            'rejected_quantity' => 12.0, // greater than checked
            'accepted_quantity' => 8.0,
            'inspector_name' => 'Hasan Inspector',
            'remarks' => 'cracks audit',
            'status' => 'passed',
        ]);
        $response->assertSessionHasErrors(['rejected_quantity']);

        // 2. Invalid accepted + rejected > checked
        $response = $this->post('/quality-inspections', [
            'inspection_date' => '2026-06-25',
            'product_id' => $product->id,
            'inventory_batch_id' => $batch->id,
            'production_schedule_id' => $schedule->id,
            'inspection_type' => 'production_qc',
            'grade' => 'A',
            'defect_type' => 'cracks',
            'checked_quantity' => 10.0,
            'rejected_quantity' => 5.0,
            'accepted_quantity' => 6.0, // sum = 11.0 > 10.0
            'inspector_name' => 'Hasan Inspector',
            'remarks' => 'cracks audit',
            'status' => 'passed',
        ]);
        $response->assertSessionHasErrors(['accepted_quantity']);

        // 3. Valid creation
        $response = $this->post('/quality-inspections', [
            'inspection_date' => '2026-06-25',
            'product_id' => $product->id,
            'inventory_batch_id' => $batch->id,
            'production_schedule_id' => $schedule->id,
            'inspection_type' => 'production_qc',
            'grade' => 'A',
            'defect_type' => 'cracks',
            'checked_quantity' => 10.0,
            'rejected_quantity' => 3.0,
            'accepted_quantity' => 7.0,
            'inspector_name' => 'Hasan Inspector',
            'remarks' => 'cracks audit',
            'status' => 'passed',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertEquals(1, QualityInspection::count());

        $qc = QualityInspection::first();
        $this->assertEquals($batch->id, $qc->inventory_batch_id);
        $this->assertEquals($schedule->id, $qc->production_schedule_id);
    }

    /**
     * Batch 4 Test: Quality Inspections does not consume or alter inventory
     */
    public function test_quality_inspections_does_not_consume_inventory(): void
    {
        $product = Product::create([
            'sku' => 'QC-PROD-2',
            'name' => 'QC Test Product 2',
            'unit' => 'pcs',
            'cost' => 10.00,
        ]);

        $batch = $this->inventoryService->createBatch(
            $product->id,
            $this->warehouse->id,
            'adjustment',
            1,
            50.0,
            10.0
        );

        $initialStock = $this->inventoryService->getTotalStock($product->id);

        $response = $this->post('/quality-inspections', [
            'inspection_date' => '2026-06-25',
            'product_id' => $product->id,
            'inventory_batch_id' => $batch->id,
            'inspection_type' => 'stock_qc',
            'grade' => 'reject',
            'defect_type' => 'breakage',
            'checked_quantity' => 10.0,
            'rejected_quantity' => 10.0,
            'accepted_quantity' => 0.0,
            'inspector_name' => 'Inspector B',
            'status' => 'failed',
        ]);
        $response->assertSessionHasNoErrors();

        // Verify stock is unchanged
        $this->assertEquals($initialStock, $this->inventoryService->getTotalStock($product->id));
    }

    /**
     * Batch 4 Test: Delivery Challan Validation & Transitions
     */
    public function test_delivery_challan_validation_and_transitions(): void
    {
        $product = Product::create([
            'sku' => 'DC-PROD-1',
            'name' => 'DC Test Product',
            'unit' => 'pcs',
            'cost' => 100.00,
            'price' => 300.00,
        ]);

        // Stock adjust
        $this->post('/inventory/adjust', [
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 20,
            'unit_cost' => 100.00,
            'notes' => 'Stock DC',
        ]);

        $customer = Contact::create(['name' => 'Customer DC', 'type' => 'customer']);

        // 1. Create Sales Order
        $this->post('/sales/store', [
            'contact_id' => $customer->id,
            'order_date' => '2026-06-25',
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10, 'unit_price' => 300.00],
            ],
            'discount_amount' => 0.0,
            'delivery_charges' => 0.0,
            'cash_received' => 0.0,
            'bank_received' => 0.0,
        ]);

        $order = Order::where('contact_id', $customer->id)->first();
        $invoice = CustomerInvoice::where('contact_id', $customer->id)->first();

        $initialStock = $this->inventoryService->getTotalStock($product->id); // 10 remaining (10 sold)

        // 2. Try creating Challan with quantity > ordered (10 ordered)
        $response = $this->post('/delivery-challans', [
            'challan_date' => '2026-06-25 12:00:00',
            'order_id' => $order->id,
            'customer_invoice_id' => $invoice->id,
            'vehicle_number' => 'LES-1111',
            'driver_name' => 'Aslam Driver',
            'prepared_by' => 'Gate Supervisor',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 12, // greater than 10 ordered
                    'unit' => 'Pcs',
                    'warehouse_id' => $this->warehouse->id,
                ],
            ],
        ]);
        $response->assertSessionHasErrors(['items']);

        // 3. Create Valid Challan
        $response = $this->post('/delivery-challans', [
            'challan_date' => '2026-06-25 12:00:00',
            'order_id' => $order->id,
            'customer_invoice_id' => $invoice->id,
            'vehicle_number' => 'LES-1111',
            'driver_name' => 'Aslam Driver',
            'prepared_by' => 'Gate Supervisor',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5, // valid
                    'unit' => 'Pcs',
                    'warehouse_id' => $this->warehouse->id,
                ],
            ],
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertEquals(1, DeliveryChallan::count());

        // Verify stock is NOT depleted again (Challan is document-only)
        $this->assertEquals($initialStock, $this->inventoryService->getTotalStock($product->id));

        $dc = DeliveryChallan::first();
        $this->assertEquals('pending', $dc->status);

        // 4. Test Invalid transitions
        // Try transition delivered -> dispatched
        $response = $this->patch("/delivery-challans/{$dc->id}/status", [
            'status' => 'delivered',
        ]);
        $response->assertSessionHasErrors(['status']); // pending to delivered is not allowed

        // Test valid transitions: pending -> packed
        $response = $this->patch("/delivery-challans/{$dc->id}/status", [
            'status' => 'packed',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertEquals('packed', $dc->fresh()->status);

        // packed -> dispatched
        $response = $this->patch("/delivery-challans/{$dc->id}/status", [
            'status' => 'dispatched',
        ]);
        $response->assertSessionHasNoErrors();
        $dc->refresh();
        $this->assertEquals('dispatched', $dc->status);
        $this->assertNotNull($dc->dispatched_at);

        // dispatched -> delivered
        $response = $this->patch("/delivery-challans/{$dc->id}/status", [
            'status' => 'delivered',
        ]);
        $response->assertSessionHasNoErrors();
        $dc->refresh();
        $this->assertEquals('delivered', $dc->status);
        $this->assertNotNull($dc->delivered_at);

        // delivered -> dispatched (invalid)
        $response = $this->patch("/delivery-challans/{$dc->id}/status", [
            'status' => 'dispatched',
        ]);
        $response->assertSessionHasErrors(['status']);
    }

    /**
     * Batch 4 Test: Daily Operations Reports Queries
     */
    public function test_daily_operations_reports(): void
    {
        $product = Product::create([
            'sku' => 'REP-PROD-1',
            'name' => 'Report Test Product',
            'unit' => 'pcs',
            'cost' => 10.00,
        ]);

        $batch = $this->inventoryService->createBatch(
            $product->id,
            $this->warehouse->id,
            'adjustment',
            1,
            50.0,
            10.0
        );

        // Log QC check
        QualityInspection::create([
            'qc_number' => 'QC-REP-01',
            'inspection_date' => '2026-06-25',
            'product_id' => $product->id,
            'inventory_batch_id' => $batch->id,
            'inspection_type' => 'stock_qc',
            'grade' => 'A',
            'checked_quantity' => 10.0,
            'rejected_quantity' => 1.0,
            'accepted_quantity' => 9.0,
            'inspector_name' => 'Afnan Inspector',
            'status' => 'passed',
        ]);

        // Get daily report
        $response = $this->get('/reports/daily?date=2026-06-25');
        $response->assertSessionHasNoErrors();

        $inertiaData = $response->original->getData()['page']['props'];

        $this->assertEquals('2026-06-25', $inertiaData['date']);
        $this->assertCount(1, $inertiaData['qualityReport']);
        $this->assertEquals('QC-REP-01', $inertiaData['qualityReport'][0]['qc_number']);
    }

    /**
     * Batch 5 Test: Roles, Permissions, Backups, Imports & Machine Register Matrix
     */
    public function test_batch_5_roles_and_permissions_matrix(): void
    {
        // 1. Setup roles & permissions
        $viewerRole = Role::firstOrCreate(['name' => 'viewer'], ['display_name' => 'Viewer']);
        $viewerRole->permissions()->sync([
            Permission::firstOrCreate(['name' => 'view_dashboard'])->id,
            Permission::firstOrCreate(['name' => 'view_reports'])->id,
        ]);

        $accountantRole = Role::firstOrCreate(['name' => 'accountant'], ['display_name' => 'Accountant']);
        $accountantRole->permissions()->sync([
            Permission::firstOrCreate(['name' => 'view_dashboard'])->id,
            Permission::firstOrCreate(['name' => 'view_accounting'])->id,
            Permission::firstOrCreate(['name' => 'manage_accounting'])->id,
        ]);

        $inventoryManagerRole = Role::firstOrCreate(['name' => 'inventory_manager'], ['display_name' => 'Inventory Manager']);
        $inventoryManagerRole->permissions()->sync([
            Permission::firstOrCreate(['name' => 'view_dashboard'])->id,
            Permission::firstOrCreate(['name' => 'view_inventory'])->id,
            Permission::firstOrCreate(['name' => 'manage_inventory'])->id,
        ]);

        $dispatchUserRole = Role::firstOrCreate(['name' => 'dispatch_user'], ['display_name' => 'Dispatch User']);
        $dispatchUserRole->permissions()->sync([
            Permission::firstOrCreate(['name' => 'view_dashboard'])->id,
            Permission::firstOrCreate(['name' => 'view_dispatch'])->id,
            Permission::firstOrCreate(['name' => 'manage_dispatch'])->id,
        ]);

        $qualityUserRole = Role::firstOrCreate(['name' => 'quality_user'], ['display_name' => 'Quality User']);
        $qualityUserRole->permissions()->sync([
            Permission::firstOrCreate(['name' => 'view_dashboard'])->id,
            Permission::firstOrCreate(['name' => 'view_quality'])->id,
            Permission::firstOrCreate(['name' => 'manage_quality'])->id,
        ]);

        // Create users for each role
        $viewer = User::factory()->create();
        $viewer->roles()->attach($viewerRole);

        $accountant = User::factory()->create();
        $accountant->roles()->attach($accountantRole);

        $inventoryManager = User::factory()->create();
        $inventoryManager->roles()->attach($inventoryManagerRole);

        $dispatchUser = User::factory()->create();
        $dispatchUser->roles()->attach($dispatchUserRole);

        $qualityUser = User::factory()->create();
        $qualityUser->roles()->attach($qualityUserRole);

        // Assert Admin can access dashboard and protected areas
        $this->actingAs($this->admin);
        $this->get('/dashboard')->assertStatus(200);
        $this->get('/accounting')->assertStatus(200);
        $this->get('/inventory')->assertStatus(200);

        // Assert Viewer blocked from write routes
        $this->actingAs($viewer);
        $this->get('/dashboard')->assertStatus(200);
        $this->get('/accounting')->assertStatus(403);
        $this->post('/accounting/journal', [])->assertStatus(403);

        // Assert Accountant allowed accounting but blocked from production write
        $this->actingAs($accountant);
        $this->get('/accounting')->assertStatus(200);
        $this->post('/production/bom', [])->assertStatus(403);

        // Assert Inventory Manager allowed inventory but blocked from accounting write
        $this->actingAs($inventoryManager);
        $this->get('/inventory')->assertStatus(200);
        $this->post('/accounting/journal', [])->assertStatus(403);

        // Assert Dispatch User allowed delivery challans but blocked from accounting
        $this->actingAs($dispatchUser);
        $this->get('/delivery-challans')->assertStatus(200);
        $this->get('/accounting')->assertStatus(403);

        // Assert Quality User allowed QC but blocked from sales write
        $this->actingAs($qualityUser);
        $this->get('/quality-inspections')->assertStatus(200);
        $this->post('/sales/store', [])->assertStatus(403);
    }

    public function test_machine_register_creation_and_scheduling(): void
    {
        $this->actingAs($this->admin);

        // Create machine in master register
        $activeMachine = Machine::create([
            'machine_code' => 'MC-TEST-01',
            'name' => 'Test Cutter A',
            'type' => 'cutter',
            'status' => 'active',
        ]);

        $maintenanceMachine = Machine::create([
            'machine_code' => 'MC-TEST-02',
            'name' => 'Test Cutter B',
            'type' => 'cutter',
            'status' => 'maintenance',
        ]);

        // Attempt scheduling on active machine
        $response = $this->post('/production/schedules', [
            'production_type' => 'stone_to_slab',
            'machine' => 'Test Cutter A',
            'planned_start_date' => '2026-06-25 10:00:00',
            'planned_end_date' => '2026-06-25 12:00:00',
            'priority' => 'normal',
            'planned_input_product_id' => Product::create(['sku' => 'TEST-RAW-01', 'name' => 'Raw', 'unit' => 'pcs', 'cost' => 10])->id,
            'planned_input_quantity' => 5,
            'expected_output_quantity' => 4,
        ]);
        $response->assertSessionHasNoErrors();

        // Attempt scheduling on maintenance machine (should be blocked)
        $response = $this->post('/production/schedules', [
            'production_type' => 'stone_to_slab',
            'machine' => 'Test Cutter B',
            'planned_start_date' => '2026-06-25 14:00:00',
            'planned_end_date' => '2026-06-25 16:00:00',
            'priority' => 'normal',
            'planned_input_product_id' => Product::create(['sku' => 'TEST-RAW-02', 'name' => 'Raw 2', 'unit' => 'pcs', 'cost' => 10])->id,
            'planned_input_quantity' => 5,
            'expected_output_quantity' => 4,
        ]);
        $response->assertSessionHasErrors(['machine']);
    }

    public function test_backup_database_command(): void
    {
        $this->actingAs($this->admin);

        // Delete existing backups to start clean
        File::cleanDirectory(storage_path('app/backups'));

        $this->artisan('db:backup')->assertExitCode(0);

        // Verify file exists
        $files = File::files(storage_path('app/backups'));
        $this->assertGreaterThanOrEqual(1, count($files));
        $this->assertStringContainsString('backup_', $files[0]->getFilename());
        $this->assertStringContainsString('.sqlite', $files[0]->getFilename());
    }

    public function test_generate_import_templates_command(): void
    {
        $this->actingAs($this->admin);

        // Delete existing import templates to start clean
        if (File::isDirectory(storage_path('app/import_templates'))) {
            File::cleanDirectory(storage_path('app/import_templates'));
        }

        $this->artisan('import:templates')->assertExitCode(0);

        // Verify templates are generated with correct CSV headers
        $productsCsv = storage_path('app/import_templates/products.csv');
        $this->assertTrue(File::exists($productsCsv));

        $handle = fopen($productsCsv, 'r');
        $headers = fgetcsv($handle);
        fclose($handle);

        $this->assertEquals('sku', $headers[0]);
        $this->assertEquals('name', $headers[1]);
        $this->assertEquals('description', $headers[2]);
    }

    public function test_login_works_for_seeded_admin_and_invalid_fails(): void
    {
        Auth::logout();

        $user = User::factory()->create([
            'email' => 'admin@erp.test',
            'password' => Hash::make('admin123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@erp.test',
            'password' => 'admin123',
        ]);
        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout');

        $responseBad = $this->post('/login', [
            'email' => 'admin@erp.test',
            'password' => 'wrongpassword',
        ]);
        $responseBad->assertSessionHasErrors(['email']);
    }

    public function test_seeded_role_access_matrix_smoke_test(): void
    {
        $this->seed(DatabaseSeeder::class);

        $adminEmail = env('OGGY_SUPERADMIN_EMAIL', 'admin@example.test');
        $accountant = $this->createUserWithRole('accountant', 'accountant@example.test');

        $this->assertDatabaseHas('users', ['email' => $adminEmail]);
        $this->assertDatabaseHas('roles', ['name' => 'accountant']);

        $admin = User::where('email', $adminEmail)->firstOrFail();

        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue($accountant->hasRole('accountant'));
        $this->assertTrue($admin->hasPermission('view_accounting'));
        $this->assertTrue($accountant->hasPermission('view_accounting'));
        $this->assertFalse($accountant->hasPermission('manage_inventory'));
    }

    public function test_dashboard_loads_and_get_routes_smoke_test(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', env('OGGY_SUPERADMIN_EMAIL', 'admin@example.test'))->firstOrFail();

        $this->actingAs($admin);
        $this->get('/dashboard')->assertStatus(200);
        $this->get('/reports')->assertStatus(200);
        $this->get('/inventory')->assertStatus(200);
        $this->get('/sales')->assertStatus(200);
        $this->get('/purchases')->assertStatus(200);
        $this->get('/production')->assertStatus(200);
        $this->get('/cash-bank')->assertStatus(200);
        $this->get('/quality-inspections')->assertStatus(200);
        $this->get('/delivery-challans')->assertStatus(200);
    }

    public function test_form_workflows_unauthorized_post_and_closed_period_blocks(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', env('OGGY_SUPERADMIN_EMAIL', 'admin@example.test'))->firstOrFail();
        $viewer = $this->createUserWithRole('viewer', 'viewer@example.test');

        // 1. Unauthorized POST returns 403
        $this->actingAs($viewer);
        $response = $this->post('/accounting/journal', [
            'date' => '2026-06-16',
            'description' => 'Test',
            'items' => [],
        ]);
        $response->assertStatus(403);

        // 2. Closed period POST blocks
        $this->actingAs($admin);

        // Create closed period
        $period = AccountingPeriod::create([
            'period_name' => 'Closed Jun 2026',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'is_closed' => true,
        ]);

        // Attempt balanced journal in closed period (should fail)
        $responseClose = $this->post('/accounting/journal', [
            'date' => '2026-06-15',
            'description' => 'Test Balanced',
            'items' => [
                ['account_id' => $this->arAccount->id, 'debit' => 100.0, 'credit' => 0.0],
                ['account_id' => $this->salesRevAccount->id, 'debit' => 0.0, 'credit' => 100.0],
            ],
        ]);
        $responseClose->assertSessionHasErrors(['period']);
    }

    public function test_journal_entry_and_item_immutability(): void
    {
        $this->seed(DatabaseSeeder::class);

        $entry = JournalEntry::create([
            'entry_number' => 'TEST-IM-001',
            'date' => '2026-06-25',
            'description' => 'Normal Journal',
        ]);

        $item = JournalItem::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $this->arAccount->id,
            'debit' => 100.0,
            'credit' => 0.0,
        ]);

        $this->assertDatabaseHas('journal_entries', ['id' => $entry->id]);
        $this->assertDatabaseHas('journal_items', ['id' => $item->id]);

        // Self-referencing updates still work (dirty keys inside allowed list)
        $entry->update([
            'reference_type' => 'journal_entry',
            'reference_id' => $entry->id,
        ]);
        $this->assertEquals('journal_entry', $entry->fresh()->reference_type);

        // Attempting to update description/date/entry_number fails
        try {
            $entry->update(['description' => 'Modified description']);
            $this->fail('Expected Exception was not thrown on JournalEntry update.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Posted journal entries are immutable and cannot be updated.', $e->getMessage());
        }

        // Attempting to update JournalItem fields fails
        try {
            $item->update(['debit' => 200.0]);
            $this->fail('Expected Exception was not thrown on JournalItem update.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Journal items are immutable and cannot be updated.', $e->getMessage());
        }

        // Attempting to delete JournalItem fails
        try {
            $item->delete();
            $this->fail('Expected Exception was not thrown on JournalItem delete.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Journal items are immutable and cannot be deleted.', $e->getMessage());
        }
    }

    public function test_rbac_intermediate_roles_access_policy(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = $this->createUserWithRole('owner', 'owner@example.test');
        $salesUser = $this->createUserWithRole('sales_user', 'sales@example.test');
        $purchaseUser = $this->createUserWithRole('purchase_user', 'purchase@example.test');

        // Owner role: views dashboard, reports, accounting, inventory, etc., but cannot write/create journal
        $this->actingAs($owner);
        $this->get('/dashboard')->assertStatus(200);
        $this->get('/reports')->assertStatus(200);
        $this->get('/inventory')->assertStatus(200);
        $this->get('/sales')->assertStatus(200);
        $this->get('/purchases')->assertStatus(200);

        $response = $this->post('/accounting/journal', [
            'date' => '2026-06-25',
            'description' => 'Owner attempt',
            'items' => [],
        ]);
        $response->assertStatus(403);

        // Sales Rep: view sales, view dispatch, but cannot access accounting
        $this->actingAs($salesUser);
        $this->get('/sales')->assertStatus(200);
        $this->get('/delivery-challans')->assertStatus(200);
        $this->get('/cash-bank')->assertStatus(403);

        $responsePeriod = $this->post('/accounting/periods', [
            'period_name' => 'Sales Attempt',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
        ]);
        $responsePeriod->assertStatus(403);

        // Purchase Agent: view purchases, but cannot view sales
        $this->actingAs($purchaseUser);
        $this->get('/purchases')->assertStatus(200);
        $this->get('/sales')->assertStatus(403);

        $responseInvoice = $this->post('/sales/store', [
            'contact_id' => 1,
            'items' => [],
        ]);
        $responseInvoice->assertStatus(403);
    }

    private function createUserWithRole(string $roleName, string $email): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'status' => 'active',
        ]);
        $role = Role::where('name', $roleName)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user;
    }
}
