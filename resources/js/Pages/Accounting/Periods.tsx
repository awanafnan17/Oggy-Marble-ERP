import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { formatDate } from '../../format';
import {
    CalendarDays,
    Lock,
    Unlock,
    Plus,
    ShieldCheck,
    ShieldAlert,
    Clock,
    CheckCircle2,
    XCircle,
    AlertTriangle,
    Calendar,
} from 'lucide-react';

interface Period {
    id: number;
    period_name: string;
    start_date: string;
    end_date: string;
    is_closed: boolean;
    closed_by: string | null;
    closed_at: string | null;
    created_at: string;
}

interface PeriodsProps {
    periods: Period[];
}

export default function Periods({ periods }: PeriodsProps) {
    const [showModal, setShowModal] = useState(false);
    const [confirmClose, setConfirmClose] = useState<number | null>(null);
    const [errors, setErrors] = useState<any>({});

    const [form, setForm] = useState({
        period_name: '',
        start_date: '',
        end_date: '',
    });

    const handleCreatePeriod = (e: React.FormEvent) => {
        e.preventDefault();

        // Client-side overlap check
        const newStart = new Date(form.start_date);
        const newEnd = new Date(form.end_date);

        const overlap = periods.find(p => {
            const pStart = new Date(p.start_date);
            const pEnd = new Date(p.end_date);
            return newStart <= pEnd && newEnd >= pStart;
        });

        if (overlap) {
            setErrors({ start_date: `Overlaps with existing period: ${overlap.period_name}` });
            return;
        }

        router.post('/accounting/periods', form, {
            onSuccess: () => {
                setShowModal(false);
                setForm({ period_name: '', start_date: '', end_date: '' });
                setErrors({});
            },
            onError: (err) => setErrors(err),
        });
    };

    const handleClose = (id: number) => {
        router.post(`/accounting/periods/${id}/close`, {}, {
            onSuccess: () => setConfirmClose(null),
            onError: (err) => setErrors(err),
        });
    };

    const handleReopen = (id: number) => {
        router.post(`/accounting/periods/${id}/reopen`, {}, {
            onError: (err) => setErrors(err),
        });
    };

    // Local formatDate removed

    const openCount = periods.filter(p => !p.is_closed).length;
    const closedCount = periods.filter(p => p.is_closed).length;

    return (
        <AppLayout title="Accounting Period Governance">
            {/* Overview Statistics */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                <div className="glass-panel rounded-2xl p-5 flex items-center gap-4">
                    <div className="w-12 h-12 rounded-xl bg-[#2563EB]/10 border border-[#2563EB]/20 flex items-center justify-center">
                        <CalendarDays size={22} className="text-[#2563EB]" />
                    </div>
                    <div>
                        <span className="text-[10px] text-gray-600 uppercase tracking-wider font-semibold">Total Periods</span>
                        <p className="text-2xl font-bold text-gray-900 font-mono">{periods.length}</p>
                    </div>
                </div>
                <div className="glass-panel rounded-2xl p-5 flex items-center gap-4">
                    <div className="w-12 h-12 rounded-xl bg-[#10B981]/10 border border-[#10B981]/20 flex items-center justify-center">
                        <Unlock size={22} className="text-[#10B981]" />
                    </div>
                    <div>
                        <span className="text-[10px] text-gray-600 uppercase tracking-wider font-semibold">Open Periods</span>
                        <p className="text-2xl font-bold text-[#10B981] font-mono">{openCount}</p>
                    </div>
                </div>
                <div className="glass-panel rounded-2xl p-5 flex items-center gap-4">
                    <div className="w-12 h-12 rounded-xl bg-[#EF4444]/10 border border-[#EF4444]/20 flex items-center justify-center">
                        <Lock size={22} className="text-[#EF4444]" />
                    </div>
                    <div>
                        <span className="text-[10px] text-gray-600 uppercase tracking-wider font-semibold">Closed Periods</span>
                        <p className="text-2xl font-bold text-[#EF4444] font-mono">{closedCount}</p>
                    </div>
                </div>
            </div>

            {/* Pre-close Checks Blockers */}
            {errors.pre_close_checks && (
                <div className="mb-6 p-5 rounded-2xl bg-red-950/40 border border-red-500/25 text-red-200 shadow-[0_0_20px_rgba(239,68,68,0.05)]">
                    <div className="flex items-center gap-2 mb-3">
                        <AlertTriangle className="text-red-400" size={18} />
                        <h4 className="font-bold text-sm text-red-400">Month-End Close Blocked: Pre-Close Checks Failed</h4>
                    </div>
                    <ul className="list-disc pl-5 space-y-1 text-xs font-mono text-red-300">
                        {Array.isArray(errors.pre_close_checks) ? (
                            errors.pre_close_checks.map((err: string, i: number) => (
                                <li key={i}>{err}</li>
                            ))
                        ) : (
                            <li>{errors.pre_close_checks}</li>
                        )}
                    </ul>
                </div>
            )}

            {/* Action Bar */}
            <div className="flex justify-between items-center mb-6">
                <div className="flex items-center gap-2">
                    <ShieldCheck size={18} className="text-amber-800" />
                    <span className="text-sm text-gray-700 font-medium">
                        Period governance controls who can post and when.
                    </span>
                </div>
                <button
                    id="btn-add-period"
                    onClick={() => setShowModal(true)}
                    className="flex items-center gap-2 px-5 py-3 bg-[#B45309] hover:bg-[#92400E] text-white rounded-xl font-semibold shadow-xs transition-all duration-200"
                >
                    <Plus size={16} />
                    Add Period
                </button>
            </div>

            {/* Periods Table */}
            <div className="glass-panel rounded-2xl overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse">
                        <thead>
                            <tr className="border-b border-gray-200 text-[10px] text-gray-600 uppercase tracking-wider font-semibold">
                                <th className="px-6 py-4">Period Name</th>
                                <th className="px-6 py-4">Start Date</th>
                                <th className="px-6 py-4">End Date</th>
                                <th className="px-6 py-4 text-center">Status</th>
                                <th className="px-6 py-4">Closed By</th>
                                <th className="px-6 py-4">Closed At</th>
                                <th className="px-6 py-4 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200">
                            {periods.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-6 py-16 text-center">
                                        <div className="flex flex-col items-center gap-3">
                                            <Calendar size={40} className="text-gray-600" />
                                            <p className="text-sm text-gray-500 font-mono">No accounting periods defined yet.</p>
                                            <p className="text-xs text-gray-600">Create your first period to enable month-end governance.</p>
                                        </div>
                                    </td>
                                </tr>
                            ) : (
                                periods.map((period) => (
                                    <tr
                                        key={period.id}
                                        className={`transition-colors duration-200 hover:bg-white/[0.02] ${
                                            period.is_closed ? 'opacity-80' : ''
                                        }`}
                                    >
                                        <td className="px-6 py-4">
                                            <div className="flex items-center gap-3">
                                                <div className={`w-8 h-8 rounded-lg flex items-center justify-center ${
                                                    period.is_closed
                                                        ? 'bg-[#EF4444]/10 border border-[#EF4444]/20'
                                                        : 'bg-[#10B981]/10 border border-[#10B981]/20'
                                                }`}>
                                                    {period.is_closed
                                                        ? <Lock size={14} className="text-[#EF4444]" />
                                                        : <Unlock size={14} className="text-[#10B981]" />
                                                    }
                                                </div>
                                                <span className="text-sm font-semibold text-gray-900">{period.period_name}</span>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-sm text-gray-800 font-mono">{formatDate(period.start_date)}</td>
                                        <td className="px-6 py-4 text-sm text-gray-800 font-mono">{formatDate(period.end_date)}</td>
                                        <td className="px-6 py-4 text-center">
                                            {period.is_closed ? (
                                                <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-[#EF4444]/10 text-[#EF4444] border border-[#EF4444]/20 shadow-[0_0_12px_rgba(239,68,68,0.15)]">
                                                    <XCircle size={12} />
                                                    Closed
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-[#10B981]/10 text-[#10B981] border border-[#10B981]/20 shadow-[0_0_12px_rgba(16,185,129,0.15)]">
                                                    <CheckCircle2 size={12} />
                                                    Open
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 text-sm text-gray-700">
                                            {period.closed_by || <span className="text-gray-600">—</span>}
                                        </td>
                                        <td className="px-6 py-4 text-sm text-gray-700 font-mono">
                                            {period.closed_at || <span className="text-gray-600">—</span>}
                                        </td>
                                        <td className="px-6 py-4 text-center">
                                            {period.is_closed ? (
                                                <button
                                                    id={`btn-reopen-${period.id}`}
                                                    onClick={() => handleReopen(period.id)}
                                                    className="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold transition-all duration-300
                                                        bg-[#F59E0B]/10 text-[#F59E0B] border border-[#F59E0B]/20
                                                        hover:bg-[#F59E0B]/20 hover:shadow-[0_0_15px_rgba(245,158,11,0.15)]"
                                                    title="Admin Only — Reopen Period"
                                                >
                                                    <Unlock size={13} />
                                                    Reopen
                                                </button>
                                            ) : (
                                                <>
                                                    {confirmClose === period.id ? (
                                                        <div className="flex items-center gap-2 justify-center">
                                                            <span className="text-[10px] text-[#EF4444] font-semibold">Confirm?</span>
                                                            <button
                                                                onClick={() => handleClose(period.id)}
                                                                className="px-3 py-1.5 rounded-lg text-[10px] font-bold bg-[#EF4444] text-white hover:bg-red-600 transition shadow-lg shadow-red-500/20"
                                                            >
                                                                Yes, Close
                                                            </button>
                                                            <button
                                                                onClick={() => setConfirmClose(null)}
                                                                className="px-3 py-1.5 rounded-lg text-[10px] font-bold text-gray-500 hover:text-gray-900 transition"
                                                            >
                                                                Cancel
                                                            </button>
                                                        </div>
                                                    ) : (
                                                        <button
                                                            id={`btn-close-${period.id}`}
                                                            onClick={() => setConfirmClose(period.id)}
                                                            className="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold transition-all duration-300
                                                                bg-[#EF4444]/10 text-[#EF4444] border border-[#EF4444]/20
                                                                hover:bg-[#EF4444]/20 hover:shadow-[0_0_15px_rgba(239,68,68,0.2)]"
                                                        >
                                                            <Lock size={13} />
                                                            Close Period
                                                        </button>
                                                    )}
                                                </>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Governance Info Banner */}
            <div className="mt-8 glass-panel rounded-2xl p-5 flex items-start gap-4 border border-[#06B6D4]/10">
                <div className="w-10 h-10 rounded-xl bg-[#06B6D4]/10 border border-[#06B6D4]/20 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <ShieldAlert size={18} className="text-amber-800" />
                </div>
                <div>
                    <h4 className="text-sm font-bold text-gray-900 mb-1">Period Locking Governance</h4>
                    <p className="text-xs text-gray-700 leading-relaxed">
                        Closing an accounting period blocks all financial transactions (sales, purchases, production, payments, journal entries)
                        from being posted to dates within that period. This enforces month-end discipline and prevents backdated entries.
                        Only administrators can reopen a closed period.
                    </p>
                </div>
            </div>

            {/* Add Period Modal */}
            {showModal && (
                <div className="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="glass-panel w-full max-w-lg rounded-2xl max-h-[90vh] overflow-y-auto shadow-2xl animate-in fade-in zoom-in-95 duration-200">
                        <div className="p-6 border-b border-gray-200 flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <div className="w-10 h-10 rounded-xl bg-[#2563EB]/10 border border-[#2563EB]/20 flex items-center justify-center">
                                    <CalendarDays size={18} className="text-[#2563EB]" />
                                </div>
                                <h3 className="font-bold text-lg text-gray-900">Create Accounting Period</h3>
                            </div>
                            <button onClick={() => { setShowModal(false); setErrors({}); }} className="text-gray-500 hover:text-gray-900 transition">
                                <Plus size={20} className="rotate-45" />
                            </button>
                        </div>
                        <form onSubmit={handleCreatePeriod} className="p-6 space-y-5">
                            <div>
                                <label className="block text-xs font-semibold uppercase text-gray-700 mb-1.5">Period Name</label>
                                <input
                                    id="input-period-name"
                                    type="text"
                                    required
                                    placeholder="e.g. July 2026"
                                    value={form.period_name}
                                    onChange={(e) => setForm({ ...form, period_name: e.target.value })}
                                    className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition placeholder-gray-600"
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1.5">Start Date</label>
                                    <input
                                        id="input-start-date"
                                        type="date"
                                        required
                                        value={form.start_date}
                                        onChange={(e) => setForm({ ...form, start_date: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    />
                                    {errors.start_date && (
                                        <p className="text-xs text-[#EF4444] mt-1.5 flex items-center gap-1">
                                            <AlertTriangle size={12} />
                                            {errors.start_date}
                                        </p>
                                    )}
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1.5">End Date</label>
                                    <input
                                        id="input-end-date"
                                        type="date"
                                        required
                                        value={form.end_date}
                                        onChange={(e) => setForm({ ...form, end_date: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    />
                                </div>
                            </div>

                            {/* Date Range Preview */}
                            {form.start_date && form.end_date && (
                                <div className="flex items-center gap-2 p-3 rounded-xl bg-[#06B6D4]/5 border border-[#06B6D4]/15">
                                    <Clock size={14} className="text-amber-800" />
                                    <span className="text-xs text-gray-800 font-mono">
                                        Duration: {Math.ceil((new Date(form.end_date).getTime() - new Date(form.start_date).getTime()) / (1000 * 60 * 60 * 24)) + 1} days
                                    </span>
                                </div>
                            )}

                            {errors.period && (
                                <div className="p-3 rounded-xl bg-[#EF4444]/5 border border-[#EF4444]/20 flex items-center gap-2">
                                    <AlertTriangle size={14} className="text-[#EF4444]" />
                                    <span className="text-xs text-[#EF4444]">{errors.period}</span>
                                </div>
                            )}

                            <div className="border-t border-gray-200 pt-4 flex gap-4 justify-end">
                                <button
                                    type="button"
                                    onClick={() => { setShowModal(false); setErrors({}); }}
                                    className="px-4 py-2 rounded-xl text-gray-500 hover:text-gray-900 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    id="btn-submit-period"
                                    type="submit"
                                    className="px-5 py-2.5 rounded-xl font-medium bg-[#2563EB] hover:bg-blue-700 text-white shadow-lg shadow-blue-500/10 transition-all duration-300"
                                >
                                    Create Period
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
