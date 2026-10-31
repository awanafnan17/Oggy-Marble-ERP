import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { formatPKR, formatDate } from '../../format';
import GlassCard from '../../components/GlassCard';
import KPICard from '../../components/KPICard';
import DataTable, { Column } from '../../components/DataTable';
import SlideModal from '../../components/SlideModal';
import StatusBadge from '../../components/StatusBadge';
import {
    Clock,
    DollarSign,
    Activity,
    Plus,
    Calendar,
    Eye,
    Percent
} from 'lucide-react';

interface Contact {
    id: number;
    name: string;
}

interface BankAccount {
    id: number;
    account_name: string;
    bank_name: string;
}

interface RentalJob {
    id: number;
    job_number: string;
    contact_id: number;
    contact: Contact;
    job_date: string;
    machine_hours: number;
    machine_rate: number;
    labor_hours: number;
    labor_rate: number;
    other_charges: number;
    total_amount: number;
    cash_received: number;
    bank_received: number;
    remaining_receivable: number;
    payment_status: 'paid' | 'partial' | 'unpaid';
    notes?: string;
}

interface MachineRentalProps {
    rentalJobs: RentalJob[];
    customers: Contact[];
    bankAccounts: BankAccount[];
}

export default function MachineRental({ rentalJobs, customers, bankAccounts }: MachineRentalProps) {
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [selectedJob, setSelectedJob] = useState<RentalJob | null>(null);
    const [isViewModalOpen, setIsViewModalOpen] = useState(false);

    // Form state
    const [form, setForm] = useState({
        contact_id: '',
        job_date: new Date().toISOString().substring(0, 10),
        machine_hours: '0',
        machine_rate: '2500', // standard marble machine rate PKR
        labor_hours: '0',
        labor_rate: '500',   // standard labor rate PKR
        other_charges: '0',
        cash_received: '0',
        bank_received: '0',
        bank_account_id: '',
        notes: ''
    });

    const [errors, setErrors] = useState<any>({});

    // Calculations
    const parseNum = (val: string) => parseFloat(val) || 0;

    const mHours = parseNum(form.machine_hours);
    const mRate = parseNum(form.machine_rate);
    const lHours = parseNum(form.labor_hours);
    const lRate = parseNum(form.labor_rate);
    const other = parseNum(form.other_charges);
    const cashRec = parseNum(form.cash_received);
    const bankRec = parseNum(form.bank_received);

    const calculatedTotal = (mHours * mRate) + (lHours * lRate) + other;
    const totalReceived = cashRec + bankRec;
    const calculatedRemaining = calculatedTotal - totalReceived;

    // KPI Summary calculations
    const totalRevenueThisMonth = rentalJobs.reduce((sum, job) => {
        const jobDate = new Date(job.job_date);
        const now = new Date();
        if (jobDate.getMonth() === now.getMonth() && jobDate.getFullYear() === now.getFullYear()) {
            return sum + job.total_amount;
        }
        return sum;
    }, 0);

    const totalHoursUtilized = rentalJobs.reduce((sum, job) => sum + Number(job.machine_hours), 0);
    const outstandingReceivables = rentalJobs.reduce((sum, job) => sum + Number(job.remaining_receivable), 0);

    const avgMachineRate = rentalJobs.length > 0
        ? rentalJobs.reduce((sum, job) => sum + Number(job.machine_rate), 0) / rentalJobs.length
        : 0;

    // Local formatPKR removed

    const handleFormSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/rental-jobs/store', form, {
            onSuccess: () => {
                setIsModalOpen(false);
                setForm({
                    contact_id: '',
                    job_date: new Date().toISOString().substring(0, 10),
                    machine_hours: '0',
                    machine_rate: '2500',
                    labor_hours: '0',
                    labor_rate: '500',
                    other_charges: '0',
                    cash_received: '0',
                    bank_received: '0',
                    bank_account_id: '',
                    notes: ''
                });
                setErrors({});
            },
            onError: (err) => {
                setErrors(err);
            }
        });
    };

    const handleViewDetails = (job: RentalJob) => {
        setSelectedJob(job);
        setIsViewModalOpen(true);
    };

    const tableColumns: Column<RentalJob>[] = [
        {
            header: 'Job #',
            accessor: (row) => <span className="text-gray-900 font-medium">{row.job_number}</span>
        },
        {
            header: 'Customer',
            accessor: (row) => row.contact?.name || 'N/A'
        },
        {
            header: 'Job Date',
            accessor: (row) => formatDate(row.job_date)
        },
        {
            header: 'Machine Hours',
            accessor: (row) => `${row.machine_hours} Hrs`,
            align: 'right'
        },
        {
            header: 'Labor Hours',
            accessor: (row) => `${row.labor_hours} Hrs`,
            align: 'right'
        },
        {
            header: 'Total Amount',
            accessor: (row) => formatPKR(row.total_amount),
            align: 'right',
            className: 'text-white font-bold'
        },
        {
            header: 'Received',
            accessor: (row) => formatPKR(Number(row.cash_received) + Number(row.bank_received)),
            align: 'right'
        },
        {
            header: 'Remaining',
            accessor: (row) => formatPKR(row.remaining_receivable),
            align: 'right',
            className: 'text-amber-800'
        },
        {
            header: 'Status',
            accessor: (row) => <StatusBadge status={row.payment_status} />
        },
        {
            header: 'Actions',
            accessor: (row) => (
                <button
                    onClick={() => handleViewDetails(row)}
                    className="p-1.5 rounded-lg border border-gray-200 bg-gray-50 hover:border-[#06B6D4]/50 hover:bg-[#06B6D4]/5 text-gray-500 hover:text-gray-900 transition duration-200"
                >
                    <Eye size={15} />
                </button>
            ),
            align: 'center'
        }
    ];

    return (
        <AppLayout title="Machine Rental Operations">
            {/* Top KPIs Row */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <KPICard
                    label="Rental Revenue (This Month)"
                    value={formatPKR(totalRevenueThisMonth)}
                    glowColor="cyan"
                    icon={DollarSign}
                    subLabel="Calculated from logged rental runs"
                />
                <KPICard
                    label="Hours Utilized"
                    value={`${totalHoursUtilized.toFixed(1)} Hrs`}
                    glowColor="blue"
                    icon={Clock}
                    subLabel="Accumulated machine hours"
                />
                <KPICard
                    label="Outstanding Receivables"
                    value={formatPKR(outstandingReceivables)}
                    glowColor="red"
                    icon={Activity}
                    subLabel="Awaiting collection"
                />
                <KPICard
                    label="Avg Machine Rate"
                    value={formatPKR(avgMachineRate)}
                    glowColor="emerald"
                    icon={Percent}
                    subLabel="Rate per hour utilized"
                />
            </div>

            {/* Actions Bar */}
            <div className="flex justify-between items-center mb-6">
                <div>
                    <h3 className="text-lg font-bold text-gray-900">Rental Jobs Registry</h3>
                    <p className="text-xs text-gray-700">Manage stone cutting and block sizing machine rentals</p>
                </div>
                <button
                    onClick={() => setIsModalOpen(true)}
                    className="flex items-center gap-2 px-5 py-3 bg-[#2563EB] hover:bg-blue-700 text-white rounded-xl font-medium shadow-[0_0_15px_rgba(37,99,235,0.3)] hover:shadow-blue-500/40 transition-all duration-300 transform hover:-translate-y-0.5"
                >
                    <Plus size={16} />
                    Log Rental Job
                </button>
            </div>

            {/* Jobs Table */}
            <DataTable
                columns={tableColumns}
                data={rentalJobs}
                keyExtractor={(row) => row.id}
                emptyMessage="No rental jobs registered yet. Click 'Log Rental Job' to configure."
            />

            {/* Slide-out Form Drawer */}
            <SlideModal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                title="Log New Rental Job"
            >
                <form onSubmit={handleFormSubmit} className="space-y-6">
                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Customer / Client</label>
                        <select
                            required
                            value={form.contact_id}
                            onChange={(e) => setForm({ ...form, contact_id: e.target.value })}
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:ring-1 focus:ring-[#06B6D4] focus:outline-none transition"
                        >
                            <option value="">Select customer...</option>
                            {customers.map((c) => (
                                <option key={c.id} value={c.id}>{c.name}</option>
                            ))}
                        </select>
                        {errors.contact_id && <p className="text-xs text-red-400 mt-1">{errors.contact_id}</p>}
                    </div>

                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Job Date</label>
                        <input
                            type="date"
                            required
                            value={form.job_date}
                            onChange={(e) => setForm({ ...form, job_date: e.target.value })}
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:ring-1 focus:ring-[#06B6D4] focus:outline-none transition font-mono"
                        />
                        {errors.job_date && <p className="text-xs text-red-400 mt-1">{errors.job_date}</p>}
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Machine Hours</label>
                            <input
                                type="number"
                                step="0.1"
                                required
                                min="0"
                                value={form.machine_hours}
                                onChange={(e) => setForm({ ...form, machine_hours: e.target.value })}
                                className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                            />
                            {errors.machine_hours && <p className="text-xs text-red-400 mt-1">{errors.machine_hours}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Rate (PKR / Hr)</label>
                            <input
                                type="number"
                                required
                                min="0"
                                value={form.machine_rate}
                                onChange={(e) => setForm({ ...form, machine_rate: e.target.value })}
                                className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                            />
                            {errors.machine_rate && <p className="text-xs text-red-400 mt-1">{errors.machine_rate}</p>}
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Labor Hours</label>
                            <input
                                type="number"
                                step="0.1"
                                required
                                min="0"
                                value={form.labor_hours}
                                onChange={(e) => setForm({ ...form, labor_hours: e.target.value })}
                                className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                            />
                            {errors.labor_hours && <p className="text-xs text-red-400 mt-1">{errors.labor_hours}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Rate (PKR / Hr)</label>
                            <input
                                type="number"
                                required
                                min="0"
                                value={form.labor_rate}
                                onChange={(e) => setForm({ ...form, labor_rate: e.target.value })}
                                className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                            />
                            {errors.labor_rate && <p className="text-xs text-red-400 mt-1">{errors.labor_rate}</p>}
                        </div>
                    </div>

                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Other Surcharges (PKR)</label>
                        <input
                            type="number"
                            required
                            min="0"
                            value={form.other_charges}
                            onChange={(e) => setForm({ ...form, other_charges: e.target.value })}
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                        />
                        {errors.other_charges && <p className="text-xs text-red-400 mt-1">{errors.other_charges}</p>}
                    </div>

                    {/* Auto Calculated Net Bill Total */}
                    <div className="p-4 rounded-xl bg-gray-50 border border-gray-200 space-y-2">
                        <div className="flex justify-between items-center text-xs text-gray-700">
                            <span>Auto-Calculated Total Amount:</span>
                            <span className="font-bold text-gray-900 font-mono">{formatPKR(calculatedTotal)}</span>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Cash Received</label>
                            <input
                                type="number"
                                required
                                min="0"
                                value={form.cash_received}
                                onChange={(e) => setForm({ ...form, cash_received: e.target.value })}
                                className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                            />
                            {errors.cash_received && <p className="text-xs text-red-400 mt-1">{errors.cash_received}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Bank/Card Received</label>
                            <input
                                type="number"
                                required
                                min="0"
                                value={form.bank_received}
                                onChange={(e) => setForm({ ...form, bank_received: e.target.value })}
                                className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                            />
                            {errors.bank_received && <p className="text-xs text-red-400 mt-1">{errors.bank_received}</p>}
                        </div>
                    </div>

                    {/* Show ledger account selector only if payment is received */}
                    {totalReceived > 0 && (
                        <div>
                            <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Deposit To Cash/Bank Account</label>
                            <select
                                required
                                value={form.bank_account_id}
                                onChange={(e) => setForm({ ...form, bank_account_id: e.target.value })}
                                className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                            >
                                <option value="">Select cash/bank account...</option>
                                {bankAccounts.map((acc) => (
                                    <option key={acc.id} value={acc.id}>
                                        {acc.account_name} ({acc.bank_name || 'Cash'})
                                    </option>
                                ))}
                            </select>
                            {errors.bank_account_id && <p className="text-xs text-red-400 mt-1">{errors.bank_account_id}</p>}
                        </div>
                    )}

                    <div className="p-4 rounded-xl bg-cyan-950/20 border border-[#06B6D4]/20 space-y-2">
                        <div className="flex justify-between items-center text-xs text-amber-800">
                            <span>Remaining Receivables:</span>
                            <span className="font-bold font-mono">{formatPKR(calculatedRemaining)}</span>
                        </div>
                    </div>

                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Notes / Description</label>
                        <textarea
                            value={form.notes}
                            onChange={(e) => setForm({ ...form, notes: e.target.value })}
                            rows={3}
                            placeholder="Provide details of blocks cut or job specifications..."
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                        />
                        {errors.notes && <p className="text-xs text-red-400 mt-1">{errors.notes}</p>}
                    </div>

                    <div className="border-t border-gray-200 pt-4 flex gap-4 justify-end">
                        <button
                            type="button"
                            onClick={() => setIsModalOpen(false)}
                            className="px-4 py-2 rounded-xl text-gray-500 hover:text-gray-900 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            className="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-cyan-500 hover:from-blue-700 hover:to-cyan-600 text-white rounded-xl font-medium shadow-[0_0_15px_rgba(6,182,212,0.3)] hover:shadow-[0_0_20px_rgba(6,182,212,0.5)] transition duration-300"
                        >
                            Log Rental Job
                        </button>
                    </div>
                </form>
            </SlideModal>

            {/* View Details Slide Modal */}
            <SlideModal
                isOpen={isViewModalOpen}
                onClose={() => setIsViewModalOpen(false)}
                title={`Rental Job ${selectedJob?.job_number}`}
            >
                {selectedJob && (
                    <div className="space-y-6">
                        <div className="flex justify-between items-center pb-4 border-b border-gray-200">
                            <span className="text-xs text-gray-700 uppercase font-mono">Job Date</span>
                            <span className="text-sm font-semibold text-gray-900 font-mono">{formatDate(selectedJob.job_date)}</span>
                        </div>

                        <div className="space-y-3">
                            <h4 className="text-xs font-semibold uppercase text-gray-700 font-mono tracking-wider">
                                Work Specifications
                            </h4>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="bg-gray-50 p-3 rounded-lg border border-gray-200 text-center">
                                    <span className="text-[10px] text-gray-500 uppercase">Machine Time</span>
                                    <p className="text-lg font-bold text-gray-900 mt-1 font-mono">{selectedJob.machine_hours} Hrs</p>
                                    <p className="text-[10px] text-gray-700 font-mono">@ {formatPKR(selectedJob.machine_rate)}/Hr</p>
                                </div>
                                <div className="bg-gray-50 p-3 rounded-lg border border-gray-200 text-center">
                                    <span className="text-[10px] text-gray-500 uppercase">Labor Time</span>
                                    <p className="text-lg font-bold text-gray-900 mt-1 font-mono">{selectedJob.labor_hours} Hrs</p>
                                    <p className="text-[10px] text-gray-700 font-mono">@ {formatPKR(selectedJob.labor_rate)}/Hr</p>
                                </div>
                            </div>
                        </div>

                        <div className="space-y-3 pt-2">
                            <h4 className="text-xs font-semibold uppercase text-gray-700 font-mono tracking-wider">
                                Financial Breakdown
                            </h4>
                            <div className="space-y-2 text-xs font-mono">
                                <div className="flex justify-between text-gray-700">
                                    <span>Machine Rental Charges:</span>
                                    <span className="text-gray-900">{formatPKR(selectedJob.machine_hours * selectedJob.machine_rate)}</span>
                                </div>
                                <div className="flex justify-between text-gray-700">
                                    <span>Labor Charges:</span>
                                    <span className="text-gray-900">{formatPKR(selectedJob.labor_hours * selectedJob.labor_rate)}</span>
                                </div>
                                <div className="flex justify-between text-gray-700 pb-2 border-b border-gray-200">
                                    <span>Other Miscellaneous Charges:</span>
                                    <span className="text-gray-900">{formatPKR(selectedJob.other_charges)}</span>
                                </div>
                                <div className="flex justify-between font-bold text-gray-900 text-sm pt-1">
                                    <span>Total Invoiced Amount:</span>
                                    <span className="text-gray-900">{formatPKR(selectedJob.total_amount)}</span>
                                </div>
                                <div className="flex justify-between text-gray-700">
                                    <span>Cash Received:</span>
                                    <span className="text-emerald-800">{formatPKR(selectedJob.cash_received)}</span>
                                </div>
                                <div className="flex justify-between text-gray-700 pb-2 border-b border-gray-200">
                                    <span>Bank Received:</span>
                                    <span className="text-emerald-800">{formatPKR(selectedJob.bank_received)}</span>
                                </div>
                                <div className="flex justify-between font-bold text-sm text-amber-800 pt-1">
                                    <span>Remaining Balance:</span>
                                    <span>{formatPKR(selectedJob.remaining_receivable)}</span>
                                </div>
                            </div>
                        </div>

                        {selectedJob.notes && (
                            <div className="space-y-2 pt-2 border-t border-gray-200">
                                <span className="text-xs font-semibold uppercase text-gray-700 font-mono">Notes</span>
                                <p className="text-xs text-gray-800 leading-relaxed font-sans bg-gray-50 p-3 rounded-lg border border-gray-200">
                                    {selectedJob.notes}
                                </p>
                            </div>
                        )}

                        <div className="border-t border-gray-200 pt-4">
                            <button
                                onClick={() => setIsViewModalOpen(false)}
                                className="w-full py-2.5 border border-gray-300 hover:border-gray-2000 bg-gray-50 hover:bg-gray-500 text-white rounded-xl text-xs font-semibold transition"
                            >
                                Close Details Panel
                            </button>
                        </div>
                    </div>
                )}
            </SlideModal>
        </AppLayout>
    );
}
