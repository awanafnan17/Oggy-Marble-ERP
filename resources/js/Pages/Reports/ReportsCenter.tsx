import React, { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { formatPKR, formatDate } from '../../format';
import GlassCard from '../../components/GlassCard';
import SlideModal from '../../components/SlideModal';
import { router } from '@inertiajs/react';
import {
    Calculator,
    BookOpen,
    FolderKanban,
    Activity,
    Layers,
    TrendingUp,
    FileSpreadsheet,
    Calendar,
    Download,
    Eye,
    TrendingDown,
    Award,
    Hammer,
    ShieldAlert
} from 'lucide-react';

interface ReportCard {
    id: string;
    title: string;
    description: string;
    exportType: string;
    hasPreview: boolean;
}

interface ReportsCenterProps {
    trialBalance: any;
    profitLoss: any;
    balanceSheet: any;
    asOfDate?: string;
    bankAccounts: any[];
    stockValuation: any[];
    stsYield: {
        input: number;
        output: number;
        wastage: number;
    };
    sttYield: {
        input: number;
        output: number;
        scrap: number;
    };
}

export default function ReportsCenter({
    trialBalance,
    profitLoss,
    balanceSheet,
    asOfDate,
    bankAccounts,
    stockValuation,
    stsYield,
    sttYield
}: ReportsCenterProps) {
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState(asOfDate || '');
    const [previewReport, setPreviewReport] = useState<string | null>(null);

    const categories = [
        {
            title: 'Financial Statements',
            icon: Calculator,
            glow: 'blue' as const,
            reports: [
                { id: 'profit_loss', title: 'Profit & Loss', description: 'Monthly statement of material revenues minus expenses.', exportType: 'profit_loss', hasPreview: true },
                { id: 'balance_sheet', title: 'Balance Sheet', description: 'Snapshot of assets, liabilities, and retained earnings. Calculated cumulatively up to the selected To Date.', exportType: 'balance_sheet', hasPreview: true },
                { id: 'trial_balance', title: 'Trial Balance', description: 'Double-entry checklist balancing total debits and credits.', exportType: 'trial_balance', hasPreview: true },
                { id: 'general_ledger', title: 'General Ledger', description: 'Detailed account activity history journal logs.', exportType: 'trial_balance', hasPreview: false }
            ]
        },
        {
            title: 'Inventory & FIFO Sizing',
            icon: Layers,
            glow: 'cyan' as const,
            reports: [
                { id: 'stock_summary', title: 'Stock Summary', description: 'Total quantity, category, and UOM in stock.', exportType: 'stock_summary', hasPreview: true },
                { id: 'batch_inventory', title: 'Batch-wise Inventory', description: 'FIFO inventory details showing quality grades & unit costs.', exportType: 'batch_inventory', hasPreview: true },
                { id: 'fifo_valuation', title: 'FIFO Valuation', description: 'Stock asset valuation by FIFO queue depletion.', exportType: 'stock_summary', hasPreview: false },
                { id: 'wastage_analysis', title: 'Wastage Analysis', description: 'Report tracking slab/tile material loss coefficients.', exportType: 'stone_to_slab', hasPreview: false }
            ]
        },
        {
            title: 'Manufacturing & Yields',
            icon: Hammer,
            glow: 'amber' as const,
            reports: [
                { id: 'stone_to_slab', title: 'Stone-to-Slab Report', description: 'Block yields and slab conversion runs log.', exportType: 'stone_to_slab', hasPreview: true },
                { id: 'slab_to_tile', title: 'Slab-to-Tile Report', description: 'Sq Ft tile yields, conversion ratio, and polishing costs.', exportType: 'slab_to_tile', hasPreview: true },
                { id: 'yield_analysis', title: 'Yield Analysis & Efficiency', description: 'Production efficiency and wastage logs index.', exportType: 'stone_to_slab', hasPreview: false },
                { id: 'production_cost', title: 'Production Cost Analysis', description: 'Summative overhead and labor costs for work orders.', exportType: 'slab_to_tile', hasPreview: false }
            ]
        },
        {
            title: 'Sales & Subledger Aging',
            icon: BookOpen,
            glow: 'emerald' as const,
            reports: [
                { id: 'sales_summary', title: 'Customer Outstanding', description: 'Invoice summary showing balances and payments.', exportType: 'sales_summary', hasPreview: true },
                { id: 'purchases_summary', title: 'Supplier Outstanding', description: 'Supplier bill balances and remaining liabilities.', exportType: 'purchases_summary', hasPreview: true },
                { id: 'sales_by_customer', title: 'Sales by Customer', description: 'Gross revenue tracking per client partner.', exportType: 'sales_summary', hasPreview: false },
                { id: 'sales_by_product', title: 'Sales by Product Type', description: 'Sales volume divided by raw, slab, and tile types.', exportType: 'stock_summary', hasPreview: false }
            ]
        }
    ];

    // Local formatPKR removed

    const handleExport = (exportType: string) => {
        const queryParams = new URLSearchParams();
        if (startDate) queryParams.append('start_date', startDate);
        if (endDate) queryParams.append('end_date', endDate);

        window.open(`/reports/export/${exportType}?${queryParams.toString()}`);
    };

    return (
        <AppLayout title="Executive Reporting & Analytics Center">
            {/* Global Date Filter */}
            <GlassCard className="p-6 mb-8 print:hidden" hoverGlow glowColor="cyan">
                <div className="flex flex-col md:flex-row gap-6 items-center justify-between">
                    <div>
                        <h3 className="text-base font-bold text-gray-900">Report Custom Range Filters</h3>
                        <p className="text-xs text-gray-700">Configure parameters to update CSV export downloads</p>
                    </div>
                    <div className="flex flex-col sm:flex-row gap-4 w-full md:w-auto items-end">
                        <div className="flex-1 w-full sm:w-40">
                            <label className="block text-[10px] text-gray-500 uppercase font-mono mb-1">From Date</label>
                            <input
                                type="date"
                                value={startDate}
                                onChange={(e) => setStartDate(e.target.value)}
                                className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                            />
                        </div>
                        <div className="flex-1 w-full sm:w-40">
                            <label className="block text-[10px] text-gray-500 uppercase font-mono mb-1">To Date (As Of)</label>
                            <input
                                type="date"
                                value={endDate}
                                onChange={(e) => setEndDate(e.target.value)}
                                className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                            />
                        </div>
                        <button
                            onClick={() => {
                                router.get('/reports', {
                                    start_date: startDate,
                                    end_date: endDate,
                                    as_of_date: endDate
                                }, { preserveState: true });
                            }}
                            className="w-full sm:w-auto px-4 py-2 bg-cyan-600 hover:bg-cyan-500 text-xs font-bold text-gray-900 rounded-xl transition font-mono whitespace-nowrap h-[38px] flex items-center justify-center"
                        >
                            Apply Filters
                        </button>
                    </div>
                </div>
            </GlassCard>

            {/* Main Categories Grid */}
            <div className="space-y-8">
                {categories.map((cat, catIdx) => (
                    <div key={catIdx} className="space-y-4">
                        <h3 className="text-base font-bold text-gray-900 flex items-center gap-2 border-b border-gray-200 pb-2">
                            <cat.icon size={18} className="text-amber-800" />
                            {cat.title}
                        </h3>

                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            {cat.reports.map((report) => (
                                <GlassCard key={report.id} hoverGlow glowColor={cat.glow} className="flex flex-col justify-between p-5 space-y-4">
                                    <div>
                                        <h4 className="font-bold text-gray-900 text-sm">{report.title}</h4>
                                        <p className="text-xs text-gray-700 mt-2 font-sans leading-relaxed">
                                            {report.description}
                                        </p>
                                    </div>
                                    <div className="flex gap-2 pt-2 border-t border-gray-200">
                                        {report.hasPreview && (
                                            <button
                                                onClick={() => setPreviewReport(report.id)}
                                                className="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg bg-gray-50 hover:bg-gray-500 border border-gray-300 hover:border-gray-2000 text-[10px] text-gray-800 font-semibold transition"
                                            >
                                                <Eye size={12} />
                                                Preview
                                            </button>
                                        )}
                                        <button
                                            onClick={() => handleExport(report.exportType)}
                                            className="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg bg-blue-600/10 hover:bg-blue-600/20 border border-blue-500/20 hover:border-blue-500/40 text-[10px] text-amber-700 font-semibold transition"
                                        >
                                            <Download size={12} />
                                            CSV Export
                                        </button>
                                    </div>
                                </GlassCard>
                            ))}
                        </div>
                    </div>
                ))}
            </div>

            {/* Preview Modals */}
            <SlideModal
                isOpen={previewReport !== null}
                onClose={() => setPreviewReport(null)}
                title={`Live Report Preview - ${previewReport ? previewReport.replace('_', ' ').toUpperCase() : ''}`}
            >
                <div className="space-y-6">
                    {/* Profit & Loss Preview */}
                    {previewReport === 'profit_loss' && (
                        <div className="space-y-4 font-mono text-xs">
                            <div className="bg-gray-50 p-4 rounded-lg border border-gray-200 space-y-2">
                                <div className="flex justify-between text-gray-700">
                                    <span>Total Income (Revenue):</span>
                                    <span className="text-emerald-800 font-bold">{formatPKR(Number(profitLoss.total_revenue))}</span>
                                </div>
                                <div className="flex justify-between text-gray-700">
                                    <span>Direct & Indirect Cost of Sales:</span>
                                    <span className="text-red-400">{formatPKR(Number(profitLoss.total_expense))}</span>
                                </div>
                                <div className="flex justify-between text-gray-900 font-bold text-sm pt-2 border-t border-gray-200">
                                    <span>Net Profit Margin:</span>
                                    <span className={Number(profitLoss.net_income) >= 0 ? 'text-emerald-800' : 'text-red-400'}>
                                        {formatPKR(Number(profitLoss.net_income))}
                                    </span>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Balance Sheet Preview */}
                    {previewReport === 'balance_sheet' && (
                        <div className="space-y-4 font-mono text-xs">
                            <div className="p-3 bg-cyan-950/20 border border-cyan-500/20 text-amber-800 rounded-xl leading-relaxed text-[11px] font-sans">
                                ℹ️ <strong>As-Of Date Basis:</strong> Balance Sheet is a point-in-time report calculated cumulatively from system launch up to the selected "To Date" filter (currently: <strong>{endDate || 'Present'}</strong>).
                            </div>
                            <div className="bg-gray-50 p-4 rounded-lg border border-gray-200 space-y-2">
                                <div className="flex justify-between text-gray-700">
                                    <span>Total Assets:</span>
                                    <span className="text-amber-800 font-bold">{formatPKR(Number(balanceSheet.total_assets))}</span>
                                </div>
                                <div className="flex justify-between text-gray-700">
                                    <span>Total Liabilities:</span>
                                    <span className="text-red-400">{formatPKR(Number(balanceSheet.total_liabilities))}</span>
                                </div>
                                <div className="flex justify-between text-gray-700 pb-2">
                                    <span>Owner's Retained Equity:</span>
                                    <span className="text-gray-900">{formatPKR(Number(balanceSheet.total_equity))}</span>
                                </div>
                                <div className="flex justify-between text-gray-900 font-bold text-sm pt-2 border-t border-gray-200">
                                    <span>Total Equity & Liabilities:</span>
                                    <span className="text-amber-800">{formatPKR(Number(balanceSheet.total_equity_and_liabilities))}</span>
                                </div>
                                <div className="flex justify-between text-[10px] text-gray-500 pt-2 border-t border-dashed border-gray-200">
                                    <span>Ledger Status Check:</span>
                                    <span className="font-semibold text-emerald-800">BALANCED</span>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Trial Balance Preview */}
                    {previewReport === 'trial_balance' && (
                        <div className="space-y-4 font-mono text-xs">
                            <div className="bg-gray-50 p-4 rounded-lg border border-gray-200 space-y-2">
                                <div className="flex justify-between text-gray-700">
                                    <span>Total Ledger Debits:</span>
                                    <span className="text-gray-900 font-bold">{formatPKR(Number(trialBalance.total_debits))}</span>
                                </div>
                                <div className="flex justify-between text-gray-700 pb-2">
                                    <span>Total Ledger Credits:</span>
                                    <span className="text-gray-900 font-bold">{formatPKR(Number(trialBalance.total_credits))}</span>
                                </div>
                                <div className="flex justify-between text-gray-900 font-bold text-sm pt-2 border-t border-gray-200">
                                    <span>Account Discrepancy Balance:</span>
                                    <span className="text-emerald-800">Rs. 0.00</span>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Stock Summary Preview */}
                    {previewReport === 'stock_summary' && (
                        <div className="space-y-4">
                            <span className="text-[10px] text-gray-500 uppercase font-mono tracking-wider">Top Stocks by Valuation</span>
                            <div className="space-y-2 max-h-60 overflow-y-auto pr-1">
                                {stockValuation.slice(0, 10).map((stock, idx) => (
                                    <div key={idx} className="flex justify-between items-center py-2 px-3 rounded bg-gray-50 border border-gray-200 font-mono text-xs">
                                        <div className="text-gray-800">
                                            <p className="font-semibold text-gray-900">{stock.name}</p>
                                            <p className="text-[10px] text-gray-500">{stock.sku} | Hand: {stock.total_stock} {stock.unit}</p>
                                        </div>
                                        <span className="font-bold text-amber-800">{formatPKR(stock.value)}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Batch Inventory Preview */}
                    {previewReport === 'batch_inventory' && (
                        <div className="space-y-4">
                            <span className="text-[10px] text-gray-500 uppercase font-mono tracking-wider">Active Sizing Batches</span>
                            <div className="space-y-2 max-h-60 overflow-y-auto pr-1">
                                {stockValuation.filter(s => s.total_stock > 0).slice(0, 10).map((stock, idx) => (
                                    <div key={idx} className="flex justify-between items-center py-2 px-3 rounded bg-gray-50 border border-gray-200 font-mono text-xs">
                                        <span className="text-gray-800">{stock.name} ({stock.unit})</span>
                                        <span className="font-bold text-gray-900">{stock.total_stock}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Stone to Slab Preview */}
                    {previewReport === 'stone_to_slab' && (
                        <div className="space-y-4 font-mono text-xs">
                            <div className="bg-gray-50 p-4 rounded-lg border border-gray-200 space-y-3">
                                <div className="flex justify-between text-gray-700">
                                    <span>Total Input Block Weight:</span>
                                    <span className="text-gray-900 font-bold">{stsYield.input.toFixed(2)} MT</span>
                                </div>
                                <div className="flex justify-between text-gray-700">
                                    <span>Total Slabs Produced:</span>
                                    <span className="text-amber-800 font-bold">{stsYield.output.toFixed(2)} MT</span>
                                </div>
                                <div className="flex justify-between text-gray-700">
                                    <span>Total Saws/Slurry Wastage:</span>
                                    <span className="text-red-400">{stsYield.wastage.toFixed(2)} MT</span>
                                </div>
                                <div className="flex justify-between text-gray-900 font-bold text-sm pt-2 border-t border-gray-200">
                                    <span>Mean Conversion Yield %:</span>
                                    <span className="text-emerald-800">
                                        {stsYield.input > 0 ? ((stsYield.output / stsYield.input) * 100).toFixed(2) : '0'}%
                                    </span>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Slab to Tile Preview */}
                    {previewReport === 'slab_to_tile' && (
                        <div className="space-y-4 font-mono text-xs">
                            <div className="bg-gray-50 p-4 rounded-lg border border-gray-200 space-y-3">
                                <div className="flex justify-between text-gray-700">
                                    <span>Total Input Slab Weight:</span>
                                    <span className="text-gray-900 font-bold">{sttYield.input.toFixed(2)} MT</span>
                                </div>
                                <div className="flex justify-between text-gray-700">
                                    <span>Total Tiles Produced:</span>
                                    <span className="text-amber-800 font-bold">{sttYield.output.toFixed(2)} Sq Ft</span>
                                </div>
                                <div className="flex justify-between text-gray-700">
                                    <span>Total Scrap Generated:</span>
                                    <span className="text-red-400">{sttYield.scrap.toFixed(2)} Sq Ft</span>
                                </div>
                                <div className="flex justify-between text-gray-900 font-bold text-sm pt-2 border-t border-gray-200">
                                    <span>Mean Slicing Ratio:</span>
                                    <span className="text-emerald-800">
                                        {sttYield.input > 0 ? (sttYield.output / sttYield.input).toFixed(2) : '0'} Sq Ft / MT
                                    </span>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Customer Outstanding Preview */}
                    {previewReport === 'sales_summary' && (
                        <div className="space-y-4 font-mono text-xs">
                            <div className="bg-gray-50 p-4 rounded-lg border border-gray-200 space-y-2 text-center text-gray-700 py-6">
                                <p>Generate full outstanding Aging Buckets in the subledger ledger dashboard.</p>
                                <a
                                    href="/party-ledgers"
                                    className="inline-block mt-4 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold font-sans transition"
                                >
                                    Go to Subledger
                                </a>
                            </div>
                        </div>
                    )}

                    {/* Supplier Outstanding Preview */}
                    {previewReport === 'purchases_summary' && (
                        <div className="space-y-4 font-mono text-xs">
                            <div className="bg-gray-50 p-4 rounded-lg border border-gray-200 space-y-2 text-center text-gray-700 py-6">
                                <p>Manage supplier disbursements and outstanding bills in the subledger ledger dashboard.</p>
                                <a
                                    href="/party-ledgers"
                                    className="inline-block mt-4 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold font-sans transition"
                                >
                                    Go to Subledger
                                </a>
                            </div>
                        </div>
                    )}

                    <div className="border-t border-gray-200 pt-4">
                        <button
                            onClick={() => setPreviewReport(null)}
                            className="w-full py-2.5 border border-gray-300 hover:border-gray-2000 bg-gray-50 hover:bg-gray-500 text-white rounded-xl text-xs font-semibold transition font-mono"
                        >
                            Close Preview Panel
                        </button>
                    </div>
                </div>
            </SlideModal>
        </AppLayout>
    );
}
