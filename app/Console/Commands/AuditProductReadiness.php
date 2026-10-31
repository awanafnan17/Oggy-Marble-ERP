<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\InventoryBatch;
use App\Models\Product;
use Illuminate\Console\Command;

class AuditProductReadiness extends Command
{
    protected $signature = 'oggy:audit-product-readiness';

    protected $description = 'Audit all products in the database for master data readiness, stock, and accounting mappings.';

    public function handle()
    {
        $this->info('=== Starting Product Master Data Readiness Audit ===');

        $products = Product::all();
        $this->info('Total products in database: '.$products->count());

        $headers = ['ID', 'SKU', 'Name', 'Type', 'UOM', 'Inv Acc', 'COGS Acc', 'Rev Acc', 'Stock', 'Status'];
        $rows = [];

        foreach ($products as $p) {
            $batches = InventoryBatch::where('product_id', $p->id)
                ->where('quantity_remaining', '>', 0)
                ->get();
            $totalStock = $batches->sum('quantity_remaining');
            $batchesCount = $batches->count();

            // Determine readiness status
            $hasInv = ! empty($p->inventory_account_id);
            $hasCogs = ! empty($p->cogs_account_id);
            $hasRev = ! empty($p->revenue_account_id);

            $status = 'READY';

            if (! $hasInv || ! $hasCogs || ! $hasRev) {
                // Check if we can determine mappings dynamically based on type
                $canRepair = false;
                $productType = $p->product_type ?: 'tile';

                if ($productType === 'raw_stone') {
                    $invCode = '1210';
                    $revCode = '4110';
                    $cogsCode = '5110';
                } elseif ($productType === 'slab') {
                    $invCode = '1230';
                    $revCode = '4120';
                    $cogsCode = '5120';
                } elseif ($productType === 'tile') {
                    $invCode = '1200';
                    $revCode = '4100';
                    $cogsCode = '5100';
                } else {
                    $invCode = '1240';
                    $revCode = '4100';
                    $cogsCode = '5100';
                }

                $invAcc = Account::where('code', $invCode)->first();
                $revAcc = Account::where('code', $revCode)->first();
                $cogsAcc = Account::where('code', $cogsCode)->first();

                if ($invAcc && $revAcc && $cogsAcc) {
                    $canRepair = true;
                }

                $status = $canRepair ? 'REPAIRABLE' : 'BLOCKED_NEEDS_OWNER_SETUP';
            }

            $rows[] = [
                $p->id,
                $p->sku,
                $p->name,
                $p->product_type ?: 'tile (default)',
                $p->unit_of_measure ?: $p->unit ?: 'N/A',
                $hasInv ? "MAPPED (#{$p->inventory_account_id})" : 'MISSING',
                $hasCogs ? "MAPPED (#{$p->cogs_account_id})" : 'MISSING',
                $hasRev ? "MAPPED (#{$p->revenue_account_id})" : 'MISSING',
                "{$totalStock} ({$batchesCount} batches)",
                $status,
            ];
        }

        $this->table($headers, $rows);
        $this->info('=== Audit Complete ===');

        return Command::SUCCESS;
    }
}
