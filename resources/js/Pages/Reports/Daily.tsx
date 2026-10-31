import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { formatDate } from '../../format';
import GlassCard from '../../components/GlassCard';
import DataTable, { Column } from '../../components/DataTable';
import StatusBadge from '../../components/StatusBadge';
import {
    Calendar,
    Hammer,
    Truck,
    ShieldAlert,
    FileText,
    ChevronRight,
    TrendingUp,
    RefreshCw
} from 'lucide-react';

interface ProductionRow {
    reference: string;
    type: 'stone_to_slab' | 'slab_to_tile';
    machine: string;
    input_product: string;
    output_product: string;
    input_qty: number;
    output_qty: number;
    wastage_qty: number;
    wastage_pct: number;
    waste_reason: string;
    operator: string;
    schedule: string;
}

interface DispatchRow {
    challan_number: string;
    customer: string;
    invoice_or_order: string;
    items_summary: string;
    vehicle: string;
    driver: string;
    status: string;
}

interface QualityRow {
    qc_number: string;
    qc_ref?: string;
    product: string;
    batch: string;
    checked_qty: number;
    accepted_qty: number;
    rejected_qty: number;
    grade: string;
    defect_type: string;
    inspector: string;
}

interface Props {
    date: string;
    productionReport: ProductionRow[];
    dispatchReport: DispatchRow[];
    qualityReport: QualityRow[];
}

export default function Daily({ date, productionReport, dispatchReport, qualityReport }: Props) {
    const [selectedDate, setSelectedDate] = useState(date);
    const [activeTab, setActiveTab] = useState<'production' | 'dispatch' | 'quality'>('production');

    const handleDateChange = (newDate: string) => {
        setSelectedDate(newDate);
        router.get('/reports/daily', { date: newDate }, { preserveState: true });
    };

    // Columns definitions
    const productionColumns: Column<ProductionRow>[] = [
        { header: 'Run #', accessor: 'reference', className: 'text-white font-mono font-medium' },
        {
            header: 'Type',
            accessor: (row) => (
                <span className={`px-1.5 py-0.5 rounded text-[10px] font-mono uppercase font-bold ${
                    row.type === 'stone_to_slab' ? 'bg-[#06B6D4]/15 text-amber-800' : 'bg-indigo-500/15 text-indigo-400'
                }`}>
                    {row.type === 'stone_to_slab' ? 'Stone -> Slab' : 'Slab -> Tile'}
                </span>
            )
        },
        { header: 'Machine', accessor: 'machine' },
        { header: 'Input Product', accessor: 'input_product' },
        { header: 'Output Product', accessor: 'output_product' },
        { header: 'Input Qty', accessor: (row) => `${row.input_qty.toFixed(2)}` },
        { header: 'Output Qty', accessor: (row) => `${row.output_qty.toFixed(2)}` },
        { header: 'Wastage Qty', accessor: (row) => `${row.wastage_qty.toFixed(2)}`, className: 'text-red-400 font-medium' },
        { header: 'Wastage %', accessor: (row) => `${row.wastage_pct.toFixed(1)}%`, className: 'font-semibold font-mono text-red-400' },
        { header: 'Waste Reason', accessor: 'waste_reason' },
        { header: 'Operator', accessor: 'operator' },
        { header: 'Schedule #', accessor: 'schedule', className: 'font-mono' }
    ];

    const dispatchColumns: Column<DispatchRow>[] = [
        { header: 'Challan #', accessor: 'challan_number', className: 'text-white font-mono font-medium' },
        { header: 'Customer', accessor: 'customer' },
        { header: 'SO/Invoice Ref', accessor: 'invoice_or_order', className: 'font-mono' },
        { header: 'Dispatched Goods Summary', accessor: 'items_summary', className: 'max-w-xs truncate font-sans text-gray-400' },
        { header: 'Vehicle', accessor: 'vehicle', className: 'font-mono' },
        { header: 'Driver', accessor: 'driver' },
        { header: 'Status', accessor: (row) => <StatusBadge status={row.status} /> }
    ];

    const qualityColumns: Column<QualityRow>[] = [
        { header: 'QC Ref', accessor: 'qc_number', className: 'text-white font-mono font-medium' },
        { header: 'Product', accessor: 'product' },
        { header: 'Batch Ref', accessor: 'batch', className: 'font-mono' },
        { header: 'Checked', accessor: (row) => `${row.checked_qty.toFixed(1)}` },
        { header: 'Accepted', accessor: (row) => `${row.accepted_qty.toFixed(1)}`, className: 'text-emerald-800 font-bold' },
        {
            header: 'Rejected',
            accessor: (row) => (
                <span className={row.rejected_qty > 0 ? 'text-red-400 font-bold' : 'text-gray-500'}>
                    {row.rejected_qty.toFixed(1)}
                </span>
            )
        },
        {
            header: 'Grade',
            accessor: (row) => (
                <span className={`font-bold font-mono px-2 py-0.5 rounded text-[10px] ${
                    row.grade === 'A' ? 'bg-emerald-500/10 text-emerald-800' :
                    row.grade === 'B' ? 'bg-blue-500/10 text-amber-800' :
                    row.grade === 'C' ? 'bg-amber-500/10 text-amber-800' : 'bg-red-500/10 text-red-400 border border-red-500/20'
                }`}>
                    Grade {row.grade}
                </span>
            )
        },
        { header: 'Defect Type', accessor: 'defect_type', className: 'capitalize text-amber-800' },
        { header: 'Inspector', accessor: 'inspector' }
    ];

    return (
        <AppLayout title="Daily Factory Operations Summary Reports">
            <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h2 className="text-lg font-bold text-gray-900">Daily Factory Operations Ledger</h2>
                    <p className="text-xs text-gray-700">Consolidated registry of logs, quality reviews, and dispatch challans executed on site.</p>
                </div>

                {/* Date Picker Input */}
                <div className="flex items-center gap-3 bg-gray-50 border border-gray-300 rounded-xl px-4 py-2.5 shadow-md">
                    <Calendar size={16} className="text-amber-800" />
                    <span className="text-xs text-gray-700 font-mono">Date:</span>
                    <input
                        type="date"
                        value={selectedDate}
                        onChange={(e) => handleDateChange(e.target.value)}
                        className="bg-transparent border-none text-xs text-white focus:outline-none focus:ring-0 p-0 font-mono cursor-pointer"
                    />
                </div>
            </div>

            {/* Quick Metrics Header Cards */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
                <GlassCard hoverGlow glowColor="blue" className="p-5 flex items-center justify-between">
                    <div>
                        <span className="text-[10px] text-gray-500 uppercase font-mono tracking-wider">Production Runs</span>
                        <h4 className="text-2xl font-bold text-gray-900 font-mono mt-1">{productionReport.length}</h4>
                        <p className="text-[10px] text-gray-700 mt-0.5">Slabs & tile cuts processed</p>
                    </div>
                    <div className="p-3 rounded-2xl bg-blue-500/10 border border-blue-500/20 text-amber-800">
                        <Hammer size={24} />
                    </div>
                </GlassCard>

                <GlassCard hoverGlow glowColor="cyan" className="p-5 flex items-center justify-between">
                    <div>
                        <span className="text-[10px] text-gray-500 uppercase font-mono tracking-wider">Dispatched Challans</span>
                        <h4 className="text-2xl font-bold text-gray-900 font-mono mt-1">{dispatchReport.length}</h4>
                        <p className="text-[10px] text-gray-700 mt-0.5">Gate passes cleared</p>
                    </div>
                    <div className="p-3 rounded-2xl bg-cyan-500/10 border border-cyan-500/20 text-amber-800">
                        <Truck size={24} />
                    </div>
                </GlassCard>

                <GlassCard hoverGlow glowColor="emerald" className="p-5 flex items-center justify-between">
                    <div>
                        <span className="text-[10px] text-gray-500 uppercase font-mono tracking-wider">Quality Audits</span>
                        <h4 className="text-2xl font-bold text-gray-900 font-mono mt-1">{qualityReport.length}</h4>
                        <p className="text-[10px] text-gray-700 mt-0.5">Inspection batches graded</p>
                    </div>
                    <div className="p-3 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-800">
                        <ShieldAlert size={24} />
                    </div>
                </GlassCard>
            </div>

            {/* Tabbed Report Sections */}
            <div className="flex border-b border-gray-200 pb-2 mb-6">
                <div className="flex space-x-2">
                    <button
                        onClick={() => setActiveTab('production')}
                        className={`px-4 py-2 rounded-xl text-xs font-semibold tracking-wide border transition ${
                            activeTab === 'production'
                                ? 'bg-gradient-to-r from-blue-950/40 to-cyan-950/20 border-[#06B6D4]/40 text-amber-800'
                                : 'border-transparent text-gray-500 hover:text-gray-900'
                        }`}
                    >
                        Daily Production Report
                    </button>
                    <button
                        onClick={() => setActiveTab('dispatch')}
                        className={`px-4 py-2 rounded-xl text-xs font-semibold tracking-wide border transition ${
                            activeTab === 'dispatch'
                                ? 'bg-gradient-to-r from-blue-950/40 to-cyan-950/20 border-[#06B6D4]/40 text-amber-800'
                                : 'border-transparent text-gray-500 hover:text-gray-900'
                        }`}
                    >
                        Daily Dispatch Report
                    </button>
                    <button
                        onClick={() => setActiveTab('quality')}
                        className={`px-4 py-2 rounded-xl text-xs font-semibold tracking-wide border transition ${
                            activeTab === 'quality'
                                ? 'bg-gradient-to-r from-blue-950/40 to-cyan-950/20 border-[#06B6D4]/40 text-amber-800'
                                : 'border-transparent text-gray-500 hover:text-gray-900'
                        }`}
                    >
                        Daily Quality Report
                    </button>
                </div>
            </div>

            {/* Render selected report */}
            <div className="glass-panel rounded-2xl p-6">
                <div className="flex justify-between items-center mb-6">
                    <h3 className="font-bold text-base text-gray-900 flex items-center gap-2 uppercase tracking-wide font-mono text-sm">
                        <FileText size={16} className="text-amber-800" />
                        {activeTab === 'production' && `Production Summary: ${formatDate(selectedDate)}`}
                        {activeTab === 'dispatch' && `Dispatch Summary: ${formatDate(selectedDate)}`}
                        {activeTab === 'quality' && `Quality Summary: ${formatDate(selectedDate)}`}
                    </h3>
                </div>

                <div className="overflow-x-auto">
                    {activeTab === 'production' && (
                        <DataTable
                            columns={productionColumns}
                            data={productionReport}
                            keyExtractor={(row) => row.reference}
                            emptyMessage={`No production runs registered on ${formatDate(selectedDate)}.`}
                        />
                    )}
                    {activeTab === 'dispatch' && (
                        <DataTable
                            columns={dispatchColumns}
                            data={dispatchReport}
                            keyExtractor={(row) => row.challan_number}
                            emptyMessage={`No delivery challans issued on ${formatDate(selectedDate)}.`}
                        />
                    )}
                    {activeTab === 'quality' && (
                        <DataTable
                            columns={qualityColumns}
                            data={qualityReport}
                            keyExtractor={(row) => row.qc_ref || row.qc_number}
                            emptyMessage={`No quality control reviews registered on ${formatDate(selectedDate)}.`}
                        />
                    )}
                </div>
            </div>

        </AppLayout>
    );
}
