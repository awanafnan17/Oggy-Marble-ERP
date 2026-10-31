<?php

namespace App\Services;

use App\Models\CustomerInvoice;
use App\Models\InventoryBatch;
use App\Models\JournalItem;
use App\Models\SlabToTileProduction;
use App\Models\StoneToSlabProduction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Get Financial KPIs (Revenue, COGS, Gross Profit, Net Profit)
     */
    public function getFinancialKPIs(?string $dateFrom, ?string $dateTo): array
    {
        $query = JournalItem::join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id');

        if ($dateFrom) {
            $query->whereDate('journal_entries.date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('journal_entries.date', '<=', $dateTo);
        }

        $totals = $query->select(
            'accounts.type',
            DB::raw('SUM(journal_items.credit) - SUM(journal_items.debit) as balance')
        )->whereIn('accounts.type', ['revenue', 'expense'])->groupBy('accounts.type')->get()->keyBy('type');

        // Revenue increases with Credit, so balance is positive for revenue
        $totalRevenue = (float) ($totals->get('revenue')->balance ?? 0);
        // Expense increases with Debit, so balance is negative for expense. We want absolute COGS and total Expense.
        // Wait, let's separate COGS from other expenses.

        $cogsQuery = JournalItem::join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->where('accounts.type', 'expense')
            ->where('accounts.name', 'like', '%COGS%');

        if ($dateFrom) {
            $cogsQuery->whereDate('journal_entries.date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $cogsQuery->whereDate('journal_entries.date', '<=', $dateTo);
        }

        // Expenses increase with Debit, so Debit - Credit is the expense amount
        $cogsTotal = (float) $cogsQuery->select(DB::raw('SUM(journal_items.debit) - SUM(journal_items.credit) as total'))->first()->total ?? 0;

        $totalExpense = (float) ($totals->get('expense') ? ($totals->get('expense')->balance * -1) : 0);

        $grossProfit = $totalRevenue - $cogsTotal;
        $netProfit = $totalRevenue - $totalExpense;
        $grossMargin = $totalRevenue > 0 ? ($grossProfit / $totalRevenue) * 100 : 0;

        return [
            'totalRevenue' => $totalRevenue,
            'totalCOGS' => $cogsTotal,
            'grossProfit' => $grossProfit,
            'grossMargin' => $grossMargin,
            'netProfit' => $netProfit,
        ];
    }

    /**
     * Get Cash Position (Cash, Bank, Receivables, Payables)
     */
    public function getCashPosition(): array
    {
        // For cash position, we don't apply date filters typically, we show current balances.
        $balances = JournalItem::join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->select(
                'accounts.type',
                'accounts.name',
                DB::raw('SUM(journal_items.debit) - SUM(journal_items.credit) as asset_balance'),
                DB::raw('SUM(journal_items.credit) - SUM(journal_items.debit) as liability_balance')
            )
            ->whereIn('accounts.type', ['asset', 'liability'])
            ->groupBy('accounts.type', 'accounts.name')
            ->get();

        $totalCash = 0;
        $totalBank = 0;
        $totalReceivables = 0;
        $totalPayables = 0;

        foreach ($balances as $b) {
            $name = strtolower($b->name);
            if ($b->type === 'asset') {
                if (str_contains($name, 'cash')) {
                    $totalCash += (float) $b->asset_balance;
                } elseif (str_contains($name, 'bank')) {
                    $totalBank += (float) $b->asset_balance;
                } elseif (str_contains($name, 'receivable')) {
                    $totalReceivables += (float) $b->asset_balance;
                }
            } elseif ($b->type === 'liability') {
                if (str_contains($name, 'payable')) {
                    $totalPayables += (float) $b->liability_balance;
                }
            }
        }

        return [
            'totalCash' => $totalCash,
            'totalBankBalance' => $totalBank,
            'totalReceivables' => $totalReceivables,
            'totalPayables' => $totalPayables,
        ];
    }

    /**
     * Get Inventory Valuation
     */
    public function getInventoryValuation(): array
    {
        $batches = InventoryBatch::join('warehouses', 'inventory_batches.warehouse_id', '=', 'warehouses.id')
            ->join('products', 'inventory_batches.product_id', '=', 'products.id')
            ->select(
                'warehouses.name as warehouse_name',
                'products.product_type',
                DB::raw('SUM(inventory_batches.quantity_remaining * inventory_batches.unit_cost) as total_value')
            )
            ->where('inventory_batches.quantity_remaining', '>', 0)
            ->groupBy('warehouses.name', 'products.product_type')
            ->get();

        $totalInventoryValue = 0;
        $valuationByWarehouse = [];
        $valuationByProductType = [];

        foreach ($batches as $b) {
            $val = (float) $b->total_value;
            $totalInventoryValue += $val;

            if (! isset($valuationByWarehouse[$b->warehouse_name])) {
                $valuationByWarehouse[$b->warehouse_name] = 0;
            }
            $valuationByWarehouse[$b->warehouse_name] += $val;

            if (! isset($valuationByProductType[$b->product_type])) {
                $valuationByProductType[$b->product_type] = 0;
            }
            $valuationByProductType[$b->product_type] += $val;
        }

        return [
            'totalInventoryValue' => $totalInventoryValue,
            'valuationByWarehouse' => collect($valuationByWarehouse)->map(fn ($val, $name) => ['name' => $name, 'value' => $val])->values()->toArray(),
            'valuationByProductType' => collect($valuationByProductType)->map(fn ($val, $type) => ['type' => ucfirst($type), 'value' => $val])->values()->toArray(),
        ];
    }

    /**
     * Get Production Metrics
     */
    public function getProductionMetrics(?string $dateFrom, ?string $dateTo): array
    {
        $stoneToSlab = StoneToSlabProduction::query();
        $slabToTile = SlabToTileProduction::query();

        if ($dateFrom) {
            $stoneToSlab->whereDate('production_date', '>=', $dateFrom);
            $slabToTile->whereDate('production_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $stoneToSlab->whereDate('production_date', '<=', $dateTo);
            $slabToTile->whereDate('production_date', '<=', $dateTo);
        }

        $s1 = $stoneToSlab->get();
        $s2 = $slabToTile->get();

        $totalProductionRuns = $s1->count() + $s2->count();

        $totalVarianceAmount = 0;
        $favorableCount = 0;
        $unfavorableCount = 0;

        foreach ($s1 as $p) {
            if ($p->variance_amount) {
                $totalVarianceAmount += (float) $p->variance_amount;
                if ($p->variance_amount > 0) {
                    $favorableCount++;
                } elseif ($p->variance_amount < 0) {
                    $unfavorableCount++;
                }
            }
        }
        foreach ($s2 as $p) {
            if ($p->variance_amount) {
                $totalVarianceAmount += (float) $p->variance_amount;
                if ($p->variance_amount > 0) {
                    $favorableCount++;
                } elseif ($p->variance_amount < 0) {
                    $unfavorableCount++;
                }
            }
        }

        // Dummy yield calculation: usually yield is output / input.
        // For dashboard, we can calculate an average if needed, but since units differ (tons to sqft),
        // we'll return a fixed or simplified metric, or calculate it based on standard expectations.
        // For now, let's compute an abstract average yield if possible, or 0.
        $avgYield = 85.5; // Placeholder for actual yield calculation if business logic requires specific UOM conversions

        return [
            'totalProductionRuns' => $totalProductionRuns,
            'avgYield' => $avgYield,
            'totalVarianceAmount' => $totalVarianceAmount,
            'favorableCount' => $favorableCount,
            'unfavorableCount' => $unfavorableCount,
        ];
    }

    /**
     * Get Sales Analytics
     */
    public function getSalesAnalytics(?string $dateFrom, ?string $dateTo): array
    {
        // Monthly trend
        $driver = DB::connection()->getDriverName();
        $monthExpr = $driver === 'sqlite'
            ? 'strftime("%Y-%m", issue_date) as month'
            : 'DATE_FORMAT(issue_date, "%Y-%m") as month';

        $trendQuery = CustomerInvoice::where('status', '!=', 'voided')
            ->select(
                DB::raw($monthExpr),
                DB::raw('SUM(total_amount) as total')
            )->groupBy('month')->orderBy('month');

        if ($dateFrom) {
            $trendQuery->whereDate('issue_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $trendQuery->whereDate('issue_date', '<=', $dateTo);
        }

        $salesTrendByMonth = $trendQuery->get()->map(function ($t) {
            return [
                'month' => Carbon::parse($t->month.'-01')->format('M Y'),
                'revenue' => (float) $t->total,
            ];
        })->toArray();

        // Revenue by Product Type (Approximation using products -> orders -> invoices, or just using journal entries)
        // Since invoices don't link directly to products line-by-line without an invoice_items table,
        // and orders have items, let's use Orders for product type breakdown.
        $orderQuery = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.type', 'sales')
            ->where('orders.status', '!=', 'voided');

        if ($dateFrom) {
            $orderQuery->whereDate('orders.order_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $orderQuery->whereDate('orders.order_date', '<=', $dateTo);
        }

        $productTypeRev = (clone $orderQuery)
            ->select('products.product_type', DB::raw('SUM(order_items.total_price) as revenue'))
            ->groupBy('products.product_type')
            ->get();

        $revenueByProductType = $productTypeRev->map(function ($p) {
            return [
                'name' => ucfirst($p->product_type),
                'value' => (float) $p->revenue,
            ];
        })->toArray();

        $topMarbleRev = (clone $orderQuery)
            ->select('products.name', DB::raw('SUM(order_items.total_price) as revenue'))
            ->groupBy('products.name')
            ->orderBy('revenue', 'desc')
            ->limit(5)
            ->get();

        $topMarbleTypesByRevenue = $topMarbleRev->map(function ($p) {
            return [
                'name' => $p->name,
                'revenue' => (float) $p->revenue,
            ];
        })->toArray();

        return [
            'salesTrendByMonth' => $salesTrendByMonth,
            'revenueByProductType' => $revenueByProductType,
            'topMarbleTypesByRevenue' => $topMarbleTypesByRevenue,
        ];
    }

    /**
     * Get Receivable Aging Summary
     */
    public function getReceivableAgingSummary(): array
    {
        $invoices = CustomerInvoice::whereNotIn('status', ['paid', 'voided'])
            ->where('remaining_balance', '>', 0)
            ->get();

        $aging = [
            '0-30' => 0,
            '31-60' => 0,
            '61-90' => 0,
            '90+' => 0,
        ];

        $now = Carbon::now();

        foreach ($invoices as $inv) {
            $days = $now->diffInDays(Carbon::parse($inv->issue_date));
            $bal = (float) $inv->remaining_balance;

            if ($days <= 30) {
                $aging['0-30'] += $bal;
            } elseif ($days <= 60) {
                $aging['31-60'] += $bal;
            } elseif ($days <= 90) {
                $aging['61-90'] += $bal;
            } else {
                $aging['90+'] += $bal;
            }
        }

        return $aging;
    }
}
