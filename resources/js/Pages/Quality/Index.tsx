import React, { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { formatDate } from '../../format';
import GlassCard from '../../components/GlassCard';
import DataTable, { Column } from '../../components/DataTable';
import StatusBadge from '../../components/StatusBadge';
import {
    ShieldCheck,
    Plus,
    Trash2,
    FileText,
    CheckCircle,
    AlertCircle,
    User,
    Calendar,
    Settings,
    Edit2
} from 'lucide-react';

interface Product {
    id: number;
    sku: string;
    name: string;
}

interface InventoryBatch {
    id: number;
    product_id: number;
    product: Product;
    quantity_remaining: number;
    quality_grade?: string;
}

interface ProductionSchedule {
    id: number;
    schedule_number: string;
    machine: string;
    production_type: string;
}

interface QualityInspection {
    id: number;
    qc_number: string;
    inspection_date: string;
    product_id: number;
    product: Product;
    inventory_batch_id: number | null;
    inventory_batch?: InventoryBatch;
    production_schedule_id: number | null;
    production_schedule?: ProductionSchedule;
    inspection_type: 'production_qc' | 'pre_dispatch_qc' | 'return_qc' | 'stock_qc';
    grade: 'A' | 'B' | 'C' | 'reject';
    defect_type: string | null;
    checked_quantity: number;
    rejected_quantity: number;
    accepted_quantity: number;
    inspector_name: string;
    remarks: string | null;
    status: 'draft' | 'passed' | 'failed' | 'partially_passed';
    created_at: string;
}

interface Props {
    inspections: QualityInspection[];
    products: Product[];
    batches: InventoryBatch[];
    schedules: ProductionSchedule[];
    filters: {
        start_date?: string;
        end_date?: string;
        inspection_type?: string;
        grade?: string;
        status?: string;
    };
}

export default function Index({ inspections, products, batches, schedules, filters }: Props) {
    const [showFormModal, setShowFormModal] = useState(false);
    const [editingInspection, setEditingInspection] = useState<QualityInspection | null>(null);
    const [errors, setErrors] = useState<any>({});

    // Search/filter local state
    const [filterState, setFilterState] = useState({
        start_date: filters.start_date || '',
        end_date: filters.end_date || '',
        inspection_type: filters.inspection_type || '',
        grade: filters.grade || '',
        status: filters.status || '',
    });

    // Form state
    const [form, setForm] = useState({
        inspection_date: new Date().toISOString().slice(0, 10),
        product_id: '',
        inventory_batch_id: '',
        production_schedule_id: '',
        inspection_type: 'production_qc',
        grade: 'A',
        defect_type: '',
        checked_quantity: '',
        rejected_quantity: '0',
        accepted_quantity: '',
        inspector_name: '',
        remarks: '',
        status: 'draft',
    });

    // Handlers for search filters
    const handleFilterChange = (e: React.ChangeEvent<HTMLSelectElement | HTMLInputElement>) => {
        const { name, value } = e.target;
        setFilterState(prev => ({ ...prev, [name]: value }));
    };

    const applyFilters = () => {
        router.get('/quality-inspections', filterState, { preserveState: true });
    };

    const resetFilters = () => {
        const empty = { start_date: '', end_date: '', inspection_type: '', grade: '', status: '' };
        setFilterState(empty);
        router.get('/quality-inspections', empty);
    };

    // Form modal helpers
    const openCreateModal = () => {
        setEditingInspection(null);
        setForm({
            inspection_date: new Date().toISOString().slice(0, 10),
            product_id: '',
            inventory_batch_id: '',
            production_schedule_id: '',
            inspection_type: 'production_qc',
            grade: 'A',
            defect_type: '',
            checked_quantity: '',
            rejected_quantity: '0',
            accepted_quantity: '',
            inspector_name: '',
            remarks: '',
            status: 'draft',
        });
        setErrors({});
        setShowFormModal(true);
    };

    const openEditModal = (inspection: QualityInspection) => {
        if (inspection.status !== 'draft') {
            alert('Only draft quality control logs can be edited.');
            return;
        }
        setEditingInspection(inspection);
        setForm({
            inspection_date: new Date(inspection.inspection_date).toISOString().slice(0, 10),
            product_id: inspection.product_id.toString(),
            inventory_batch_id: inspection.inventory_batch_id ? inspection.inventory_batch_id.toString() : '',
            production_schedule_id: inspection.production_schedule_id ? inspection.production_schedule_id.toString() : '',
            inspection_type: inspection.inspection_type,
            grade: inspection.grade,
            defect_type: inspection.defect_type || '',
            checked_quantity: inspection.checked_quantity.toString(),
            rejected_quantity: inspection.rejected_quantity.toString(),
            accepted_quantity: inspection.accepted_quantity.toString(),
            inspector_name: inspection.inspector_name,
            remarks: inspection.remarks || '',
            status: inspection.status,
        });
        setErrors({});
        setShowFormModal(true);
    };

    // Client-side validation
    const checked = parseFloat(form.checked_quantity) || 0;
    const rejected = parseFloat(form.rejected_quantity) || 0;
    const accepted = parseFloat(form.accepted_quantity) || 0;

    const validationErrors: string[] = [];
    if (checked <= 0 && form.checked_quantity !== '') {
        validationErrors.push('Checked quantity must be greater than 0.');
    }
    if (rejected > checked) {
        validationErrors.push('Rejected quantity cannot exceed checked quantity.');
    }
    if ((accepted + rejected) > checked) {
        validationErrors.push('Sum of accepted and rejected quantities cannot exceed checked quantity.');
    }

    const handleFormSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (validationErrors.length > 0) return;

        const actionUrl = editingInspection
            ? `/quality-inspections/${editingInspection.id}`
            : '/quality-inspections';

        const method = editingInspection ? 'put' : 'post';

        router[method](actionUrl, form, {
            onSuccess: () => {
                setShowFormModal(false);
                setErrors({});
            },
            onError: (err) => setErrors(err),
        });
    };

    const deleteInspection = (id: number) => {
        if (!confirm('Are you sure you want to delete this draft QC log?')) return;
        router.delete(`/quality-inspections/${id}`, {
            onError: (err) => {
                if (err.status) alert(err.status);
            }
        });
    };

    const columns: Column<QualityInspection>[] = [
        { header: 'QC Number', accessor: 'qc_number', className: 'text-white font-mono font-medium' },
        { header: 'Date', accessor: (row) => formatDate(row.inspection_date) },
        { header: 'Product', accessor: (row) => row.product.name },
        {
            header: 'Type',
            accessor: (row) => (
                <span className="capitalize text-xs font-mono">
                    {row.inspection_type.replace(/_/g, ' ')}
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
        { header: 'Checked Qty', accessor: (row) => `${parseFloat(row.checked_quantity.toString()).toFixed(1)}` },
        { header: 'Accepted Qty', accessor: (row) => `${parseFloat(row.accepted_quantity.toString()).toFixed(1)}`, className: 'text-emerald-800 font-semibold' },
        {
            header: 'Rejected Qty',
            accessor: (row) => (
                <span className={row.rejected_quantity > 0 ? 'text-red-400 font-semibold' : 'text-gray-500'}>
                    {parseFloat(row.rejected_quantity.toString()).toFixed(1)}
                </span>
            )
        },
        {
            header: 'Defect',
            accessor: (row) => row.defect_type ? (
                <span className="text-[10px] capitalize font-mono text-amber-800 bg-amber-500/5 px-1.5 py-0.5 rounded">
                    {row.defect_type.replace(/_/g, ' ')}
                </span>
            ) : 'None'
        },
        { header: 'Status', accessor: (row) => <StatusBadge status={row.status} /> },
        {
            header: 'Actions',
            accessor: (row) => (
                <div className="flex gap-2 justify-end">
                    {row.status === 'draft' ? (
                        <>
                            <button
                                onClick={() => openEditModal(row)}
                                className="p-1 bg-gray-50 hover:bg-gray-500 text-white border border-gray-300 rounded transition"
                                title="Edit Log"
                            >
                                <Edit2 size={12} />
                            </button>
                            <button
                                onClick={() => deleteInspection(row.id)}
                                className="p-1 bg-red-600/10 hover:bg-red-600/20 text-red-400 border border-red-500/10 rounded transition"
                                title="Delete Log"
                            >
                                <Trash2 size={12} />
                            </button>
                        </>
                    ) : (
                        <span className="text-[10px] text-gray-500 font-mono italic">Finalized</span>
                    )}
                </div>
            ),
            align: 'right'
        }
    ];

    return (
        <AppLayout title="Quality Inspection Logs (QC)">
            <div className="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center mb-8">
                <div>
                    <h2 className="text-lg font-bold text-gray-900">Quality Control Registry</h2>
                    <p className="text-xs text-gray-700">Record stone blocks, slabs sizing quality grading and pre-dispatch audits.</p>
                </div>
                <button
                    onClick={openCreateModal}
                    className="flex items-center gap-2 px-5 py-3 bg-[#B45309] hover:bg-[#92400E] text-white rounded-xl font-semibold shadow-xs transition-all duration-200"
                >
                    <Plus size={16} />
                    New Quality Log
                </button>
            </div>

            {/* Date and Type Filters */}
            <div className="glass-panel p-5 rounded-2xl mb-8 space-y-4">
                <h3 className="text-xs font-semibold text-gray-700 uppercase tracking-wider font-mono">Filters</h3>
                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4">
                    <div>
                        <label className="block text-[10px] text-gray-700 mb-1">Start Date</label>
                        <input
                            type="date"
                            name="start_date"
                            value={filterState.start_date}
                            onChange={handleFilterChange}
                            className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                        />
                    </div>
                    <div>
                        <label className="block text-[10px] text-gray-700 mb-1">End Date</label>
                        <input
                            type="date"
                            name="end_date"
                            value={filterState.end_date}
                            onChange={handleFilterChange}
                            className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                        />
                    </div>
                    <div>
                        <label className="block text-[10px] text-gray-700 mb-1">Inspection Type</label>
                        <select
                            name="inspection_type"
                            value={filterState.inspection_type}
                            onChange={handleFilterChange}
                            className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                        >
                            <option value="">All Types</option>
                            <option value="production_qc">Production QC</option>
                            <option value="pre_dispatch_qc">Pre-Dispatch QC</option>
                            <option value="return_qc">Return QC</option>
                            <option value="stock_qc">Stock QC</option>
                        </select>
                    </div>
                    <div>
                        <label className="block text-[10px] text-gray-700 mb-1">Grade</label>
                        <select
                            name="grade"
                            value={filterState.grade}
                            onChange={handleFilterChange}
                            className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                        >
                            <option value="">All Grades</option>
                            <option value="A">Grade A</option>
                            <option value="B">Grade B</option>
                            <option value="C">Grade C</option>
                            <option value="reject">Reject</option>
                        </select>
                    </div>
                    <div>
                        <label className="block text-[10px] text-gray-700 mb-1">Status</label>
                        <select
                            name="status"
                            value={filterState.status}
                            onChange={handleFilterChange}
                            className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                        >
                            <option value="">All Statuses</option>
                            <option value="draft">Draft</option>
                            <option value="passed">Passed</option>
                            <option value="failed">Failed</option>
                            <option value="partially_passed">Partially Passed</option>
                        </select>
                    </div>
                </div>
                <div className="flex gap-2 justify-end pt-2 border-t border-gray-200">
                    <button
                        onClick={resetFilters}
                        className="px-4 py-2 border border-gray-300 rounded-xl text-xs text-gray-500 hover:text-gray-900 hover:bg-gray-50 transition"
                    >
                        Reset
                    </button>
                    <button
                        onClick={applyFilters}
                        className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold transition"
                    >
                        Apply Filters
                    </button>
                </div>
            </div>

            {/* Table panel */}
            <div className="glass-panel rounded-2xl p-6">
                <div className="overflow-x-auto">
                    <DataTable
                        columns={columns}
                        data={inspections}
                        keyExtractor={(row) => row.id}
                        emptyMessage="No quality inspections logs recorded yet matching these filters."
                    />
                </div>
            </div>

            {/* QC Form Modal */}
            {showFormModal && (
                <div className="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="glass-panel w-full max-w-lg rounded-2xl overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-200">
                        <div className="p-6 border-b border-gray-200 flex items-center justify-between">
                            <h3 className="font-bold text-lg text-gray-900">
                                {editingInspection ? 'Edit Quality Inspection Log' : 'New Quality Control Log'}
                            </h3>
                            <button onClick={() => setShowFormModal(false)} className="text-gray-500 hover:text-gray-900 transition">
                                <Plus size={20} className="rotate-45" />
                            </button>
                        </div>
                        <form onSubmit={handleFormSubmit} className="p-6 space-y-4 max-h-[80vh] overflow-y-auto">

                            {validationErrors.length > 0 && (
                                <div className="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-xs space-y-1">
                                    {validationErrors.map((err, idx) => (
                                        <p key={idx} className="flex items-center gap-1.5 font-semibold">
                                            <AlertCircle size={12} />
                                            {err}
                                        </p>
                                    ))}
                                </div>
                            )}

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Inspection Date</label>
                                    <input
                                        type="date"
                                        required
                                        value={form.inspection_date}
                                        onChange={(e) => setForm({ ...form, inspection_date: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                    />
                                    {errors.inspection_date && <p className="text-xs text-red-400 mt-1">{errors.inspection_date}</p>}
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">QC Type</label>
                                    <select
                                        required
                                        value={form.inspection_type}
                                        onChange={(e) => setForm({ ...form, inspection_type: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    >
                                        <option value="production_qc">Production QC</option>
                                        <option value="pre_dispatch_qc">Pre-Dispatch QC</option>
                                        <option value="return_qc">Return QC</option>
                                        <option value="stock_qc">Stock QC</option>
                                    </select>
                                    {errors.inspection_type && <p className="text-xs text-red-400 mt-1">{errors.inspection_type}</p>}
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Product</label>
                                <select
                                    required
                                    value={form.product_id}
                                    onChange={(e) => setForm({ ...form, product_id: e.target.value, inventory_batch_id: '' })}
                                    className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                >
                                    <option value="">Select product...</option>
                                    {products.map(p => (
                                        <option key={p.id} value={p.id}>{p.sku} - {p.name}</option>
                                    ))}
                                </select>
                                {errors.product_id && <p className="text-xs text-red-400 mt-1">{errors.product_id}</p>}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Inventory Batch (Optional)</label>
                                    <select
                                        value={form.inventory_batch_id}
                                        onChange={(e) => setForm({ ...form, inventory_batch_id: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    >
                                        <option value="">Choose batch...</option>
                                        {batches.filter(b => b.product_id === Number(form.product_id)).map(b => (
                                            <option key={b.id} value={b.id}>
                                                Batch #{b.id} - Remaining {parseFloat(b.quantity_remaining.toString()).toFixed(1)}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Production Schedule (Optional)</label>
                                    <select
                                        value={form.production_schedule_id}
                                        onChange={(e) => setForm({ ...form, production_schedule_id: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    >
                                        <option value="">Choose schedule...</option>
                                        {schedules.map(s => (
                                            <option key={s.id} value={s.id}>
                                                {s.schedule_number} ({s.machine})
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className="grid grid-cols-3 gap-3">
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Checked Qty</label>
                                    <input
                                        type="number"
                                        required
                                        step="0.01"
                                        min="0.01"
                                        value={form.checked_quantity}
                                        onChange={(e) => setForm({ ...form, checked_quantity: e.target.value })}
                                        placeholder="0.00"
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                    />
                                    {errors.checked_quantity && <p className="text-xs text-red-400 mt-1">{errors.checked_quantity}</p>}
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Rejected Qty</label>
                                    <input
                                        type="number"
                                        required
                                        step="0.01"
                                        min="0"
                                        value={form.rejected_quantity}
                                        onChange={(e) => setForm({ ...form, rejected_quantity: e.target.value })}
                                        placeholder="0.00"
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                    />
                                    {errors.rejected_quantity && <p className="text-xs text-red-400 mt-1">{errors.rejected_quantity}</p>}
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Accepted Qty</label>
                                    <input
                                        type="number"
                                        required
                                        step="0.01"
                                        min="0"
                                        value={form.accepted_quantity}
                                        onChange={(e) => setForm({ ...form, accepted_quantity: e.target.value })}
                                        placeholder="0.00"
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                    />
                                    {errors.accepted_quantity && <p className="text-xs text-red-400 mt-1">{errors.accepted_quantity}</p>}
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Grade</label>
                                    <select
                                        required
                                        value={form.grade}
                                        onChange={(e) => setForm({ ...form, grade: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    >
                                        <option value="A">Grade A</option>
                                        <option value="B">Grade B</option>
                                        <option value="C">Grade C</option>
                                        <option value="reject">Reject</option>
                                    </select>
                                    {errors.grade && <p className="text-xs text-red-400 mt-1">{errors.grade}</p>}
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Defect Type</label>
                                    <select
                                        value={form.defect_type}
                                        onChange={(e) => setForm({ ...form, defect_type: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    >
                                        <option value="">No Defect</option>
                                        <option value="cracks">Cracks</option>
                                        <option value="size_variation">Size Variation</option>
                                        <option value="color_variation">Color Variation</option>
                                        <option value="polish_issue">Polish Issue</option>
                                        <option value="thickness_issue">Thickness Issue</option>
                                        <option value="breakage">Breakage</option>
                                        <option value="edge_damage">Edge Damage</option>
                                        <option value="other">Other</option>
                                    </select>
                                    {errors.defect_type && <p className="text-xs text-red-400 mt-1">{errors.defect_type}</p>}
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Inspector Name</label>
                                    <input
                                        type="text"
                                        required
                                        value={form.inspector_name}
                                        onChange={(e) => setForm({ ...form, inspector_name: e.target.value })}
                                        placeholder="Inspector name"
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    />
                                    {errors.inspector_name && <p className="text-xs text-red-400 mt-1">{errors.inspector_name}</p>}
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Save Status</label>
                                    <select
                                        required
                                        value={form.status}
                                        onChange={(e) => setForm({ ...form, status: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    >
                                        <option value="draft">Draft (Can edit later)</option>
                                        <option value="passed">Passed (Final)</option>
                                        <option value="failed">Failed (Final)</option>
                                        <option value="partially_passed">Partially Passed (Final)</option>
                                    </select>
                                    {errors.status && <p className="text-xs text-red-400 mt-1">{errors.status}</p>}
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Remarks</label>
                                <textarea
                                    value={form.remarks}
                                    onChange={(e) => setForm({ ...form, remarks: e.target.value })}
                                    rows={2}
                                    placeholder="Defect details or adjustments made..."
                                    className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                ></textarea>
                            </div>

                            <button
                                type="submit"
                                disabled={validationErrors.length > 0}
                                className={`w-full mt-4 py-3 text-white rounded-xl font-bold tracking-wide transition duration-300 ${
                                    validationErrors.length > 0
                                        ? 'bg-white shadow-sm cursor-not-allowed text-gray-400'
                                        : 'bg-gradient-to-r from-blue-600 to-cyan-500 hover:from-blue-700 hover:to-cyan-600 shadow-[0_0_15px_rgba(6,182,212,0.3)] hover:shadow-[0_0_20px_rgba(6,182,212,0.5)]'
                                }`}
                            >
                                {editingInspection ? 'Update Quality Log' : 'Register Quality Inspection'}
                            </button>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
