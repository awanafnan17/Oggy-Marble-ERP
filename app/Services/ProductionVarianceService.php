<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use Illuminate\Database\Eloquent\Model;

class ProductionVarianceService
{
    /**
     * Calculate variance fields and post variance journal entry for a production run.
     *
     * @param  Model  $run  The StoneToSlabProduction or SlabToTileProduction model
     * @param  float  $actualMaterialCost  The actual FIFO-computed material cost
     * @param  float  $actualLaborCost  Actual labor cost entered by user
     * @param  float  $actualOverheadCost  Actual overhead cost entered by user
     * @param  float  $expectedMaterialCost  BOM-based expected material cost
     * @param  float  $expectedLaborCost  BOM-based expected labor cost
     * @param  float  $expectedOverheadCost  BOM-based expected overhead cost
     * @param  string  $date  The production date for journal posting
     * @param  string  $productionNumber  The production order number for reference
     */
    public function calculateAndPostVariance(
        Model $run,
        float $actualMaterialCost,
        float $actualLaborCost,
        float $actualOverheadCost,
        float $expectedMaterialCost,
        float $expectedLaborCost,
        float $expectedOverheadCost,
        string $date,
        string $productionNumber
    ): void {
        $expectedTotal = $expectedMaterialCost + $expectedLaborCost + $expectedOverheadCost;
        $actualTotal = $actualMaterialCost + $actualLaborCost + $actualOverheadCost;

        $varianceAmount = $actualTotal - $expectedTotal;
        $variancePercentage = $expectedTotal > 0
            ? ($varianceAmount / $expectedTotal) * 100
            : 0.0;

        $isFavorable = $varianceAmount < 0; // Negative variance = favorable (spent less)

        // Store expected cost and variance fields on the run
        $run->update([
            'expected_material_cost' => $expectedMaterialCost,
            'expected_labor_cost' => $expectedLaborCost,
            'expected_overhead_cost' => $expectedOverheadCost,
            'expected_total_cost' => $expectedTotal,
            'variance_amount' => $varianceAmount,
            'variance_percentage' => $variancePercentage,
            'is_favorable' => $isFavorable,
        ]);

        // Only post variance journal if there's a material variance
        if (abs($varianceAmount) < 0.0001) {
            return;
        }

        // Resolve variance accounts
        $varianceExpenseAccount = Account::where('code', '5900')->first();
        $varianceGainAccount = Account::where('code', '4900')->first();
        $wipAccount = Account::where('type', 'asset')
            ->where('name', 'like', '%Work In Progress%')
            ->orWhere('name', 'like', '%WIP%')
            ->first();

        if (! $varianceExpenseAccount || ! $varianceGainAccount || ! $wipAccount) {
            return; // Safety guard — accounts not seeded
        }

        $absVariance = abs($varianceAmount);

        $varianceEntry = JournalEntry::create([
            'entry_number' => 'JV-VAR-'.time().'-'.rand(100, 999),
            'date' => $date,
            'description' => ($isFavorable ? 'Favorable' : 'Unfavorable')
                ." production variance — {$productionNumber}"
                .' (Δ '.number_format($varianceAmount, 4).')',
            'reference_type' => 'production_variance',
            'reference_id' => $run->id,
        ]);

        if ($varianceAmount > 0) {
            // Unfavorable: Dr Production Variance Expense, Cr WIP
            JournalItem::create([
                'journal_entry_id' => $varianceEntry->id,
                'account_id' => $varianceExpenseAccount->id,
                'debit' => $absVariance,
                'credit' => 0,
            ]);
            JournalItem::create([
                'journal_entry_id' => $varianceEntry->id,
                'account_id' => $wipAccount->id,
                'debit' => 0,
                'credit' => $absVariance,
            ]);
        } else {
            // Favorable: Dr WIP, Cr Production Variance Gain
            JournalItem::create([
                'journal_entry_id' => $varianceEntry->id,
                'account_id' => $wipAccount->id,
                'debit' => $absVariance,
                'credit' => 0,
            ]);
            JournalItem::create([
                'journal_entry_id' => $varianceEntry->id,
                'account_id' => $varianceGainAccount->id,
                'debit' => 0,
                'credit' => $absVariance,
            ]);
        }

        // Link variance journal to the production run
        $run->update([
            'variance_journal_entry_id' => $varianceEntry->id,
        ]);
    }
}
