import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { formatPKR, formatDate } from '../../format';
import GlassCard from '../../components/GlassCard';
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
    Layers
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

interface SlabToTileProduction {
    id: number;
    production_order_number: string;
    input_batch_id: number;
    input_batch: InventoryBatch;
    input_quantity: number;
    tile_product_id: number;
    tile_product: Product;
    tiles_produced: number;
    wastage_percentage: number;
    scrap_quantity: number;
    total_cost: number;
    cost_per_sqft: number;
    conversion_ratio: number;
    production_date: string;
}

interface Warehouse {
    id: number;
    name: string;
}

interface SlabToTileProps {
    products: Product[];
    slabProducts: Product[];
    tileProducts: Product[];
    scrapProducts: Product[];
    slabBatches: InventoryBatch[];
    slabToTileProductions: SlabToTileProduction[];
    warehouses: Warehouse[];
    schedules: any[];
}

export default function SlabToTile({
    products,
    slabProducts,
    tileProducts,
    scrapProducts,
    slabBatches,
    slabToTileProductions,
    warehouses,
    schedules
}: SlabToTileProps) {
    // Form state
    const [form, setForm] = useState({
        input_batch_id: '',
        input_quantity: '',
        tile_product_id: '',
        tiles_produced: '',
        wastage_percentage: '0',
        scrap_product_id: '',
        scrap_quantity: '0',
        scrap_unit: 'Sq Ft',
        labor_cost: '0',
        cutting_cost: '0',
        polishing_cost: '0',
        packaging_cost: '0',
        overhead_cost: '0',
        production_date: new Date().toISOString().substring(0, 10),
        warehouse_id: warehouses[0]?.id.toString() || '1',
        production_schedule_id: '',
        waste_reason: '',
        waste_operator_name: '',
    });

    const [errors, setErrors] = useState<any>({});

    const selectedBatch = slabBatches.find(b => b.id === Number(form.input_batch_id));

    // Dynamic calculations
    const inputQty = parseFloat(form.input_quantity) || 0;
    const tilesProduced = parseFloat(form.tiles_produced) || 0;
    const wastagePct = parseFloat(form.wastage_percentage) || 0;
    const scrapQty = parseFloat(form.scrap_quantity) || 0;

    const labor = parseFloat(form.labor_cost) || 0;
    const cutting = parseFloat(form.cutting_cost) || 0;
    const polishing = parseFloat(form.polishing_cost) || 0;
    const packaging = parseFloat(form.packaging_cost) || 0;
    const overhead = parseFloat(form.overhead_cost) || 0;

    const unitCost = selectedBatch ? Number(selectedBatch.unit_cost) : 0;
    const liveMaterialCost = inputQty * unitCost;
    const additionalCosts = labor + cutting + polishing + packaging + overhead;
    const liveTotalCost = liveMaterialCost + additionalCosts;

    const liveCostPerSqft = tilesProduced > 0 ? liveTotalCost / tilesProduced : 0;
    const liveConversionRatio = inputQty > 0 ? tilesProduced / inputQty : 0;

    // Local formatPKR removed

    const handleFormSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/production/slab-to-tile', form, {
            onSuccess: () => {
                setForm({
                    input_batch_id: '',
                    input_quantity: '',
                    tile_product_id: '',
                    tiles_produced: '',
                    wastage_percentage: '0',
                    scrap_product_id: '',
                    scrap_quantity: '0',
                    scrap_unit: 'Sq Ft',
                    labor_cost: '0',
                    cutting_cost: '0',
                    polishing_cost: '0',
                    packaging_cost: '0',
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

    const historyColumns: Column<SlabToTileProduction>[] = [
        { header: 'Run #', accessor: 'production_order_number', className: 'text-white font-medium' },
        { header: 'Date', accessor: (row) => formatDate(row.production_date) },
        { header: 'Input Slab Batch', accessor: (row) => `Batch #${row.input_batch_id}` },
        { header: 'Tile Product', accessor: (row) => row.tile_product ? `${row.tile_product.name} (${row.tile_product.sku})` : 'N/A' },
        { header: 'Size', accessor: (row) => row.tile_product?.size || '-' },
        { header: 'Input (MT)', accessor: (row) => `${row.input_quantity.toFixed(2)} MT`, align: 'right' },
        { header: 'Tiles (Sq Ft)', accessor: (row) => `${row.tiles_produced.toFixed(2)} Sq Ft`, align: 'right' },
        { header: 'Wastage %', accessor: (row) => `${row.wastage_percentage.toFixed(1)}%`, align: 'right', className: 'text-red-400' },
        { header: 'Total Cost', accessor: (row) => formatPKR(row.total_cost), align: 'right', className: 'text-white' },
        { header: 'Cost / Sq Ft', accessor: (row) => formatPKR(row.cost_per_sqft), align: 'right', className: 'text-amber-800 font-bold' },
        {
            header: 'Ratio (Sq Ft/MT)',
            accessor: (row) => (
                <span className="font-bold text-emerald-800">
                    {row.conversion_ratio.toFixed(1)}
                </span>
            ),
            align: 'right'
        }
    ];

    return (
        <AppLayout title="Slab-to-Tile Production Slicing Panel">
            <ProductionTabs activeTab="slab_to_tile" />
            {/* Header info */}
            <div className="mb-6 print:hidden">
                <p className="text-xs text-gray-700">
                    Log slab cutting, sizing, polishing, packaging, and capitalized tile costs.
                </p>
            </div>

            {/* Split layout Cockpit */}
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-8 print:hidden">
                {/* Left Panel: Slicing configuration */}
                <div className="lg:col-span-7">
                    <GlassCard hoverGlow glowColor="cyan" className="p-6 h-full flex flex-col justify-between">
                        <div className="space-y-6">
                            <h3 className="text-base font-bold text-gray-900 flex items-center gap-2 border-b border-gray-200 pb-3">
                                <Layers size={18} className="text-amber-800" />
                                Sliced Tile Output (Slab Processing)
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
                                                tile_product_id: '', // let user select target tile manually
                                                tiles_produced: sched.expected_output_quantity.toString(),
                                                wastage_percentage: sched.expected_wastage_percentage ? sched.expected_wastage_percentage.toString() : '0',
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
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Slab Input Batch</label>
                                    <select
                                        required
                                        value={form.input_batch_id}
                                        onChange={(e) => setForm({ ...form, input_batch_id: e.target.value, input_quantity: '' })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    >
                                        <option value="">Select slab batch...</option>
                                        {slabBatches.map(b => (
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
                                        value={selectedBatch ? `${selectedBatch.quantity_remaining.toFixed(2)} MT (Cost: ${formatPKR(selectedBatch.unit_cost)}/MT)` : 'No batch selected'}
                                        className="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-xs text-gray-400 focus:outline-none font-mono"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                {/* Input weight */}
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Slab Weight Consumed (MT)</label>
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

                                {/* Target Tile Product */}
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Produced Tile Type</label>
                                    <select
                                        required
                                        value={form.tile_product_id}
                                        onChange={(e) => setForm({ ...form, tile_product_id: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    >
                                        <option value="">Select tile type...</option>
                                        {tileProducts.map(p => (
                                            <option key={p.id} value={p.id}>{p.sku} - {p.name} {p.size ? `(${p.size})` : ''}</option>
                                        ))}
                                    </select>
                                    {errors.tile_product_id && <p className="text-xs text-red-400 mt-1">{errors.tile_product_id}</p>}
                                </div>

                                {/* Produced Tiles Qty */}
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Tiles Produced (Sq Ft)</label>
                                    <input
                                        type="number"
                                        required
                                        step="0.01"
                                        min="0.01"
                                        value={form.tiles_produced}
                                        onChange={(e) => setForm({ ...form, tiles_produced: e.target.value })}
                                        placeholder="0.00"
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                    />
                                    {errors.tiles_produced && <p className="text-xs text-red-400 mt-1">{errors.tiles_produced}</p>}
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                {/* Wastage Slabs Qty */}
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Cutting Wastage %</label>
                                    <input
                                        type="number"
                                        required
                                        step="0.1"
                                        min="0"
                                        value={form.wastage_percentage}
                                        onChange={(e) => setForm({ ...form, wastage_percentage: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                    />
                                    {errors.wastage_percentage && <p className="text-xs text-red-400 mt-1">{errors.wastage_percentage}</p>}
                                </div>

                                {/* Scrap Product */}
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Scrap Product</label>
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
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Scrap Area (Sq Ft)</label>
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

                        {/* highlight conversion ratio */}
                        <div className="flex items-center gap-6 mt-6 p-4 rounded-xl bg-gradient-to-tr from-blue-950/20 to-cyan-950/20 border border-[#06B6D4]/30 shadow-[0_0_15px_rgba(6,182,212,0.05)]">
                            <div className="w-16 h-16 shrink-0 flex items-center justify-center rounded-2xl bg-cyan-500/10 border border-cyan-500/20 text-amber-800">
                                <TrendingUp size={24} className="animate-pulse" />
                            </div>
                            <div>
                                <span className="text-[10px] text-gray-500 uppercase font-mono tracking-wider">Conversion Ratio Metric</span>
                                <h4 className="text-lg font-bold text-gray-900 font-mono mt-0.5">
                                    {liveConversionRatio.toFixed(1)} <span className="text-xs font-normal text-gray-700">Sq Ft / MT</span>
                                </h4>
                                <p className="text-[10px] text-gray-700 mt-1 font-sans leading-relaxed">
                                    Shows direct output yield efficiency in terms of square foot tile area generated from 1 metric ton of raw slab block. Higher conversion represents optimized blade kerf loss.
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
                                    Slicing Job Direct Costs
                                </h3>

                                <div className="grid grid-cols-2 gap-4">
                                    {/* Labor Cost */}
                                    <div>
                                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Labor (PKR)</label>
                                        <input
                                            type="number"
                                            required
                                            min="0"
                                            value={form.labor_cost}
                                            onChange={(e) => setForm({ ...form, labor_cost: e.target.value })}
                                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                        />
                                    </div>

                                    {/* Cutting Cost */}
                                    <div>
                                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Blade Cutting (PKR)</label>
                                        <input
                                            type="number"
                                            required
                                            min="0"
                                            value={form.cutting_cost}
                                            onChange={(e) => setForm({ ...form, cutting_cost: e.target.value })}
                                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                        />
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    {/* Polishing Cost */}
                                    <div>
                                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Abrasive Polish (PKR)</label>
                                        <input
                                            type="number"
                                            required
                                            min="0"
                                            value={form.polishing_cost}
                                            onChange={(e) => setForm({ ...form, polishing_cost: e.target.value })}
                                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                        />
                                    </div>

                                    {/* Packaging Cost */}
                                    <div>
                                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Packaging (PKR)</label>
                                        <input
                                            type="number"
                                            required
                                            min="0"
                                            value={form.packaging_cost}
                                            onChange={(e) => setForm({ ...form, packaging_cost: e.target.value })}
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

                                    {/* Storage warehouse */}
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
                                        <span>Live Material Cost:</span>
                                        <span className="text-gray-900">{formatPKR(liveMaterialCost)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>Live Processing Cost:</span>
                                        <span className="text-gray-900">{formatPKR(additionalCosts)}</span>
                                    </div>
                                    <div className="flex justify-between border-t border-gray-200 pt-2 text-gray-900 font-bold text-sm">
                                        <span>Total Production Cost:</span>
                                        <span className="text-amber-800">{formatPKR(liveTotalCost)}</span>
                                    </div>
                                    <div className="flex justify-between text-xs text-amber-800 pt-1 border-t border-dashed border-gray-200 font-semibold">
                                        <span>Unit Cost / Sq Ft Tiles:</span>
                                        <span>{formatPKR(liveCostPerSqft)}</span>
                                    </div>
                                </div>
                            </div>

                            <button
                                type="submit"
                                className="w-full mt-6 py-3 bg-gradient-to-r from-blue-600 to-cyan-500 hover:from-blue-700 hover:to-cyan-600 text-white rounded-xl font-bold tracking-wide shadow-[0_0_15px_rgba(6,182,212,0.3)] hover:shadow-[0_0_20px_rgba(6,182,212,0.5)] transition duration-300 transform hover:-translate-y-0.5"
                            >
                                Process Slab-To-Tile
                            </button>
                        </GlassCard>
                    </form>
                </div>
            </div>

            {/* Run History list */}
            <div>
                <h3 className="text-lg font-bold text-gray-900 mb-4">Slab-to-Tile Runs History</h3>
                <DataTable
                    columns={historyColumns}
                    data={slabToTileProductions}
                    keyExtractor={(row) => row.id}
                    emptyMessage="No slab processing runs logged yet. Configure cockpit to process."
                />
            </div>
        </AppLayout>
    );
}
