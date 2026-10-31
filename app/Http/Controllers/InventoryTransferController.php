<?php

namespace App\Http\Controllers;

use App\Models\InventoryBatch;
use App\Models\InventoryTransfer;
use App\Models\Warehouse;
use App\Services\TransferService;
use Exception;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InventoryTransferController extends Controller
{
    protected TransferService $transferService;

    public function __construct(TransferService $transferService)
    {
        $this->transferService = $transferService;
    }

    public function index()
    {
        $warehouses = Warehouse::orderBy('name')->get();

        $activeBatches = InventoryBatch::with(['product', 'warehouse'])
            ->where('quantity_remaining', '>', 0)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'product_name' => $batch->product->name,
                    'warehouse_name' => $batch->warehouse->name,
                    'quantity_remaining' => (float) $batch->quantity_remaining,
                    'unit_cost' => (float) $batch->unit_cost,
                    'unit_of_measure' => $batch->unit_of_measure,
                    'batch_reference' => $batch->batch_reference,
                    'label' => "Batch #{$batch->id} - {$batch->product->name} in {$batch->warehouse->name} ({$batch->quantity_remaining} {$batch->unit_of_measure} @ ".number_format($batch->unit_cost, 2).')',
                ];
            });

        $transfers = InventoryTransfer::with(['product', 'fromWarehouse', 'toWarehouse', 'createdBy'])
            ->orderBy('transfer_date', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'date' => $t->transfer_date->toDateString(),
                    'product' => $t->product->name,
                    'from' => $t->fromWarehouse->name,
                    'to' => $t->toWarehouse->name,
                    'quantity' => (float) $t->quantity,
                    'unit_cost' => (float) $t->sourceBatch->unit_cost,
                    'total_value' => (float) $t->quantity * (float) $t->sourceBatch->unit_cost,
                    'created_by' => $t->createdBy ? $t->createdBy->name : 'System',
                ];
            });

        return Inertia::render('Inventory/Transfers', [
            'warehouses' => $warehouses,
            'activeBatches' => $activeBatches,
            'transfers' => $transfers,
        ]);
    }

    public function transfer(Request $request)
    {
        $validated = $request->validate([
            'source_batch_id' => 'required|exists:inventory_batches,id',
            'to_warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0.0001',
            'transfer_date' => 'required|date',
            'vehicle_number' => 'nullable|string|max:255',
            'driver_name' => 'nullable|string|max:255',
            'receiver_name' => 'nullable|string|max:255',
            'authorized_by_name' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
        ]);

        try {
            $gatePassData = $request->only(['vehicle_number', 'driver_name', 'receiver_name', 'authorized_by_name', 'remarks']);

            $this->transferService->transferBatch(
                $validated['source_batch_id'],
                $validated['to_warehouse_id'],
                $validated['quantity'],
                $validated['transfer_date'],
                $gatePassData
            );

            return back()->with('success', 'Inventory transferred successfully.');
        } catch (Exception $e) {
            return back()->withErrors(['transfer' => $e->getMessage()]);
        }
    }

    public function gatePass($id)
    {
        $transfer = InventoryTransfer::with(['product', 'fromWarehouse', 'toWarehouse', 'createdBy', 'sourceBatch'])
            ->findOrFail($id);

        return Inertia::render('Inventory/GatePass', [
            'transfer' => [
                'id' => $transfer->id,
                'gate_pass_number' => 'GP-'.str_pad($transfer->id, 6, '0', STR_PAD_LEFT),
                'transfer_number' => 'TR-'.str_pad($transfer->id, 6, '0', STR_PAD_LEFT),
                'date' => $transfer->transfer_date->toDateString(),
                'from_warehouse' => $transfer->fromWarehouse->name,
                'from_location' => $transfer->fromWarehouse->location,
                'to_warehouse' => $transfer->toWarehouse->name,
                'to_location' => $transfer->toWarehouse->location,
                'product_sku' => $transfer->product->sku,
                'product_name' => $transfer->product->name,
                'quantity' => (float) $transfer->quantity,
                'unit' => $transfer->sourceBatch->unit_of_measure,
                'vehicle_number' => $transfer->vehicle_number ?: 'N/A',
                'driver_name' => $transfer->driver_name ?: 'N/A',
                'receiver_name' => $transfer->receiver_name ?: 'N/A',
                'prepared_by' => $transfer->createdBy ? $transfer->createdBy->name : 'System',
                'authorized_by' => $transfer->authorized_by_name ?: 'N/A',
                'remarks' => $transfer->remarks ?: 'No remarks',
            ],
        ]);
    }
}
