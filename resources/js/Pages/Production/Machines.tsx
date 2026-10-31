import React, { useState } from 'react';
import { router, Head, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import GlassCard from '../../components/GlassCard';
import DataTable, { Column } from '../../components/DataTable';
import StatusBadge from '../../components/StatusBadge';
import { ProductionTabs } from './Schedules';
import { Wrench, Plus, User, AlertCircle, Edit, ToggleLeft } from 'lucide-react';

interface Machine {
    id: number;
    machine_code: string;
    name: string;
    type: 'cutter' | 'polishing' | 'crane' | 'loader' | 'other';
    capacity_note: string | null;
    operator_name: string | null;
    status: 'active' | 'inactive' | 'maintenance';
    remarks: string | null;
    created_at: string;
}

interface MachinesProps {
    machines: Machine[];
}

export default function Machines({ machines }: MachinesProps) {
    const { errors, auth } = usePage().props as any;
    const [showFormModal, setShowFormModal] = useState(false);
    const [editingMachine, setEditingMachine] = useState<Machine | null>(null);

    const userPermissions = auth?.user?.permissions || [];
    const userRoles = auth?.user?.roles || [];
    const canManageMachines = userRoles.includes('admin') || userPermissions.includes('manage_machines');

    const [form, setForm] = useState({
        machine_code: '',
        name: '',
        type: 'cutter',
        capacity_note: '',
        operator_name: '',
        status: 'active',
        remarks: '',
    });

    const openCreateModal = () => {
        setEditingMachine(null);
        setForm({
            machine_code: '',
            name: '',
            type: 'cutter',
            capacity_note: '',
            operator_name: '',
            status: 'active',
            remarks: '',
        });
        setShowFormModal(true);
    };

    const openEditModal = (machine: Machine) => {
        setEditingMachine(machine);
        setForm({
            machine_code: machine.machine_code,
            name: machine.name,
            type: machine.type,
            capacity_note: machine.capacity_note || '',
            operator_name: machine.operator_name || '',
            status: machine.status,
            remarks: machine.remarks || '',
        });
        setShowFormModal(true);
    };

    const handleFormSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingMachine) {
            router.put(`/production/machines/${editingMachine.id}`, form, {
                onSuccess: () => setShowFormModal(false),
            });
        } else {
            router.post('/production/machines', form, {
                onSuccess: () => setShowFormModal(false),
            });
        }
    };

    const updateStatus = (machineId: number, status: 'active' | 'inactive' | 'maintenance') => {
        router.put(`/production/machines/${machineId}`, {
            ...machines.find(m => m.id === machineId),
            status: status
        });
    };

    const columns: Column<Machine>[] = [
        {
            header: 'Machine Code',
            accessor: (row) => <span className="font-mono font-bold text-gray-900">{row.machine_code}</span>,
        },
        {
            header: 'Name',
            accessor: (row) => <span className="font-semibold text-amber-800">{row.name}</span>,
        },
        {
            header: 'Type',
            accessor: (row) => <span className="capitalize">{row.type.replace(/_/g, ' ')}</span>,
        },
        {
            header: 'Capacity Details',
            accessor: (row) => row.capacity_note || <span className="text-gray-500 italic">None</span>,
        },
        {
            header: 'Assigned Operator',
            accessor: (row) => (
                row.operator_name ? (
                    <span className="flex items-center gap-1.5 text-gray-800">
                        <User size={12} className="text-gray-400" />
                        {row.operator_name}
                    </span>
                ) : <span className="text-gray-500 italic">Unassigned</span>
            ),
        },
        {
            header: 'Status',
            accessor: (row) => (
                <StatusBadge
                    status={row.status}
                    typeMap={{
                        active: 'success',
                        inactive: 'danger',
                        maintenance: 'warning',
                    }}
                />
            ),
        },
        {
            header: 'Actions',
            accessor: (row) => (
                canManageMachines ? (
                    <div className="flex gap-2">
                        <button
                            onClick={() => openEditModal(row)}
                            className="p-1 hover:text-amber-800 transition"
                            title="Edit Machine Details"
                        >
                            <Edit size={14} />
                        </button>
                        <select
                            value={row.status}
                            onChange={(e) => updateStatus(row.id, e.target.value as any)}
                            className="bg-white border border-gray-300 rounded px-1 py-0.5 text-[10px] text-gray-800 focus:outline-none focus:border-[#06B6D4]"
                        >
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                ) : <span className="text-[10px] text-gray-500 italic">Read-only</span>
            ),
        },
    ];

    return (
        <AppLayout title="Machine Master Register">
            <Head title="Machine Master Register" />

            <ProductionTabs activeTab="machines" />

            <div className="space-y-6">
                {/* Header Actions */}
                <div className="flex justify-between items-center">
                    <div>
                        <h2 className="text-lg font-bold text-gray-900">Machine Master Registry</h2>
                        <p className="text-xs text-gray-700">Configure factory cutting, polishing lines and loading cranes.</p>
                    </div>
                    {canManageMachines && (
                        <button
                            onClick={openCreateModal}
                            className="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-blue-600 to-cyan-500 text-white rounded-xl text-xs font-semibold shadow hover:scale-[1.02] active:scale-95 transition duration-300"
                        >
                            <Plus size={14} />
                            Register Machine
                        </button>
                    )}
                </div>

                {/* Machines List */}
                <div className="glass-panel rounded-2xl p-6">
                    <DataTable
                        columns={columns}
                        data={machines}
                        keyExtractor={(row) => row.id}
                        emptyMessage="No machines registered in the factory master record."
                    />
                </div>
            </div>

            {/* Form Modal */}
            {showFormModal && (
                <div className="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="glass-panel w-full max-w-md rounded-2xl overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-200">
                        <div className="p-6 border-b border-gray-200 flex items-center justify-between">
                            <h3 className="font-bold text-lg text-gray-900">
                                {editingMachine ? 'Edit Machine Registry' : 'Register New Factory Machine'}
                            </h3>
                            <button onClick={() => setShowFormModal(false)} className="text-gray-500 hover:text-gray-900 transition">
                                <Plus size={20} className="rotate-45" />
                            </button>
                        </div>
                        <form onSubmit={handleFormSubmit} className="p-6 space-y-4">

                            <div>
                                <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Machine Code</label>
                                <input
                                    type="text"
                                    required
                                    placeholder="e.g. MC-GSAW-03"
                                    value={form.machine_code}
                                    onChange={(e) => setForm({ ...form, machine_code: e.target.value.toUpperCase() })}
                                    className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                />
                                {errors.machine_code && <p className="text-xs text-red-400 mt-1">{errors.machine_code}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Machine Name</label>
                                <input
                                    type="text"
                                    required
                                    placeholder="e.g. Gang Saw 3"
                                    value={form.name}
                                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                                    className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                />
                                {errors.name && <p className="text-xs text-red-400 mt-1">{errors.name}</p>}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Machine Type</label>
                                    <select
                                        value={form.type}
                                        onChange={(e) => setForm({ ...form, type: e.target.value as any })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    >
                                        <option value="cutter">Cutter (Saw)</option>
                                        <option value="polishing">Polishing Line</option>
                                        <option value="crane">Crane</option>
                                        <option value="loader">Loader</option>
                                        <option value="other">Other Machinery</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Status</label>
                                    <select
                                        value={form.status}
                                        onChange={(e) => setForm({ ...form, status: e.target.value as any })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    >
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="maintenance">Maintenance</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Capacity Details</label>
                                <input
                                    type="text"
                                    placeholder="e.g. 1500 sqft / day"
                                    value={form.capacity_note}
                                    onChange={(e) => setForm({ ...form, capacity_note: e.target.value })}
                                    className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Operator Name</label>
                                <input
                                    type="text"
                                    placeholder="e.g. Sajid Ali"
                                    value={form.operator_name}
                                    onChange={(e) => setForm({ ...form, operator_name: e.target.value })}
                                    className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Remarks</label>
                                <textarea
                                    placeholder="Any technical or operational details"
                                    value={form.remarks}
                                    onChange={(e) => setForm({ ...form, remarks: e.target.value })}
                                    rows={2}
                                    className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                />
                            </div>

                            <div className="flex justify-end gap-3 pt-4 border-t border-gray-200">
                                <button
                                    type="button"
                                    onClick={() => setShowFormModal(false)}
                                    className="px-4 py-2 rounded-xl border border-gray-300 hover:bg-gray-50 text-xs text-gray-800 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-xs font-semibold text-white transition"
                                >
                                    {editingMachine ? 'Save Changes' : 'Register Machine'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
