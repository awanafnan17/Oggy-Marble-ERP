<?php

use App\Http\Controllers\AccountingPeriodController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BankReconciliationController;
use App\Http\Controllers\CashBankController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryChallanController;
use App\Http\Controllers\InventoryReconciliationController;
use App\Http\Controllers\InventoryTransferController;
use App\Http\Controllers\MachineController;
use App\Http\Controllers\PartyLedgerController;
use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\ProductionScheduleController;
use App\Http\Controllers\ProductionVarianceController;
use App\Http\Controllers\QualityInspectionController;
use App\Http\Controllers\RentalJobController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SecurityController;
use App\Modules\Accounting\Controllers\AccountingController;
use App\Modules\Inventory\Controllers\InventoryController;
use App\Modules\Production\Controllers\ProductionController;
use App\Modules\Purchases\Controllers\PurchasesController;
use App\Modules\Sales\Controllers\SalesController;
use Illuminate\Support\Facades\Route;

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware(['guest', 'throttle:6,1']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Protected Application Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    // ============================================================
    // GET Routes & Basic Navigation Gating
    // ============================================================

    // Dashboard & Reports
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard')->middleware('permission:view_dashboard');

    Route::middleware(['permission:view_reports'])->group(function () {
        Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');
        Route::get('/reports/daily', [ReportsController::class, 'dailyReport'])->name('reports.daily');
        Route::get('/reports/export/{type}', [ReportsController::class, 'exportCsv'])->name('reports.export');
    });

    // Inventory Module Routes (GET)
    Route::middleware(['permission:view_inventory'])->group(function () {
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('/inventory/products', [InventoryController::class, 'products'])->name('inventory.products');
        Route::get('/inventory/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');
        Route::get('/inventory/reconciliation', [InventoryReconciliationController::class, 'index'])->name('inventory.reconciliation.index');
        Route::get('/inventory/transfers', [InventoryTransferController::class, 'index'])->name('inventory.transfers.index');
        Route::get('/inventory/transfers/{id}/gate-pass', [InventoryTransferController::class, 'gatePass'])->name('inventory.transfers.gate_pass');
    });

    // Sales Module Routes (GET)
    Route::middleware(['permission:view_sales'])->group(function () {
        Route::get('/sales', [SalesController::class, 'index'])->name('sales.index');
        Route::get('/sales/invoice/{id}/print', [SalesController::class, 'printInvoice'])->name('sales.invoice.print');
    });

    // Purchases Module Routes (GET)
    Route::get('/purchases', [PurchasesController::class, 'index'])->name('purchases.index')->middleware('permission:view_purchases');

    // Production Module Routes (GET)
    Route::middleware(['permission:view_production'])->group(function () {
        Route::get('/production', [ProductionController::class, 'index'])->name('production.index');
        Route::get('/production/stone-to-slab', [ProductionController::class, 'stoneToSlabIndex'])->name('production.stone_to_slab.index');
        Route::get('/production/slab-to-tile', [ProductionController::class, 'slabToTileIndex'])->name('production.slab_to_tile.index');
        Route::get('/production/variance', [ProductionVarianceController::class, 'index'])->name('production.variance.index');
        Route::get('/production/schedules', [ProductionScheduleController::class, 'index'])->name('production.schedules.index');

        // Machine Register (GET)
        Route::get('/production/machines', [MachineController::class, 'index'])->name('production.machines.index');
    });

    // Machine Rental Module Routes (GET)
    Route::get('/rental-jobs', [RentalJobController::class, 'index'])->name('rental.index')->middleware('permission:view_production');

    // Cash & Bank, Party Ledgers, Accounting (GET)
    Route::middleware(['permission:view_accounting'])->group(function () {
        Route::get('/cash-bank', [CashBankController::class, 'index'])->name('cashbank.index');
        Route::get('/cash-bank/reconciliation', [BankReconciliationController::class, 'index'])->name('cashbank.reconciliation.index');
        Route::get('/cash-bank/transactions/{bankTransaction}/proof', [CashBankController::class, 'showProof'])->name('cashbank.transactions.proof');
        Route::get('/party-ledgers', [PartyLedgerController::class, 'index'])->name('party_ledger.index');
        Route::get('/accounting', [AccountingController::class, 'index'])->name('accounting.index');
        Route::get('/accounting/periods', [AccountingPeriodController::class, 'index'])->name('accounting.periods.index');
    });

    // Administration (GET)
    Route::get('/administration/audit-logs', [AuditLogController::class, 'index'])->name('administration.audit_logs')->middleware('permission:view_audit_logs');

    // Quality Inspections (GET)
    Route::get('/quality-inspections', [QualityInspectionController::class, 'index'])->name('quality.index')->middleware('permission:view_quality');

    // Delivery Challans & Dispatch Tracking (GET)
    Route::middleware(['permission:view_dispatch'])->group(function () {
        Route::get('/delivery-challans', [DeliveryChallanController::class, 'index'])->name('challans.index');
        Route::get('/delivery-challans/{id}/print', [DeliveryChallanController::class, 'print'])->name('challans.print');
    });

    // ============================================================
    // POST/WRITE Routes (subject to period locking and manage permissions)
    // ============================================================
    Route::middleware(['period.open'])->group(function () {

        // Inventory Writing
        Route::middleware(['permission:manage_inventory'])->group(function () {
            Route::post('/inventory/products', [InventoryController::class, 'storeProduct'])->name('inventory.products.store');
            Route::post('/inventory/adjust', [InventoryController::class, 'adjustStock'])->name('inventory.adjust.store');
            Route::post('/inventory/reconciliation', [InventoryReconciliationController::class, 'reconcile'])->name('inventory.reconciliation.reconcile');
            Route::post('/inventory/transfers', [InventoryTransferController::class, 'transfer'])->name('inventory.transfers.transfer');
        });

        // Sales Writing
        Route::post('/sales/store', [SalesController::class, 'storeSalesOrder'])->name('sales.store')->middleware('permission:manage_sales');

        // Purchases Writing
        Route::post('/purchases/store', [PurchasesController::class, 'storePurchaseOrder'])->name('purchases.store')->middleware('permission:manage_purchases');

        // Production Writing
        Route::middleware(['permission:manage_production'])->group(function () {
            Route::post('/production/bom', [ProductionController::class, 'storeBom'])->name('production.bom.store');
            Route::post('/production/order', [ProductionController::class, 'storeProductionOrder'])->name('production.order.store');
            Route::post('/production/order/{id}/complete', [ProductionController::class, 'completeProductionOrder'])->name('production.order.complete');
            Route::post('/production/stone-to-slab', [ProductionController::class, 'storeStoneToSlab'])->name('production.stone_to_slab');
            Route::post('/production/slab-to-tile', [ProductionController::class, 'storeSlabToTile'])->name('production.slab_to_tile');
        });

        // Machine Rental Writing
        Route::post('/rental-jobs/store', [RentalJobController::class, 'store'])->name('rental.store')->middleware('permission:manage_production');

        // Cash & Bank / Accounting Writing
        Route::middleware(['permission:manage_accounting'])->group(function () {
            Route::post('/cash-bank/accounts', [CashBankController::class, 'storeAccount'])->name('cashbank.account.store');
            Route::post('/cash-bank/transactions', [CashBankController::class, 'storeTransaction'])->name('cashbank.transaction.store');
            Route::post('/cash-bank/transfers', [CashBankController::class, 'storeTransfer'])->name('cashbank.transfer.store');

            Route::post('/cash-bank/reconciliation/open', [BankReconciliationController::class, 'open'])->name('cashbank.reconciliation.open');
            Route::post('/cash-bank/reconciliation/bulk-reconcile-to-date', [BankReconciliationController::class, 'bulkReconcileToDate'])->name('cashbank.reconciliation.bulk_reconcile_to_date');
            Route::post('/cash-bank/reconciliation/reconcile-transaction', [BankReconciliationController::class, 'reconcileTransaction'])->name('cashbank.reconciliation.reconcile_transaction');
            Route::post('/cash-bank/reconciliation/close', [BankReconciliationController::class, 'close'])->name('cashbank.reconciliation.close');

            Route::post('/accounting/journal', [AccountingController::class, 'storeJournalEntry'])->name('accounting.journal.store');
        });

        // Payments Writing
        Route::post('/payments/customer', [PaymentsController::class, 'storeCustomerPayment'])->middleware('permission:manage_sales');
        Route::post('/payments/supplier', [PaymentsController::class, 'storeSupplierPayment'])->middleware('permission:manage_purchases');

        // Voiding / Reversal endpoints
        Route::post('/sales/invoice/{id}/void', [SalesController::class, 'voidSalesInvoice'])->name('sales.invoice.void')->middleware('permission:manage_sales');
        Route::post('/purchases/bill/{id}/void', [PurchasesController::class, 'voidPurchaseBill'])->name('purchases.bill.void')->middleware('permission:manage_purchases');
        Route::post('/payments/{id}/void', [PaymentsController::class, 'voidPayment'])->name('payments.void');
    });

    // Accounting Period Management (POST)
    Route::middleware(['permission:manage_accounting'])->group(function () {
        Route::post('/accounting/periods', [AccountingPeriodController::class, 'store'])->name('accounting.periods.store');
        Route::post('/accounting/periods/{id}/close', [AccountingPeriodController::class, 'close'])->name('accounting.periods.close');
        Route::post('/accounting/periods/{id}/reopen', [AccountingPeriodController::class, 'reopen'])->name('accounting.periods.reopen');
    });

    // Production Scheduling Writing (planning-only, not period-locked)
    Route::middleware(['permission:manage_production'])->group(function () {
        Route::post('/production/schedules', [ProductionScheduleController::class, 'store'])->name('production.schedules.store');
        Route::put('/production/schedules/{id}', [ProductionScheduleController::class, 'update'])->name('production.schedules.update');
        Route::patch('/production/schedules/{id}/status', [ProductionScheduleController::class, 'updateStatus'])->name('production.schedules.updateStatus');
    });

    // Machine Register Writing (not period-locked)
    Route::middleware(['permission:manage_machines'])->group(function () {
        Route::post('/production/machines', [MachineController::class, 'store'])->name('production.machines.store');
        Route::put('/production/machines/{id}', [MachineController::class, 'update'])->name('production.machines.update');
    });

    // Quality Inspections Writing (not period-locked)
    Route::middleware(['permission:manage_quality'])->group(function () {
        Route::post('/quality-inspections', [QualityInspectionController::class, 'store'])->name('quality.store');
        Route::put('/quality-inspections/{id}', [QualityInspectionController::class, 'update'])->name('quality.update');
        Route::delete('/quality-inspections/{id}', [QualityInspectionController::class, 'destroy'])->name('quality.destroy');
    });

    // Delivery Challans Writing (not period-locked)
    Route::middleware(['permission:manage_dispatch'])->group(function () {
        Route::post('/delivery-challans', [DeliveryChallanController::class, 'store'])->name('challans.store');
        Route::patch('/delivery-challans/{id}/status', [DeliveryChallanController::class, 'updateStatus'])->name('challans.updateStatus');
    });

    // Party Ledgers Writing (not period-locked)
    Route::post('/party-ledgers', [PartyLedgerController::class, 'store'])->name('party_ledger.store')->middleware('permission:manage_accounting');

    // Security and Passwords (not period-locked)
    Route::get('/profile/password', [SecurityController::class, 'showPasswordForm'])->name('profile.password');
    Route::post('/profile/password', [SecurityController::class, 'updatePassword'])->name('profile.password.update');

    Route::get('/administration/users', [SecurityController::class, 'indexUsers'])->name('administration.users');
    Route::post('/administration/users/{id}/reset-password', [SecurityController::class, 'resetUserPassword'])->name('administration.users.reset_password');
    Route::post('/administration/users/{id}/toggle-status', [SecurityController::class, 'toggleUserStatus'])->name('administration.users.toggle_status');
});
