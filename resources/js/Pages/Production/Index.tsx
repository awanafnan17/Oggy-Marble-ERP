import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { formatPKR, formatDate } from '../../format';
import { ProductionTabs } from './Schedules';
import {
    Wrench,
    Plus,
    Trash2,
    Settings,
    Play,
    CheckCircle,
    DollarSign,
    AlertCircle
} from 'lucide-react';

interface BomItem {
    id: number;
    product_id: number;
    product_sku: string;
    product_name: string;
    product_size?: string;
    quantity: number;
    unit_cost: number;
    total_cost: number;
}

interface Bom {
    id: number;
    name: string;
    product_id: number;
    product_sku: string;
    product_name: string;
    product_size?: string;
    labor_cost_estimate: number;
    overhead_cost_estimate: number;
    raw_material_cost: number;
    total_estimated_cost: number;
    items: BomItem[];
}

interface ProductionOrder {
    id: number;
    bom: {
        id: number;
        product_sku: string;
        product_name: string;
        product?: {
            size?: string;
        };
    };
    status: 'pending' | 'completed';
    quantity: number;
    actual_material_cost: number;
    actual_labor_cost: number;
    actual_overhead_cost: number;
    completed_date: string;
    created_at: string;
}

interface Product {
    id: number;
    sku: string;
    name: string;
    cost: number;
    size?: string;
    unit?: string;
}

interface ProductionProps {
    boms: Bom[];
    productionOrders: ProductionOrder[];
    rawMaterials: Product[];
}

export default function Production({ boms, productionOrders, rawMaterials }: ProductionProps) {
    const [showBomModal, setShowBomModal] = useState(false);
    const [showOrderModal, setShowOrderModal] = useState(false);
    const [showCompleteModal, setShowCompleteModal] = useState(false);
    const [activeOrderId, setActiveOrderId] = useState<number | null>(null);

    // BOM form state
    const [bomForm, setBomForm] = useState({
        product_id: '',
        name: '',
        labor_cost_estimate: '10.00',
        overhead_cost_estimate: '5.00',
        items: [
            { product_id: '', quantity: '1' }
        ]
    });

    // Order form state
    const [orderForm, setOrderForm] = useState({
        bom_id: '',
        quantity: '1',
    });

    // Complete order form state
    const [completeForm, setCompleteForm] = useState({
        warehouse_id: '1',
        actual_labor_cost: '',
        actual_overhead_cost: '',
    });

    const [errors, setErrors] = useState<any>({});

    const handleAddBomItemRow = () => {
        setBomForm({
            ...bomForm,
            items: [...bomForm.items, { product_id: '', quantity: '1' }]
        });
    };

    const handleRemoveBomItemRow = (index: number) => {
        const updated = [...bomForm.items];
        updated.splice(index, 1);
        setBomForm({ ...bomForm, items: updated });
    };

    const handleBomItemChange = (index: number, field: string, value: string) => {
        const updated = [...bomForm.items];
        updated[index] = {
            ...updated[index],
            [field]: value
        };
        setBomForm({ ...bomForm, items: updated });
    };

    const handleCreateBom = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/production/bom', bomForm, {
            onSuccess: () => {
                setShowBomModal(false);
                setBomForm({
                    product_id: '',
                    name: '',
                    labor_cost_estimate: '10.00',
                    overhead_cost_estimate: '5.00',
                    items: [{ product_id: '', quantity: '1' }]
                });
                setErrors({});
            },
            onError: (err) => setErrors(err),
        });
    };

    const handleCreateOrder = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/production/order', orderForm, {
            onSuccess: () => {
                setShowOrderModal(false);
                setOrderForm({ bom_id: '', quantity: '1' });
                setErrors({});
            },
            onError: (err) => setErrors(err),
        });
    };

    const handleOpenCompleteModal = (orderId: number, order: ProductionOrder) => {
        setActiveOrderId(orderId);
        // Find parent BOM to prepopulate estimates based on quantity
        const matchingBom = boms.find(b => b.product_name === order.bom.product_name);
        setCompleteForm({
            warehouse_id: '1',
            actual_labor_cost: matchingBom ? (matchingBom.labor_cost_estimate * order.quantity).toString() : '0',
            actual_overhead_cost: matchingBom ? (matchingBom.overhead_cost_estimate * order.quantity).toString() : '0',
        });
        setShowCompleteModal(true);
    };

    const handleCompleteOrder = (e: React.FormEvent) => {
        e.preventDefault();
        if (!activeOrderId) return;
        router.post(`/production/order/${activeOrderId}/complete`, completeForm, {
            onSuccess: () => {
                setShowCompleteModal(false);
                setActiveOrderId(null);
                setErrors({});
            },
            onError: (err) => setErrors(err),
        });
    };

    return (
        <AppLayout title="Manufacturing & Production Costing">
            <ProductionTabs activeTab="boms" />
            {/* Top row actions */}
            <div className="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center mb-8">
                <div className="flex gap-4">
                    <button
                        onClick={() => setShowBomModal(true)}
                        className="flex items-center gap-2 px-5 py-3 bg-[#B45309] hover:bg-[#92400E] text-white rounded-xl font-semibold shadow-xs transition-all duration-200"
                    >
                        <Plus size={16} />
                        Configure BOM
                    </button>
                    <button
                        onClick={() => setShowOrderModal(true)}
                        className="flex items-center gap-2 px-5 py-3 border border-gray-300 hover:border-blue-500/50 bg-gray-50 hover:bg-blue-500/5 text-white rounded-xl font-medium transition-all duration-300"
                    >
                        <Play size={16} className="text-amber-700" />
                        Run Production Job
                    </button>
                </div>

                <div className="flex gap-6 text-sm font-mono text-gray-700">
                    <div>
                        Active BOMs: <span className="text-gray-900 font-bold">{boms.length}</span>
                    </div>
                    <div>
                        Pending Jobs: <span className="text-amber-800 font-bold">
                            {productionOrders.filter(o => o.status === 'pending').length}
                        </span>
                    </div>
                </div>
            </div>

            {/* BOMs & Job orders sections split layout */}
            <div className="space-y-8">
                {/* Production Jobs Register */}
                <div className="glass-panel rounded-2xl p-6">
                    <div className="flex items-center gap-2 mb-6">
                        <Play size={18} className="text-amber-700 animate-pulse" />
                        <h3 className="font-bold text-lg text-gray-900">Work Order Jobs Registry</h3>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="border-b border-gray-200 text-gray-600 text-xs uppercase font-semibold font-mono">
                                    <th className="pb-3">Job ID</th>
                                    <th className="pb-3">Product Name</th>
                                    <th className="pb-3">Size</th>
                                    <th className="pb-3 text-right">Target Qty</th>
                                    <th className="pb-3">Status</th>
                                    <th className="pb-3 text-right">Actual Material Cost</th>
                                    <th className="pb-3 text-right">Actual Labor & Overhead</th>
                                    <th className="pb-3 text-right">Calculated Unit Cost</th>
                                    <th className="pb-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 text-sm">
                                {productionOrders.length === 0 ? (
                                    <tr>
                                        <td colSpan={9} className="py-6 text-center text-gray-600 font-mono">
                                            No production jobs scheduled. Click "Run Production Job" to start.
                                        </td>
                                    </tr>
                                ) : (
                                    productionOrders.map((order) => {
                                        const sumCosts = order.actual_material_cost + order.actual_labor_cost + order.actual_overhead_cost;
                                        const calculatedUnitCost = order.quantity > 0 ? sumCosts / order.quantity : 0;

                                        return (
                                            <tr key={order.id} className="hover:bg-gray-50 transition duration-150">
                                                <td className="py-4 font-mono font-medium text-gray-900">#JOB-{order.id.toString().padStart(4, '0')}</td>
                                                <td className="py-4">
                                                    <div>
                                                        <p className="font-semibold text-gray-900">{order.bom.product_name}</p>
                                                        <p className="text-xs text-gray-500 font-mono">{order.bom.product_sku}</p>
                                                    </div>
                                                </td>
                                                <td className="py-4 text-gray-700 font-mono">
                                                    {order.bom.product?.size || '-'}
                                                </td>
                                                <td className="py-4 text-right font-mono font-bold text-gray-900">{order.quantity.toFixed(0)} pcs</td>
                                                <td className="py-4">
                                                    <span className={`
                                                        inline-flex items-center px-2 py-0.5 rounded text-[10px] uppercase font-mono font-semibold
                                                        ${order.status === 'completed' ? 'bg-[#10B981]/15 text-[#10B981]' : 'bg-amber-400/15 text-amber-800'}
                                                    `}>
                                                        {order.status}
                                                    </span>
                                                </td>
                                                <td className="py-4 text-right font-mono">
                                                    {order.status === 'completed'
                                                        ? formatPKR(order.actual_material_cost)
                                                        : <span className="text-gray-500">TBD</span>
                                                    }
                                                </td>
                                                <td className="py-4 text-right font-mono">
                                                    {order.status === 'completed'
                                                        ? formatPKR(order.actual_labor_cost + order.actual_overhead_cost)
                                                        : <span className="text-gray-500">TBD</span>
                                                    }
                                                </td>
                                                <td className="py-4 text-right font-mono font-bold text-amber-800">
                                                    {order.status === 'completed'
                                                        ? formatPKR(calculatedUnitCost)
                                                        : <span className="text-gray-500">TBD</span>
                                                    }
                                                </td>
                                                <td className="py-4 text-right">
                                                    {order.status === 'pending' ? (
                                                        <button
                                                            onClick={() => handleOpenCompleteModal(order.id, order)}
                                                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#10B981] hover:bg-emerald-600 text-white text-xs font-semibold transition shadow-md hover:shadow-emerald-500/15"
                                                        >
                                                            <CheckCircle size={12} />
                                                            Complete Job
                                                        </button>
                                                    ) : (
                                                        <span className="text-xs text-gray-500 font-mono">Fulfillment {order.completed_date}</span>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Bill of Materials Configurations */}
                <div className="glass-panel rounded-2xl p-6">
                    <div className="flex items-center gap-2 mb-6">
                        <Settings size={18} className="text-gray-400" />
                        <h3 className="font-bold text-lg text-gray-900">Bill of Materials Formulas</h3>
                    </div>

                    <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
                        {boms.length === 0 ? (
                            <div className="col-span-2 py-12 text-center text-gray-600 font-mono">
                                No formulas defined. Click "Configure BOM" to build your first recipe structure.
                            </div>
                        ) : (
                            boms.map((bom) => (
                                <div key={bom.id} className="p-5 rounded-2xl bg-gray-50 border border-gray-200 space-y-4">
                                    <div className="flex justify-between items-start border-b border-gray-200 pb-3">
                                        <div>
                                            <h4 className="font-bold text-gray-900 text-base">{bom.name}</h4>
                                            <p className="text-xs text-gray-700 mt-0.5">Output finished good: <span className="font-semibold text-gray-800 font-mono">{bom.product_sku} {bom.product_size ? `(${bom.product_size})` : ''}</span></p>
                                        </div>
                                        <div className="text-right">
                                            <span className="text-[10px] text-gray-700 uppercase font-mono tracking-wider">Est. Unit Cost</span>
                                            <p className="text-base font-bold text-amber-800 font-mono">
                                                {formatPKR(bom.total_estimated_cost)}
                                            </p>
                                        </div>
                                    </div>

                                    {/* BOM Ingredients Items */}
                                    <div className="space-y-2">
                                        <span className="text-[10px] text-gray-500 uppercase font-mono tracking-wider">Required Material Components</span>
                                        <div className="space-y-1.5 font-mono text-xs text-gray-800 max-h-40 overflow-y-auto pr-1">
                                            {bom.items.map((item) => (
                                                <div key={item.id} className="flex justify-between items-center py-1.5 px-3.5 rounded bg-gray-50 border border-gray-200">
                                                    <span>{item.product_name} ({item.product_sku}) {item.product_size ? `[Size: ${item.product_size}]` : ''}</span>
                                                    <span className="font-semibold">{item.quantity} units</span>
                                                </div>
                                            ))}
                                        </div>
                                    </div>

                                    {/* Costing Breakdowns */}
                                    <div className="grid grid-cols-3 gap-2 text-center pt-2 text-[10px] font-mono text-gray-500">
                                        <div className="bg-gray-50 p-2 rounded border border-gray-200">
                                            <span>Est. Materials</span>
                                            <p className="font-semibold text-gray-900 mt-0.5">{formatPKR(bom.raw_material_cost)}</p>
                                        </div>
                                        <div className="bg-gray-50 p-2 rounded border border-gray-200">
                                            <span>Est. Labor</span>
                                            <p className="font-semibold text-gray-900 mt-0.5">{formatPKR(bom.labor_cost_estimate)}</p>
                                        </div>
                                        <div className="bg-gray-50 p-2 rounded border border-gray-200">
                                            <span>Est. Overhead</span>
                                            <p className="font-semibold text-gray-900 mt-0.5">{formatPKR(bom.overhead_cost_estimate)}</p>
                                        </div>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            </div>

            {/* Modals */}
            {/* Configure BOM Modal */}
            {showBomModal && (
                <div className="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="glass-panel w-full max-w-2xl rounded-2xl overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-200">
                        <div className="p-6 border-b border-gray-200 flex items-center justify-between">
                            <h3 className="font-bold text-lg text-gray-900">Create Bill of Materials (BOM) Formula</h3>
                            <button onClick={() => setShowBomModal(false)} className="text-gray-500 hover:text-gray-900 transition">
                                <Plus size={20} className="rotate-45" />
                            </button>
                        </div>
                        <form onSubmit={handleCreateBom} className="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Target Finished Good</label>
                                    <select
                                        required
                                        value={bomForm.product_id}
                                        onChange={(e) => setBomForm({ ...bomForm, product_id: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    >
                                        <option value="">Select product to produce...</option>
                                        {rawMaterials.filter(p => !p.sku.startsWith('RM')).map(p => (
                                            <option key={p.id} value={p.id}>{p.sku} - {p.name} {p.size ? `(${p.size})` : ''}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Formula Code/Name</label>
                                    <input
                                        type="text"
                                        required
                                        placeholder="e.g. Standard 60x60 Polish"
                                        value={bomForm.name}
                                        onChange={(e) => setBomForm({ ...bomForm, name: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Est. Labor Unit Cost (Rs.)</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        required
                                        value={bomForm.labor_cost_estimate}
                                        onChange={(e) => setBomForm({ ...bomForm, labor_cost_estimate: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Est. Factory Overhead (Rs.)</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        required
                                        value={bomForm.overhead_cost_estimate}
                                        onChange={(e) => setBomForm({ ...bomForm, overhead_cost_estimate: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                    />
                                </div>
                            </div>

                            {/* Dynamic items entries */}
                            <div className="space-y-3 pt-2">
                                <div className="flex justify-between items-center">
                                    <label className="block text-xs font-semibold uppercase text-gray-700">Required Ingredients</label>
                                    <button
                                        type="button"
                                        onClick={handleAddBomItemRow}
                                        className="text-xs text-amber-800 hover:text-cyan-300 font-medium font-mono flex items-center gap-1"
                                    >
                                        <Plus size={12} /> Add Component Row
                                    </button>
                                </div>

                                <div className="space-y-3">
                                    {bomForm.items.map((row, index) => (
                                        <div key={index} className="flex gap-3 items-end">
                                            <div className="flex-1">
                                                <label className="block text-[10px] uppercase text-gray-500 mb-1">Raw Material</label>
                                                <select
                                                    required
                                                    value={row.product_id}
                                                    onChange={(e) => handleBomItemChange(index, 'product_id', e.target.value)}
                                                    className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                                >
                                                    <option value="">Select ingredient...</option>
                                                     {rawMaterials.map(p => (
                                                         <option key={p.id} value={p.id}>{p.sku} - {p.name} {p.size ? `(${p.size})` : ''} ({formatPKR(p.cost ?? 0)}/{p.unit || 'pcs'})</option>
                                                     ))}
                                                </select>
                                            </div>
                                            <div className="w-32">
                                                <label className="block text-[10px] uppercase text-gray-500 mb-1">Required Qty</label>
                                                <input
                                                    type="number"
                                                    required
                                                    step="0.0001"
                                                    min="0.0001"
                                                    value={row.quantity}
                                                    onChange={(e) => handleBomItemChange(index, 'quantity', e.target.value)}
                                                    className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                                />
                                            </div>
                                            {bomForm.items.length > 1 && (
                                                <button
                                                    type="button"
                                                    onClick={() => handleRemoveBomItemRow(index)}
                                                    className="p-2 text-red-400 hover:text-red-300 hover:bg-gray-50 rounded-lg border border-transparent hover:border-gray-200 transition mb-0.5"
                                                >
                                                    <Trash2 size={16} />
                                                </button>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="border-t border-gray-200 pt-4 flex gap-4 justify-end">
                                <button
                                    type="button"
                                    onClick={() => setShowBomModal(false)}
                                    className="px-4 py-2 rounded-xl text-gray-500 hover:text-gray-900 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="px-5 py-2.5 bg-[#2563EB] hover:bg-blue-700 text-white rounded-xl font-medium transition"
                                >
                                    Save Formula
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Run Production Job Modal */}
            {showOrderModal && (
                <div className="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="glass-panel w-full max-w-md rounded-2xl max-h-[90vh] overflow-y-auto shadow-2xl animate-in fade-in zoom-in-95 duration-200">
                        <div className="p-6 border-b border-gray-200 flex items-center justify-between">
                            <h3 className="font-bold text-lg text-gray-900">Create Production Job Order</h3>
                            <button onClick={() => setShowOrderModal(false)} className="text-gray-500 hover:text-gray-900 transition">
                                <Plus size={20} className="rotate-45" />
                            </button>
                        </div>
                        <form onSubmit={handleCreateOrder} className="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                            <div>
                                <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Select BOM formula</label>
                                <select
                                    required
                                    value={orderForm.bom_id}
                                    onChange={(e) => setOrderForm({ ...orderForm, bom_id: e.target.value })}
                                    className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                >
                                    <option value="">Choose recipe formula...</option>
                                    {boms.map(b => (
                                        <option key={b.id} value={b.id}>{b.name} ({b.product_sku})</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Target Quantity to Produce</label>
                                <input
                                    type="number"
                                    required
                                    min="1"
                                    value={orderForm.quantity}
                                    onChange={(e) => setOrderForm({ ...orderForm, quantity: e.target.value })}
                                    className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                />
                            </div>

                            <div className="border-t border-gray-200 pt-4 flex gap-4 justify-end">
                                <button
                                    type="button"
                                    onClick={() => setShowOrderModal(false)}
                                    className="px-4 py-2 rounded-xl text-gray-500 hover:text-gray-900 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="px-5 py-2.5 bg-[#2563EB] hover:bg-blue-700 text-white rounded-xl font-medium transition"
                                >
                                    Launch Job
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Complete Job Modal */}
            {showCompleteModal && (
                <div className="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="glass-panel w-full max-w-md rounded-2xl max-h-[90vh] overflow-y-auto shadow-2xl animate-in fade-in zoom-in-95 duration-200">
                        <div className="p-6 border-b border-gray-200 flex items-center justify-between">
                            <h3 className="font-bold text-lg text-gray-900">Record Production Completion</h3>
                            <button onClick={() => setShowCompleteModal(false)} className="text-gray-500 hover:text-gray-900 transition">
                                <Plus size={20} className="rotate-45" />
                            </button>
                        </div>
                        <form onSubmit={handleCompleteOrder} className="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                            <div>
                                <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Inventory Storage Warehouse</label>
                                <select
                                    required
                                    value={completeForm.warehouse_id}
                                    onChange={(e) => setCompleteForm({ ...completeForm, warehouse_id: e.target.value })}
                                    className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                >
                                    <option value="1">Main Showroom</option>
                                    <option value="2">Raw Materials Depot</option>
                                </select>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Actual Direct Labor (Rs.)</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        required
                                        value={completeForm.actual_labor_cost}
                                        onChange={(e) => setCompleteForm({ ...completeForm, actual_labor_cost: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Actual Direct Overhead (Rs.)</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        required
                                        value={completeForm.actual_overhead_cost}
                                        onChange={(e) => setCompleteForm({ ...completeForm, actual_overhead_cost: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                    />
                                </div>
                            </div>

                            <div className="p-3 bg-[#10B981]/5 border border-[#10B981]/15 rounded-xl flex gap-3 text-xs text-[#10B981]">
                                <AlertCircle size={16} className="shrink-0" />
                                <p>Saving this logs raw materials out of inventory, records finished goods into stock at calculated average actual unit cost, and posts double-entry ledger items.</p>
                            </div>

                            <div className="border-t border-gray-200 pt-4 flex gap-4 justify-end">
                                <button
                                    type="button"
                                    onClick={() => setShowCompleteModal(false)}
                                    className="px-4 py-2 rounded-xl text-gray-500 hover:text-gray-900 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="px-5 py-2.5 bg-[#10B981] hover:bg-emerald-600 text-white rounded-xl font-medium transition"
                                >
                                    Finalize Completion
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
