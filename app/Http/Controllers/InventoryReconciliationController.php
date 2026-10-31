<?php

namespace App\Http\Controllers;

use App\Models\InventoryAdjustment;
use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\ReconciliationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InventoryReconciliationController extends Controller
{
    protected ReconciliationService $reconciliationService;

    public function __construct(ReconciliationService $reconciliationService)
    {
        $this->reconciliationService = $reconciliationService;
    }

    /**
     * Display batches with system quantity for reconciliation.
     */
    public function index(Request $request)
    {
        $products = Product::orderBy('name')->get(['id', 'sku', 'name']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name', 'location']);

        $batchesQuery = InventoryBatch::with(['product:id,sku,name', 'warehouse:id,name'])
            ->where('quantity_remaining', '>', 0);

        if ($request->filled('product_id')) {
            $batchesQuery->where('product_id', $request->product_id);
        }
        if ($request->filled('warehouse_id')) {
            $batchesQuery->where('warehouse_id', $request->warehouse_id);
        }

        $batches = $batchesQuery->orderBy('received_at', 'asc')->get();

        $recentAdjustments = InventoryAdjustment::with([
            'product:id,sku,name',
            'inventoryBatch:id,batch_reference',
            'creator:id,name',
        ])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return Inertia::render('Inventory/Reconciliation', [
            'products' => $products,
            'warehouses' => $warehouses,
            'batches' => $batches,
            'recentAdjustments' => $recentAdjustments,
            'filters' => [
                'product_id' => $request->product_id,
                'warehouse_id' => $request->warehouse_id,
            ],
        ]);
    }

    /**
     * Process a batch reconciliation.
     */
    public function reconcile(Request $request)
    {
        $validated = $request->validate([
            'inventory_batch_id' => 'required|exists:inventory_batches,id',
            'physical_quantity' => 'required|numeric|min:0',
            'adjustment_date' => 'required|date',
            'reason' => 'nullable|string|max:255',
        ]);

        try {
            $adjustment = $this->reconciliationService->reconcileBatch(
                (int) $validated['inventory_batch_id'],
                (float) $validated['physical_quantity'],
                $validated['reason'] ?? null,
                $validated['adjustment_date']
            );

            if ($adjustment) {
                return redirect()->back()->with('success', 'Reconciliation completed. Adjustment recorded.');
            }

            return redirect()->back()->with('info', 'No variance detected. System and physical counts match.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['reconciliation' => $e->getMessage()]);
        }
    }
}
