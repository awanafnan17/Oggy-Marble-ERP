<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateImportTemplates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:templates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate CSV data import templates for factory onboarding.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dir = storage_path('app/import_templates');
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $templates = [
            'products.csv' => [
                ['sku', 'name', 'description', 'unit', 'cost', 'price', 'product_type', 'unit_of_measure', 'stone_color', 'quality_grade', 'thickness', 'size', 'inventory_account_code', 'cogs_account_code', 'revenue_account_code'],
                ['TL-WHITE-01', 'White Marble Tile 60x60', 'Example white marble tile', 'pcs', '280.00', '380.00', 'tile', 'Sq Ft', 'White', 'Grade A', '2cm', '60x60', '1200', '5100', '4100'],
            ],
            'customers.csv' => [
                ['name', 'email', 'phone', 'address'],
                ['Example Customer', 'customer@example.test', '+00 000 0000000', 'Example customer address'],
            ],
            'suppliers.csv' => [
                ['name', 'email', 'phone', 'address'],
                ['Example Supplier', 'supplier@example.test', '+00 000 0000000', 'Example supplier address'],
            ],
            'warehouses.csv' => [
                ['name', 'location'],
                ['Main Factory Yard', 'Configured facility'],
                ['Secondary Outlet', 'Configured outlet'],
            ],
            'opening_stock_batches.csv' => [
                ['sku', 'warehouse_name', 'quantity', 'unit_cost', 'batch_reference', 'quality_grade', 'received_at'],
                ['RM-VOLCANIC-01', 'Raw Blocks Yard', '15.5', '25000.00', 'OB-STONE-NEW-01', 'Premium', '2026-06-25'],
            ],
            'machines.csv' => [
                ['machine_code', 'name', 'type', 'capacity_note', 'operator_name', 'status', 'remarks'],
                ['MC-GSAW-01', 'Gang Saw 1', 'cutter', 'Heavy-capacity slab slicer', 'Example Operator', 'active', 'Example import row.'],
            ],
            'chart_of_accounts_opening_balances.csv' => [
                ['account_code', 'account_name', 'account_type', 'opening_debit', 'opening_credit'],
                ['1010', 'Cash Account (Hand)', 'asset', '100000.00', '0.00'],
                ['3100', 'Owner Equity', 'equity', '0.00', '100000.00'],
            ],
        ];

        foreach ($templates as $filename => $rows) {
            $path = $dir.'/'.$filename;
            $handle = fopen($path, 'w');
            if ($handle) {
                foreach ($rows as $row) {
                    fputcsv($handle, $row);
                }
                fclose($handle);
                $this->info('Created import template: '.$path);
            }
        }

        $this->info('All factory data import CSV templates generated successfully.');

        return 0;
    }
}
