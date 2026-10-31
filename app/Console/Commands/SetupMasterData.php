<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\PakistanBank;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class SetupMasterData extends Command
{
    protected $signature = 'oggy:setup-master-data';

    protected $description = 'Orchestrate all canonical system master data initialization (roles, permissions, admin user, COA, Pakistan banks, default warehouse) idempotently. Safe to re-run on production without destroying factory data.';

    public function handle(): int
    {
        $this->info('=================================================');
        $this->info('  OGGY MARBLE ERP — SYSTEM MASTER DATA SETUP');
        $this->info('=================================================');

        // 1. Seed Permissions
        $this->info('1. Synchronizing Permissions...');
        $permissions = [
            'view_dashboard' => 'View Dashboard Overview',
            'view_reports' => 'View Analytics and Reports',
            'view_accounting' => 'View Ledger and Cash Accounts',
            'manage_accounting' => 'Manage Accounting Entries and Periods',
            'view_inventory' => 'View Inventory Batches and Stock levels',
            'manage_inventory' => 'Create Adjustments and Stock Transfers',
            'view_production' => 'View Production Schedules and BOMs',
            'manage_production' => 'Create and Complete Production Runs',
            'view_sales' => 'View Sales Orders and Invoices',
            'manage_sales' => 'Create Invoices and Customer Payments',
            'view_purchases' => 'View Purchases and Supplier Bills',
            'manage_purchases' => 'Create Supplier Bills and Payments',
            'view_quality' => 'View Quality Inspection Logs',
            'manage_quality' => 'Create and Manage Quality Inspections',
            'view_dispatch' => 'View Dispatch Tracking and Challans',
            'manage_dispatch' => 'Create and Dispatch Delivery Challans',
            'manage_machines' => 'Manage Master Machine Register',
            'manage_backup' => 'Generate Database Backups',
            'view_audit_logs' => 'View System Administrative Audit Logs',
        ];

        $permissionModels = [];
        foreach ($permissions as $name => $displayName) {
            $permissionModels[$name] = Permission::updateOrCreate(
                ['name' => $name],
                ['display_name' => $displayName]
            );
        }
        $this->info('   ✔ '.count($permissionModels).' permissions active.');

        // 2. Seed Roles & Mappings
        $this->info('2. Synchronizing System Roles & Permissions...');
        $rolesData = [
            'admin' => ['Administrator', 'Full control over all ERP systems', []],
            'owner' => ['Business Owner', 'Dashboard, reports, and read-only view of everything', [
                'view_dashboard', 'view_reports', 'view_accounting', 'view_inventory',
                'view_production', 'view_sales', 'view_purchases', 'view_quality', 'view_dispatch',
            ]],
            'accountant' => ['Accountant', 'Full accounting, reporting, sales, purchases and cash/bank access', [
                'view_dashboard', 'view_reports', 'view_accounting', 'manage_accounting',
                'view_sales', 'manage_sales', 'view_purchases', 'manage_purchases', 'view_inventory',
            ]],
            'inventory_manager' => ['Inventory Manager', 'Manages products, batches, warehouses, adjustments, and transfers', [
                'view_dashboard', 'view_reports', 'view_inventory', 'manage_inventory',
                'view_production', 'view_dispatch',
            ]],
            'production_manager' => ['Production Manager', 'Manages BOMs, job orders, machine registry, schedules, runs and variance', [
                'view_dashboard', 'view_reports', 'view_production', 'manage_production',
                'view_inventory', 'manage_machines',
            ]],
            'sales_user' => ['Sales Rep', 'Manages sales orders, invoices, customers, and challans', [
                'view_dashboard', 'view_sales', 'manage_sales', 'view_dispatch', 'manage_dispatch',
            ]],
            'purchase_user' => ['Purchase Agent', 'Manages purchases, supplier bills, payments, and vendors', [
                'view_dashboard', 'view_purchases', 'manage_purchases',
            ]],
            'quality_user' => ['QC Inspector', 'Manages quality inspections and QC logs', [
                'view_dashboard', 'view_quality', 'manage_quality',
            ]],
            'dispatch_user' => ['Dispatch Clerk', 'Manages delivery challans, dispatches, gate passes, and transfers', [
                'view_dashboard', 'view_dispatch', 'manage_dispatch',
            ]],
            'viewer' => ['Read-only Viewer', 'Read-only access to dashboard and reports', [
                'view_dashboard', 'view_reports',
            ]],
        ];

        $roleModels = [];
        foreach ($rolesData as $roleName => [$displayName, $description, $perms]) {
            $role = Role::updateOrCreate(
                ['name' => $roleName],
                ['display_name' => $displayName, 'description' => $description]
            );
            $roleModels[$roleName] = $role;

            if ($roleName === 'admin') {
                $role->permissions()->sync(Permission::all()->pluck('id'));
            } else {
                $pIds = Permission::whereIn('name', $perms)->pluck('id');
                $role->permissions()->sync($pIds);
            }
        }
        $this->info('   ✔ '.count($roleModels).' roles synchronized with permission mappings.');

        // 3. Canonical Super Admin User
        $this->info('3. Provisioning Canonical Super Admin Account...');
        $targetEmail = env('OGGY_SUPERADMIN_EMAIL', 'admin@example.test');
        $superAdmin = User::where('email', $targetEmail)->first();

        if (! $superAdmin) {
            $targetPass = env('OGGY_SUPERADMIN_PASSWORD');
            if (! is_string($targetPass) || strlen($targetPass) < 12) {
                $this->error('OGGY_SUPERADMIN_PASSWORD must be set to at least 12 characters for first-time setup.');

                return self::FAILURE;
            }

            $superAdmin = User::create([
                'name' => 'Oggy Marble Super Admin',
                'email' => $targetEmail,
                'password' => Hash::make($targetPass),
                'status' => 'active',
            ]);
        } else {
            if (Schema::hasColumn('users', 'status')) {
                $superAdmin->status = 'active';
                $superAdmin->save();
            }
        }

        if (isset($roleModels['admin'])) {
            $superAdmin->roles()->sync([$roleModels['admin']->id]);
        }
        $this->info("   ✔ Canonical Super Admin ({$targetEmail}) provisioned with active status & admin role.");

        // 4. Chart of Accounts (Canonical PKR Setup)
        $this->info('4. Synchronizing Canonical Chart of Accounts...');
        $accountsData = [
            // Assets
            ['code' => '1010', 'name' => 'Cash Account (Hand)', 'type' => 'asset'],
            ['code' => '1011', 'name' => 'Primary Bank Account', 'type' => 'asset'],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset'],
            ['code' => '1200', 'name' => 'Tile Inventory (Sq Ft)', 'type' => 'asset'],
            ['code' => '1210', 'name' => 'Raw Stone Inventory (MT)', 'type' => 'asset'],
            ['code' => '1230', 'name' => 'Slab Inventory (MT)', 'type' => 'asset'],
            ['code' => '1220', 'name' => 'Work In Progress (WIP)', 'type' => 'asset'],
            ['code' => '1240', 'name' => 'Scrap Inventory', 'type' => 'asset'],

            // Liabilities & Equity
            ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability'],
            ['code' => '3100', 'name' => 'Owner Equity', 'type' => 'equity'],

            // Revenues
            ['code' => '4100', 'name' => 'Sales Revenue - Tiles', 'type' => 'revenue'],
            ['code' => '4110', 'name' => 'Sales Revenue - Raw Stone', 'type' => 'revenue'],
            ['code' => '4120', 'name' => 'Sales Revenue - Slabs', 'type' => 'revenue'],
            ['code' => '4200', 'name' => 'Machine Rental Revenue', 'type' => 'revenue'],
            ['code' => '4800', 'name' => 'Inventory Gain Adjustment', 'type' => 'revenue'],
            ['code' => '4900', 'name' => 'Production Variance Gain', 'type' => 'revenue'],

            // Expenses
            ['code' => '5100', 'name' => 'COGS - Tiles', 'type' => 'expense'],
            ['code' => '5110', 'name' => 'COGS - Raw Stone', 'type' => 'expense'],
            ['code' => '5120', 'name' => 'COGS - Slabs', 'type' => 'expense'],
            ['code' => '5500', 'name' => 'Inventory Adjustments Gain/Loss', 'type' => 'expense'],
            ['code' => '5600', 'name' => 'Production Overhead & Labor Allocation', 'type' => 'expense'],
            ['code' => '5800', 'name' => 'Inventory Shrinkage Expense', 'type' => 'expense'],
            ['code' => '5900', 'name' => 'Production Variance Expense', 'type' => 'expense'],
        ];

        $accountCount = 0;
        foreach ($accountsData as $acc) {
            Account::firstOrCreate(['code' => $acc['code']], $acc);
            $accountCount++;
        }
        $this->info("   ✔ {$accountCount} Chart of Accounts records active.");

        // 5. Pakistan Banks Master List
        $this->info('5. Synchronizing Pakistan Banks Master List...');
        $this->call('oggy:sync-pakistan-banks');

        // 6. Default Factory Warehouse
        $this->info('6. Verifying Default Factory Warehouse...');
        $wh = Warehouse::firstOrCreate(
            ['name' => 'Main Factory Yard & Warehouse'],
            ['location' => 'Configured Facility']
        );
        $this->info("   ✔ Warehouse initialized: #{$wh->id} {$wh->name}");

        // 7. Audit Product Readiness if products exist
        if (Product::count() > 0) {
            $this->info('7. Auditing Product Account Readiness...');
            $this->call('oggy:audit-product-readiness');
        } else {
            $this->info('7. Products table is clean (0 records). Ready for factory onboarding.');
        }

        $this->info("\n=================================================");
        $this->info('  MASTER DATA INITIALIZATION COMPLETE (SUCCESS)');
        $this->info('=================================================');

        // Summary table
        $summary = [
            ['Permissions', Permission::count()],
            ['Roles', Role::count()],
            ['Users', User::count()],
            ['Chart of Accounts', Account::count()],
            ['Pakistan Banks', PakistanBank::count()],
            ['Warehouses', Warehouse::count()],
            ['Products (Onboarding)', Product::count()],
        ];
        $this->table(['System Master Entity', 'Record Count'], $summary);

        return self::SUCCESS;
    }
}
