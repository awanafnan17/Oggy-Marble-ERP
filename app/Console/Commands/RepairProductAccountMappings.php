<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Product;
use Illuminate\Console\Command;

class RepairProductAccountMappings extends Command
{
    protected $signature = 'oggy:repair-product-account-mappings {--dry-run : Perform a dry run without saving changes}';

    protected $description = 'Safely repair missing or incomplete product account mappings based on product type and deterministic rules.';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $this->info('=== Starting Product Account Mappings Repair ===');
        if ($dryRun) {
            $this->comment('DRY-RUN MODE ENABLED. NO CHANGES WILL BE SAVED.');
        } else {
            // Production safety confirmation check
            if (app()->environment('production') || config('app.env') === 'production') {
                if (! $this->confirm('WARNING: You are running this repair command on PRODUCTION. Have you obtained owner approval?', false)) {
                    $this->error('Aborted. Repair requires owner approval confirmation.');

                    return Command::FAILURE;
                }
            }
        }

        $products = Product::all();
        $repairedCount = 0;
        $blockedCount = 0;
        $alreadyReadyCount = 0;

        foreach ($products as $p) {
            $hasInv = ! empty($p->inventory_account_id);
            $hasCogs = ! empty($p->cogs_account_id);
            $hasRev = ! empty($p->revenue_account_id);

            if ($hasInv && $hasCogs && $hasRev) {
                $this->line("Product ID {$p->id} (SKU: {$p->sku}) is already fully mapped. [READY]");
                $alreadyReadyCount++;

                continue;
            }

            // Mappings lookup based on product type
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

            if (! $invAcc || ! $revAcc || ! $cogsAcc) {
                $this->error("Product ID {$p->id} (SKU: {$p->sku}) cannot be repaired. Required G/L accounts are missing in DB (Inv: {$invCode}, Rev: {$revCode}, COGS: {$cogsCode}). [BLOCKED]");
                $blockedCount++;

                continue;
            }

            // Determine what needs to be mapped
            $updatedFields = [];
            if (! $hasInv) {
                $updatedFields['inventory_account_id'] = $invAcc->id;
            }
            if (! $hasCogs) {
                $updatedFields['cogs_account_id'] = $cogsAcc->id;
            }
            if (! $hasRev) {
                $updatedFields['revenue_account_id'] = $revAcc->id;
            }

            $this->warn("Product ID {$p->id} (SKU: {$p->sku}) is incomplete. Mappings to apply: ".json_encode($updatedFields));

            if (! $dryRun) {
                $p->update($updatedFields);
                $this->info("Product ID {$p->id} (SKU: {$p->sku}) successfully repaired!");
            }
            $repairedCount++;
        }

        $this->info("\n=== Repair Process Completed ===");
        $this->info("Already Ready: {$alreadyReadyCount}");
        $this->info($dryRun ? "Repairable (Dry Run): {$repairedCount}" : "Repaired: {$repairedCount}");
        $this->info("Blocked (Needs Owner Action): {$blockedCount}");

        return Command::SUCCESS;
    }
}
