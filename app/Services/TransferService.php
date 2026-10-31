<?php

namespace App\Services;

use App\Models\InventoryBatch;
use App\Models\InventoryTransfer;
use App\Models\StockLedger;
use Exception;
use Illuminate\Support\Facades\DB;

class TransferService
{
    /**
     * Transfer a portion (or all) of an inventory batch to another warehouse.
     *
     * @throws Exception
     */
    public function transferBatch(int $sourceBatchId, int $toWarehouseId, float $quantity, string $date, array $gatePassData = []): InventoryTransfer
    {
        return DB::transaction(function () use ($sourceBatchId, $toWarehouseId, $quantity, $date, $gatePassData) {

            if ($quantity <= 0) {
                throw new Exception('Transfer quantity must be greater than zero.');
            }

            // Lock source batch
            $sourceBatch = InventoryBatch::where('id', $sourceBatchId)->lockForUpdate()->first();

            if (! $sourceBatch) {
                throw new Exception('Source batch not found.');
            }

            if ($sourceBatch->warehouse_id === $toWarehouseId) {
                throw new Exception('Source and destination warehouses cannot be the same.');
            }

            if ($sourceBatch->quantity_remaining < $quantity) {
                throw new Exception('Insufficient quantity in source batch.');
            }

            // Deduct from source
            $sourceBatch->quantity_remaining -= $quantity;
            $sourceBatch->save();

            // Create Transfer Record first (destination_batch_id is nullable)
            $transfer = InventoryTransfer::create(array_merge([
                'source_batch_id' => $sourceBatch->id,
                'destination_batch_id' => null, // Will be updated
                'product_id' => $sourceBatch->product_id,
                'from_warehouse_id' => $sourceBatch->warehouse_id,
                'to_warehouse_id' => $toWarehouseId,
                'quantity' => $quantity,
                'transfer_date' => $date,
                'created_by' => auth()->id(),
            ], $gatePassData));

            // Create Destination Batch
            $destinationBatch = InventoryBatch::create([
                'product_id' => $sourceBatch->product_id,
                'warehouse_id' => $toWarehouseId,
                'reference_type' => 'transfer',
                'reference_id' => $transfer->id,
                'quantity_received' => $quantity,
                'quantity_remaining' => $quantity,
                'unit_cost' => $sourceBatch->unit_cost,
                'total_cost' => $sourceBatch->unit_cost * $quantity,
                'received_at' => $date,
                'unit_of_measure' => $sourceBatch->unit_of_measure,
                'product_type' => $sourceBatch->product_type,
                'batch_reference' => $sourceBatch->batch_reference,
                'quality_grade' => $sourceBatch->quality_grade,
            ]);

            // Update destination batch reference to point to transfer
            $transfer->update(['destination_batch_id' => $destinationBatch->id]);

            // Stock Ledger OUT (Source Warehouse)
            StockLedger::create([
                'product_id' => $sourceBatch->product_id,
                'warehouse_id' => $sourceBatch->warehouse_id,
                'batch_id' => $sourceBatch->id,
                'quantity' => -$quantity,
                'unit_cost' => $sourceBatch->unit_cost,
                'type' => 'OUT',
                'reference_type' => 'transfer',
                'reference_id' => $transfer->id,
                'notes' => 'Internal transfer to warehouse '.$toWarehouseId,
            ]);

            // Stock Ledger IN (Destination Warehouse)
            StockLedger::create([
                'product_id' => $destinationBatch->product_id,
                'warehouse_id' => $toWarehouseId,
                'batch_id' => $destinationBatch->id,
                'quantity' => $quantity,
                'unit_cost' => $destinationBatch->unit_cost,
                'type' => 'IN',
                'reference_type' => 'transfer',
                'reference_id' => $transfer->id,
                'notes' => 'Internal transfer from warehouse '.$sourceBatch->warehouse_id,
            ]);

            return $transfer;
        });
    }
}
