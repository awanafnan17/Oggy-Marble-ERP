<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Contact;
use App\Models\CustomerInvoice;
use App\Models\InventoryBatch;
use App\Models\PakistanBank;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Batch11pPakistanBankMasterListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    private BankAccount $bankAccount;

    private Product $product;

    private Contact $customer;

    private InventoryBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        // Sync the Pakistan bank master list
        $this->artisan('oggy:sync-pakistan-banks');

        // Setup admin user with full permissions (same pattern as Batch 11O)
        $this->admin = User::factory()->create([
            'name' => 'Batch 11P Admin',
            'email' => 'admin_11p@example.test',
        ]);
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrator']);
        $adminRole->permissions()->sync(Permission::all()->pluck('id'));
        $this->admin->roles()->syncWithoutDetaching([$adminRole->id]);

        // Create warehouse
        $this->warehouse = Warehouse::create(['name' => 'QA 11P Warehouse', 'location' => 'Islamabad']);

        // Create G/L accounts
        $arAccount = Account::create(['code' => '1100-11P', 'name' => 'AR 11P', 'type' => 'asset']);
        $invAccount = Account::create(['code' => '1200-11P', 'name' => 'Inventory 11P', 'type' => 'asset']);
        $cogsAccount = Account::create(['code' => '5100-11P', 'name' => 'COGS 11P', 'type' => 'expense']);
        $revAccount = Account::create(['code' => '4100-11P', 'name' => 'Revenue 11P', 'type' => 'revenue']);

        // Create bank account
        $glBank = Account::create(['code' => '1011-11P', 'name' => 'Bank GL 11P', 'type' => 'asset']);
        $this->bankAccount = BankAccount::create([
            'account_id' => $glBank->id,
            'account_name' => 'QA 11P Bank Account',
            'bank_name' => 'Meezan Bank Limited',
            'type' => 'bank',
        ]);

        // Create product
        $this->product = Product::create([
            'sku' => 'QA-11P-P1',
            'name' => 'QA 11P Product',
            'unit' => 'pcs',
            'cost' => 25000.00,
            'price' => 50000.00,
            'product_type' => 'tile',
            'unit_of_measure' => 'Sq Ft',
            'inventory_account_id' => $invAccount->id,
            'cogs_account_id' => $cogsAccount->id,
            'revenue_account_id' => $revAccount->id,
        ]);

        // Create inventory batch
        $this->batch = InventoryBatch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_received' => 100.0,
            'quantity_remaining' => 100.0,
            'unit_cost' => 25000.00,
            'total_cost' => 2500000.00,
            'unit_of_measure' => 'Sq Ft',
            'product_type' => 'tile',
            'received_at' => now(),
            'reference_type' => 'beginning_inventory',
            'reference_id' => 1,
        ]);

        // Create customer
        $this->customer = Contact::create(['name' => 'QA_11P_Customer', 'type' => 'customer']);
    }

    // ────────────────────────────────────────────────────
    // 1. Bank master list exists
    // ────────────────────────────────────────────────────
    public function test_pakistan_bank_master_list_exists(): void
    {
        $this->assertGreaterThan(0, PakistanBank::count());
        $this->assertEquals(61, PakistanBank::count(), 'Expected 61 banks in master list');
    }

    // ────────────────────────────────────────────────────
    // 2. Contains key commercial banks
    // ────────────────────────────────────────────────────
    public function test_bank_list_contains_key_commercial_banks(): void
    {
        $commercialBanks = PakistanBank::where('category', 'commercial')->pluck('name')->toArray();

        $this->assertContains('Habib Bank Limited', $commercialBanks);
        $this->assertContains('National Bank of Pakistan', $commercialBanks);
        $this->assertContains('United Bank Limited', $commercialBanks);
        $this->assertContains('MCB Bank Limited', $commercialBanks);
        $this->assertContains('Allied Bank Limited', $commercialBanks);
        $this->assertContains('Bank Alfalah Limited', $commercialBanks);
        $this->assertContains('Bank AL Habib Limited', $commercialBanks);
        $this->assertContains('Faysal Bank Limited', $commercialBanks);
        $this->assertEquals(18, count($commercialBanks), 'Expected 18 commercial banks');
    }

    // ────────────────────────────────────────────────────
    // 3. Contains Islamic banks
    // ────────────────────────────────────────────────────
    public function test_bank_list_contains_islamic_banks(): void
    {
        $islamicBanks = PakistanBank::where('category', 'islamic')->pluck('name')->toArray();

        $this->assertContains('Meezan Bank Limited', $islamicBanks);
        $this->assertContains('BankIslami Pakistan Limited', $islamicBanks);
        $this->assertContains('Dubai Islamic Bank Pakistan Limited', $islamicBanks);
        $this->assertGreaterThanOrEqual(10, count($islamicBanks));

        // Verify is_islamic flag
        $allIslamic = PakistanBank::where('category', 'islamic')->get();
        foreach ($allIslamic as $bank) {
            $this->assertTrue($bank->is_islamic, "{$bank->name} should have is_islamic=true");
        }
    }

    // ────────────────────────────────────────────────────
    // 4. Contains microfinance/digital banks
    // ────────────────────────────────────────────────────
    public function test_bank_list_contains_microfinance_and_digital_banks(): void
    {
        $digitalBanks = PakistanBank::where('category', 'digital')->pluck('name')->toArray();
        $this->assertContains('Easypaisa Bank Limited', $digitalBanks);
        $this->assertContains('JazzCash / Mobilink Microfinance Bank', $digitalBanks);
        $this->assertGreaterThanOrEqual(3, count($digitalBanks));

        $microBanks = PakistanBank::where('category', 'microfinance')->pluck('name')->toArray();
        $this->assertContains('HBL Microfinance Bank Limited', $microBanks);
        $this->assertContains('NRSP Microfinance Bank Limited', $microBanks);
        $this->assertGreaterThanOrEqual(10, count($microBanks));
    }

    // ────────────────────────────────────────────────────
    // 5. Contains specialized/cooperative banks
    // ────────────────────────────────────────────────────
    public function test_bank_list_contains_specialized_and_cooperative_banks(): void
    {
        $specializedBanks = PakistanBank::where('category', 'specialized')->pluck('name')->toArray();
        $this->assertContains('Zarai Taraqiati Bank Limited', $specializedBanks);
        $this->assertContains('Punjab Provincial Cooperative Bank Limited', $specializedBanks);
        $this->assertGreaterThanOrEqual(2, count($specializedBanks));
    }

    // ────────────────────────────────────────────────────
    // 6. Contains foreign banks
    // ────────────────────────────────────────────────────
    public function test_bank_list_contains_foreign_banks(): void
    {
        $foreignBanks = PakistanBank::where('category', 'foreign')->pluck('name')->toArray();
        $this->assertContains('Standard Chartered Bank Pakistan Limited', $foreignBanks);
        $this->assertContains('Deutsche Bank AG Pakistan', $foreignBanks);
        $this->assertGreaterThanOrEqual(3, count($foreignBanks));
    }

    // ────────────────────────────────────────────────────
    // 7. Bank dropdown loads on CashBank page
    // ────────────────────────────────────────────────────
    public function test_bank_dropdown_loads_on_cash_bank_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/cash-bank');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page->component('CashBank/Index')
            ->has('pakistanBanks')
        );
    }

    // ────────────────────────────────────────────────────
    // 8. Existing bank account creation still works
    // ────────────────────────────────────────────────────
    public function test_existing_bank_account_creation_still_works(): void
    {
        $response = $this->actingAs($this->admin)->post('/cash-bank/accounts', [
            'account_name' => 'Test HBL Account',
            'account_number' => 'PK12HABB1234567890',
            'bank_name' => 'Habib Bank Limited',
            'type' => 'bank',
            'gl_code' => 'TEST-BANK-11P-01',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('bank_accounts', [
            'account_name' => 'Test HBL Account',
            'bank_name' => 'Habib Bank Limited',
        ]);
    }

    // ────────────────────────────────────────────────────
    // 9. Existing payment workflows still work
    // ────────────────────────────────────────────────────
    public function test_existing_payment_workflows_still_work(): void
    {
        // Create invoice via POST
        $this->actingAs($this->admin)->post('/sales/store', [
            'contact_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => '2',
                    'unit_price' => '50000',
                    'selected_batch_id' => $this->batch->id,
                ],
            ],
            'discount_amount' => '0',
            'delivery_charges' => '0',
            'cash_received' => '0',
            'bank_received' => '0',
            'bank_account_id' => '',
        ]);

        $invoice = CustomerInvoice::where('contact_id', $this->customer->id)->first();
        $this->assertNotNull($invoice);

        // Now test payment
        $response = $this->actingAs($this->admin)->post('/payments/customer', [
            'contact_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'amount' => '25000',
            'payment_method' => 'bank_transfer',
            'bank_account_id' => $this->bankAccount->id,
            'invoice_id' => $invoice->id,
        ]);

        $response->assertRedirect();
    }

    // ────────────────────────────────────────────────────
    // 10. Sales invoice deposit account dropdown still works
    // ────────────────────────────────────────────────────
    public function test_sales_invoice_deposit_account_dropdown_still_works(): void
    {
        $response = $this->actingAs($this->admin)->get('/sales');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page->component('Sales/Index')
            ->has('bankAccounts')
        );
    }

    // ────────────────────────────────────────────────────
    // 11. Purchases payment dropdown still works
    // ────────────────────────────────────────────────────
    public function test_purchases_payment_dropdown_still_works(): void
    {
        $response = $this->actingAs($this->admin)->get('/purchases');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page->component('Purchases/Index')
            ->has('bankAccounts')
        );
    }

    // ────────────────────────────────────────────────────
    // 12. Manual / Other bank backward compatibility
    // ────────────────────────────────────────────────────
    public function test_other_manual_bank_backward_compatibility(): void
    {
        $response = $this->actingAs($this->admin)->post('/cash-bank/accounts', [
            'account_name' => 'My Custom Bank Account',
            'account_number' => '9999888877776666',
            'bank_name' => 'Some Unlisted Village Bank',
            'type' => 'bank',
            'gl_code' => 'TEST-MANUAL-11P-01',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('bank_accounts', [
            'account_name' => 'My Custom Bank Account',
            'bank_name' => 'Some Unlisted Village Bank',
        ]);
    }

    // ────────────────────────────────────────────────────
    // 13. Sync command idempotency — no duplicates
    // ────────────────────────────────────────────────────
    public function test_sync_command_twice_does_not_duplicate_banks(): void
    {
        $countBefore = PakistanBank::count();

        // Run sync again (setUp already ran it once)
        $this->artisan('oggy:sync-pakistan-banks');

        $countAfter = PakistanBank::count();
        $this->assertEquals($countBefore, $countAfter, 'Running sync twice must not create duplicates');
    }

    // ────────────────────────────────────────────────────
    // 14. Grouped dropdown structure is correct
    // ────────────────────────────────────────────────────
    public function test_grouped_dropdown_structure_is_correct(): void
    {
        $groups = PakistanBank::getGroupedForDropdown();

        $this->assertIsArray($groups);
        $this->assertGreaterThanOrEqual(5, count($groups));

        foreach ($groups as $group) {
            $this->assertArrayHasKey('label', $group);
            $this->assertArrayHasKey('banks', $group);
            $this->assertIsArray($group['banks']);
            $this->assertGreaterThan(0, count($group['banks']));

            foreach ($group['banks'] as $bank) {
                $this->assertArrayHasKey('id', $bank);
                $this->assertArrayHasKey('name', $bank);
                $this->assertArrayHasKey('is_islamic', $bank);
            }
        }
    }

    // ────────────────────────────────────────────────────
    // 15. Cash account creation still works (no bank_name needed)
    // ────────────────────────────────────────────────────
    public function test_cash_account_creation_without_bank_name(): void
    {
        $response = $this->actingAs($this->admin)->post('/cash-bank/accounts', [
            'account_name' => 'Main Cash Counter',
            'type' => 'cash',
            'gl_code' => 'TEST-CASH-11P-01',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('bank_accounts', [
            'account_name' => 'Main Cash Counter',
            'type' => 'cash',
            'bank_name' => null,
        ]);
    }

    // ────────────────────────────────────────────────────
    // 16. Short names exist for major banks
    // ────────────────────────────────────────────────────
    public function test_short_names_exist_for_major_banks(): void
    {
        $hbl = PakistanBank::where('name', 'Habib Bank Limited')->first();
        $this->assertEquals('HBL', $hbl->short_name);

        $meezan = PakistanBank::where('name', 'Meezan Bank Limited')->first();
        $this->assertEquals('MEBL', $meezan->short_name);

        $ubl = PakistanBank::where('name', 'United Bank Limited')->first();
        $this->assertEquals('UBL', $ubl->short_name);
    }

    // ────────────────────────────────────────────────────
    // 17. All 6 categories present
    // ────────────────────────────────────────────────────
    public function test_all_six_categories_present(): void
    {
        $categories = PakistanBank::distinct()->pluck('category')->toArray();

        $this->assertContains('commercial', $categories);
        $this->assertContains('islamic', $categories);
        $this->assertContains('digital', $categories);
        $this->assertContains('microfinance', $categories);
        $this->assertContains('specialized', $categories);
        $this->assertContains('foreign', $categories);
    }
}
