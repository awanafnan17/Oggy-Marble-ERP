<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Bom;
use App\Models\BomItem;
use App\Models\InventoryBatch;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionSchedule;
use App\Models\SlabToTileProduction;
use App\Models\StockLedger;
use App\Models\StoneToSlabProduction;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\ProductionVarianceService;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProductionController extends Controller
{
    protected InventoryService $inventoryService;

    protected ProductionVarianceService $varianceService;

    public function __construct(InventoryService $inventoryService, ProductionVarianceService $varianceService)
    {
        $this->inventoryService = $inventoryService;
        $this->varianceService = $varianceService;
    }

    public function index()
    {
        $products = Product::orderBy('name')->get();
        $rawStoneProducts = Product::where('product_type', 'raw_stone')->orderBy('name')->get();
        $slabProducts = Product::where('product_type', 'slab')->orderBy('name')->get();
        $tileProducts = Product::where('product_type', 'tile')->orderBy('name')->get();
        $scrapProducts = Product::where('product_type', 'scrap')->orderBy('name')->get();

        $rawStoneBatches = InventoryBatch::with('product')
            ->whereHas('product', function ($q) {
                $q->where('product_type', 'raw_stone');
            })
            ->where('quantity_remaining', '>', 0)
            ->get();

        $slabBatches = InventoryBatch::with('product')
            ->whereHas('product', function ($q) {
                $q->where('product_type', 'slab');
            })
            ->where('quantity_remaining', '>', 0)
            ->get();

        $stoneToSlabProductions = StoneToSlabProduction::with(['inputBatch.product', 'slabProduct', 'scrapProduct'])
            ->orderBy('created_at', 'desc')
            ->get();

        $slabToTileProductions = SlabToTileProduction::with(['inputBatch.product', 'tileProduct', 'scrapProduct'])
            ->orderBy('created_at', 'desc')
            ->get();

        $boms = Bom::with(['product', 'items.product'])->get()->map(function ($bom) {
            $rawMaterialCost = $bom->items->sum(function ($item) {
                return $item->quantity * ($item->product->cost ?? 0);
            });
            $totalEstimatedCost = $rawMaterialCost + $bom->labor_cost_estimate + $bom->overhead_cost_estimate;

            return [
                'id' => $bom->id,
                'name' => $bom->name,
                'product_id' => $bom->product_id,
                'product_name' => $bom->product->name,
                'product_sku' => $bom->product->sku,
                'product_size' => $bom->product->size,
                'labor_cost_estimate' => $bom->labor_cost_estimate,
                'overhead_cost_estimate' => $bom->overhead_cost_estimate,
                'raw_material_cost' => $rawMaterialCost,
                'total_estimated_cost' => $totalEstimatedCost,
                'items' => $bom->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product->name,
                        'product_sku' => $item->product->sku,
                        'quantity' => $item->quantity,
                        'unit_cost' => $item->product->cost,
                        'product_size' => $item->product->size,
                        'total_cost' => $item->quantity * ($item->product->cost ?? 0),
                    ];
                }),
            ];
        });

        $productionOrders = ProductionOrder::with(['bom.product'])->orderBy('created_at', 'desc')->get();

        return Inertia::render('Production/Index', [
            'boms' => $boms,
            'productionOrders' => $productionOrders,
            'products' => $products,
            'rawStoneProducts' => $rawStoneProducts,
            'slabProducts' => $slabProducts,
            'tileProducts' => $tileProducts,
            'scrapProducts' => $scrapProducts,
            'rawStoneBatches' => $rawStoneBatches,
            'slabBatches' => $slabBatches,
            'stoneToSlabProductions' => $stoneToSlabProductions,
            'slabToTileProductions' => $slabToTileProductions,
        ]);
    }

    public function storeBom(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'name' => 'required|string|max:255',
            'labor_cost_estimate' => 'required|numeric|min:0',
            'overhead_cost_estimate' => 'required|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id|different:product_id',
            'items.*.quantity' => 'required|numeric|gt:0',
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $bom = Bom::create([
                    'product_id' => $validated['product_id'],
                    'name' => $validated['name'],
                    'labor_cost_estimate' => $validated['labor_cost_estimate'],
                    'overhead_cost_estimate' => $validated['overhead_cost_estimate'],
                ]);

                foreach ($validated['items'] as $item) {
                    BomItem::create([
                        'bom_id' => $bom->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                    ]);
                }
            });
        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            $businessMessages = ['ledger account not found', 'closed', 'missing dynamic accounting setup'];
            $isBusiness = false;
            foreach ($businessMessages as $msg) {
                if (stripos($e->getMessage(), $msg) !== false) {
                    $isBusiness = true;
                    break;
                }
            }
            if ($isBusiness && ! ($e instanceof QueryException) && ! ($e instanceof \TypeError) && ! ($e instanceof \Error)) {
                throw ValidationException::withMessages([
                    'error' => $e->getMessage(),
                ]);
            }
            throw $e;
        }

        return redirect()->back()->with('success', 'BOM created successfully.');
    }

    public function storeProductionOrder(Request $request)
    {
        $validated = $request->validate([
            'bom_id' => 'required|exists:boms,id',
            'quantity' => 'required|numeric|gt:0',
        ]);

        ProductionOrder::create([
            'bom_id' => $validated['bom_id'],
            'quantity' => $validated['quantity'],
            'status' => 'pending',
        ]);

        return redirect()->back()->with('success', 'Production order created.');
    }

    public function completeProductionOrder(Request $request, $id)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'actual_labor_cost' => 'nullable|numeric|min:0',
            'actual_overhead_cost' => 'nullable|numeric|min:0',
        ]);

        $order = ProductionOrder::findOrFail($id);
        if ($order->status !== 'completed') {
            $bom = Bom::with('items.product')->findOrFail($order->bom_id);
            $finishedProduct = Product::findOrFail($bom->product_id);

            $wipAccount = Account::where('type', 'asset')->where('name', 'like', '%Work In Progress%')->orWhere('name', 'like', '%WIP%')->first();
            if (! $wipAccount) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'Work In Progress (WIP) asset ledger account is missing in the database.',
                ]);
            }

            $allocationAccount = Account::where('type', 'expense')->where('name', 'like', '%Overhead%')->orWhere('name', 'like', '%Allocation%')->first();
            if (! $allocationAccount) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'Production Overhead & Labor Allocation expense ledger account is missing in the database.',
                ]);
            }

            if (empty($finishedProduct->inventory_account_id)) {
                throw ValidationException::withMessages([
                    'warehouse_id' => "Finished product '{$finishedProduct->name}' is missing required Inventory Asset G/L mapping.",
                ]);
            }

            $qtyToProduce = (float) $order->quantity;
            foreach ($bom->items as $item) {
                if (empty($item->product->inventory_account_id)) {
                    throw ValidationException::withMessages([
                        'warehouse_id' => "Raw material product '{$item->product->name}' (SKU: {$item->product->sku}) is missing required Inventory G/L mapping.",
                    ]);
                }

                $requiredQty = (float) $item->quantity * $qtyToProduce;
                try {
                    $this->inventoryService->ensureStockAvailable($item->product_id, $requiredQty, (int) $validated['warehouse_id']);
                } catch (ValidationException $ex) {
                    throw ValidationException::withMessages([
                        'items' => $ex->validator->errors()->first('items') ?: $ex->getMessage(),
                        'warehouse_id' => "Insufficient stock for '{$item->product->name}' (SKU: {$item->product->sku}): ".($ex->validator->errors()->first('items') ?: $ex->getMessage()),
                    ]);
                }
            }
        }

        try {
            DB::transaction(function () use ($id, $validated) {
                $order = ProductionOrder::findOrFail($id);
                if ($order->status === 'completed') {
                    return;
                }

                $bom = Bom::with('items.product')->findOrFail($order->bom_id);
                $finishedProduct = Product::findOrFail($bom->product_id);
                $qtyToProduce = (float) $order->quantity;

                // Generate Journal Voucher Entry
                $entry = JournalEntry::create([
                    'entry_number' => 'PO-'.time().'-'.rand(100, 999),
                    'date' => now()->toDateString(),
                    'description' => 'Completed production order #'.$order->id.' for '.$finishedProduct->sku,
                ]);

                // Negative inventory guard check for all inputs
                foreach ($bom->items as $item) {
                    $requiredQty = (float) $item->quantity * $qtyToProduce;
                    $this->inventoryService->ensureStockAvailable($item->product_id, $requiredQty, (int) $validated['warehouse_id']);
                }

                // WIP and Allocation Accounts lookup dynamically
                $wipAccount = Account::where('type', 'asset')->where('name', 'like', '%Work In Progress%')->orWhere('name', 'like', '%WIP%')->first();
                $allocationAccount = Account::where('type', 'expense')->where('name', 'like', '%Overhead%')->orWhere('name', 'like', '%Allocation%')->first();
                $fgAccount = Account::find($finishedProduct->inventory_account_id);

                // 1. Issue & Consume Raw Materials via FIFO
                $totalMaterialCost = 0.0;
                foreach ($bom->items as $item) {
                    $requiredQty = (float) $item->quantity * $qtyToProduce;
                    $rawProduct = $item->product;

                    // Deplete FIFO stock
                    $actualCost = $this->inventoryService->depleteStock(
                        $rawProduct->id,
                        $validated['warehouse_id'],
                        $requiredQty,
                        'production',
                        $order->id,
                        'Consumed for production order #'.$order->id
                    );

                    $totalMaterialCost += $actualCost;

                    // Credit specific raw material inventory account dynamically
                    $rawInvAcc = Account::find($rawProduct->inventory_account_id);
                    if ($rawInvAcc && $actualCost > 0 && $wipAccount) {
                        JournalItem::create([
                            'journal_entry_id' => $entry->id,
                            'account_id' => $wipAccount->id,
                            'debit' => $actualCost,
                            'credit' => 0,
                        ]);
                        JournalItem::create([
                            'journal_entry_id' => $entry->id,
                            'account_id' => $rawInvAcc->id,
                            'debit' => 0,
                            'credit' => $actualCost,
                        ]);
                    }
                }

                $actualLabor = (float) ($validated['actual_labor_cost'] ?? ($bom->labor_cost_estimate * $qtyToProduce));
                $actualOverhead = (float) ($validated['actual_overhead_cost'] ?? ($bom->overhead_cost_estimate * $qtyToProduce));
                $totalActualCost = $totalMaterialCost + $actualLabor + $actualOverhead;
                $unitCostFinishedProduct = $qtyToProduce > 0 ? ($totalActualCost / $qtyToProduce) : 0.0;

                if ($wipAccount && $allocationAccount && ($actualLabor + $actualOverhead) > 0) {
                    // Step B: Capitalize Labor & Overhead: Dr WIP (1220), Cr Allocation (5600)
                    $combinedOverheadLabor = $actualLabor + $actualOverhead;
                    JournalItem::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $wipAccount->id,
                        'debit' => $combinedOverheadLabor,
                        'credit' => 0,
                    ]);
                    JournalItem::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $allocationAccount->id,
                        'debit' => 0,
                        'credit' => $combinedOverheadLabor,
                    ]);
                }

                // 3. Complete Production Job: Create Finished Goods FIFO Batch
                $batch = $this->inventoryService->createBatch(
                    $finishedProduct->id,
                    $validated['warehouse_id'],
                    'production',
                    $order->id,
                    $qtyToProduce,
                    $unitCostFinishedProduct,
                    $finishedProduct->unit_of_measure ?: $finishedProduct->unit,
                    $finishedProduct->product_type,
                    'PO-'.$order->id,
                    $finishedProduct->quality_grade
                );

                // Record finished goods stock ledger IN
                StockLedger::create([
                    'product_id' => $finishedProduct->id,
                    'warehouse_id' => $validated['warehouse_id'],
                    'batch_id' => $batch->id,
                    'quantity' => $qtyToProduce,
                    'unit_cost' => $unitCostFinishedProduct,
                    'type' => 'IN',
                    'reference_type' => 'production',
                    'reference_id' => $order->id,
                    'notes' => 'Produced finished goods from order #'.$order->id,
                ]);

                // Update product cost index
                $finishedProduct->cost = $unitCostFinishedProduct;
                $finishedProduct->save();

                if ($fgAccount && $wipAccount) {
                    // Step C: Capitalize finished goods: Dr FG (1200), Cr WIP (1220)
                    JournalItem::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $fgAccount->id,
                        'debit' => $totalActualCost,
                        'credit' => 0,
                    ]);
                    JournalItem::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $wipAccount->id,
                        'debit' => 0,
                        'credit' => $totalActualCost,
                    ]);
                }

                // 4. Update production order status & details
                $order->update([
                    'status' => 'completed',
                    'actual_material_cost' => $totalMaterialCost,
                    'actual_labor_cost' => $actualLabor,
                    'actual_overhead_cost' => $actualOverhead,
                    'completed_date' => now()->toDateString(),
                ]);

                // Link tracing reference
                $entry->update([
                    'reference_type' => 'production_order',
                    'reference_id' => $order->id,
                ]);
            });
        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            $businessMessages = ['Insufficient', 'ledger account not found', 'closed', 'missing dynamic accounting setup', 'depletion', 'negative'];
            $isBusiness = false;
            foreach ($businessMessages as $msg) {
                if (stripos($e->getMessage(), $msg) !== false) {
                    $isBusiness = true;
                    break;
                }
            }
            if ($isBusiness && ! ($e instanceof QueryException) && ! ($e instanceof \TypeError) && ! ($e instanceof \Error)) {
                throw ValidationException::withMessages([
                    'error' => $e->getMessage(),
                ]);
            }
            throw $e;
        }

        return redirect()->back()->with('success', 'Production order marked as completed and inventory updated.');
    }

    /**
     * Phase 3: Stone-to-Slab Production Job
     */
    public function storeStoneToSlab(Request $request)
    {
        $validated = $request->validate([
            'input_batch_id' => 'required|exists:inventory_batches,id',
            'input_quantity' => 'required|numeric|gt:0',
            'slab_product_id' => 'required|exists:products,id',
            'slabs_produced' => 'required|numeric|gt:0',
            'wastage_quantity' => 'required|numeric|min:0',
            'scrap_product_id' => 'nullable|exists:products,id',
            'scrap_quantity' => 'required|numeric|min:0',
            'scrap_unit' => 'nullable|string|max:10',
            'labor_cost' => 'required|numeric|min:0',
            'electricity_cost' => 'required|numeric|min:0',
            'fuel_cost' => 'required|numeric|min:0',
            'maintenance_cost' => 'required|numeric|min:0',
            'overhead_cost' => 'required|numeric|min:0',
            'production_date' => 'required|date',
            'warehouse_id' => 'required|exists:warehouses,id',
            'production_schedule_id' => 'nullable|exists:production_schedules,id',
            'waste_reason' => 'nullable|string|in:cutting_loss,breakage,quality_reject,machine_error,measurement_loss,other',
            'waste_operator_name' => 'nullable|string|max:255',
        ]);

        // Master-data readiness validations before transaction
        $wipAccount = Account::where('type', 'asset')->where('name', 'like', '%Work In Progress%')->orWhere('name', 'like', '%WIP%')->first();
        if (! $wipAccount) {
            throw ValidationException::withMessages([
                'input_batch_id' => 'Work In Progress (WIP) asset ledger account is missing in the database.',
            ]);
        }

        $allocationAccount = Account::where('type', 'expense')->where('name', 'like', '%Overhead%')->orWhere('name', 'like', '%Allocation%')->first();
        if (! $allocationAccount) {
            throw ValidationException::withMessages([
                'input_batch_id' => 'Production Overhead & Labor Allocation expense ledger account is missing in the database.',
            ]);
        }

        $inputBatch = InventoryBatch::with('product')->find($validated['input_batch_id']);
        if (! $inputBatch) {
            throw ValidationException::withMessages([
                'input_batch_id' => 'Selected input inventory batch does not exist.',
            ]);
        }
        if (! $inputBatch->product || ! $inputBatch->product->inventory_account_id) {
            throw ValidationException::withMessages([
                'input_batch_id' => 'Input raw stone product is missing required Inventory G/L account mapping.',
            ]);
        }

        $slabProduct = Product::find($validated['slab_product_id']);
        if (! $slabProduct || ! $slabProduct->inventory_account_id) {
            throw ValidationException::withMessages([
                'slab_product_id' => 'Output slab product is missing required Inventory G/L account mapping.',
            ]);
        }

        if ($validated['scrap_quantity'] > 0 && ! empty($validated['scrap_product_id'])) {
            $scrapProduct = Product::find($validated['scrap_product_id']);
            if (! $scrapProduct || ! $scrapProduct->inventory_account_id) {
                throw ValidationException::withMessages([
                    'scrap_product_id' => 'Output scrap product is missing required Inventory G/L account mapping.',
                ]);
            }
        }

        try {
            $this->inventoryService->ensureStockAvailable($inputBatch->product_id, (float) $validated['input_quantity'], (int) $inputBatch->warehouse_id, (int) $inputBatch->id);
        } catch (ValidationException $ex) {
            throw ValidationException::withMessages([
                'items' => $ex->validator->errors()->first('items') ?: $ex->getMessage(),
                'input_quantity' => $ex->validator->errors()->first('items') ?: $ex->getMessage(),
            ]);
        }

        try {
            DB::transaction(function () use ($validated) {
                $inputBatch = InventoryBatch::findOrFail($validated['input_batch_id']);
                $slabProduct = Product::findOrFail($validated['slab_product_id']);
                $inputQty = (float) $validated['input_quantity'];

                $productionNumber = 'STS-'.time().'-'.rand(100, 999);

                // Calculate computed wastage percentage
                $wastageQty = (float) $validated['wastage_quantity'];
                $wastagePct = $inputQty > 0 ? (($wastageQty / $inputQty) * 100) : 0.0;

                // Handle production schedule association
                if (! empty($validated['production_schedule_id'])) {
                    $schedule = ProductionSchedule::findOrFail($validated['production_schedule_id']);
                    if ($schedule->production_type !== 'stone_to_slab') {
                        throw ValidationException::withMessages(['production_schedule_id' => 'Selected schedule is not a Stone-to-Slab job.']);
                    }
                    if (in_array($schedule->status, ['completed', 'cancelled'])) {
                        throw ValidationException::withMessages(['production_schedule_id' => 'Selected schedule is already completed or cancelled.']);
                    }
                    $schedule->update(['status' => 'completed']);
                }

                // Create temporary run record so we have an ID for reference
                $run = StoneToSlabProduction::create(array_merge($validated, [
                    'production_order_number' => $productionNumber,
                    'total_cost' => 0.0,
                    'cost_per_mt' => 0.0,
                    'yield_percentage' => 0.0,
                    'wastage_percentage' => $wastagePct,
                ]));

                // 2. Negative inventory guard
                $this->inventoryService->ensureStockAvailable($inputBatch->product_id, $inputQty, (int) $inputBatch->warehouse_id, (int) $inputBatch->id);

                // 1. Deplete FIFO stock from selected batch
                $consumedCost = $this->inventoryService->depleteStock(
                    $inputBatch->product_id,
                    $inputBatch->warehouse_id,
                    $inputQty,
                    'stone_to_slab',
                    $run->id,
                    'Consumed raw stone for '.$productionNumber,
                    $inputBatch->id
                );

                // 2. Compute Yields and Slabs Cost
                $labor = (float) $validated['labor_cost'];
                $electricity = (float) $validated['electricity_cost'];
                $fuel = (float) $validated['fuel_cost'];
                $maintenance = (float) $validated['maintenance_cost'];
                $overhead = (float) $validated['overhead_cost'];
                $additionalCosts = $labor + $electricity + $fuel + $maintenance + $overhead;

                $totalCost = $consumedCost + $additionalCosts;
                $slabsProduced = (float) $validated['slabs_produced'];
                $costPerMt = $slabsProduced > 0 ? ($totalCost / $slabsProduced) : 0.0;
                $yieldPct = $inputQty > 0 ? (($slabsProduced / $inputQty) * 100) : 0.0;

                // 3. Post double entry journal entries
                $wipAccount = Account::where('type', 'asset')->where('name', 'like', '%Work In Progress%')->orWhere('name', 'like', '%WIP%')->first();
                $rawAccount = Account::find($inputBatch->product->inventory_account_id);
                $slabAccount = Account::find($slabProduct->inventory_account_id);
                $allocationAccount = Account::where('type', 'expense')->where('name', 'like', '%Overhead%')->orWhere('name', 'like', '%Allocation%')->first();

                $entry = JournalEntry::create([
                    'entry_number' => 'JV-'.time().'-'.rand(100, 999),
                    'date' => $validated['production_date'],
                    'description' => 'Stone-to-Slab conversion production '.$productionNumber,
                    'reference_type' => 'stone_to_slab',
                    'reference_id' => $run->id,
                ]);

                // Raw material issue: Dr WIP, Cr Raw Stone
                if ($wipAccount && $rawAccount && $consumedCost > 0) {
                    JournalItem::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $wipAccount->id,
                        'debit' => $consumedCost,
                        'credit' => 0,
                    ]);
                    JournalItem::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $rawAccount->id,
                        'debit' => 0,
                        'credit' => $consumedCost,
                    ]);
                }

                // Overhead capitalization: Dr WIP, Cr Overhead offset allocation
                if ($wipAccount && $allocationAccount && $additionalCosts > 0) {
                    JournalItem::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $wipAccount->id,
                        'debit' => $additionalCosts,
                        'credit' => 0,
                    ]);
                    JournalItem::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $allocationAccount->id,
                        'debit' => 0,
                        'credit' => $additionalCosts,
                    ]);
                }

                // Complete: Dr Slab Inventory, Cr WIP
                if ($slabAccount && $wipAccount && $totalCost > 0) {
                    JournalItem::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $slabAccount->id,
                        'debit' => $totalCost,
                        'credit' => 0,
                    ]);
                    JournalItem::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $wipAccount->id,
                        'debit' => 0,
                        'credit' => $totalCost,
                    ]);
                }

                // 4. Create Slab inventory batch
                $slabBatch = $this->inventoryService->createBatch(
                    $slabProduct->id,
                    $validated['warehouse_id'],
                    'stone_to_slab',
                    $run->id,
                    $slabsProduced,
                    $costPerMt,
                    'MT',
                    'slab',
                    $productionNumber,
                    $slabProduct->quality_grade
                );

                // Record Slab StockLedger IN
                StockLedger::create([
                    'product_id' => $slabProduct->id,
                    'warehouse_id' => $validated['warehouse_id'],
                    'batch_id' => $slabBatch->id,
                    'quantity' => $slabsProduced,
                    'unit_cost' => $costPerMt,
                    'type' => 'IN',
                    'reference_type' => 'stone_to_slab',
                    'reference_id' => $run->id,
                    'notes' => 'Produced slabs from stone batch #'.$inputBatch->id,
                ]);

                // Update slab product cost index
                $slabProduct->cost = $costPerMt;
                $slabProduct->save();

                // 5. Create Scrap inventory batch if scrap generated
                $scrapQty = (float) $validated['scrap_quantity'];
                if ($scrapQty > 0 && ! empty($validated['scrap_product_id'])) {
                    $scrapProduct = Product::findOrFail($validated['scrap_product_id']);

                    $scrapBatch = $this->inventoryService->createBatch(
                        $scrapProduct->id,
                        $validated['warehouse_id'],
                        'stone_to_slab',
                        $run->id,
                        $scrapQty,
                        0.0,
                        $validated['scrap_unit'] ?: 'MT',
                        'scrap',
                        $productionNumber,
                        null
                    );

                    StockLedger::create([
                        'product_id' => $scrapProduct->id,
                        'warehouse_id' => $validated['warehouse_id'],
                        'batch_id' => $scrapBatch->id,
                        'quantity' => $scrapQty,
                        'unit_cost' => 0.0,
                        'type' => 'IN',
                        'reference_type' => 'stone_to_slab',
                        'reference_id' => $run->id,
                        'notes' => 'Scrap generated during STS '.$productionNumber,
                    ]);
                }

                // 6. Update run record details
                $run->update([
                    'total_cost' => $totalCost,
                    'cost_per_mt' => $costPerMt,
                    'yield_percentage' => $yieldPct,
                    'journal_entry_id' => $entry->id,
                ]);

                // 7. Calculate and post production variance (only when BOM exists)
                $bom = Bom::where('product_id', $slabProduct->id)->first();
                if ($bom) {
                    $expectedMaterialCost = $consumedCost;
                    $expectedLaborCost = (float) $bom->labor_cost_estimate * $inputQty;
                    $expectedOverheadCost = (float) $bom->overhead_cost_estimate * $inputQty;

                    $this->varianceService->calculateAndPostVariance(
                        $run,
                        $consumedCost,
                        $additionalCosts,
                        0.0,
                        $expectedMaterialCost,
                        $expectedLaborCost,
                        $expectedOverheadCost,
                        $validated['production_date'],
                        $productionNumber
                    );
                }
            });
        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            $businessMessages = ['Insufficient', 'negative', 'closed', 'not found', 'cannot transfer', 'overlap', 'Selected schedule'];
            $isBusiness = false;
            foreach ($businessMessages as $msg) {
                if (stripos($e->getMessage(), $msg) !== false) {
                    $isBusiness = true;
                    break;
                }
            }
            if ($isBusiness && ! ($e instanceof QueryException) && ! ($e instanceof \TypeError) && ! ($e instanceof \Error)) {
                throw ValidationException::withMessages([
                    'input_batch_id' => $e->getMessage(),
                ]);
            }
            throw $e;
        }

        return redirect()->back()->with('success', 'Stone-to-Slab production run logged and inventory updated.');
    }

    /**
     * Phase 4: Slab-to-Tile Production Job
     */
    public function storeSlabToTile(Request $request)
    {
        $validated = $request->validate([
            'input_batch_id' => 'required|exists:inventory_batches,id',
            'input_quantity' => 'required|numeric|gt:0',
            'tile_product_id' => 'required|exists:products,id',
            'tiles_produced' => 'required|numeric|gt:0',
            'wastage_percentage' => 'required|numeric|min:0',
            'scrap_product_id' => 'nullable|exists:products,id',
            'scrap_quantity' => 'required|numeric|min:0',
            'scrap_unit' => 'nullable|string|max:10',
            'labor_cost' => 'required|numeric|min:0',
            'cutting_cost' => 'required|numeric|min:0',
            'polishing_cost' => 'required|numeric|min:0',
            'packaging_cost' => 'required|numeric|min:0',
            'overhead_cost' => 'required|numeric|min:0',
            'production_date' => 'required|date',
            'warehouse_id' => 'required|exists:warehouses,id',
            'production_schedule_id' => 'nullable|exists:production_schedules,id',
            'waste_reason' => 'nullable|string|in:cutting_loss,breakage,quality_reject,machine_error,measurement_loss,other',
            'waste_operator_name' => 'nullable|string|max:255',
        ]);

        // Master-data readiness validations before transaction
        $wipAccount = Account::where('type', 'asset')->where('name', 'like', '%Work In Progress%')->orWhere('name', 'like', '%WIP%')->first();
        if (! $wipAccount) {
            throw ValidationException::withMessages([
                'input_batch_id' => 'Work In Progress (WIP) asset ledger account is missing in the database.',
            ]);
        }

        $allocationAccount = Account::where('type', 'expense')->where('name', 'like', '%Overhead%')->orWhere('name', 'like', '%Allocation%')->first();
        if (! $allocationAccount) {
            throw ValidationException::withMessages([
                'input_batch_id' => 'Production Overhead & Labor Allocation expense ledger account is missing in the database.',
            ]);
        }

        $inputBatch = InventoryBatch::with('product')->find($validated['input_batch_id']);
        if (! $inputBatch) {
            throw ValidationException::withMessages([
                'input_batch_id' => 'Selected input inventory batch does not exist.',
            ]);
        }
        if (! $inputBatch->product || ! $inputBatch->product->inventory_account_id) {
            throw ValidationException::withMessages([
                'input_batch_id' => 'Input slab product is missing required Inventory G/L account mapping.',
            ]);
        }

        $tileProduct = Product::find($validated['tile_product_id']);
        if (! $tileProduct || ! $tileProduct->inventory_account_id) {
            throw ValidationException::withMessages([
                'tile_product_id' => 'Output tile product is missing required Inventory G/L account mapping.',
            ]);
        }

        if ($validated['scrap_quantity'] > 0 && ! empty($validated['scrap_product_id'])) {
            $scrapProduct = Product::find($validated['scrap_product_id']);
            if (! $scrapProduct || ! $scrapProduct->inventory_account_id) {
                throw ValidationException::withMessages([
                    'scrap_product_id' => 'Output scrap product is missing required Inventory G/L account mapping.',
                ]);
            }
        }

        try {
            $this->inventoryService->ensureStockAvailable($inputBatch->product_id, (float) $validated['input_quantity'], (int) $inputBatch->warehouse_id, (int) $inputBatch->id);
        } catch (ValidationException $ex) {
            throw ValidationException::withMessages([
                'items' => $ex->validator->errors()->first('items') ?: $ex->getMessage(),
                'input_quantity' => $ex->validator->errors()->first('items') ?: $ex->getMessage(),
            ]);
        }

        try {
            DB::transaction(function () use ($validated) {
                $inputBatch = InventoryBatch::findOrFail($validated['input_batch_id']);
                $tileProduct = Product::findOrFail($validated['tile_product_id']);
                $inputQty = (float) $validated['input_quantity'];

                $productionNumber = 'STT-'.time().'-'.rand(100, 999);

                // Calculate computed wastage quantity
                $wastagePct = (float) $validated['wastage_percentage'];
                $wastageQty = ($wastagePct / 100) * $inputQty;

                // Handle production schedule association
                if (! empty($validated['production_schedule_id'])) {
                    $schedule = ProductionSchedule::findOrFail($validated['production_schedule_id']);
                    if ($schedule->production_type !== 'slab_to_tile') {
                        throw ValidationException::withMessages(['production_schedule_id' => 'Selected schedule is not a Slab-to-Tile job.']);
                    }
                    if (in_array($schedule->status, ['completed', 'cancelled'])) {
                        throw ValidationException::withMessages(['production_schedule_id' => 'Selected schedule is already completed or cancelled.']);
                    }
                    $schedule->update(['status' => 'completed']);
                }

                $run = SlabToTileProduction::create(array_merge($validated, [
                    'production_order_number' => $productionNumber,
                    'total_cost' => 0.0,
                    'cost_per_sqft' => 0.0,
                    'conversion_ratio' => 0.0,
                    'wastage_quantity' => $wastageQty,
                ]));

                // 2. Negative inventory guard
                $this->inventoryService->ensureStockAvailable($inputBatch->product_id, $inputQty, (int) $inputBatch->warehouse_id, (int) $inputBatch->id);

                // 1. Deplete FIFO stock from selected batch
                $consumedCost = $this->inventoryService->depleteStock(
                    $inputBatch->product_id,
                    $inputBatch->warehouse_id,
                    $inputQty,
                    'slab_to_tile',
                    $run->id,
                    'Consumed slab for '.$productionNumber,
                    $inputBatch->id
                );

                // 2. Cost calculations
                $labor = (float) $validated['labor_cost'];
                $cutting = (float) $validated['cutting_cost'];
                $polishing = (float) $validated['polishing_cost'];
                $packaging = (float) $validated['packaging_cost'];
                $overhead = (float) $validated['overhead_cost'];

                $additionalCosts = $labor + $cutting + $polishing + $packaging + $overhead;
                $totalCost = $consumedCost + $additionalCosts;

                $tilesProduced = (float) $validated['tiles_produced'];
                $costPerSqft = $tilesProduced > 0 ? ($totalCost / $tilesProduced) : 0.0;
                $conversionRatio = $inputQty > 0 ? ($tilesProduced / $inputQty) : 0.0;

                // 3. Post double entry journal entries
                $wipAccount = Account::where('type', 'asset')->where('name', 'like', '%Work In Progress%')->orWhere('name', 'like', '%WIP%')->first();
                $slabAccount = Account::find($inputBatch->product->inventory_account_id);
                $tileAccount = Account::find($tileProduct->inventory_account_id);
                $allocationAccount = Account::where('type', 'expense')->where('name', 'like', '%Overhead%')->orWhere('name', 'like', '%Allocation%')->first();

                $entry = JournalEntry::create([
                    'entry_number' => 'JV-'.time().'-'.rand(100, 999),
                    'date' => $validated['production_date'],
                    'description' => 'Slab-to-Tile slicing production '.$productionNumber,
                    'reference_type' => 'slab_to_tile',
                    'reference_id' => $run->id,
                ]);

                // Slab issue: Dr WIP, Cr Slab Inventory
                if ($wipAccount && $slabAccount && $consumedCost > 0) {
                    JournalItem::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $wipAccount->id,
                        'debit' => $consumedCost,
                        'credit' => 0,
                    ]);
                    JournalItem::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $slabAccount->id,
                        'debit' => 0,
                        'credit' => $consumedCost,
                    ]);
                }

                // Overhead capitalization: Dr WIP, Cr Allocation Offset
                if ($wipAccount && $allocationAccount && $additionalCosts > 0) {
                    JournalItem::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $wipAccount->id,
                        'debit' => $additionalCosts,
                        'credit' => 0,
                    ]);
                    JournalItem::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $allocationAccount->id,
                        'debit' => 0,
                        'credit' => $additionalCosts,
                    ]);
                }

                // Complete: Dr Tile Inventory, Cr WIP
                if ($tileAccount && $wipAccount && $totalCost > 0) {
                    JournalItem::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $tileAccount->id,
                        'debit' => $totalCost,
                        'credit' => 0,
                    ]);
                    JournalItem::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $wipAccount->id,
                        'debit' => 0,
                        'credit' => $totalCost,
                    ]);
                }

                // 4. Create Tile inventory batch
                $tileBatch = $this->inventoryService->createBatch(
                    $tileProduct->id,
                    $validated['warehouse_id'],
                    'slab_to_tile',
                    $run->id,
                    $tilesProduced,
                    $costPerSqft,
                    'Sq Ft',
                    'tile',
                    $productionNumber,
                    $tileProduct->quality_grade
                );

                // Record Tile StockLedger IN
                StockLedger::create([
                    'product_id' => $tileProduct->id,
                    'warehouse_id' => $validated['warehouse_id'],
                    'batch_id' => $tileBatch->id,
                    'quantity' => $tilesProduced,
                    'unit_cost' => $costPerSqft,
                    'type' => 'IN',
                    'reference_type' => 'slab_to_tile',
                    'reference_id' => $run->id,
                    'notes' => 'Produced tiles from slab batch #'.$inputBatch->id,
                ]);

                // Update product cost index
                $tileProduct->cost = $costPerSqft;
                $tileProduct->save();

                // 5. Create Scrap inventory batch if scrap generated
                $scrapQty = (float) $validated['scrap_quantity'];
                if ($scrapQty > 0 && ! empty($validated['scrap_product_id'])) {
                    $scrapProduct = Product::findOrFail($validated['scrap_product_id']);

                    $scrapBatch = $this->inventoryService->createBatch(
                        $scrapProduct->id,
                        $validated['warehouse_id'],
                        'slab_to_tile',
                        $run->id,
                        $scrapQty,
                        0.0,
                        $validated['scrap_unit'] ?: 'Sq Ft',
                        'scrap',
                        $productionNumber,
                        null
                    );

                    StockLedger::create([
                        'product_id' => $scrapProduct->id,
                        'warehouse_id' => $validated['warehouse_id'],
                        'batch_id' => $scrapBatch->id,
                        'quantity' => $scrapQty,
                        'unit_cost' => 0.0,
                        'type' => 'IN',
                        'reference_type' => 'slab_to_tile',
                        'reference_id' => $run->id,
                        'notes' => 'Scrap generated during STT '.$productionNumber,
                    ]);
                }

                // 6. Update run record details
                $run->update([
                    'total_cost' => $totalCost,
                    'cost_per_sqft' => $costPerSqft,
                    'conversion_ratio' => $conversionRatio,
                    'journal_entry_id' => $entry->id,
                ]);

                // 7. Calculate and post production variance (only when BOM exists)
                $bom = Bom::where('product_id', $tileProduct->id)->first();
                if ($bom) {
                    $expectedMaterialCost = $consumedCost;
                    $expectedLaborCost = (float) $bom->labor_cost_estimate * $inputQty;
                    $expectedOverheadCost = (float) $bom->overhead_cost_estimate * $inputQty;

                    $this->varianceService->calculateAndPostVariance(
                        $run,
                        $consumedCost,
                        $additionalCosts,
                        0.0,
                        $expectedMaterialCost,
                        $expectedLaborCost,
                        $expectedOverheadCost,
                        $validated['production_date'],
                        $productionNumber
                    );
                }
            });
        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            $businessMessages = ['Insufficient', 'negative', 'closed', 'not found', 'cannot transfer', 'overlap', 'Selected schedule'];
            $isBusiness = false;
            foreach ($businessMessages as $msg) {
                if (stripos($e->getMessage(), $msg) !== false) {
                    $isBusiness = true;
                    break;
                }
            }
            if ($isBusiness && ! ($e instanceof QueryException) && ! ($e instanceof \TypeError) && ! ($e instanceof \Error)) {
                throw ValidationException::withMessages([
                    'input_batch_id' => $e->getMessage(),
                ]);
            }
            throw $e;
        }

        return redirect()->back()->with('success', 'Slab-to-Tile production run logged and inventory updated.');
    }

    public function stoneToSlabIndex()
    {
        $products = Product::orderBy('name')->get();
        $rawStoneProducts = Product::where('product_type', 'raw_stone')->orderBy('name')->get();
        $slabProducts = Product::where('product_type', 'slab')->orderBy('name')->get();
        $scrapProducts = Product::where('product_type', 'scrap')->orderBy('name')->get();

        $rawStoneBatches = InventoryBatch::with('product')
            ->whereHas('product', function ($q) {
                $q->where('product_type', 'raw_stone');
            })
            ->where('quantity_remaining', '>', 0)
            ->get();

        $stoneToSlabProductions = StoneToSlabProduction::with(['inputBatch.product', 'slabProduct', 'scrapProduct'])
            ->orderBy('created_at', 'desc')
            ->get();

        $warehouses = Warehouse::orderBy('name')->get();

        $schedules = ProductionSchedule::where('production_type', 'stone_to_slab')
            ->whereIn('status', ['planned', 'queued', 'in_progress'])
            ->orderBy('planned_start_date', 'asc')
            ->get();

        return Inertia::render('Production/StoneToSlab', [
            'products' => $products,
            'rawStoneProducts' => $rawStoneProducts,
            'slabProducts' => $slabProducts,
            'scrapProducts' => $scrapProducts,
            'rawStoneBatches' => $rawStoneBatches,
            'stoneToSlabProductions' => $stoneToSlabProductions,
            'warehouses' => $warehouses,
            'schedules' => $schedules,
        ]);
    }

    public function slabToTileIndex()
    {
        $products = Product::orderBy('name')->get();
        $slabProducts = Product::where('product_type', 'slab')->orderBy('name')->get();
        $tileProducts = Product::where('product_type', 'tile')->orderBy('name')->get();
        $scrapProducts = Product::where('product_type', 'scrap')->orderBy('name')->get();

        $slabBatches = InventoryBatch::with('product')
            ->whereHas('product', function ($q) {
                $q->where('product_type', 'slab');
            })
            ->where('quantity_remaining', '>', 0)
            ->get();

        $slabToTileProductions = SlabToTileProduction::with(['inputBatch.product', 'tileProduct', 'scrapProduct'])
            ->orderBy('created_at', 'desc')
            ->get();

        $warehouses = Warehouse::orderBy('name')->get();

        $schedules = ProductionSchedule::where('production_type', 'slab_to_tile')
            ->whereIn('status', ['planned', 'queued', 'in_progress'])
            ->orderBy('planned_start_date', 'asc')
            ->get();

        return Inertia::render('Production/SlabToTile', [
            'products' => $products,
            'slabProducts' => $slabProducts,
            'tileProducts' => $tileProducts,
            'scrapProducts' => $scrapProducts,
            'slabBatches' => $slabBatches,
            'slabToTileProductions' => $slabToTileProductions,
            'warehouses' => $warehouses,
            'schedules' => $schedules,
        ]);
    }
}
