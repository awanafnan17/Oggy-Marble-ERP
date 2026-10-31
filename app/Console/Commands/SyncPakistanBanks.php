<?php

namespace App\Console\Commands;

use App\Models\PakistanBank;
use Illuminate\Console\Command;

class SyncPakistanBanks extends Command
{
    protected $signature = 'oggy:sync-pakistan-banks';

    protected $description = 'Upsert the Pakistan bank master list. Safe to run multiple times (idempotent).';

    public function handle(): int
    {
        $banks = $this->getBankList();

        $created = 0;
        $updated = 0;

        foreach ($banks as $index => $bank) {
            $result = PakistanBank::updateOrCreate(
                ['name' => $bank['name'], 'category' => $bank['category']],
                [
                    'short_name' => $bank['short_name'] ?? null,
                    'is_islamic' => $bank['is_islamic'] ?? false,
                    'is_active' => $bank['is_active'] ?? true,
                    'sort_order' => $index + 1,
                ]
            );

            if ($result->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        $this->info("Pakistan bank sync complete: {$created} created, {$updated} updated. Total: ".count($banks));

        return self::SUCCESS;
    }

    /**
     * Master list of Pakistan banks.
     */
    public function getBankList(): array
    {
        return [
            // ── Commercial / Public / Private Scheduled Banks ──
            ['name' => 'Allied Bank Limited', 'short_name' => 'ABL', 'category' => 'commercial', 'is_islamic' => false],
            ['name' => 'Askari Bank Limited', 'short_name' => 'AKBL', 'category' => 'commercial', 'is_islamic' => false],
            ['name' => 'Bank Alfalah Limited', 'short_name' => 'BAFL', 'category' => 'commercial', 'is_islamic' => false],
            ['name' => 'Bank AL Habib Limited', 'short_name' => 'BAHL', 'category' => 'commercial', 'is_islamic' => false],
            ['name' => 'Faysal Bank Limited', 'short_name' => 'FBL', 'category' => 'commercial', 'is_islamic' => false],
            ['name' => 'First Women Bank Limited', 'short_name' => 'FWBL', 'category' => 'commercial', 'is_islamic' => false],
            ['name' => 'Habib Bank Limited', 'short_name' => 'HBL', 'category' => 'commercial', 'is_islamic' => false],
            ['name' => 'Habib Metropolitan Bank Limited', 'short_name' => 'HMB', 'category' => 'commercial', 'is_islamic' => false],
            ['name' => 'JS Bank Limited', 'short_name' => 'JSBL', 'category' => 'commercial', 'is_islamic' => false],
            ['name' => 'MCB Bank Limited', 'short_name' => 'MCB', 'category' => 'commercial', 'is_islamic' => false],
            ['name' => 'National Bank of Pakistan', 'short_name' => 'NBP', 'category' => 'commercial', 'is_islamic' => false],
            ['name' => 'Samba Bank Limited', 'short_name' => 'SBL', 'category' => 'commercial', 'is_islamic' => false],
            ['name' => 'Silkbank Limited', 'short_name' => 'SILK', 'category' => 'commercial', 'is_islamic' => false],
            ['name' => 'Sindh Bank Limited', 'short_name' => 'SNDB', 'category' => 'commercial', 'is_islamic' => false],
            ['name' => 'Soneri Bank Limited', 'short_name' => 'SNBL', 'category' => 'commercial', 'is_islamic' => false],
            ['name' => 'The Bank of Khyber', 'short_name' => 'BOK', 'category' => 'commercial', 'is_islamic' => false],
            ['name' => 'The Bank of Punjab', 'short_name' => 'BOP', 'category' => 'commercial', 'is_islamic' => false],
            ['name' => 'United Bank Limited', 'short_name' => 'UBL', 'category' => 'commercial', 'is_islamic' => false],

            // ── Islamic Banks / Islamic Banking ──
            ['name' => 'Meezan Bank Limited', 'short_name' => 'MEBL', 'category' => 'islamic', 'is_islamic' => true],
            ['name' => 'BankIslami Pakistan Limited', 'short_name' => 'BIPL', 'category' => 'islamic', 'is_islamic' => true],
            ['name' => 'Dubai Islamic Bank Pakistan Limited', 'short_name' => 'DIBP', 'category' => 'islamic', 'is_islamic' => true],
            ['name' => 'MCB Islamic Bank Limited', 'short_name' => 'MCBI', 'category' => 'islamic', 'is_islamic' => true],
            ['name' => 'Al Baraka Bank Pakistan Limited', 'short_name' => 'ABPL', 'category' => 'islamic', 'is_islamic' => true],
            ['name' => 'Bank Makramah Limited', 'short_name' => 'BML', 'category' => 'islamic', 'is_islamic' => true],
            ['name' => 'Faysal Bank Limited (Islamic)', 'short_name' => 'FBL-I', 'category' => 'islamic', 'is_islamic' => true],
            ['name' => 'Askari Islamic Banking', 'short_name' => 'AKBL-I', 'category' => 'islamic', 'is_islamic' => true],
            ['name' => 'Bank Alfalah Islamic Banking', 'short_name' => 'BAFL-I', 'category' => 'islamic', 'is_islamic' => true],
            ['name' => 'Bank AL Habib Islamic Banking', 'short_name' => 'BAHL-I', 'category' => 'islamic', 'is_islamic' => true],
            ['name' => 'HBL Islamic Banking', 'short_name' => 'HBL-I', 'category' => 'islamic', 'is_islamic' => true],
            ['name' => 'UBL Islamic Banking', 'short_name' => 'UBL-I', 'category' => 'islamic', 'is_islamic' => true],
            ['name' => 'NBP Islamic Banking', 'short_name' => 'NBP-I', 'category' => 'islamic', 'is_islamic' => true],
            ['name' => 'Soneri Mustaqeem Islamic Banking', 'short_name' => 'SNBL-I', 'category' => 'islamic', 'is_islamic' => true],
            ['name' => 'HabibMetro Sirat Islamic Banking', 'short_name' => 'HMB-I', 'category' => 'islamic', 'is_islamic' => true],
            ['name' => 'The Bank of Khyber Islamic Banking', 'short_name' => 'BOK-I', 'category' => 'islamic', 'is_islamic' => true],
            ['name' => 'The Bank of Punjab Islamic Banking', 'short_name' => 'BOP-I', 'category' => 'islamic', 'is_islamic' => true],

            // ── Digital / Branchless / Wallet Banks ──
            ['name' => 'Easypaisa Bank Limited', 'short_name' => 'EPB', 'category' => 'digital', 'is_islamic' => false],
            ['name' => 'Mashreq Bank Pakistan Limited', 'short_name' => 'MASH', 'category' => 'digital', 'is_islamic' => false],
            ['name' => 'Raqami Islamic Digital Bank Limited', 'short_name' => 'RAQAMI', 'category' => 'digital', 'is_islamic' => true],
            ['name' => 'JazzCash / Mobilink Microfinance Bank', 'short_name' => 'JAZZ', 'category' => 'digital', 'is_islamic' => false],
            ['name' => 'U Bank / U Microfinance Bank', 'short_name' => 'UBANK', 'category' => 'digital', 'is_islamic' => false],

            // ── Microfinance Banks ──
            ['name' => 'HBL Microfinance Bank Limited', 'short_name' => 'HBL-MF', 'category' => 'microfinance', 'is_islamic' => false],
            ['name' => 'NRSP Microfinance Bank Limited', 'short_name' => 'NRSP', 'category' => 'microfinance', 'is_islamic' => false],
            ['name' => 'Khushhali Microfinance Bank Limited', 'short_name' => 'KMBL', 'category' => 'microfinance', 'is_islamic' => false],
            ['name' => 'LOLC Microfinance Bank Limited', 'short_name' => 'LOLC', 'category' => 'microfinance', 'is_islamic' => false],
            ['name' => 'U Microfinance Bank Limited', 'short_name' => 'UMBL', 'category' => 'microfinance', 'is_islamic' => false],
            ['name' => 'Mobilink Microfinance Bank Limited', 'short_name' => 'MMBL', 'category' => 'microfinance', 'is_islamic' => false],
            ['name' => 'APNA Microfinance Bank Limited', 'short_name' => 'APNA', 'category' => 'microfinance', 'is_islamic' => false],
            ['name' => 'Halan Microfinance Bank Limited', 'short_name' => 'HALAN', 'category' => 'microfinance', 'is_islamic' => false],
            ['name' => 'Sindh Microfinance Bank Limited', 'short_name' => 'SMBL', 'category' => 'microfinance', 'is_islamic' => false],
            ['name' => 'FINCA Microfinance Bank Limited', 'short_name' => 'FINCA', 'category' => 'microfinance', 'is_islamic' => false],
            ['name' => 'Pak Oman Microfinance Bank Limited', 'short_name' => 'POMBL', 'category' => 'microfinance', 'is_islamic' => false],
            ['name' => 'The First MicroFinanceBank Limited', 'short_name' => 'FMFB', 'category' => 'microfinance', 'is_islamic' => false],

            // ── Specialized / Cooperative Banks ──
            ['name' => 'Zarai Taraqiati Bank Limited', 'short_name' => 'ZTBL', 'category' => 'specialized', 'is_islamic' => false],
            ['name' => 'Punjab Provincial Cooperative Bank Limited', 'short_name' => 'PPCBL', 'category' => 'specialized', 'is_islamic' => false],
            ['name' => 'SME Bank Limited', 'short_name' => 'SMEB', 'category' => 'specialized', 'is_islamic' => false, 'is_active' => true],
            ['name' => 'Industrial Development Bank of Pakistan', 'short_name' => 'IDBP', 'category' => 'specialized', 'is_islamic' => false, 'is_active' => true],

            // ── Foreign / International Banks Operating in Pakistan ──
            ['name' => 'Standard Chartered Bank Pakistan Limited', 'short_name' => 'SCB', 'category' => 'foreign', 'is_islamic' => false],
            ['name' => 'Deutsche Bank AG Pakistan', 'short_name' => 'DB', 'category' => 'foreign', 'is_islamic' => false],
            ['name' => 'Industrial and Commercial Bank of China Limited', 'short_name' => 'ICBC', 'category' => 'foreign', 'is_islamic' => false],
            ['name' => 'Bank of China Limited Pakistan', 'short_name' => 'BOC', 'category' => 'foreign', 'is_islamic' => false],
            ['name' => 'Citibank N.A. Pakistan', 'short_name' => 'CITI', 'category' => 'foreign', 'is_islamic' => false, 'is_active' => true],
        ];
    }
}
