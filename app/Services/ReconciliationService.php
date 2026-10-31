<?php

namespace App\Services;

use App\Models\Account;
use App\Models\InventoryAdjustment;
use App\Models\InventoryBatch;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\StockLedger;
use Exception;
use Illuminate\Support\Facades\DB;

class ReconciliationService
{
    /**
     * Reconcile a batch's physical count against system quantity.
     */
    public function reconcileBatch(int $batchId, float $physicalQty, ?string $reason, string $date): ?InventoryAdjustment
    {
        return DB::transaction(function () use ($batchId, $physicalQty, $reason, $date) {
            // 1. Lock batch for update to prevent race conditions
            $batch = InventoryBatch::where('id', $batchId)
                ->lockForUpdate()
                ->first();

            if (! $batch) {
                throw new Exception("Inventory batch #{$batchId} not found.");
            }

            $systemQty = (float) $batch->quantity_remaining;
            $difference = $physicalQty - $systemQty;

            // 2. If no difference, no adjustment needed
            if (abs($difference) < 0.0001) {
                return null;
            }

            // 3. Update batch quantity_remaining to physical count
            $batch->quantity_remaining = $physicalQty;
            $batch->save();

            // 4. Record stock ledger entry for the adjustment
            StockLedger::create([
                'product_id' => $batch->product_id,
                'warehouse_id' => $batch->warehouse_id,
                'batch_id' => $batch->id,
                'quantity' => $difference,
                'unit_cost' => $batch->unit_cost,
                'type' => $difference > 0 ? 'IN' : 'OUT',
                'reference_type' => 'reconciliation',
                'reference_id' => $batch->id,
                'notes' => $reason ?: 'Physical stock reconciliation',
            ]);

            // 5. Load product for account mapping
            $product = $batch->product;
            $inventoryAccountId = $product->inventory_account_id;

            // 6. Resolve shrinkage and gain accounts
            $shrinkageAccount = Account::where('code', '5800')->first();
            $gainAccount = Account::where('code', '4800')->first();

            // 7. Post journal entry
            $absValue = abs($difference) * (float) $batch->unit_cost;

            $journalEntry = JournalEntry::create([
                'entry_number' => 'JV-RECON-'.time().'-'.rand(100, 999),
                'date' => $date,
                'description' => ($difference < 0 ? 'Inventory Shrinkage' : 'Inventory Surplus')
                    ." — Batch #{$batch->id} ({$product->name})"
                    .($reason ? ": {$reason}" : ''),
                'reference_type' => 'reconciliation',
                'reference_id' => $batch->id,
            ]);

            if ($difference > 0) {
                // Stock gain: Dr Inventory Asset, Cr Inventory Gain Account
                JournalItem::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $inventoryAccountId,
                    'debit' => $absValue,
                    'credit' => 0,
                ]);
                JournalItem::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $gainAccount->id,
                    'debit' => 0,
                    'credit' => $absValue,
                ]);
            } else {
                // Stock loss: Dr Inventory Shrinkage Expense, Cr Inventory Asset
                JournalItem::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $shrinkageAccount->id,
                    'debit' => $absValue,
                    'credit' => 0,
                ]);
                JournalItem::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $inventoryAccountId,
                    'debit' => 0,
                    'credit' => $absValue,
                ]);
            }

            // 8. Create InventoryAdjustment record
            $adjustment = InventoryAdjustment::create([
                'inventory_batch_id' => $batch->id,
                'product_id' => $batch->product_id,
                'warehouse_id' => $batch->warehouse_id,
                'system_quantity' => $systemQty,
                'physical_quantity' => $physicalQty,
                'difference' => $difference,
                'reason' => $reason,
                'adjustment_date' => $date,
                'journal_entry_id' => $journalEntry->id,
                'created_by' => auth()->id(),
            ]);

            return $adjustment;
        });
    }
}
