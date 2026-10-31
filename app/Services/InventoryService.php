<?php

namespace App\Services;

use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\StockLedger;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    /**
     * Create a new inventory batch on stock inward.
     */
    public function createBatch(
        int $productId,
        int $warehouseId,
        string $refType,
        int $refId,
        float $quantity,
        float $unitCost,
        ?string $uom = null,
        ?string $productType = null,
        ?string $batchRef = null,
        ?string $qualityGrade = null
    ): InventoryBatch {
        $product = Product::findOrFail($productId);
        $totalCost = $quantity * $unitCost;

        return InventoryBatch::create([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'quantity_received' => $quantity,
            'quantity_remaining' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'unit_of_measure' => $uom ?: ($product->unit_of_measure ?: $product->unit),
            'product_type' => $productType ?: $product->product_type,
            'batch_reference' => $batchRef,
            'quality_grade' => $qualityGrade ?: $product->quality_grade,
            'received_at' => now(),
        ]);
    }

    /**
     * Deplete stock using First-In-First-Out (FIFO) or manual batch selection.
     * Returns calculated cost of goods sold.
     */
    public function depleteStock(
        int $productId,
        int $warehouseId,
        float $qtyNeeded,
        string $refType,
        int $refId,
        string $notes = '',
        ?int $selectedBatchId = null
    ): float {
        return DB::transaction(function () use ($productId, $warehouseId, $qtyNeeded, $refType, $refId, $notes, $selectedBatchId) {
            $product = Product::findOrFail($productId);

            if ($selectedBatchId) {
                $batches = InventoryBatch::where('id', $selectedBatchId)
                    ->where('quantity_remaining', '>', 0)
                    ->lockForUpdate()
                    ->get();
            } else {
                // Retrieve active batches sorted by received_at ASC (FIFO)
                $batches = InventoryBatch::where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('quantity_remaining', '>', 0)
                    ->orderBy('received_at', 'asc')
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->get();
            }

            $availableStock = $batches->sum('quantity_remaining');

            if ($availableStock < $qtyNeeded) {
                throw new Exception('Insufficient stock for product '.$product->sku.' in selected warehouse. Needed: '.$qtyNeeded.', Available: '.$availableStock);
            }

            $remainingToDeplete = $qtyNeeded;
            $totalCogs = 0.0;

            foreach ($batches as $batch) {
                if ($remainingToDeplete <= 0) {
                    break;
                }

                $qtyToTake = min($batch->quantity_remaining, $remainingToDeplete);

                // Deduct from batch
                $batch->quantity_remaining -= $qtyToTake;
                if ($batch->quantity_remaining < 0) {
                    throw new Exception('Stock deduction would result in negative quantity remaining.');
                }
                $batch->save();

                // Record transaction stock ledger
                StockLedger::create([
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'batch_id' => $batch->id,
                    'quantity' => -$qtyToTake,
                    'unit_cost' => $batch->unit_cost,
                    'type' => 'OUT',
                    'reference_type' => $refType,
                    'reference_id' => $refId,
                    'notes' => $notes ?: 'Depletion from batch #'.$batch->id,
                ]);

                $totalCogs += $qtyToTake * (float) $batch->unit_cost;
                $remainingToDeplete -= $qtyToTake;
            }

            return $totalCogs;
        });
    }

    /**
     * Ensure stock is available before starting operations.
     */
    public function ensureStockAvailable(int $productId, float $qty, ?int $warehouseId = null, ?int $selectedBatchId = null)
    {
        $query = InventoryBatch::where('product_id', $productId);
        if ($selectedBatchId) {
            $query->where('id', $selectedBatchId);
        } elseif ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }
        $total = $query->sum('quantity_remaining');

        if ($total < $qty) {
            $product = Product::find($productId);
            $sku = $product ? $product->sku : 'Product';
            if ($selectedBatchId) {
                $msg = "Insufficient stock for product {$sku} in selected batch.";
            } elseif ($warehouseId) {
                $msg = "Insufficient stock for product {$sku} in selected warehouse.";
            } else {
                $msg = "Insufficient stock for product {$sku}.";
            }
            throw ValidationException::withMessages([
                'items' => $msg,
            ]);
        }
    }

    /**
     * Calculate current valuation of a product across all warehouses.
     */
    public function getValuation(int $productId): float
    {
        return (float) InventoryBatch::where('product_id', $productId)
            ->where('quantity_remaining', '>', 0)
            ->selectRaw('SUM(quantity_remaining * unit_cost) as total_val')
            ->value('total_val') ?? 0.0;
    }

    /**
     * Get total quantity on hand for a product.
     */
    public function getTotalStock(int $productId): float
    {
        return (float) InventoryBatch::where('product_id', $productId)
            ->where('quantity_remaining', '>', 0)
            ->sum('quantity_remaining');
    }

    /**
     * Reverse stock depletion for a transaction reference.
     */
    public function reverseDepletion(string $refType, int $refId, string $reason): void
    {
        DB::transaction(function () use ($refType, $refId, $reason) {
            $ledgerEntries = StockLedger::where('reference_type', $refType)
                ->where('reference_id', $refId)
                ->where('type', 'OUT')
                ->get();

            foreach ($ledgerEntries as $entry) {
                $batch = InventoryBatch::findOrFail($entry->batch_id);
                $qtyToRestore = abs((float) $entry->quantity);

                // Add back quantity_remaining to batch
                $batch->quantity_remaining += $qtyToRestore;
                $batch->save();

                // Create reversing IN entry in stock ledger
                StockLedger::create([
                    'product_id' => $entry->product_id,
                    'warehouse_id' => $entry->warehouse_id,
                    'batch_id' => $entry->batch_id,
                    'quantity' => $qtyToRestore,
                    'unit_cost' => $entry->unit_cost,
                    'type' => 'IN',
                    'reference_type' => $refType,
                    'reference_id' => $refId,
                    'notes' => 'Reversal: '.$reason." (from ledger entry #{$entry->id})",
                ]);
            }
        });
    }

    /**
     * Reverse inward stock (from a PO/inward) for a batch.
     */
    public function reverseInward(int $batchId, float $qtyToDeduct, string $refType, int $refId, string $reason): void
    {
        DB::transaction(function () use ($batchId, $qtyToDeduct, $refType, $refId, $reason) {
            $batch = InventoryBatch::lockForUpdate()->findOrFail($batchId);

            if ($batch->quantity_remaining < $qtyToDeduct) {
                $sku = $batch->product ? $batch->product->sku : 'Product';
                throw new Exception("Cannot reverse inward stock for product {$sku}. Part of the stock has already been depleted/sold.");
            }

            $batch->quantity_remaining -= $qtyToDeduct;
            $batch->save();

            // Create reversing OUT entry in stock ledger
            StockLedger::create([
                'product_id' => $batch->product_id,
                'warehouse_id' => $batch->warehouse_id,
                'batch_id' => $batch->id,
                'quantity' => -$qtyToDeduct,
                'unit_cost' => $batch->unit_cost,
                'type' => 'OUT',
                'reference_type' => $refType,
                'reference_id' => $refId,
                'notes' => 'Reversal: '.$reason,
            ]);
        });
    }
}
