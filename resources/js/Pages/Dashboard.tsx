import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout';
import {
    TrendingUp, DollarSign, Activity,
    Banknote, Wallet, FileText, ShoppingCart,
    Percent, BarChart3, PieChart, Calendar, Package
} from 'lucide-react';
import {
    AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer,
    PieChart as RechartsPieChart, Pie, Cell
} from 'recharts';
import { formatPKR } from '../format';

interface FinancialKPIs {
    totalRevenue: number;
    totalCOGS: number;
    grossProfit: number;
    grossMargin: number;
    netProfit: number;
}

interface CashPosition {
    totalCash: number;
    totalBankBalance: number;
    totalReceivables: number;
    totalPayables: number;
}

interface InventoryValuation {
    totalInventoryValue: number;
    valuationByWarehouse: { name: string; value: number }[];
    valuationByProductType: { type: string; value: number }[];
}

interface SalesAnalytics {
    salesTrendByMonth: { month: string; revenue: number }[];
    revenueByProductType: { name: string; value: number }[];
}

interface DashboardProps {
    filters: {
        date_from?: string;
        date_to?: string;
    };
    financialKPIs: FinancialKPIs;
    cashPosition: CashPosition;
    inventoryValuation: InventoryValuation;
    salesAnalytics: SalesAnalytics;
}

const COLORS = ['#D97706', '#2563EB', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6'];

export default function Dashboard({
    filters = {},
    financialKPIs = {} as any,
    cashPosition = {} as any,
    inventoryValuation = {} as any,
    salesAnalytics = {} as any
}: DashboardProps) {
    const safeFilters = filters || {};
    const safeFinancialKPIs = financialKPIs || {};
    const safeCashPosition = cashPosition || {};
    const safeInventoryValuation = inventoryValuation || {};
    const safeSalesAnalytics = salesAnalytics || {};

    const [dateFrom, setDateFrom] = useState(safeFilters.date_from || '');
    const [dateTo, setDateTo] = useState(safeFilters.date_to || '');

    const formatPercent = (val: number) => new Intl.NumberFormat('en-US', { style: 'percent', maximumFractionDigits: 1 }).format(val / 100);
    const applyFilters = () => {
        router.get('/dashboard', {
            date_from: dateFrom,
            date_to: dateTo
        }, { preserveState: true });
    };

    const KPICard = ({ title, value, icon, isPositive = true, subtitle = '' }: any) => (
        <div className="bg-white border border-slate-200 shadow-sm p-6 rounded-2xl relative overflow-hidden group hover:border-amber-400 transition duration-200">
            <div className="flex items-center justify-between mb-3">
                <span className="text-[15.5px] font-semibold text-slate-600">{title}</span>
                <div className={`p-2.5 rounded-xl ${isPositive ? 'bg-amber-50 text-amber-600 border border-amber-200' : 'bg-red-50 text-red-600 border border-red-200'}`}>
                    {icon}
                </div>
            </div>
            <h2 className="text-3xl font-extrabold tracking-tight mb-1 text-slate-900">{value}</h2>
            {subtitle && <p className="text-[13px] text-slate-500 font-mono mt-1">{subtitle}</p>}
            <div className="absolute bottom-0 left-0 right-0 h-1 bg-amber-500 opacity-0 group-hover:opacity-100 transition-opacity duration-200" />
        </div>
    );

    return (
        <AppLayout title="Executive Dashboard">
            <Head title="Executive Dashboard" />

            {/* Header & Date Filter Bar */}
            <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                <div>
                    <h1 className="text-3xl font-bold text-slate-900 tracking-tight flex items-center gap-3">
                        <Activity className="text-amber-600 w-8 h-8" /> Executive Dashboard
                    </h1>
                    <p className="text-[16px] text-slate-600 mt-1 font-medium">Clear overview of sales, inventory, and cash balance.</p>
                </div>
                <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 bg-white p-2.5 rounded-xl border border-slate-200 shadow-xs w-full sm:w-auto">
                    <div className="flex items-center gap-2">
                        <Calendar size={18} className="text-slate-400 ml-2 shrink-0" />
                        <input
                            type="date"
                            className="bg-transparent border-none text-[15px] text-slate-800 focus:ring-0 w-full font-mono font-medium"
                            value={dateFrom}
                            onChange={(e) => setDateFrom(e.target.value)}
                        />
                    </div>
                    <span className="text-slate-700 font-medium text-center sm:text-left">to</span>
                    <div className="flex items-center gap-2">
                        <input
                            type="date"
                            className="bg-transparent border-none text-[15px] text-slate-800 focus:ring-0 w-full font-mono font-medium"
                            value={dateTo}
                            onChange={(e) => setDateTo(e.target.value)}
                        />
                    </div>
                    <button
                        onClick={applyFilters}
                        className="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white text-[15px] font-bold rounded-lg transition-colors shadow-xs w-full sm:w-auto cursor-pointer"
                    >
                        Filter
                    </button>
                </div>
            </div>

            {/* SECTION 1 — Financial KPI Cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <KPICard
                    title="Total Revenue"
                    value={formatPKR(safeFinancialKPIs.totalRevenue || 0)}
                    icon={<TrendingUp size={22} />}
                    isPositive={true}
                />
                <KPICard
                    title="Gross Profit"
                    value={formatPKR(safeFinancialKPIs.grossProfit || 0)}
                    icon={<DollarSign size={22} />}
                    isPositive={(safeFinancialKPIs.grossProfit || 0) >= 0}
                    subtitle={`COGS: ${formatPKR(safeFinancialKPIs.totalCOGS || 0)}`}
                />
                <KPICard
                    title="Gross Margin"
                    value={formatPercent(safeFinancialKPIs.grossMargin || 0)}
                    icon={<Percent size={22} />}
                    isPositive={(safeFinancialKPIs.grossMargin || 0) >= 20}
                />
                <KPICard
                    title="Total Inventory Value"
                    value={formatPKR(safeInventoryValuation.totalInventoryValue || 0)}
                    icon={<Package size={22} />}
                    isPositive={true}
                />
            </div>

            {/* SECTION 2 — Cash & Exposure Cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div className="bg-white border border-slate-200 shadow-sm p-6 rounded-2xl">
                    <div className="flex items-center gap-2.5 mb-2 text-slate-600 font-semibold text-[15px]">
                        <Wallet size={18} className="text-amber-600" /> <span>Cash in Hand</span>
                    </div>
                    <div className="text-2xl font-bold text-slate-900">{formatPKR(safeCashPosition.totalCash || 0)}</div>
                </div>
                <div className="bg-white border border-slate-200 shadow-sm p-6 rounded-2xl">
                    <div className="flex items-center gap-2.5 mb-2 text-slate-600 font-semibold text-[15px]">
                        <Banknote size={18} className="text-amber-600" /> <span>Bank Balance</span>
                    </div>
                    <div className="text-2xl font-bold text-slate-900">{formatPKR(safeCashPosition.totalBankBalance || 0)}</div>
                </div>
                <div className="bg-white border-l-4 border-l-emerald-500 border border-slate-200 shadow-sm p-6 rounded-2xl">
                    <div className="flex items-center gap-2.5 mb-2 text-slate-600 font-semibold text-[15px]">
                        <FileText size={18} className="text-emerald-600" /> <span>Accounts Receivable</span>
                    </div>
                    <div className="text-2xl font-bold text-emerald-700">{formatPKR(safeCashPosition.totalReceivables || 0)}</div>
                </div>
                <div className="bg-white border-l-4 border-l-red-500 border border-slate-200 shadow-sm p-6 rounded-2xl">
                    <div className="flex items-center gap-2.5 mb-2 text-slate-600 font-semibold text-[15px]">
                        <ShoppingCart size={18} className="text-red-600" /> <span>Accounts Payable</span>
                    </div>
                    <div className="text-2xl font-bold text-red-700">{formatPKR(safeCashPosition.totalPayables || 0)}</div>
                </div>
            </div>

            {/* SECTION 3 — Analytics & Revenue Trend */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                <div className="bg-white border border-slate-200 shadow-sm p-6 rounded-2xl col-span-2">
                    <h3 className="text-[17px] font-bold text-slate-900 mb-6 flex items-center gap-2">
                        <BarChart3 size={20} className="text-amber-600" /> Monthly Revenue Trend
                    </h3>
                    <div className="h-72 w-full">
                        <ResponsiveContainer width="100%" height="100%">
                            <AreaChart data={safeSalesAnalytics.salesTrendByMonth || []} margin={{ top: 10, right: 10, left: 0, bottom: 0 }}>
                                <defs>
                                    <linearGradient id="colorRev" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="5%" stopColor="#D97706" stopOpacity={0.3}/>
                                        <stop offset="95%" stopColor="#D97706" stopOpacity={0}/>
                                    </linearGradient>
                                </defs>
                                <CartesianGrid strokeDasharray="3 3" stroke="#E2E8F0" vertical={false} />
                                <XAxis dataKey="month" stroke="#64748B" fontSize={13} tickLine={false} axisLine={false} />
                                <YAxis stroke="#64748B" fontSize={13} tickLine={false} axisLine={false} tickFormatter={(v) => `Rs. ${v/1000}k`} />
                                <Tooltip
                                    contentStyle={{ backgroundColor: '#FFFFFF', borderColor: '#E2E8F0', borderRadius: '8px', boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)' }}
                                    itemStyle={{ color: '#0F172A', fontWeight: 'bold' }}
                                    formatter={(value: any) => [formatPKR(Number(value || 0)), 'Revenue']}
                                />
                                <Area type="monotone" dataKey="revenue" stroke="#D97706" strokeWidth={3} fillOpacity={1} fill="url(#colorRev)" />
                            </AreaChart>
                        </ResponsiveContainer>
                    </div>
                </div>

                <div className="bg-white border border-slate-200 shadow-sm p-6 rounded-2xl">
                    <h3 className="text-[17px] font-bold text-slate-900 mb-6 flex items-center gap-2">
                        <PieChart size={20} className="text-amber-600" /> Revenue Breakdown
                    </h3>
                    <div className="h-72 w-full">
                        <ResponsiveContainer width="100%" height="100%">
                            <RechartsPieChart>
                                <Pie
                                    data={safeSalesAnalytics.revenueByProductType || []}
                                    cx="50%"
                                    cy="50%"
                                    innerRadius={55}
                                    outerRadius={80}
                                    paddingAngle={5}
                                    dataKey="value"
                                >
                                    {(safeSalesAnalytics.revenueByProductType || []).map((entry: any, index: number) => (
                                        <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                                    ))}
                                </Pie>
                                <Tooltip
                                    contentStyle={{ backgroundColor: '#FFFFFF', borderColor: '#E2E8F0', borderRadius: '8px' }}
                                    formatter={(value: any) => formatPKR(Number(value || 0))}
                                />
                            </RechartsPieChart>
                        </ResponsiveContainer>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
