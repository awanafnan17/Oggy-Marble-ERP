import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { formatPKR, formatDate } from '../../format';
import { ProductionTabs } from './Schedules';
import {
    TrendingUp,
    TrendingDown,
    Activity,
    DollarSign,
    Filter,
    RotateCcw,
    Search,
    CheckCircle2,
    AlertTriangle,
    BarChart3,
} from 'lucide-react';

interface Product {
    id: number;
    sku: string;
    name: string;
    size?: string;
}

interface VarianceRow {
    id: string;
    production_number: string;
    production_date: string;
    type: string;
    product_name: string;
    product_sku: string;
    product_size?: string;
    expected_cost: number;
    actual_cost: number;
    variance_amount: number;
    variance_percentage: number;
    is_favorable: boolean;
}

interface KPI {
    total_expected: number;
    total_actual: number;
    total_variance: number;
    avg_variance_pct: number;
    total_runs: number;
    favorable_count: number;
    unfavorable_count: number;
}

interface VarianceReportProps {
    products: Product[];
    varianceData: VarianceRow[];
    kpi: KPI;
    filters: {
        product_id: string | null;
        date_from: string | null;
        date_to: string | null;
    };
}

export default function VarianceReport({
    products,
    varianceData,
    kpi,
    filters,
}: VarianceReportProps) {
    const [productId, setProductId] = useState(filters.product_id || '');
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');

    const fmt = formatPKR;

    const handleFilter = () => {
        router.get('/production/variance', {
            product_id: productId || undefined,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
        }, { preserveState: true, replace: true });
    };

    const handleClearFilters = () => {
        setProductId('');
        setDateFrom('');
        setDateTo('');
        router.get('/production/variance', {}, { preserveState: false, replace: true });
    };

    return (
        <AppLayout title="Production Variance Analysis">
            <ProductionTabs activeTab="variance" />
            {/* KPI Cards */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                {/* Total Expected */}
                <div className="glass-panel p-5 rounded-2xl relative overflow-hidden group hover:border-blue-500/30 transition duration-300">
                    <div className="flex items-center justify-between mb-2">
                        <span className="text-xs text-gray-500 uppercase font-mono font-semibold tracking-wider">Total Expected</span>
                        <div className="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center">
                            <DollarSign size={16} className="text-amber-700" />
                        </div>
                    </div>
                    <p className="text-2xl font-bold text-gray-900">{fmt(kpi.total_expected)}</p>
                    <p className="text-[10px] text-gray-500 mt-1 font-mono">{kpi.total_runs} production runs</p>
                    <div className="absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r from-blue-500 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300" />
                </div>

                {/* Total Actual */}
                <div className="glass-panel p-5 rounded-2xl relative overflow-hidden group hover:border-cyan-500/30 transition duration-300">
                    <div className="flex items-center justify-between mb-2">
                        <span className="text-xs text-gray-500 uppercase font-mono font-semibold tracking-wider">Total Actual</span>
                        <div className="w-8 h-8 rounded-lg bg-cyan-500/10 flex items-center justify-center">
                            <Activity size={16} className="text-amber-800" />
                        </div>
                    </div>
                    <p className="text-2xl font-bold text-gray-900">{fmt(kpi.total_actual)}</p>
                    <p className="text-[10px] text-gray-500 mt-1 font-mono">
                        {kpi.favorable_count} favorable · {kpi.unfavorable_count} unfavorable
                    </p>
                    <div className="absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r from-cyan-500 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300" />
                </div>

                {/* Total Variance */}
                <div className={`glass-panel p-5 rounded-2xl relative overflow-hidden group transition duration-300 ${
                    kpi.total_variance > 0 ? 'hover:border-red-500/30' : 'hover:border-[#10B981]/30'
                }`}>
                    <div className="flex items-center justify-between mb-2">
                        <span className="text-xs text-gray-500 uppercase font-mono font-semibold tracking-wider">Total Variance</span>
                        <div className={`w-8 h-8 rounded-lg flex items-center justify-center ${
                            kpi.total_variance > 0 ? 'bg-red-500/10' : 'bg-[#10B981]/10'
                        }`}>
                            {kpi.total_variance > 0
                                ? <TrendingUp size={16} className="text-red-400" />
                                : <TrendingDown size={16} className="text-[#10B981]" />
                            }
                        </div>
                    </div>
                    <p className={`text-2xl font-bold ${kpi.total_variance > 0 ? 'text-red-400' : 'text-[#10B981]'}`}>
                        {fmt(kpi.total_variance)}
                    </p>
                    <p className="text-[10px] text-gray-500 mt-1 font-mono">
                        {kpi.total_variance > 0 ? 'Net unfavorable' : kpi.total_variance < 0 ? 'Net favorable' : 'No variance'}
                    </p>
                    <div className={`absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r ${
                        kpi.total_variance > 0 ? 'from-red-500' : 'from-[#10B981]'
                    } to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300`} />
                </div>

                {/* Avg Variance % */}
                <div className="glass-panel p-5 rounded-2xl relative overflow-hidden group hover:border-purple-500/30 transition duration-300">
                    <div className="flex items-center justify-between mb-2">
                        <span className="text-xs text-gray-500 uppercase font-mono font-semibold tracking-wider">Avg Variance %</span>
                        <div className="w-8 h-8 rounded-lg bg-purple-500/10 flex items-center justify-center">
                            <BarChart3 size={16} className="text-purple-400" />
                        </div>
                    </div>
                    <p className={`text-2xl font-bold ${
                        kpi.avg_variance_pct > 0 ? 'text-red-400' :
                        kpi.avg_variance_pct < 0 ? 'text-[#10B981]' : 'text-white'
                    }`}>
                        {kpi.avg_variance_pct >= 0 ? '+' : ''}{kpi.avg_variance_pct.toFixed(2)}%
                    </p>
                    <p className="text-[10px] text-gray-500 mt-1 font-mono">Across all filtered runs</p>
                    <div className="absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r from-purple-500 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300" />
                </div>
            </div>

            {/* Filters */}
            <div className="glass-panel rounded-2xl p-5 mb-8">
                <div className="flex items-center gap-2 mb-4">
                    <Filter size={16} className="text-gray-400" />
                    <h3 className="text-sm font-bold text-gray-900">Report Filters</h3>
                </div>
                <div className="flex flex-col sm:flex-row gap-4 items-end">
                    <div className="flex-1">
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Product</label>
                        <select
                            value={productId}
                            onChange={(e) => setProductId(e.target.value)}
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                        >
                            <option value="">All Products</option>
                            {products.map((p) => (
                                <option key={p.id} value={p.id}>{p.sku} — {p.name} {p.size ? `(${p.size})` : ''}</option>
                            ))}
                        </select>
                    </div>
                    <div className="flex-1">
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Date From</label>
                        <input
                            type="date"
                            value={dateFrom}
                            onChange={(e) => setDateFrom(e.target.value)}
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                        />
                    </div>
                    <div className="flex-1">
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Date To</label>
                        <input
                            type="date"
                            value={dateTo}
                            onChange={(e) => setDateTo(e.target.value)}
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                        />
                    </div>
                    <div className="flex gap-2">
                        <button
                            onClick={handleFilter}
                            className="flex items-center gap-2 px-5 py-2.5 bg-[#2563EB] hover:bg-blue-700 text-white rounded-xl font-medium shadow-lg hover:shadow-blue-500/20 transition-all duration-300"
                        >
                            <Search size={14} />
                            Filter
                        </button>
                        <button
                            onClick={handleClearFilters}
                            className="flex items-center gap-2 px-4 py-2.5 border border-gray-300 hover:border-gray-2000 bg-gray-50 text-gray-800 rounded-xl font-medium transition-all duration-300"
                        >
                            <RotateCcw size={14} />
                            Clear
                        </button>
                    </div>
                </div>
            </div>

            {/* Variance Table */}
            <div className="glass-panel rounded-2xl p-6">
                <div className="flex items-center gap-3 mb-6">
                    <BarChart3 size={20} className="text-amber-800" />
                    <h3 className="font-bold text-lg text-gray-900">Production Variance Detail</h3>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse">
                        <thead>
                            <tr className="border-b border-gray-200 text-gray-600 text-xs uppercase font-semibold font-mono">
                                <th className="pb-3">Production #</th>
                                <th className="pb-3">Date</th>
                                <th className="pb-3">Type</th>
                                <th className="pb-3">Product</th>
                                <th className="pb-3 text-right">Expected Cost</th>
                                <th className="pb-3 text-right">Actual Cost</th>
                                <th className="pb-3 text-right">Variance</th>
                                <th className="pb-3 text-right">Variance %</th>
                                <th className="pb-3 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 text-sm">
                            {varianceData.length === 0 ? (
                                <tr>
                                    <td colSpan={9} className="py-8 text-center text-gray-600 font-mono">
                                        No production variance data available. Complete production runs with BOM standards to see variance analysis.
                                    </td>
                                </tr>
                            ) : (
                                varianceData.map((row) => (
                                    <tr key={row.id} className="hover:bg-white/[0.01] transition duration-150">
                                        <td className="py-4 font-mono font-medium text-gray-900">
                                            {row.production_number}
                                        </td>
                                        <td className="py-4 text-gray-700 font-mono">
                                            {formatDate(row.production_date)}
                                        </td>
                                        <td className="py-4">
                                            <span className="px-2.5 py-1 rounded-lg text-[10px] font-semibold font-mono bg-gray-50 border border-gray-300 text-gray-800">
                                                {row.type}
                                            </span>
                                        </td>
                                        <td className="py-4">
                                            <div>
                                                <p className="text-gray-900 font-medium">{row.product_name}</p>
                                                <p className="text-[10px] text-gray-500 font-mono">
                                                    {row.product_sku} {row.product_size ? `(${row.product_size})` : ''}
                                                </p>
                                            </div>
                                        </td>
                                        <td className="py-4 text-right font-mono text-gray-800">
                                            {fmt(row.expected_cost)}
                                        </td>
                                        <td className="py-4 text-right font-mono text-gray-900 font-bold">
                                            {fmt(row.actual_cost)}
                                        </td>
                                        <td className={`py-4 text-right font-mono font-bold ${
                                            row.variance_amount > 0 ? 'text-red-400' :
                                            row.variance_amount < 0 ? 'text-[#10B981]' : 'text-gray-400'
                                        }`}>
                                            {row.variance_amount >= 0 ? '+' : ''}{fmt(row.variance_amount)}
                                        </td>
                                        <td className={`py-4 text-right font-mono font-bold ${
                                            row.variance_percentage > 0 ? 'text-red-400' :
                                            row.variance_percentage < 0 ? 'text-[#10B981]' : 'text-gray-400'
                                        }`}>
                                            {row.variance_percentage >= 0 ? '+' : ''}{row.variance_percentage.toFixed(2)}%
                                        </td>
                                        <td className="py-4 text-center">
                                            {row.is_favorable ? (
                                                <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-[#10B981]/10 border border-[#10B981]/20 text-[#10B981]">
                                                    <CheckCircle2 size={12} />
                                                    Favorable
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-red-500/10 border border-red-500/20 text-red-400">
                                                    <AlertTriangle size={12} />
                                                    Unfavorable
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
