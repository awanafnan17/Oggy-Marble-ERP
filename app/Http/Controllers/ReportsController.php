<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\CustomerInvoice;
use App\Models\DeliveryChallan;
use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\QualityInspection;
use App\Models\SlabToTileProduction;
use App\Models\StoneToSlabProduction;
use App\Models\SupplierBill;
use App\Services\AccountService;
use App\Services\CashBankService;
use App\Services\InventoryService;
use App\Services\SubledgerService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;

class ReportsController extends Controller
{
    protected AccountService $accountService;

    protected InventoryService $inventoryService;

    protected SubledgerService $subledgerService;

    protected CashBankService $cashBankService;

    public function __construct(
        AccountService $accountService,
        InventoryService $inventoryService,
        SubledgerService $subledgerService,
        CashBankService $cashBankService
    ) {
        $this->accountService = $accountService;
        $this->inventoryService = $inventoryService;
        $this->subledgerService = $subledgerService;
        $this->cashBankService = $cashBankService;
    }

    public function index(Request $request)
    {
        $asOfDate = $request->input('as_of_date') ?: $request->input('end_date');
        $accounts = Account::orderBy('code')->get();
        $trialBalance = $this->accountService->getTrialBalance();
        $profitLoss = $this->accountService->getProfitLoss();
        $balanceSheet = $this->accountService->getBalanceSheet($asOfDate);

        $bankAccounts = BankAccount::all()->map(function ($acc) {
            return [
                'name' => $acc->account_name,
                'balance' => $this->cashBankService->getBalance($acc->id),
            ];
        });

        // Stock valuation summary
        $stockValuation = Product::all()->map(function ($p) {
            return [
                'sku' => $p->sku,
                'name' => $p->name,
                'type' => $p->product_type,
                'unit' => $p->unit_of_measure ?: $p->unit,
                'total_stock' => $this->inventoryService->getTotalStock($p->id),
                'value' => $this->inventoryService->getValuation($p->id),
            ];
        });

        // Yield & wastage summaries
        $stsYield = StoneToSlabProduction::selectRaw('SUM(input_quantity) as total_input, SUM(slabs_produced) as total_slabs, SUM(wastage_quantity) as total_wastage')->first();
        $sttYield = SlabToTileProduction::selectRaw('SUM(input_quantity) as total_input, SUM(tiles_produced) as total_tiles, SUM(scrap_quantity) as total_scrap')->first();

        return Inertia::render('Reports/ReportsCenter', [
            'trialBalance' => $trialBalance,
            'profitLoss' => $profitLoss,
            'balanceSheet' => $balanceSheet,
            'asOfDate' => $asOfDate,
            'bankAccounts' => $bankAccounts,
            'stockValuation' => $stockValuation,
            'stsYield' => [
                'input' => (float) ($stsYield->total_input ?? 0),
                'output' => (float) ($stsYield->total_slabs ?? 0),
                'wastage' => (float) ($stsYield->total_wastage ?? 0),
            ],
            'sttYield' => [
                'input' => (float) ($sttYield->total_input ?? 0),
                'output' => (float) ($sttYield->total_tiles ?? 0),
                'scrap' => (float) ($sttYield->total_scrap ?? 0),
            ],
        ]);
    }

    /**
     * Centralized export endpoint outputting clean CSV files openable directly in Microsoft Excel.
     */
    public function exportCsv(Request $request, string $type)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $headers = [
            'Content-type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=Report_'.$type.'_'.now()->format('Ymd_His').'.csv',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($type, $startDate, $endDate) {
            $file = fopen('php://output', 'w');

            // Add UTF-8 BOM to prevent excel parsing glitches
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            switch ($type) {
                case 'trial_balance':
                    fputcsv($file, ['Trial Balance Report (PKR)']);
                    fputcsv($file, ['Date range', ($startDate ?: 'All-Time').' to '.($endDate ?: 'Present')]);
                    fputcsv($file, []);
                    fputcsv($file, ['Account Code', 'Account Name', 'Type', 'Debit (PKR)', 'Credit (PKR)', 'Net Balance (PKR)']);

                    $tb = $this->accountService->getTrialBalance();
                    foreach ($tb['accounts'] as $acc) {
                        fputcsv($file, [
                            $acc['code'],
                            $acc['name'],
                            ucfirst($acc['type']),
                            'Rs. '.number_format($acc['debit'], 2),
                            'Rs. '.number_format($acc['credit'], 2),
                            'Rs. '.number_format($acc['balance'], 2),
                        ]);
                    }
                    fputcsv($file, []);
                    fputcsv($file, ['', 'Total Debits', '', 'Rs. '.number_format($tb['total_debits'], 2), '', '']);
                    fputcsv($file, ['', 'Total Credits', '', '', 'Rs. '.number_format($tb['total_credits'], 2), '']);
                    fputcsv($file, ['', 'Is Balanced', '', $tb['is_balanced'] ? 'YES' : 'NO']);
                    break;

                case 'profit_loss':
                    fputcsv($file, ['Profit & Loss Statement (PKR)']);
                    fputcsv($file, ['Date range', ($startDate ?: 'All-Time').' to '.($endDate ?: 'Present')]);
                    fputcsv($file, []);

                    $pl = $this->accountService->getProfitLoss();
                    fputcsv($file, ['Revenue (PKR)', 'Rs. '.number_format($pl['total_revenue'], 2)]);
                    fputcsv($file, ['Expenses (PKR)', 'Rs. '.number_format($pl['total_expense'], 2)]);
                    fputcsv($file, ['Net Income (PKR)', 'Rs. '.number_format($pl['net_income'], 2)]);
                    break;

                case 'balance_sheet':
                    fputcsv($file, ['Balance Sheet (PKR)']);
                    fputcsv($file, ['As Of Date', $endDate ?: 'Present']);
                    fputcsv($file, []);

                    $bs = $this->accountService->getBalanceSheet($endDate);
                    fputcsv($file, ['Assets (PKR)', 'Rs. '.number_format($bs['total_assets'], 2)]);
                    fputcsv($file, ['Liabilities (PKR)', 'Rs. '.number_format($bs['total_liabilities'], 2)]);
                    fputcsv($file, ['Owner Equity (PKR)', 'Rs. '.number_format($bs['total_equity'], 2)]);
                    fputcsv($file, ['Net Income (Retained)', 'Rs. '.number_format($bs['net_income'], 2)]);
                    fputcsv($file, ['Total Equity & Liabilities', 'Rs. '.number_format($bs['total_equity_and_liabilities'], 2)]);
                    fputcsv($file, ['Is Balanced', $bs['is_balanced'] ? 'YES' : 'NO']);
                    break;

                case 'stock_summary':
                    fputcsv($file, ['Inventory Stock Summary (MT / Sq Ft)']);
                    fputcsv($file, []);
                    fputcsv($file, ['SKU', 'Product Name', 'Category', 'UOM', 'Stock On Hand', 'FIFO Valuation (PKR)']);

                    foreach (Product::all() as $p) {
                        fputcsv($file, [
                            $p->sku,
                            $p->name,
                            ucfirst($p->product_type),
                            $p->unit_of_measure ?: $p->unit,
                            number_format($this->inventoryService->getTotalStock($p->id), 2),
                            'Rs. '.number_format($this->inventoryService->getValuation($p->id), 2),
                        ]);
                    }
                    break;

                case 'batch_inventory':
                    fputcsv($file, ['Batch-wise Inventory Listing']);
                    fputcsv($file, []);
                    fputcsv($file, ['Batch ID', 'SKU', 'Product Name', 'Grade', 'Received Date', 'Qty Received', 'Qty Remaining', 'Unit Cost (PKR)', 'Total Value (PKR)']);

                    $batches = InventoryBatch::with('product')
                        ->where('quantity_remaining', '>', 0)
                        ->get();

                    foreach ($batches as $b) {
                        fputcsv($file, [
                            '#'.$b->id,
                            $b->product ? $b->product->sku : 'N/A',
                            $b->product ? $b->product->name : 'Unknown Product',
                            $b->quality_grade ?: 'N/A',
                            $b->received_at->toDateString(),
                            number_format($b->quantity_received, 2),
                            number_format($b->quantity_remaining, 2),
                            'Rs. '.number_format($b->unit_cost, 2),
                            'Rs. '.number_format($b->quantity_remaining * $b->unit_cost, 2),
                        ]);
                    }
                    break;

                case 'stone_to_slab':
                    fputcsv($file, ['Stone-to-Slab Production Yields & Costs']);
                    fputcsv($file, []);
                    fputcsv($file, ['Run Number', 'Date', 'Input Batch', 'Input (MT)', 'Slabs (MT)', 'Wastage (MT)', 'Scrap (MT)', 'Expenses (PKR)', 'Total Cost (PKR)', 'Cost / MT (PKR)', 'Yield %']);

                    foreach (StoneToSlabProduction::with('inputBatch')->get() as $run) {
                        fputcsv($file, [
                            $run->production_order_number,
                            $run->production_date->toDateString(),
                            'Batch #'.$run->input_batch_id,
                            number_format($run->input_quantity, 2),
                            number_format($run->slabs_produced, 2),
                            number_format($run->wastage_quantity, 2),
                            number_format($run->scrap_quantity, 2),
                            'Rs. '.number_format($run->labor_cost + $run->electricity_cost + $run->fuel_cost + $run->maintenance_cost + $run->overhead_cost, 2),
                            'Rs. '.number_format($run->total_cost, 2),
                            'Rs. '.number_format($run->cost_per_mt, 2),
                            number_format($run->yield_percentage, 2).'%',
                        ]);
                    }
                    break;

                case 'slab_to_tile':
                    fputcsv($file, ['Slab-to-Tile Production Yields & Costs']);
                    fputcsv($file, []);
                    fputcsv($file, ['Run Number', 'Date', 'Input Batch', 'Input (MT)', 'Tiles (Sq Ft)', 'Wastage %', 'Scrap (Sq Ft)', 'Expenses (PKR)', 'Total Cost (PKR)', 'Cost / Sq Ft (PKR)', 'Ratio (Sq Ft/MT)']);

                    foreach (SlabToTileProduction::with('inputBatch')->get() as $run) {
                        fputcsv($file, [
                            $run->production_order_number,
                            $run->production_date->toDateString(),
                            'Batch #'.$run->input_batch_id,
                            number_format($run->input_quantity, 2),
                            number_format($run->tiles_produced, 2),
                            number_format($run->wastage_percentage, 2).'%',
                            number_format($run->scrap_quantity, 2),
                            'Rs. '.number_format($run->labor_cost + $run->cutting_cost + $run->polishing_cost + $run->packaging_cost + $run->overhead_cost, 2),
                            'Rs. '.number_format($run->total_cost, 2),
                            'Rs. '.number_format($run->cost_per_sqft, 2),
                            number_format($run->conversion_ratio, 2),
                        ]);
                    }
                    break;

                case 'sales_summary':
                    fputcsv($file, ['Sales Invoices Outstanding Balance Summary']);
                    fputcsv($file, []);
                    fputcsv($file, ['Invoice Number', 'Issue Date', 'Customer', 'Gross Amount (PKR)', 'Discount (PKR)', 'Freight (PKR)', 'Net Invoiced (PKR)', 'Amt Paid (PKR)', 'Balance (PKR)', 'Status']);

                    foreach (CustomerInvoice::with('contact')->get() as $inv) {
                        fputcsv($file, [
                            $inv->invoice_number,
                            $inv->issue_date->toDateString(),
                            $inv->contact->name,
                            'Rs. '.number_format($inv->gross_amount, 2),
                            'Rs. '.number_format($inv->discount_amount, 2),
                            'Rs. '.number_format($inv->delivery_charges, 2),
                            'Rs. '.number_format($inv->net_amount, 2),
                            'Rs. '.number_format($inv->cash_received + $inv->bank_received, 2),
                            'Rs. '.number_format($inv->remaining_balance, 2),
                            strtoupper($inv->status),
                        ]);
                    }
                    break;

                case 'purchases_summary':
                    fputcsv($file, ['Supplier Purchases Outstanding Balance Summary']);
                    fputcsv($file, []);
                    fputcsv($file, ['Bill Number', 'Issue Date', 'Supplier', 'Gross Cost (PKR)', 'Freight (PKR)', 'Total Landed Cost (PKR)', 'Amt Paid (PKR)', 'Balance (PKR)', 'Status']);

                    foreach (SupplierBill::with('contact')->get() as $bill) {
                        fputcsv($file, [
                            $bill->bill_number,
                            $bill->issue_date->toDateString(),
                            $bill->contact->name,
                            'Rs. '.number_format($bill->gross_amount, 2),
                            'Rs. '.number_format($bill->delivery_charges, 2),
                            'Rs. '.number_format($bill->net_amount, 2),
                            'Rs. '.number_format($bill->cash_paid + $bill->bank_paid, 2),
                            'Rs. '.number_format($bill->remaining_balance, 2),
                            strtoupper($bill->status),
                        ]);
                    }
                    break;
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    public function dailyReport(Request $request)
    {
        $date = $request->input('date', Carbon::today()->toDateString());
        $parsedDate = Carbon::parse($date);

        // 1. Production report data
        $stoneProductions = StoneToSlabProduction::with(['inputBatch.product', 'slabProduct', 'productionSchedule'])
            ->whereDate('production_date', $parsedDate)
            ->get()
            ->map(function ($p) {
                $wastage_percentage = $p->wastage_percentage;
                if ($wastage_percentage === null || $wastage_percentage == 0) {
                    $input = (float) $p->input_quantity;
                    $wastage = (float) $p->wastage_quantity;
                    $wastage_percentage = $input > 0 ? ($wastage / $input) * 100 : 0.0;
                }

                return [
                    'reference' => $p->production_order_number,
                    'type' => 'stone_to_slab',
                    'machine' => $p->productionSchedule ? $p->productionSchedule->machine : 'Gang Saw / Block Cutter',
                    'input_product' => $p->inputBatch && $p->inputBatch->product ? $p->inputBatch->product->name : 'N/A',
                    'output_product' => $p->slabProduct ? $p->slabProduct->name : 'N/A',
                    'input_qty' => (float) $p->input_quantity,
                    'output_qty' => (float) $p->slabs_produced,
                    'wastage_qty' => (float) $p->wastage_quantity,
                    'wastage_pct' => (float) $wastage_percentage,
                    'waste_reason' => $p->waste_reason ?: 'N/A',
                    'operator' => $p->waste_operator_name ?: 'N/A',
                    'schedule' => $p->productionSchedule ? $p->productionSchedule->schedule_number : 'N/A',
                ];
            });

        $tileProductions = SlabToTileProduction::with(['inputBatch.product', 'tileProduct', 'productionSchedule'])
            ->whereDate('production_date', $parsedDate)
            ->get()
            ->map(function ($p) {
                $wastage_percentage = $p->wastage_percentage;
                if ($wastage_percentage === null || $wastage_percentage == 0) {
                    // if it's area-based wastage
                    $input = (float) $p->input_quantity;
                    // typical tile conversion logic if required
                    $wastage_percentage = 0.0;
                }

                return [
                    'reference' => $p->production_order_number,
                    'type' => 'slab_to_tile',
                    'machine' => $p->productionSchedule ? $p->productionSchedule->machine : 'Edge Cutter / Polisher',
                    'input_product' => $p->inputBatch && $p->inputBatch->product ? $p->inputBatch->product->name : 'N/A',
                    'output_product' => $p->tileProduct ? $p->tileProduct->name : 'N/A',
                    'input_qty' => (float) $p->input_quantity,
                    'output_qty' => (float) $p->tiles_produced,
                    'wastage_qty' => (float) $p->wastage_quantity,
                    'wastage_pct' => (float) $wastage_percentage,
                    'waste_reason' => $p->waste_reason ?: 'N/A',
                    'operator' => $p->waste_operator_name ?: 'N/A',
                    'schedule' => $p->productionSchedule ? $p->productionSchedule->schedule_number : 'N/A',
                ];
            });

        $productionReport = $stoneProductions->concat($tileProductions);

        // 2. Dispatch report data (delivery challans)
        $dispatchReport = DeliveryChallan::with(['order.contact', 'customerInvoice.contact', 'items.product', 'items.inventoryBatch'])
            ->whereDate('challan_date', $parsedDate)
            ->get()
            ->map(function ($dc) {
                $contactName = 'N/A';
                if ($dc->order && $dc->order->contact) {
                    $contactName = $dc->order->contact->name;
                } elseif ($dc->customerInvoice && $dc->customerInvoice->contact) {
                    $contactName = $dc->customerInvoice->contact->name;
                }

                $itemsSummary = $dc->items->map(function ($item) {
                    $batchStr = $item->inventoryBatch ? " (Batch #{$item->inventory_batch_id})" : '';

                    return "{$item->product->name}{$batchStr}: ".(float) $item->quantity." {$item->unit}";
                })->implode(', ');

                return [
                    'challan_number' => $dc->challan_number,
                    'customer' => $contactName,
                    'invoice_or_order' => $dc->order ? $dc->order->order_number : ($dc->customerInvoice ? $dc->customerInvoice->invoice_number : 'N/A'),
                    'items_summary' => $itemsSummary,
                    'vehicle' => $dc->vehicle_number,
                    'driver' => $dc->driver_name,
                    'status' => $dc->status,
                ];
            });

        // 3. Quality report data
        $qualityReport = QualityInspection::with(['product', 'inventoryBatch'])
            ->whereDate('inspection_date', $parsedDate)
            ->get()
            ->map(function ($qi) {
                return [
                    'qc_number' => $qi->qc_number,
                    'product' => $qi->product->name,
                    'batch' => $qi->inventory_batch_id ? "Batch #{$qi->inventory_batch_id}" : 'N/A',
                    'checked_qty' => (float) $qi->checked_quantity,
                    'accepted_qty' => (float) $qi->accepted_quantity,
                    'rejected_qty' => (float) $qi->rejected_quantity,
                    'grade' => $qi->grade,
                    'defect_type' => $qi->defect_type ?: 'None',
                    'inspector' => $qi->inspector_name,
                ];
            });

        return Inertia::render('Reports/Daily', [
            'date' => $date,
            'productionReport' => $productionReport,
            'dispatchReport' => $dispatchReport,
            'qualityReport' => $qualityReport,
        ]);
    }
}
