import React, { useState, useMemo } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { formatPKR, formatDate } from '../../format';
import {
    ClipboardCheck,
    Search,
    AlertTriangle,
    TrendingUp,
    TrendingDown,
    Minus,
    CheckCircle2,
    X,
    Filter,
    RotateCcw,
} from 'lucide-react';

interface Product {
    id: number;
    sku: string;
    name: string;
}

interface Warehouse {
    id: number;
    name: string;
    location: string;
}

interface Batch {
    id: number;
    product_id: number;
    warehouse_id: number;
    batch_reference: string | null;
    quantity_remaining: string;
    unit_cost: string;
    received_at: string;
    product: Product;
    warehouse: { id: number; name: string };
}

interface Adjustment {
    id: number;
    system_quantity: string;
    physical_quantity: string;
    difference: string;
    reason: string | null;
    adjustment_date: string;
    created_at: string;
    product: Product | null;
    inventory_batch: { id: number; batch_reference: string | null } | null;
    creator: { id: number; name: string } | null;
}

interface ReconciliationProps {
    products: Product[];
    warehouses: Warehouse[];
    batches: Batch[];
    recentAdjustments: Adjustment[];
    filters: {
        product_id: string | null;
        warehouse_id: string | null;
    };
}

export default function Reconciliation({
    products,
    warehouses,
    batches,
    recentAdjustments,
    filters,
}: ReconciliationProps) {
    const [selectedProductId, setSelectedProductId] = useState(filters.product_id || '');
    const [selectedWarehouseId, setSelectedWarehouseId] = useState(filters.warehouse_id || '');

    // Per-batch form state: physical quantity and reason
    const [batchForms, setBatchForms] = useState<Record<number, { physical: string; reason: string }>>({});

    // Confirmation modal state
    const [confirmModal, setConfirmModal] = useState<{
        batchId: number;
        batchRef: string;
        systemQty: number;
        physicalQty: number;
        difference: number;
        reason: string;
    } | null>(null);

    const [errors, setErrors] = useState<any>({});

    const handleFilter = () => {
        router.get('/inventory/reconciliation', {
            product_id: selectedProductId || undefined,
            warehouse_id: selectedWarehouseId || undefined,
        }, { preserveState: true, replace: true });
    };

    const handleClearFilters = () => {
        setSelectedProductId('');
        setSelectedWarehouseId('');
        router.get('/inventory/reconciliation', {}, { preserveState: false, replace: true });
    };

    const getBatchForm = (batchId: number) =>
        batchForms[batchId] || { physical: '', reason: '' };

    const updateBatchForm = (batchId: number, field: 'physical' | 'reason', value: string) => {
        setBatchForms((prev) => ({
            ...prev,
            [batchId]: { ...getBatchForm(batchId), [field]: value },
        }));
    };

    const getDifference = (batch: Batch): number | null => {
        const form = getBatchForm(batch.id);
        if (form.physical === '') return null;
        return parseFloat(form.physical) - parseFloat(batch.quantity_remaining);
    };

    const handleOpenConfirm = (batch: Batch) => {
        const form = getBatchForm(batch.id);
        const physicalQty = parseFloat(form.physical);
        const systemQty = parseFloat(batch.quantity_remaining);
        const difference = physicalQty - systemQty;

        setConfirmModal({
            batchId: batch.id,
            batchRef: batch.batch_reference || `Batch #${batch.id}`,
            systemQty,
            physicalQty,
            difference,
            reason: form.reason,
        });
    };

    const handleSubmitReconciliation = () => {
        if (!confirmModal) return;

        router.post('/inventory/reconciliation', {
            inventory_batch_id: confirmModal.batchId,
            physical_quantity: confirmModal.physicalQty,
            adjustment_date: new Date().toISOString().split('T')[0],
            reason: confirmModal.reason || null,
        }, {
            onSuccess: () => {
                setConfirmModal(null);
                // Clear that batch's form
                setBatchForms((prev) => {
                    const next = { ...prev };
                    delete next[confirmModal.batchId];
                    return next;
                });
                setErrors({});
            },
            onError: (err) => {
                setErrors(err);
                setConfirmModal(null);
            },
        });
    };

    // Summary stats
    const totalBatches = batches.length;
    const totalSystemQty = useMemo(
        () => batches.reduce((acc, b) => acc + parseFloat(b.quantity_remaining), 0),
        [batches]
    );
    const totalValuation = useMemo(
        () => batches.reduce((acc, b) => acc + parseFloat(b.quantity_remaining) * parseFloat(b.unit_cost), 0),
        [batches]
    );

    const fmt = formatPKR;

    return (
        <AppLayout title="Batch Reconciliation Console">
            {/* Error banner */}
            {errors.reconciliation && (
                <div className="mb-6 p-4 bg-red-500/10 border border-red-500/30 rounded-xl flex items-center gap-3 text-red-400 text-sm">
                    <AlertTriangle size={18} />
                    <span>{errors.reconciliation}</span>
                </div>
            )}

            {/* KPI Summary Bar */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div className="glass-panel p-5 rounded-2xl relative overflow-hidden group hover:border-[#06B6D4]/30 transition duration-300">
                    <span className="text-xs text-gray-500 uppercase font-mono font-semibold tracking-wider">Active Batches</span>
                    <p className="text-2xl font-bold text-gray-900 mt-1">{totalBatches}</p>
                    <div className="absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r from-blue-500 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300" />
                </div>
                <div className="glass-panel p-5 rounded-2xl relative overflow-hidden group hover:border-[#06B6D4]/30 transition duration-300">
                    <span className="text-xs text-gray-500 uppercase font-mono font-semibold tracking-wider">System Stock (Total)</span>
                    <p className="text-2xl font-bold text-gray-900 mt-1">{totalSystemQty.toFixed(4)}</p>
                    <div className="absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r from-cyan-500 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300" />
                </div>
                <div className="glass-panel p-5 rounded-2xl relative overflow-hidden group hover:border-[#10B981]/30 transition duration-300">
                    <span className="text-xs text-gray-500 uppercase font-mono font-semibold tracking-wider">Valuation (Filtered)</span>
                    <p className="text-2xl font-bold text-[#10B981] mt-1">{fmt(totalValuation)}</p>
                    <div className="absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r from-[#10B981] to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300" />
                </div>
            </div>

            {/* Filter Controls */}
            <div className="glass-panel rounded-2xl p-5 mb-8">
                <div className="flex items-center gap-2 mb-4">
                    <Filter size={16} className="text-gray-400" />
                    <h3 className="text-sm font-bold text-gray-900">Batch Filters</h3>
                </div>
                <div className="flex flex-col sm:flex-row gap-4 items-end">
                    <div className="flex-1">
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Product</label>
                        <select
                            value={selectedProductId}
                            onChange={(e) => setSelectedProductId(e.target.value)}
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                        >
                            <option value="">All Products</option>
                            {products.map((p) => (
                                <option key={p.id} value={p.id}>{p.sku} — {p.name}</option>
                            ))}
                        </select>
                    </div>
                    <div className="flex-1">
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Warehouse</label>
                        <select
                            value={selectedWarehouseId}
                            onChange={(e) => setSelectedWarehouseId(e.target.value)}
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                        >
                            <option value="">All Warehouses</option>
                            {warehouses.map((w) => (
                                <option key={w.id} value={w.id}>{w.name}</option>
                            ))}
                        </select>
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

            {/* Reconciliation Table */}
            <div className="glass-panel rounded-2xl p-6 mb-8">
                <div className="flex items-center gap-3 mb-6">
                    <ClipboardCheck size={20} className="text-amber-800" />
                    <h3 className="font-bold text-lg text-gray-900">Physical Count Entry</h3>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse">
                        <thead>
                            <tr className="border-b border-gray-200 text-gray-600 text-xs uppercase font-semibold font-mono">
                                <th className="pb-3">Batch Reference</th>
                                <th className="pb-3">Product</th>
                                <th className="pb-3">Warehouse</th>
                                <th className="pb-3 text-right">System Qty</th>
                                <th className="pb-3 text-right">Unit Cost</th>
                                <th className="pb-3" style={{ minWidth: 140 }}>Physical Qty</th>
                                <th className="pb-3 text-right">Difference</th>
                                <th className="pb-3" style={{ minWidth: 160 }}>Reason</th>
                                <th className="pb-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 text-sm">
                            {batches.length === 0 ? (
                                <tr>
                                    <td colSpan={9} className="py-8 text-center text-gray-600 font-mono">
                                        No active batches found for the current filters. Adjust your filters above.
                                    </td>
                                </tr>
                            ) : (
                                batches.map((batch) => {
                                    const form = getBatchForm(batch.id);
                                    const diff = getDifference(batch);
                                    const diffColor = diff === null
                                        ? 'text-gray-500'
                                        : diff > 0
                                            ? 'text-[#10B981]'
                                            : diff < 0
                                                ? 'text-red-400'
                                                : 'text-gray-400';
                                    const DiffIcon = diff === null
                                        ? Minus
                                        : diff > 0
                                            ? TrendingUp
                                            : diff < 0
                                                ? TrendingDown
                                                : CheckCircle2;
                                    const canSubmit = form.physical !== '' && diff !== null && Math.abs(diff) > 0.0001;

                                    return (
                                        <tr key={batch.id} className="hover:bg-white/[0.01] transition duration-150">
                                            <td className="py-4 font-mono font-medium text-gray-900">
                                                {batch.batch_reference || `#${batch.id}`}
                                            </td>
                                            <td className="py-4">
                                                <div>
                                                    <p className="text-gray-900 font-medium">{batch.product.name}</p>
                                                    <p className="text-[10px] text-gray-500 font-mono">{batch.product.sku}</p>
                                                </div>
                                            </td>
                                            <td className="py-4 text-gray-800">{batch.warehouse.name}</td>
                                            <td className="py-4 text-right font-mono font-bold text-gray-900">
                                                {parseFloat(batch.quantity_remaining).toFixed(4)}
                                            </td>
                                            <td className="py-4 text-right font-mono text-gray-700">
                                                {fmt(parseFloat(batch.unit_cost))}
                                            </td>
                                            <td className="py-4">
                                                <input
                                                    type="number"
                                                    step="0.0001"
                                                    min="0"
                                                    placeholder="Enter count…"
                                                    value={form.physical}
                                                    onChange={(e) => updateBatchForm(batch.id, 'physical', e.target.value)}
                                                    className="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                                />
                                            </td>
                                            <td className={`py-4 text-right font-mono font-bold ${diffColor}`}>
                                                <span className="inline-flex items-center gap-1">
                                                    <DiffIcon size={14} />
                                                    {diff !== null ? (diff >= 0 ? '+' : '') + diff.toFixed(4) : '—'}
                                                </span>
                                            </td>
                                            <td className="py-4">
                                                <input
                                                    type="text"
                                                    placeholder="Reason…"
                                                    value={form.reason}
                                                    onChange={(e) => updateBatchForm(batch.id, 'reason', e.target.value)}
                                                    className="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                                />
                                            </td>
                                            <td className="py-4 text-center">
                                                <button
                                                    disabled={!canSubmit}
                                                    onClick={() => handleOpenConfirm(batch)}
                                                    className={`px-4 py-2 rounded-lg text-xs font-semibold transition-all duration-300 ${
                                                        canSubmit
                                                            ? 'bg-[#2563EB] hover:bg-blue-700 text-white shadow-lg hover:shadow-blue-500/20 cursor-pointer'
                                                            : 'bg-gray-50 text-gray-600 cursor-not-allowed'
                                                    }`}
                                                >
                                                    Reconcile
                                                </button>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Recent Adjustments Log */}
            <div className="glass-panel rounded-2xl p-6">
                <div className="flex items-center gap-2 mb-6">
                    <ClipboardCheck size={18} className="text-gray-400" />
                    <h3 className="font-bold text-lg text-gray-900">Recent Reconciliation Log</h3>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse">
                        <thead>
                            <tr className="border-b border-gray-200 text-gray-600 text-xs uppercase font-semibold font-mono">
                                <th className="pb-3">Date</th>
                                <th className="pb-3">Batch</th>
                                <th className="pb-3">Product</th>
                                <th className="pb-3 text-right">System Qty</th>
                                <th className="pb-3 text-right">Physical Qty</th>
                                <th className="pb-3 text-right">Difference</th>
                                <th className="pb-3">Reason</th>
                                <th className="pb-3">By</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 text-xs font-mono">
                            {recentAdjustments.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="py-6 text-center text-gray-500">
                                        No reconciliation adjustments recorded yet.
                                    </td>
                                </tr>
                            ) : (
                                recentAdjustments.map((adj) => {
                                    const diff = parseFloat(adj.difference);
                                    return (
                                        <tr key={adj.id} className="hover:bg-white/[0.01] transition duration-150">
                                            <td className="py-3 text-gray-500">
                                                {formatDate(adj.adjustment_date)}
                                            </td>
                                            <td className="py-3 text-gray-900 font-medium">
                                                {adj.inventory_batch?.batch_reference || `#${adj.id}`}
                                            </td>
                                            <td className="py-3 text-gray-800">
                                                {adj.product?.sku || '—'}
                                            </td>
                                            <td className="py-3 text-right text-gray-700">
                                                {parseFloat(adj.system_quantity).toFixed(4)}
                                            </td>
                                            <td className="py-3 text-right text-gray-900">
                                                {parseFloat(adj.physical_quantity).toFixed(4)}
                                            </td>
                                            <td className={`py-3 text-right font-bold ${diff > 0 ? 'text-[#10B981]' : diff < 0 ? 'text-red-400' : 'text-gray-400'}`}>
                                                {diff >= 0 ? '+' : ''}{diff.toFixed(4)}
                                            </td>
                                            <td className="py-3 text-gray-700 truncate max-w-xs">
                                                {adj.reason || '—'}
                                            </td>
                                            <td className="py-3 text-gray-500">
                                                {adj.creator?.name || '—'}
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Confirmation Modal */}
            {confirmModal && (
                <div className="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="glass-panel w-full max-w-md rounded-2xl max-h-[90vh] overflow-y-auto shadow-2xl animate-in fade-in zoom-in-95 duration-200">
                        <div className="p-6 border-b border-gray-200 flex items-center justify-between">
                            <h3 className="font-bold text-lg text-gray-900">Confirm Reconciliation</h3>
                            <button
                                onClick={() => setConfirmModal(null)}
                                className="text-gray-500 hover:text-gray-900 transition"
                            >
                                <X size={20} />
                            </button>
                        </div>

                        <div className="p-6 space-y-5">
                            <div className="p-4 rounded-xl bg-white/60 border border-gray-200 space-y-3">
                                <div className="flex justify-between text-sm">
                                    <span className="text-gray-700">Batch</span>
                                    <span className="text-gray-900 font-mono font-bold">{confirmModal.batchRef}</span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-gray-700">System Quantity</span>
                                    <span className="text-gray-900 font-mono">{confirmModal.systemQty.toFixed(4)}</span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-gray-700">Physical Quantity</span>
                                    <span className="text-gray-900 font-mono">{confirmModal.physicalQty.toFixed(4)}</span>
                                </div>
                                <div className="border-t border-gray-200 pt-3 flex justify-between text-sm">
                                    <span className="text-gray-700">Variance</span>
                                    <span className={`font-mono font-bold ${confirmModal.difference > 0 ? 'text-[#10B981]' : 'text-red-400'}`}>
                                        {confirmModal.difference >= 0 ? '+' : ''}{confirmModal.difference.toFixed(4)}
                                    </span>
                                </div>
                            </div>

                            <div className={`p-3 rounded-xl flex items-start gap-3 text-xs ${
                                confirmModal.difference > 0
                                    ? 'bg-[#10B981]/5 border border-[#10B981]/15 text-[#10B981]'
                                    : 'bg-red-500/5 border border-red-500/15 text-red-400'
                            }`}>
                                <AlertTriangle size={16} className="shrink-0 mt-0.5" />
                                <p>
                                    {confirmModal.difference > 0
                                        ? 'This will record a stock SURPLUS. A journal entry will debit the Inventory Asset and credit the Inventory Gain account.'
                                        : 'This will record a stock SHRINKAGE. A journal entry will debit the Inventory Shrinkage Expense and credit the Inventory Asset account.'}
                                </p>
                            </div>

                            {confirmModal.reason && (
                                <div className="text-xs text-gray-700">
                                    <span className="font-semibold text-gray-800">Reason:</span> {confirmModal.reason}
                                </div>
                            )}

                            <div className="border-t border-gray-200 pt-4 flex gap-4 justify-end">
                                <button
                                    onClick={() => setConfirmModal(null)}
                                    className="px-4 py-2 rounded-xl text-gray-500 hover:text-gray-900 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    onClick={handleSubmitReconciliation}
                                    className="px-5 py-2.5 bg-[#2563EB] hover:bg-blue-700 text-white rounded-xl font-medium shadow-lg hover:shadow-blue-500/20 transition-all duration-300"
                                >
                                    Post Adjustment
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
