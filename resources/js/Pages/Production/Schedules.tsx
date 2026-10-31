import React, { useState } from 'react';
import { router, Link, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { formatPKR, formatDate } from '../../format';
import GlassCard from '../../components/GlassCard';
import DataTable, { Column } from '../../components/DataTable';
import StatusBadge from '../../components/StatusBadge';
import {
    Calendar,
    Plus,
    Trash2,
    Clock,
    TrendingUp,
    Scale,
    Layers,
    User,
    Play,
    CheckCircle,
    XCircle,
    ArrowRightLeft,
    AlertCircle,
    ArrowRight
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
}

interface ProductionSchedule {
    id: number;
    schedule_number: string;
    production_order_reference: string | null;
    production_type: 'stone_to_slab' | 'slab_to_tile';
    machine: string;
    planned_start_date: string;
    planned_end_date: string;
    priority: 'low' | 'normal' | 'high' | 'urgent';
    planned_input_product_id: number;
    planned_input_product?: Product;
    planned_input_batch_id: number | null;
    planned_input_batch?: InventoryBatch;
    planned_input_quantity: number;
    expected_output_quantity: number;
    expected_wastage_quantity: number;
    expected_wastage_percentage: number;
    assigned_operator: string | null;
    status: 'planned' | 'queued' | 'in_progress' | 'completed' | 'cancelled';
    remarks: string | null;
    created_at: string;
    stone_to_slab_productions?: any[];
    slab_to_tile_productions?: any[];
}

interface Machine {
    id: number;
    name: string;
    machine_code: string;
    status: string;
}

interface SchedulesProps {
    schedules: ProductionSchedule[];
    products: Product[];
    batches: InventoryBatch[];
    machines: Machine[];
    yieldStandards?: {
        stone_to_slab_output_percentage: number;
        stone_to_slab_wastage_percentage: number;
        slab_to_tile_sqft_per_metric_ton: number;
        slab_to_tile_yield_percentage: number;
        slab_to_tile_wastage_percentage: number;
    };
}

export function ProductionTabs({ activeTab }: { activeTab: string }) {
    const tabs = [
        { id: 'boms', label: 'BOMs & Job Orders', href: '/production' },
        { id: 'schedules', label: 'Production Schedules', href: '/production/schedules' },
        { id: 'machines', label: 'Machine Register', href: '/production/machines' },
        { id: 'stone_to_slab', label: 'Stone-to-Slab Runs', href: '/production/stone-to-slab' },
        { id: 'slab_to_tile', label: 'Slab-to-Tile Runs', href: '/production/slab-to-tile' },
        { id: 'variance', label: 'Production Variance', href: '/production/variance' },
    ];

    return (
        <div className="flex overflow-x-auto pb-2 mb-6 border-b border-gray-200 no-scrollbar scroll-smooth">
            <div className="flex space-x-1 shrink-0">
                {tabs.map((tab) => (
                    <Link
                        key={tab.id}
                        href={tab.href}
                        className={`px-4 py-2.5 rounded-xl text-xs font-semibold tracking-wide border transition-all duration-300 ${
                            activeTab === tab.id ? 'bg-[#FFFBEB] text-[#92400E] border-[#FCD34D] font-bold shadow-xs' : 'bg-white text-[#4B5563] border-gray-200 hover:bg-[#F9FAFB] font-medium'
                        }`}
                    >
                        {tab.label}
                    </Link>
                ))}
            </div>
        </div>
    );
}

export default function Schedules({ schedules, products, batches, machines, yieldStandards }: SchedulesProps) {
    const [showFormModal, setShowFormModal] = useState(false);
    const [errors, setErrors] = useState<any>({});
    const [editingSchedule, setEditingSchedule] = useState<ProductionSchedule | null>(null);

    // Form state
    const [form, setForm] = useState({
        production_order_reference: '',
        production_type: 'stone_to_slab',
        machine: machines[0]?.name || '',
        planned_start_date: '',
        planned_end_date: '',
        priority: 'normal',
        planned_input_product_id: '',
        planned_input_batch_id: '',
        planned_input_quantity: '',
        expected_output_quantity: '',
        expected_wastage_quantity: '0',
        expected_wastage_percentage: '0',
        assigned_operator: '',
        remarks: '',
    });

    // Helper to open create modal
    const openCreateModal = () => {
        setEditingSchedule(null);
        setForm({
            production_order_reference: '',
            production_type: 'stone_to_slab',
            machine: machines[0]?.name || '',
            planned_start_date: '',
            planned_end_date: '',
            priority: 'normal',
            planned_input_product_id: '',
            planned_input_batch_id: '',
            planned_input_quantity: '',
            expected_output_quantity: '',
            expected_wastage_quantity: '0',
            expected_wastage_percentage: '0',
            assigned_operator: '',
            remarks: '',
        });
        setErrors({});
        setShowFormModal(true);
    };

    // Helper to open edit modal
    const openEditModal = (schedule: ProductionSchedule) => {
        if (['completed', 'cancelled'].includes(schedule.status)) {
            alert('Cannot edit a schedule that is completed or cancelled.');
            return;
        }
        setEditingSchedule(schedule);
        setForm({
            production_order_reference: schedule.production_order_reference || '',
            production_type: schedule.production_type,
            machine: schedule.machine,
            planned_start_date: new Date(schedule.planned_start_date).toISOString().slice(0, 16),
            planned_end_date: new Date(schedule.planned_end_date).toISOString().slice(0, 16),
            priority: schedule.priority,
            planned_input_product_id: schedule.planned_input_product_id.toString(),
            planned_input_batch_id: schedule.planned_input_batch_id ? schedule.planned_input_batch_id.toString() : '',
            planned_input_quantity: schedule.planned_input_quantity.toString(),
            expected_output_quantity: schedule.expected_output_quantity.toString(),
            expected_wastage_quantity: schedule.expected_wastage_quantity.toString(),
            expected_wastage_percentage: schedule.expected_wastage_percentage.toString(),
            assigned_operator: schedule.assigned_operator || '',
            remarks: schedule.remarks || '',
        });
        setErrors({});
        setShowFormModal(true);
    };

    const handleFormSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const actionUrl = editingSchedule
            ? `/production/schedules/${editingSchedule.id}`
            : '/production/schedules';

        const method = editingSchedule ? 'put' : 'post';

        router[method](actionUrl, form, {
            onSuccess: () => {
                setShowFormModal(false);
                setErrors({});
            },
            onError: (err) => setErrors(err),
        });
    };

    const updateStatus = (id: number, newStatus: string) => {
        if (!confirm(`Are you sure you want to transition this schedule to '${newStatus}'?`)) return;
        router.patch(`/production/schedules/${id}/status`, { status: newStatus }, {
            onError: (err) => {
                if (err.status) alert(err.status);
            }
        });
    };

    // Real-time Yield Estimation Logic for UI display
    const plannedInputQty = parseFloat(form.planned_input_quantity) || 0;

    // Estimates calculations
    let calculatedEstOutput = 0;
    let calculatedEstWastageQty = 0;
    let calculatedEstWastagePct = 0;

    const yieldSettings = yieldStandards || {
        stone_to_slab_output_percentage: 70,
        stone_to_slab_wastage_percentage: 30,
        slab_to_tile_sqft_per_metric_ton: 180,
        slab_to_tile_yield_percentage: 90,
        slab_to_tile_wastage_percentage: 10,
    };

    if (form.production_type === 'stone_to_slab') {
        calculatedEstOutput = plannedInputQty * (yieldSettings.stone_to_slab_output_percentage / 100);
        calculatedEstWastageQty = plannedInputQty * (yieldSettings.stone_to_slab_wastage_percentage / 100);
        calculatedEstWastagePct = yieldSettings.stone_to_slab_wastage_percentage;
    } else {
        calculatedEstOutput = plannedInputQty * yieldSettings.slab_to_tile_sqft_per_metric_ton * (yieldSettings.slab_to_tile_yield_percentage / 100);
        calculatedEstWastageQty = plannedInputQty * yieldSettings.slab_to_tile_sqft_per_metric_ton * (yieldSettings.slab_to_tile_wastage_percentage / 100);
        calculatedEstWastagePct = yieldSettings.slab_to_tile_wastage_percentage;
    }

    const applyEstimations = () => {
        setForm(prev => ({
            ...prev,
            expected_output_quantity: calculatedEstOutput.toFixed(2),
            expected_wastage_quantity: calculatedEstWastageQty.toFixed(2),
            expected_wastage_percentage: calculatedEstWastagePct.toString()
        }));
    };

    // Priority sorting order helper
    const getPriorityWeight = (priority: string) => {
        switch (priority) {
            case 'urgent': return 4;
            case 'high': return 3;
            case 'normal': return 2;
            case 'low': return 1;
            default: return 0;
        }
    };

    // Filter schedules by status groupings for Machine Queue View
    const activeSchedules = schedules.filter(s => ['planned', 'queued', 'in_progress'].includes(s.status));
    const completedOrCancelledSchedules = schedules.filter(s => ['completed', 'cancelled'].includes(s.status));

    // Group active schedules by machine
    const schedulesByMachine: Record<string, ProductionSchedule[]> = {};
    machines.forEach(m => {
        schedulesByMachine[m.name] = activeSchedules
            .filter(s => s.machine === m.name)
            .sort((a, b) => {
                // First sort by start date
                const dateA = new Date(a.planned_start_date).getTime();
                const dateB = new Date(b.planned_start_date).getTime();
                if (dateA !== dateB) return dateA - dateB;
                // Then sort by priority weight descending
                return getPriorityWeight(b.priority) - getPriorityWeight(a.priority);
            });
    });

    const activeListColumns: Column<ProductionSchedule>[] = [
        { header: 'Reference', accessor: 'schedule_number', className: 'text-white font-mono font-medium' },
        {
            header: 'Type',
            accessor: (row) => (
                <span className={`inline-flex items-center px-2 py-0.5 rounded text-[10px] uppercase font-mono font-semibold ${
                    row.production_type === 'stone_to_slab' ? 'bg-[#06B6D4]/15 text-amber-800' : 'bg-indigo-400/15 text-indigo-400'
                }`}>
                    {row.production_type === 'stone_to_slab' ? 'Stone -> Slab' : 'Slab -> Tile'}
                </span>
            )
        },
        {
            header: 'Product',
            accessor: (row) => (
                <div>
                    <p className="font-semibold text-gray-900">{row.planned_input_product?.name || 'Unknown'}</p>
                    <p className="text-[10px] text-gray-500 font-mono">{row.planned_input_product?.sku || 'Unknown'}</p>
                </div>
            )
        },
        {
            header: 'Size',
            accessor: (row) => row.planned_input_product?.size || '-',
            className: 'font-mono'
        },
        { header: 'Machine', accessor: 'machine' },
        { header: 'Planned Start', accessor: (row) => new Date(row.planned_start_date).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' }) },
        {
            header: 'Priority',
            accessor: (row) => (
                <span className={`font-semibold text-xs capitalize ${
                    row.priority === 'urgent' ? 'text-red-400' :
                    row.priority === 'high' ? 'text-amber-800' :
                    row.priority === 'normal' ? 'text-amber-700' : 'text-gray-400'
                }`}>
                    {row.priority}
                </span>
            )
        },
        { header: 'Input Qty', accessor: (row) => `${parseFloat(row.planned_input_quantity.toString()).toFixed(2)}` },
        { header: 'Est. Output', accessor: (row) => `${parseFloat(row.expected_output_quantity.toString()).toFixed(2)}` },
        {
            header: 'Status',
            accessor: (row) => <StatusBadge status={row.status} />
        },
        {
            header: 'Actions',
            accessor: (row) => (
                <div className="flex gap-2 justify-end">
                    {row.status === 'planned' && (
                        <>
                            <button onClick={() => updateStatus(row.id, 'queued')} className="px-2 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-[10px] font-semibold transition">Queue</button>
                            <button onClick={() => updateStatus(row.id, 'cancelled')} className="px-2 py-1 bg-red-600/20 hover:bg-red-600/30 text-red-400 rounded text-[10px] font-semibold transition">Cancel</button>
                            <button onClick={() => openEditModal(row)} className="px-2 py-1 bg-gray-50 hover:bg-gray-500 text-white border border-gray-300 rounded text-[10px] font-semibold transition">Edit</button>
                        </>
                    )}
                    {row.status === 'queued' && (
                        <>
                            <button onClick={() => updateStatus(row.id, 'in_progress')} className="px-2 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded text-[10px] font-semibold transition">Start</button>
                            <button onClick={() => updateStatus(row.id, 'cancelled')} className="px-2 py-1 bg-red-600/20 hover:bg-red-600/30 text-red-400 rounded text-[10px] font-semibold transition">Cancel</button>
                            <button onClick={() => openEditModal(row)} className="px-2 py-1 bg-gray-50 hover:bg-gray-500 text-white border border-gray-300 rounded text-[10px] font-semibold transition">Edit</button>
                        </>
                    )}
                    {row.status === 'in_progress' && (
                        <div className="text-[10px] text-gray-500 font-mono italic">
                            Execute on run panel to complete
                        </div>
                    )}
                </div>
            ),
            align: 'right'
        }
    ];

    return (
        <AppLayout title="Production Scheduling & Machine Queue">
            {/* Common subnavigation */}
            <ProductionTabs activeTab="schedules" />

            <div className="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center mb-8">
                <div>
                    <h2 className="text-lg font-bold text-gray-900">Factory Production Planning</h2>
                    <p className="text-xs text-gray-700">Schedule machine slicing workloads, optimize yields, and dispatch queues.</p>
                </div>
                <button
                    onClick={openCreateModal}
                    className="flex items-center gap-2 px-5 py-3 bg-[#B45309] hover:bg-[#92400E] text-white rounded-xl font-semibold shadow-xs transition-all duration-200"
                >
                    <Plus size={16} />
                    Schedule Job Workload
                </button>
            </div>

            {/* Layout Split: Left: Machine-wise Queue, Right: Active schedules datatable */}
            <div className="space-y-8">
                {/* Machine Queue View */}
                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                    {machines.map((machine) => {
                        const machineJobs = schedulesByMachine[machine.name] || [];
                        return (
                            <GlassCard key={machine.id} hoverGlow glowColor="blue" className="p-5 flex flex-col justify-between space-y-4">
                                <div>
                                    <div className="flex justify-between items-center border-b border-gray-200 pb-2.5">
                                        <h3 className="font-bold text-sm text-gray-900 font-mono uppercase tracking-wide">{machine.name}</h3>
                                        <span className="text-[10px] font-bold font-mono px-2 py-0.5 rounded bg-blue-500/10 text-amber-800">
                                            {machineJobs.length} Jobs
                                        </span>
                                    </div>
                                    <div className="mt-3 space-y-2.5 max-h-72 overflow-y-auto pr-1">
                                        {machineJobs.length === 0 ? (
                                            <p className="text-xs text-gray-500 italic py-4 text-center font-mono">No active workload queued.</p>
                                        ) : (
                                            machineJobs.map((job, idx) => (
                                                <div key={job.id} className="p-3 rounded-lg bg-gray-50 border border-gray-200 hover:border-gray-300 transition space-y-2">
                                                    <div className="flex justify-between items-start">
                                                        <div>
                                                            <span className="text-[10px] text-gray-700 font-mono font-medium">{job.schedule_number}</span>
                                                            <h4 className="text-xs font-semibold text-gray-900 mt-0.5">
                                                                {job.planned_input_product?.name || 'Unknown Product'} {job.planned_input_product?.size ? `(${job.planned_input_product.size})` : ''}
                                                            </h4>
                                                        </div>
                                                        <span className={`text-[9px] uppercase font-mono px-1.5 py-0.5 rounded font-bold ${
                                                            job.priority === 'urgent' ? 'bg-red-500/10 text-red-400 border border-red-500/20' :
                                                            job.priority === 'high' ? 'bg-amber-500/10 text-amber-800' : 'bg-gray-50 text-gray-400'
                                                        }`}>
                                                            {job.priority}
                                                        </span>
                                                    </div>
                                                    <div className="flex justify-between items-center text-[10px] text-gray-700 font-mono pt-1 border-t border-gray-200">
                                                        <div className="flex items-center gap-1">
                                                            <Clock size={10} className="text-amber-800" />
                                                            <span>{new Date(job.planned_start_date).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                                                        </div>
                                                        <div>
                                                            <span>Qty: {parseFloat(job.planned_input_quantity.toString()).toFixed(1)}</span>
                                                            <span className="text-amber-800 ml-1">→ {parseFloat(job.expected_output_quantity.toString()).toFixed(0)}</span>
                                                        </div>
                                                    </div>
                                                    <div className="flex justify-between items-center pt-2">
                                                        <span className={`text-[9px] uppercase font-mono px-1.5 py-0.5 rounded font-semibold ${
                                                            job.status === 'in_progress' ? 'bg-amber-500/20 text-amber-800' :
                                                            job.status === 'queued' ? 'bg-blue-500/20 text-amber-800' : 'bg-gray-50 text-gray-400'
                                                        }`}>
                                                            {job.status}
                                                        </span>
                                                        <div className="flex gap-1.5">
                                                            {job.status === 'planned' && (
                                                                <button onClick={() => updateStatus(job.id, 'queued')} className="px-1.5 py-0.5 bg-blue-600 hover:bg-blue-700 text-white rounded text-[9px] font-semibold transition">Queue</button>
                                                            )}
                                                            {job.status === 'queued' && (
                                                                <button onClick={() => updateStatus(job.id, 'in_progress')} className="px-1.5 py-0.5 bg-amber-600 hover:bg-amber-700 text-white rounded text-[9px] font-semibold transition">Start</button>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            ))
                                        )}
                                    </div>
                                </div>
                            </GlassCard>
                        );
                    })}
                </div>

                {/* All Active Schedules registry */}
                <div className="glass-panel rounded-2xl p-6">
                    <h3 className="font-bold text-base text-gray-900 mb-4 flex items-center gap-2">
                        <Calendar size={16} className="text-amber-800" />
                        Active Production Schedules Registry
                    </h3>
                    <div className="overflow-x-auto">
                        <DataTable
                            columns={activeListColumns}
                            data={activeSchedules}
                            keyExtractor={(row) => row.id}
                            emptyMessage="No schedules defined. Click 'Schedule Job Workload' to begin."
                        />
                    </div>
                </div>

                {/* Estimation vs Actual Audit Log comparison panel */}
                <div className="glass-panel rounded-2xl p-6">
                    <h3 className="font-bold text-base text-gray-900 mb-4 flex items-center gap-2">
                        <ArrowRightLeft size={16} className="text-amber-800" />
                        Yield & Waste Comparison Audit (Est. vs Actual)
                    </h3>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr className="border-b border-gray-200 text-gray-400 font-semibold font-mono uppercase">
                                    <th className="pb-3 pl-2">Schedule</th>
                                    <th className="pb-3">Type</th>
                                    <th className="pb-3 text-right">Est. Output</th>
                                    <th className="pb-3 text-right">Actual Output</th>
                                    <th className="pb-3 text-right">Est. Wastage</th>
                                    <th className="pb-3 text-right">Actual Wastage</th>
                                    <th className="pb-3 text-right">Output Var.</th>
                                    <th className="pb-3 text-right">Wastage Var.</th>
                                    <th className="pb-3">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 text-gray-800 font-mono">
                                {completedOrCancelledSchedules.length === 0 ? (
                                    <tr>
                                        <td colSpan={9} className="py-6 text-center text-gray-500 italic">
                                            No completed production schedules to audit. Compare comparisons will print here.
                                        </td>
                                    </tr>
                                ) : (
                                    completedOrCancelledSchedules.map((schedule) => {
                                        // Find associated completed productions to get actual outputs
                                        let actualOutput = 0;
                                        let actualWastage = 0;
                                        let isCompleted = schedule.status === 'completed';

                                        if (isCompleted) {
                                            if (schedule.production_type === 'stone_to_slab') {
                                                const stoneRuns = stoneToSlabProductionsList(schedule.id);
                                                stoneRuns.forEach(r => {
                                                    actualOutput += parseFloat(r.slabs_produced);
                                                    actualWastage += parseFloat(r.wastage_quantity);
                                                });
                                            } else {
                                                const tileRuns = slabToTileProductionsList(schedule.id);
                                                tileRuns.forEach(r => {
                                                    actualOutput += parseFloat(r.tiles_produced);
                                                    actualWastage += parseFloat(r.wastage_quantity);
                                                });
                                            }
                                        }

                                        const estOutput = parseFloat(schedule.expected_output_quantity.toString());
                                        const estWastage = parseFloat(schedule.expected_wastage_quantity.toString());

                                        const outputVar = isCompleted ? (actualOutput - estOutput) : 0;
                                        const wastageVar = isCompleted ? (actualWastage - estWastage) : 0;

                                        return (
                                            <tr key={schedule.id} className="hover:bg-gray-50">
                                                <td className="py-3 pl-2 font-semibold text-gray-900">{schedule.schedule_number}</td>
                                                <td className="py-3 capitalize text-gray-700">{schedule.production_type.replace(/_/g, ' ')}</td>
                                                <td className="py-3 text-right">{estOutput.toFixed(1)}</td>
                                                <td className="py-3 text-right text-gray-900 font-bold">{isCompleted ? actualOutput.toFixed(1) : '—'}</td>
                                                <td className="py-3 text-right">{estWastage.toFixed(1)}</td>
                                                <td className="py-3 text-right text-gray-900 font-bold">{isCompleted ? actualWastage.toFixed(1) : '—'}</td>
                                                <td className={`py-3 text-right font-bold ${outputVar >= 0 ? 'text-emerald-800' : 'text-red-400'}`}>
                                                    {isCompleted ? (outputVar >= 0 ? `+${outputVar.toFixed(1)}` : outputVar.toFixed(1)) : '—'}
                                                </td>
                                                <td className={`py-3 text-right font-bold ${wastageVar <= 0 ? 'text-emerald-800' : 'text-red-400'}`}>
                                                    {isCompleted ? (wastageVar >= 0 ? `+${wastageVar.toFixed(1)}` : wastageVar.toFixed(1)) : '—'}
                                                </td>
                                                <td className="py-3">
                                                    <span className={`px-1.5 py-0.5 rounded text-[10px] uppercase font-semibold ${
                                                        schedule.status === 'completed' ? 'bg-emerald-500/10 text-emerald-800' : 'bg-red-500/10 text-red-400'
                                                    }`}>
                                                        {schedule.status}
                                                    </span>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Schedules Form Modal */}
            {showFormModal && (
                <div className="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="glass-panel w-full max-w-lg rounded-2xl overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-200">
                        <div className="p-6 border-b border-gray-200 flex items-center justify-between">
                            <h3 className="font-bold text-lg text-gray-900">
                                {editingSchedule ? 'Edit Production Schedule' : 'Schedule New Job Workload'}
                            </h3>
                            <button onClick={() => setShowFormModal(false)} className="text-gray-500 hover:text-gray-900 transition">
                                <Plus size={20} className="rotate-45" />
                            </button>
                        </div>
                        <form onSubmit={handleFormSubmit} className="p-6 space-y-4 max-h-[80vh] overflow-y-auto">

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Production Type</label>
                                    <select
                                        required
                                        value={form.production_type}
                                        onChange={(e) => setForm({ ...form, production_type: e.target.value, planned_input_product_id: '', planned_input_batch_id: '' })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    >
                                        <option value="stone_to_slab">Stone to Slab</option>
                                        <option value="slab_to_tile">Slab to Tile</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Machine Line</label>
                                    <select
                                        required
                                        value={form.machine}
                                        onChange={(e) => setForm({ ...form, machine: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    >
                                        {machines.map(m => (
                                            <option key={m.id} value={m.name}>{m.name}</option>
                                        ))}
                                    </select>
                                    {errors.machine && <p className="text-xs text-red-400 mt-1">{errors.machine}</p>}
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Planned Start</label>
                                    <input
                                        type="datetime-local"
                                        required
                                        value={form.planned_start_date}
                                        onChange={(e) => setForm({ ...form, planned_start_date: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                    />
                                    {errors.planned_start_date && <p className="text-xs text-red-400 mt-1">{errors.planned_start_date}</p>}
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Planned End</label>
                                    <input
                                        type="datetime-local"
                                        required
                                        value={form.planned_end_date}
                                        onChange={(e) => setForm({ ...form, planned_end_date: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                    />
                                    {errors.planned_end_date && <p className="text-xs text-red-400 mt-1">{errors.planned_end_date}</p>}
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Work Order Ref (Optional)</label>
                                    <input
                                        type="text"
                                        value={form.production_order_reference}
                                        onChange={(e) => setForm({ ...form, production_order_reference: e.target.value })}
                                        placeholder="e.g. WO-5678"
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Priority</label>
                                    <select
                                        required
                                        value={form.priority}
                                        onChange={(e) => setForm({ ...form, priority: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    >
                                        <option value="low">Low</option>
                                        <option value="normal">Normal</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Input Material Product</label>
                                    <select
                                        required
                                        value={form.planned_input_product_id}
                                        onChange={(e) => setForm({ ...form, planned_input_product_id: e.target.value, planned_input_batch_id: '' })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    >
                                        <option value="">Select product...</option>
                                        {products.filter(p => {
                                            if (form.production_type === 'stone_to_slab') {
                                                return p.sku.startsWith('RM-BLOCK') || p.sku.startsWith('RM');
                                            } else {
                                                return p.sku.startsWith('SF-SLAB') || p.sku.startsWith('RM-SLAB') || p.sku.startsWith('RM');
                                            }
                                        }).map(p => (
                                            <option key={p.id} value={p.id}>{p.sku} - {p.name} {p.size ? `(${p.size})` : ''}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Specific Stock Batch (Optional)</label>
                                    <select
                                        value={form.planned_input_batch_id}
                                        onChange={(e) => setForm({ ...form, planned_input_batch_id: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    >
                                        <option value="">Choose batch if reserved...</option>
                                        {batches.filter(b => b.product_id === Number(form.planned_input_product_id)).map(b => (
                                            <option key={b.id} value={b.id}>
                                                Batch #{b.id} - Remaining {parseFloat(b.quantity_remaining.toString()).toFixed(1)}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            {/* Yield Estimation Panel */}
                            <div className="p-4 rounded-2xl bg-gradient-to-tr from-blue-950/30 to-cyan-950/20 border border-[#06B6D4]/25 space-y-3">
                                <div className="flex justify-between items-center border-b border-[#06B6D4]/10 pb-1.5">
                                    <h4 className="text-xs font-bold text-gray-900 flex items-center gap-1.5">
                                        <TrendingUp size={14} className="text-amber-800" />
                                        Yield Estimation Calculator
                                    </h4>
                                    <button
                                        type="button"
                                        onClick={applyEstimations}
                                        className="text-[10px] font-mono text-amber-800 hover:text-cyan-300 font-bold"
                                        disabled={plannedInputQty <= 0}
                                    >
                                        Apply Estimates
                                    </button>
                                </div>

                                <div className="grid grid-cols-3 gap-3">
                                    <div>
                                        <label className="block text-[10px] uppercase text-gray-500 mb-0.5">Input weight (MT)</label>
                                        <input
                                            type="number"
                                            required
                                            min="0.01"
                                            step="0.01"
                                            value={form.planned_input_quantity}
                                            onChange={(e) => setForm({ ...form, planned_input_quantity: e.target.value })}
                                            placeholder="0.00"
                                            className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none font-mono"
                                        />
                                    </div>
                                    <div className="bg-gray-50 p-2 rounded-xl border border-gray-200 text-center font-mono">
                                        <span className="text-[9px] text-gray-500 uppercase">Estimated Output</span>
                                        <p className="text-xs font-bold text-gray-900 mt-0.5">{calculatedEstOutput.toFixed(1)} {form.production_type === 'stone_to_slab' ? 'MT' : 'Sq Ft'}</p>
                                    </div>
                                    <div className="bg-gray-50 p-2 rounded-xl border border-gray-200 text-center font-mono">
                                        <span className="text-[9px] text-gray-500 uppercase">Est. Wastage</span>
                                        <p className="text-xs font-bold text-red-400 mt-0.5">{calculatedEstWastageQty.toFixed(1)} {form.production_type === 'stone_to_slab' ? 'MT' : 'Sq Ft'}</p>
                                    </div>
                                </div>
                            </div>

                            <div className="grid grid-cols-3 gap-3">
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Expected Output</label>
                                    <input
                                        type="number"
                                        required
                                        step="0.01"
                                        min="0.01"
                                        value={form.expected_output_quantity}
                                        onChange={(e) => setForm({ ...form, expected_output_quantity: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Expected Wastage</label>
                                    <input
                                        type="number"
                                        required
                                        step="0.01"
                                        min="0"
                                        value={form.expected_wastage_quantity}
                                        onChange={(e) => setForm({ ...form, expected_wastage_quantity: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Expected Waste %</label>
                                    <input
                                        type="number"
                                        required
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        value={form.expected_wastage_percentage}
                                        onChange={(e) => setForm({ ...form, expected_wastage_percentage: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Assigned Operator / Supervisor</label>
                                    <input
                                        type="text"
                                        value={form.assigned_operator}
                                        onChange={(e) => setForm({ ...form, assigned_operator: e.target.value })}
                                        placeholder="e.g. Muhammad Ali"
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Planning Remarks</label>
                                    <textarea
                                        value={form.remarks}
                                        onChange={(e) => setForm({ ...form, remarks: e.target.value })}
                                        placeholder="Add notes about slab size specifications or batch checks..."
                                        rows={2}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    />
                                </div>
                            </div>

                            <div className="border-t border-gray-200 pt-4 flex gap-4 justify-end">
                                <button
                                    type="button"
                                    onClick={() => setShowFormModal(false)}
                                    className="px-4 py-2 rounded-xl text-gray-500 hover:text-gray-900 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="px-5 py-2.5 bg-[#2563EB] hover:bg-blue-700 text-white rounded-xl font-medium transition"
                                >
                                    {editingSchedule ? 'Save Changes' : 'Schedule Workload'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}

// Inline helper to map completed productions related to a schedule for output audit
function stoneToSlabProductionsList(scheduleId: number): any[] {
    const { props } = usePage() as any;
    const stoneToSlab = props.stoneToSlabProductions || [];
    return stoneToSlab.filter((p: any) => p.production_schedule_id === scheduleId);
}

function slabToTileProductionsList(scheduleId: number): any[] {
    const { props } = usePage() as any;
    const slabToTile = props.slabToTileProductions || [];
    return slabToTile.filter((p: any) => p.production_schedule_id === scheduleId);
}
