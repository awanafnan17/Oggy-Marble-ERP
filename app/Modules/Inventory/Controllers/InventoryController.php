<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\InventoryBatch;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Product;
use App\Models\StockLedger;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class InventoryController extends Controller
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    public function index()
    {
        $warehouses = Warehouse::all();

        // Fetch products with their stock levels per warehouse from active batches
        $products = Product::all()->map(function ($product) {
            $totalStock = $this->inventoryService->getTotalStock($product->id);
            $inventoryValue = $this->inventoryService->getValuation($product->id);

            // Format stock per warehouse
            $warehouseStock = InventoryBatch::where('product_id', $product->id)
                ->where('quantity_remaining', '>', 0)
                ->groupBy('warehouse_id')
                ->selectRaw('warehouse_id, SUM(quantity_remaining) as total_qty')
                ->get()
                ->pluck('total_qty', 'warehouse_id');

            return [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'description' => $product->description,
                'unit' => $product->unit,
                'cost' => $product->cost,
                'price' => $product->price,
                'size' => $product->size,
                'total_stock' => $totalStock,
                'inventory_value' => $inventoryValue,
                'warehouse_stock' => $warehouseStock,
            ];
        });

        $stockLedger = StockLedger::with(['product', 'warehouse'])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return Inertia::render('Inventory/Index', [
            'products' => $products,
            'warehouses' => $warehouses,
            'stockLedger' => $stockLedger,
        ]);
    }

    public function storeProduct(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|unique:products,sku|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'unit' => 'required|string|max:20',
            'cost' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'product_type' => 'nullable|string|max:50',
            'unit_of_measure' => 'nullable|string|max:50',
            'stone_color' => 'nullable|string|max:50',
            'quality_grade' => 'nullable|string|max:50',
            'thickness' => 'nullable|string|max:50',
            'size' => 'nullable|string|max:50',
            'opening_quantity' => 'nullable|numeric|min:0',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'inventory_account_id' => 'nullable|exists:accounts,id',
            'revenue_account_id' => 'nullable|exists:accounts,id',
            'cogs_account_id' => 'nullable|exists:accounts,id',
        ]);

        $productType = $validated['product_type'] ?? 'finished_goods';
        $validated['product_type'] = $productType;
        $validated['unit_of_measure'] = $validated['unit_of_measure'] ?? $validated['unit'];

        // Resolve deterministic inventory, revenue, and COGS mappings by product type.
        $accountCodes = match ($productType) {
            'raw_stone', 'raw_material' => ['inventory' => '1210', 'revenue' => '4110', 'cogs' => '5110'],
            'slab' => ['inventory' => '1230', 'revenue' => '4120', 'cogs' => '5120'],
            default => ['inventory' => '1200', 'revenue' => '4100', 'cogs' => '5100'],
        };

        $invAccId = $validated['inventory_account_id'] ?? null;
        if (! $invAccId) {
            $invAcc = Account::where('code', $accountCodes['inventory'])->first()
                ?? Account::where('code', '1200')->first()
                ?? Account::where('code', '1220')->first()
                ?? Account::where('code', '1210')->first();
            if (! $invAcc) {
                $invAcc = Account::where('type', 'asset')->where('name', 'like', '%Inventory%')->first()
                    ?? Account::where('type', 'asset')->first();
            }
            $invAccId = $invAcc ? $invAcc->id : null;
        }

        $revAccId = $validated['revenue_account_id'] ?? null;
        if (! $revAccId) {
            $revAcc = Account::where('code', $accountCodes['revenue'])->first()
                ?? Account::where('code', '4100')->first()
                ?? Account::where('code', '4000')->first()
                ?? Account::where('code', '4010')->first();
            if (! $revAcc) {
                $revAcc = Account::where('type', 'revenue')->where('name', 'like', '%Revenue%')->first()
                    ?? Account::where('type', 'revenue')->first();
            }
            $revAccId = $revAcc ? $revAcc->id : null;
        }

        $cogsAccId = $validated['cogs_account_id'] ?? null;
        if (! $cogsAccId) {
            $cogsAcc = Account::where('code', $accountCodes['cogs'])->first()
                ?? Account::where('code', '5100')->first()
                ?? Account::where('code', '5000')->first()
                ?? Account::where('code', '5010')->first()
                ?? Account::where('code', '5020')->first();
            if (! $cogsAcc) {
                $cogsAcc = Account::where('type', 'expense')->where('name', 'like', '%Cost%')->orWhere('name', 'like', '%COGS%')->first()
                    ?? Account::where('type', 'expense')->first();
            }
            $cogsAccId = $cogsAcc ? $cogsAcc->id : null;
        }

        $productData = [
            'sku' => $validated['sku'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'unit' => $validated['unit'],
            'cost' => $validated['cost'],
            'price' => $validated['price'],
            'product_type' => $productType,
            'unit_of_measure' => $validated['unit_of_measure'],
            'stone_color' => $validated['stone_color'] ?? null,
            'quality_grade' => $validated['quality_grade'] ?? null,
            'thickness' => $validated['thickness'] ?? null,
            'size' => $validated['size'] ?? null,
            'inventory_account_id' => $invAccId,
            'revenue_account_id' => $revAccId,
            'cogs_account_id' => $cogsAccId,
        ];

        DB::transaction(function () use ($productData, $validated, &$product) {
            $product = Product::create($productData);

            $openingQty = isset($validated['opening_quantity']) ? (float) $validated['opening_quantity'] : 0;
            if ($openingQty > 0) {
                $warehouseId = $validated['warehouse_id'] ?? Warehouse::value('id');
                if ($warehouseId) {
                    $unitCost = (float) $validated['cost'];
                    $batch = $this->inventoryService->createBatch(
                        $product->id,
                        $warehouseId,
                        'opening_stock',
                        $product->id,
                        $openingQty,
                        $unitCost,
                        $product->unit_of_measure,
                        $product->product_type
                    );

                    StockLedger::create([
                        'product_id' => $product->id,
                        'warehouse_id' => $warehouseId,
                        'batch_id' => $batch->id,
                        'quantity' => $openingQty,
                        'unit_cost' => $unitCost,
                        'type' => 'IN',
                        'reference_type' => 'opening_stock',
                        'reference_id' => $product->id,
                        'notes' => 'Initial registered opening stock',
                    ]);
                }
            }
        });

        return redirect()->back()->with('success', 'Product created successfully.');
    }

    public function adjustStock(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric|not_in:0',
            'unit_cost' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:255',
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $product = Product::findOrFail($validated['product_id']);
                $qty = (float) $validated['quantity'];
                $unitCost = (float) $validated['unit_cost'];

                // Resolve dynamic inventory and adjustment accounts
                $inventoryAccount = ($product->inventory_account_id ? Account::find($product->inventory_account_id) : null)
                    ?? Account::where('code', '1200')->first()
                    ?? Account::where('type', 'asset')->where('name', 'like', '%Inventory%')->first()
                    ?? Account::where('type', 'asset')->first();

                $adjustmentAccount = Account::where('type', 'expense')->where('name', 'like', '%Adjustment%')->first()
                    ?? Account::where('code', '5010')->first()
                    ?? Account::where('code', '5000')->first()
                    ?? Account::where('type', 'expense')->first();

                $entry = JournalEntry::create([
                    'entry_number' => 'IA-'.time().'-'.rand(100, 999),
                    'date' => now()->toDateString(),
                    'description' => 'Inventory adjustment for '.$product->sku.' ('.$qty.' '.$product->unit.')',
                ]);

                if ($qty > 0) {
                    // Stock In: Create FIFO Batch
                    $batch = $this->inventoryService->createBatch(
                        $product->id,
                        $validated['warehouse_id'],
                        'adjustment',
                        $entry->id,
                        $qty,
                        $unitCost
                    );

                    // Create stock ledger entry linking to the batch
                    StockLedger::create([
                        'product_id' => $product->id,
                        'warehouse_id' => $validated['warehouse_id'],
                        'batch_id' => $batch->id,
                        'quantity' => $qty,
                        'unit_cost' => $unitCost,
                        'type' => 'IN',
                        'reference_type' => 'adjustment',
                        'notes' => $validated['notes'] ?? 'Manual adjustment stock in',
                    ]);

                    // Update product cost index
                    $product->cost = $unitCost;
                    $product->save();

                    // Bookkeeping
                    $value = $qty * $unitCost;
                    if ($inventoryAccount && $adjustmentAccount) {
                        // Debit Inventory (Asset Increase)
                        JournalItem::create([
                            'journal_entry_id' => $entry->id,
                            'account_id' => $inventoryAccount->id,
                            'debit' => $value,
                            'credit' => 0,
                        ]);

                        // Credit Adjustments (Gain)
                        JournalItem::create([
                            'journal_entry_id' => $entry->id,
                            'account_id' => $adjustmentAccount->id,
                            'debit' => 0,
                            'credit' => $value,
                        ]);
                    }
                } else {
                    // Stock Out: Deplete FIFO batches and calculate actual cost
                    $qtyNeeded = abs($qty);
                    $calculatedCost = $this->inventoryService->depleteStock(
                        $product->id,
                        $validated['warehouse_id'],
                        $qtyNeeded,
                        'adjustment',
                        $entry->id,
                        $validated['notes'] ?? 'Manual adjustment stock out'
                    );

                    // Bookkeeping
                    if ($inventoryAccount && $adjustmentAccount && $calculatedCost > 0) {
                        // Debit Adjustments (Expense Increase)
                        JournalItem::create([
                            'journal_entry_id' => $entry->id,
                            'account_id' => $adjustmentAccount->id,
                            'debit' => $calculatedCost,
                            'credit' => 0,
                        ]);

                        // Credit Inventory (Asset Decrease)
                        JournalItem::create([
                            'journal_entry_id' => $entry->id,
                            'account_id' => $inventoryAccount->id,
                            'debit' => 0,
                            'credit' => $calculatedCost,
                        ]);
                    }
                }

                // Link ledger trace reference back to the entry
                $entry->update([
                    'reference_type' => 'journal_entry',
                    'reference_id' => $entry->id,
                ]);
            });
        } catch (\Exception $e) {
            $businessMessages = ['Insufficient stock', 'negative', 'closed'];
            $isBusiness = false;
            foreach ($businessMessages as $msg) {
                if (stripos($e->getMessage(), $msg) !== false) {
                    $isBusiness = true;
                    break;
                }
            }
            if ($isBusiness && ! ($e instanceof QueryException) && ! ($e instanceof \TypeError) && ! ($e instanceof \Error)) {
                throw ValidationException::withMessages([
                    'quantity' => $e->getMessage(),
                ]);
            }
            throw $e;
        }

        return redirect()->back()->with('success', 'Stock adjusted successfully.');
    }

    public function products()
    {
        return $this->index();
    }

    public function adjust()
    {
        return $this->index();
    }
}
