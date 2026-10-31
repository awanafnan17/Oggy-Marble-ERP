import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { formatPKR, formatDate } from '../../format';
import GlassCard from '../../components/GlassCard';
import KPICard from '../../components/KPICard';
import DataTable, { Column } from '../../components/DataTable';
import StatusBadge from '../../components/StatusBadge';
import { ProductionTabs } from './Schedules';
import {
    Hammer,
    ArrowDownRight,
    TrendingUp,
    Settings,
    Calendar,
    DollarSign,
    Scale,
    Trash2
} from 'lucide-react';

interface Product {
    id: number;
    sku: string;
    name: string;
    cost: number;
    size?: string;
}

interface InventoryBatch {
    id: number;
    product_id: number;
    product: Product;
    quantity_remaining: number;
    unit_cost: number;
    quality_grade?: string;
    batch_reference?: string;
}

interface StoneToSlabProduction {
    id: number;
    production_order_number: string;
    input_batch_id: number;
    input_batch: InventoryBatch;
    input_quantity: number;
    slab_product_id: number;
    slab_product: Product;
    slabs_produced: number;
    wastage_quantity: number;
    scrap_quantity: number;
    total_cost: number;
    cost_per_mt: number;
    yield_percentage: number;
    production_date: string;
}

interface Warehouse {
    id: number;
    name: string;
}

interface StoneToSlabProps {
    products: Product[];
    rawStoneProducts: Product[];
    slabProducts: Product[];
    scrapProducts: Product[];
    rawStoneBatches: InventoryBatch[];
    stoneToSlabProductions: StoneToSlabProduction[];
    warehouses: Warehouse[];
    schedules: any[];
}

export default function StoneToSlab({
    products,
    rawStoneProducts,
    slabProducts,
    scrapProducts,
    rawStoneBatches,
    stoneToSlabProductions,
    warehouses,
    schedules
}: StoneToSlabProps) {
    // Form state
    const [form, setForm] = useState({
        input_batch_id: '',
        input_quantity: '',
        slab_product_id: '',
        slabs_produced: '',
        wastage_quantity: '0',
        scrap_product_id: '',
        scrap_quantity: '0',
        scrap_unit: 'MT',
        labor_cost: '0',
        electricity_cost: '0',
        fuel_cost: '0',
        maintenance_cost: '0',
        overhead_cost: '0',
        production_date: new Date().toISOString().substring(0, 10),
        warehouse_id: warehouses[0]?.id.toString() || '1',
        production_schedule_id: '',
        waste_reason: '',
        waste_operator_name: '',
    });

    const [errors, setErrors] = useState<any>({});

    const selectedBatch = rawStoneBatches.find(b => b.id === Number(form.input_batch_id));
    const selectedSlabProduct = slabProducts.find(p => p.id === Number(form.slab_product_id));

    // Dynamic calculations
    const inputQty = parseFloat(form.input_quantity) || 0;
    const slabsProduced = parseFloat(form.slabs_produced) || 0;
    const wastageQty = parseFloat(form.wastage_quantity) || 0;
    const scrapQty = parseFloat(form.scrap_quantity) || 0;

    const labor = parseFloat(form.labor_cost) || 0;
    const electricity = parseFloat(form.electricity_cost) || 0;
    const fuel = parseFloat(form.fuel_cost) || 0;
    const maintenance = parseFloat(form.maintenance_cost) || 0;
    const overhead = parseFloat(form.overhead_cost) || 0;

    const unitCost = selectedBatch ? Number(selectedBatch.unit_cost) : 0;
    const liveMaterialCost = inputQty * unitCost;
    const additionalCosts = labor + electricity + fuel + maintenance + overhead;
    const liveTotalCost = liveMaterialCost + additionalCosts;

    const liveCostPerMt = slabsProduced > 0 ? liveTotalCost / slabsProduced : 0;
    const liveYieldPct = inputQty > 0 ? (slabsProduced / inputQty) * 100 : 0;

    // Local formatPKR removed

    const handleFormSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/production/stone-to-slab', form, {
            onSuccess: () => {
                setForm({
                    input_batch_id: '',
                    input_quantity: '',
                    slab_product_id: '',
                    slabs_produced: '',
                    wastage_quantity: '0',
                    scrap_product_id: '',
                    scrap_quantity: '0',
                    scrap_unit: 'MT',
                    labor_cost: '0',
                    electricity_cost: '0',
                    fuel_cost: '0',
                    maintenance_cost: '0',
                    overhead_cost: '0',
                    production_date: new Date().toISOString().substring(0, 10),
                    warehouse_id: warehouses[0]?.id.toString() || '1',
                    production_schedule_id: '',
                    waste_reason: '',
                    waste_operator_name: '',
                });
                setErrors({});
            },
            onError: (err) => setErrors(err)
        });
    };

    const historyColumns: Column<StoneToSlabProduction>[] = [
        { header: 'Run #', accessor: 'production_order_number', className: 'text-white font-medium' },
        { header: 'Date', accessor: (row) => formatDate(row.production_date) },
        { header: 'Input Block Batch', accessor: (row) => `Batch #${row.input_batch_id}` },
        { header: 'Slab Product', accessor: (row) => row.slab_product ? `${row.slab_product.name} (${row.slab_product.sku})` : 'N/A' },
        { header: 'Size', accessor: (row) => row.slab_product?.size || '-' },
        { header: 'Input (MT)', accessor: (row) => `${row.input_quantity.toFixed(2)} MT`, align: 'right' },
        { header: 'Slabs (MT)', accessor: (row) => `${row.slabs_produced.toFixed(2)} MT`, align: 'right' },
        { header: 'Wastage (MT)', accessor: (row) => `${row.wastage_quantity.toFixed(2)} MT`, align: 'right', className: 'text-red-400' },
        { header: 'Total Cost', accessor: (row) => formatPKR(row.total_cost), align: 'right', className: 'text-white' },
        { header: 'Cost / MT', accessor: (row) => formatPKR(row.cost_per_mt), align: 'right', className: 'text-amber-800 font-bold' },
        {
            header: 'Yield %',
            accessor: (row) => (
                <span className={`font-bold ${row.yield_percentage >= 70 ? 'text-emerald-800' : 'text-amber-800'}`}>
                    {row.yield_percentage.toFixed(1)}%
                </span>
            ),
            align: 'right'
        }
    ];

    // circular svg parameters
    const radius = 50;
    const strokeWidth = 8;
    const circumference = 2 * Math.PI * radius;
    const strokeDashoffset = circumference - (Math.min(100, liveYieldPct) / 100) * circumference;

    return (
        <AppLayout title="Stone-to-Slab Production Panel">
            <ProductionTabs activeTab="stone_to_slab" />
            {/* Header info */}
            <div className="mb-6 print:hidden">
                <p className="text-xs text-gray-700">
                    Log block raw stone crushing, sawing, and slab capitalize costing runs.
                </p>
            </div>

            {/* Split layout Cockpit */}
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-8 print:hidden">
                {/* Left Panel: Sizing configuration */}
                <div className="lg:col-span-7">
                    <GlassCard hoverGlow glowColor="cyan" className="p-6 h-full flex flex-col justify-between">
                        <div className="space-y-6">
                            <h3 className="text-base font-bold text-gray-900 flex items-center gap-2 border-b border-gray-200 pb-3">
                                <Scale size={18} className="text-amber-800" />
                                Slabs Produced (Material Sizing)
                            </h3>

                            {/* Associated Production Schedule Dropdown */}
                            <div className="pb-4 border-b border-gray-200">
                                <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Link Scheduled Production Job (Optional)</label>
                                <select
                                    value={form.production_schedule_id}
                                    onChange={(e) => {
                                        const schedId = e.target.value;
                                        const sched = schedules.find(s => s.id === Number(schedId));
                                        if (sched) {
                                            setForm(prev => ({
                                                ...prev,
                                                production_schedule_id: schedId,
                                                input_batch_id: sched.planned_input_batch_id ? sched.planned_input_batch_id.toString() : '',
                                                input_quantity: sched.planned_input_quantity.toString(),
                                                slab_product_id: '', // let user select target slab type
                                                slabs_produced: sched.expected_output_quantity.toString(),
                                                wastage_quantity: sched.expected_wastage_quantity.toString(),
                                            }));
                                        } else {
                                            setForm(prev => ({
                                                ...prev,
                                                production_schedule_id: '',
                                            }));
                                        }
                                    }}
                                    className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                >
                                    <option value="">Choose an active scheduled job to pre-fill...</option>
                                    {schedules.map(s => (
                                        <option key={s.id} value={s.id}>
                                            {s.schedule_number} - Machine: {s.machine} (Input: {parseFloat(s.planned_input_quantity).toFixed(1)} MT)
                                        </option>
                                    ))}
                                </select>
                                {errors.production_schedule_id && <p className="text-xs text-red-400 mt-1">{errors.production_schedule_id}</p>}
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                {/* Batch selector */}
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Raw Stone Batch</label>
                                    <select
                                        required
                                        value={form.input_batch_id}
                                        onChange={(e) => setForm({ ...form, input_batch_id: e.target.value, input_quantity: '' })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    >
                                        <option value="">Select raw block batch...</option>
                                        {rawStoneBatches.map(b => (
                                            <option key={b.id} value={b.id}>
                                                Batch #{b.id} - {b.product?.name || 'Unknown Product'} (Grade: {b.quality_grade || 'Standard'})
                                            </option>
                                        ))}
                                    </select>
                                    {errors.input_batch_id && <p className="text-xs text-red-400 mt-1">{errors.input_batch_id}</p>}
                                </div>

                                {/* Available Qty indicator */}
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Available Qty (MT)</label>
                                    <input
                                        type="text"
                                        disabled
                                        value={selectedBatch ? `${selectedBatch.quantity_remaining.toFixed(2)} MT (Unit Cost: ${formatPKR(selectedBatch.unit_cost)})` : 'No batch selected'}
                                        className="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-xs text-gray-400 focus:outline-none font-mono"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                {/* Input weight */}
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Consumed Weight (MT)</label>
                                    <input
                                        type="number"
                                        required
                                        step="0.01"
                                        min="0.01"
                                        max={selectedBatch ? selectedBatch.quantity_remaining : undefined}
                                        value={form.input_quantity}
                                        onChange={(e) => setForm({ ...form, input_quantity: e.target.value })}
                                        placeholder="0.00"
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                    />
                                    {errors.input_quantity && <p className="text-xs text-red-400 mt-1">{errors.input_quantity}</p>}
                                </div>

                                {/* Target Slab Product */}
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Produced Slab Type</label>
                                    <select
                                        required
                                        value={form.slab_product_id}
                                        onChange={(e) => setForm({ ...form, slab_product_id: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    >
                                        <option value="">Select slab type...</option>
                                        {slabProducts.map(p => (
                                            <option key={p.id} value={p.id}>{p.sku} - {p.name} {p.size ? `(${p.size})` : ''}</option>
                                        ))}
                                    </select>
                                    {errors.slab_product_id && <p className="text-xs text-red-400 mt-1">{errors.slab_product_id}</p>}
                                </div>

                                {/* Produced Slabs Qty */}
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Slabs Produced (MT)</label>
                                    <input
                                        type="number"
                                        required
                                        step="0.01"
                                        min="0"
                                        value={form.slabs_produced}
                                        onChange={(e) => setForm({ ...form, slabs_produced: e.target.value })}
                                        placeholder="0.00"
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                    />
                                    {errors.slabs_produced && <p className="text-xs text-red-400 mt-1">{errors.slabs_produced}</p>}
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                {/* Wastage Slabs Qty */}
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Wastage Loss (MT)</label>
                                    <input
                                        type="number"
                                        required
                                        step="0.01"
                                        min="0"
                                        value={form.wastage_quantity}
                                        onChange={(e) => setForm({ ...form, wastage_quantity: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                    />
                                    {errors.wastage_quantity && <p className="text-xs text-red-400 mt-1">{errors.wastage_quantity}</p>}
                                </div>

                                {/* Scrap Product */}
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Scrap Product (By-product)</label>
                                    <select
                                        value={form.scrap_product_id}
                                        onChange={(e) => setForm({ ...form, scrap_product_id: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    >
                                        <option value="">No scrap generated...</option>
                                        {scrapProducts.map(p => (
                                            <option key={p.id} value={p.id}>{p.sku} - {p.name}</option>
                                        ))}
                                    </select>
                                    {errors.scrap_product_id && <p className="text-xs text-red-400 mt-1">{errors.scrap_product_id}</p>}
                                </div>

                                {/* Scrap Quantity */}
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Scrap Weight (MT)</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={form.scrap_quantity}
                                        onChange={(e) => setForm({ ...form, scrap_quantity: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                    />
                                    {errors.scrap_quantity && <p className="text-xs text-red-400 mt-1">{errors.scrap_quantity}</p>}
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                {/* Waste Reason */}
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Waste Reason</label>
                                    <select
                                        value={form.waste_reason}
                                        onChange={(e) => setForm({ ...form, waste_reason: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    >
                                        <option value="">No specific waste reason...</option>
                                        <option value="cutting_loss">Cutting Loss</option>
                                        <option value="breakage">Breakage/Crack</option>
                                        <option value="quality_reject">Quality Reject</option>
                                        <option value="machine_error">Machine Error</option>
                                        <option value="measurement_loss">Measurement Loss</option>
                                        <option value="other">Other</option>
                                    </select>
                                    {errors.waste_reason && <p className="text-xs text-red-400 mt-1">{errors.waste_reason}</p>}
                                </div>

                                {/* Waste Operator Name */}
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Waste Logged Operator</label>
                                    <input
                                        type="text"
                                        value={form.waste_operator_name}
                                        onChange={(e) => setForm({ ...form, waste_operator_name: e.target.value })}
                                        placeholder="Operator/supervisor name..."
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    />
                                    {errors.waste_operator_name && <p className="text-xs text-red-400 mt-1">{errors.waste_operator_name}</p>}
                                </div>
                            </div>
                        </div>

                        {/* circular yield visualization */}
                        <div className="flex items-center gap-6 mt-6 p-4 rounded-xl bg-gray-50 border border-gray-200">
                            <div className="relative w-24 h-24 shrink-0 flex items-center justify-center">
                                <svg className="w-full h-full transform -rotate-90">
                                    <circle
                                        cx="48"
                                        cy="48"
                                        r={radius}
                                        fill="transparent"
                                        stroke="rgba(255,255,255,0.05)"
                                        strokeWidth={strokeWidth}
                                    />
                                    <circle
                                        cx="48"
                                        cy="48"
                                        r={radius}
                                        fill="transparent"
                                        stroke={liveYieldPct >= 70 ? '#10B981' : '#F59E0B'}
                                        strokeWidth={strokeWidth}
                                        strokeDasharray={circumference}
                                        strokeDashoffset={isNaN(strokeDashoffset) ? circumference : strokeDashoffset}
                                        className="transition-all duration-500 ease-out"
                                    />
                                </svg>
                                <div className="absolute inset-0 flex flex-col items-center justify-center font-mono">
                                    <span className="text-base font-bold text-gray-900">
                                        {isNaN(liveYieldPct) ? '0' : liveYieldPct.toFixed(0)}%
                                    </span>
                                    <span className="text-[8px] text-gray-700 uppercase tracking-wider">Yield</span>
                                </div>
                            </div>
                            <div>
                                <h4 className="text-xs font-bold text-gray-900 font-mono">Slab Output Rate</h4>
                                <p className="text-xs text-gray-700 mt-1 font-sans leading-relaxed">
                                    Conversion ratio of stone block mass to processed slabs. Saws cutting friction, slurry, and block corners constitute the remaining weight.
                                </p>
                            </div>
                        </div>
                    </GlassCard>
                </div>

                {/* Right Panel: Cost inputs */}
                <div className="lg:col-span-5">
                    <form onSubmit={handleFormSubmit} className="h-full">
                        <GlassCard hoverGlow glowColor="blue" className="p-6 h-full flex flex-col justify-between">
                            <div className="space-y-4">
                                <h3 className="text-base font-bold text-gray-900 flex items-center gap-2 border-b border-gray-200 pb-3">
                                    <DollarSign size={18} className="text-amber-700" />
                                    Job Direct Expenditures
                                </h3>

                                <div className="grid grid-cols-2 gap-4">
                                    {/* Labor Cost */}
                                    <div>
                                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Direct Labor (PKR)</label>
                                        <input
                                            type="number"
                                            required
                                            min="0"
                                            value={form.labor_cost}
                                            onChange={(e) => setForm({ ...form, labor_cost: e.target.value })}
                                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                        />
                                    </div>

                                    {/* Electricity Cost */}
                                    <div>
                                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Electricity (PKR)</label>
                                        <input
                                            type="number"
                                            required
                                            min="0"
                                            value={form.electricity_cost}
                                            onChange={(e) => setForm({ ...form, electricity_cost: e.target.value })}
                                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                        />
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    {/* Fuel Cost */}
                                    <div>
                                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Fuel / Diesel (PKR)</label>
                                        <input
                                            type="number"
                                            required
                                            min="0"
                                            value={form.fuel_cost}
                                            onChange={(e) => setForm({ ...form, fuel_cost: e.target.value })}
                                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                        />
                                    </div>

                                    {/* Maintenance Cost */}
                                    <div>
                                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Maintenance / Blades</label>
                                        <input
                                            type="number"
                                            required
                                            min="0"
                                            value={form.maintenance_cost}
                                            onChange={(e) => setForm({ ...form, maintenance_cost: e.target.value })}
                                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                        />
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    {/* Overheads */}
                                    <div>
                                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Indirect Overheads</label>
                                        <input
                                            type="number"
                                            required
                                            min="0"
                                            value={form.overhead_cost}
                                            onChange={(e) => setForm({ ...form, overhead_cost: e.target.value })}
                                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                        />
                                    </div>

                                    {/* Production Warehouse destination */}
                                    <div>
                                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Storage Warehouse</label>
                                        <select
                                            required
                                            value={form.warehouse_id}
                                            onChange={(e) => setForm({ ...form, warehouse_id: e.target.value })}
                                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition h-[38px]"
                                        >
                                            {warehouses.map(w => (
                                                <option key={w.id} value={w.id}>{w.name}</option>
                                            ))}
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Run Date</label>
                                    <input
                                        type="date"
                                        required
                                        value={form.production_date}
                                        onChange={(e) => setForm({ ...form, production_date: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                    />
                                </div>

                                {/* Live costing indicators */}
                                <div className="p-4 rounded-xl bg-white border border-gray-200 space-y-2 font-mono text-xs text-gray-700 pt-3">
                                    <div className="flex justify-between">
                                        <span>Live Raw Materials Cost:</span>
                                        <span className="text-gray-900">{formatPKR(liveMaterialCost)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>Live Operational Expenditures:</span>
                                        <span className="text-gray-900">{formatPKR(additionalCosts)}</span>
                                    </div>
                                    <div className="flex justify-between border-t border-gray-200 pt-2 text-gray-900 font-bold text-sm">
                                        <span>Total Production Cost:</span>
                                        <span className="text-amber-800">{formatPKR(liveTotalCost)}</span>
                                    </div>
                                    <div className="flex justify-between text-xs text-amber-800 pt-1 border-t border-dashed border-gray-200 font-semibold">
                                        <span>Unit Rate Cost / MT Slabs:</span>
                                        <span>{formatPKR(liveCostPerMt)}</span>
                                    </div>
                                </div>
                            </div>

                            <button
                                type="submit"
                                className="w-full mt-6 py-3 bg-gradient-to-r from-blue-600 to-cyan-500 hover:from-blue-700 hover:to-cyan-600 text-white rounded-xl font-bold tracking-wide shadow-[0_0_15px_rgba(6,182,212,0.3)] hover:shadow-[0_0_20px_rgba(6,182,212,0.5)] transition duration-300 transform hover:-translate-y-0.5"
                            >
                                Process Stone-To-Slab
                            </button>
                        </GlassCard>
                    </form>
                </div>
            </div>

            {/* Run History list */}
            <div>
                <h3 className="text-lg font-bold text-gray-900 mb-4">Stone-to-Slab Runs History</h3>
                <DataTable
                    columns={historyColumns}
                    data={stoneToSlabProductions}
                    keyExtractor={(row) => row.id}
                    emptyMessage="No production runs executed yet. Fill out the cockpit configuration to process."
                />
            </div>
        </AppLayout>
    );
}
