import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { formatPKR, formatDate } from '../../format';
import {
    FileSpreadsheet,
    Plus,
    Trash2,
    Calendar,
    BookOpen,
    DollarSign,
    CheckCircle2,
    AlertTriangle
} from 'lucide-react';

interface Account {
    id: number;
    code: string;
    name: string;
    type: 'asset' | 'liability' | 'equity' | 'revenue' | 'expense';
    balance: number;
}

interface JournalItem {
    id: number;
    account: {
        code: string;
        name: string;
    };
    debit: number;
    credit: number;
}

interface JournalEntry {
    id: number;
    entry_number: string;
    date: string;
    description: string;
    items: JournalItem[];
}

interface Metrics {
    totalAssets: number;
    totalLiabilities: number;
    totalEquity: number;
    totalRevenue: number;
    totalExpense: number;
    netIncome: number;
}

interface AccountingProps {
    accounts: Account[];
    journalEntries: JournalEntry[];
    metrics: Metrics;
}

export default function Accounting({ accounts, journalEntries, metrics }: AccountingProps) {
    const [showJournalModal, setShowJournalModal] = useState(false);

    // Journal entry form state
    const [journalForm, setJournalForm] = useState({
        date: new Date().toISOString().split('T')[0],
        description: '',
        items: [
            { account_id: '', debit: '0.00', credit: '0.00' },
            { account_id: '', debit: '0.00', credit: '0.00' }
        ]
    });

    const [errors, setErrors] = useState<any>({});

    const handleAddItemRow = () => {
        setJournalForm({
            ...journalForm,
            items: [...journalForm.items, { account_id: '', debit: '0.00', credit: '0.00' }]
        });
    };

    const handleRemoveItemRow = (index: number) => {
        const updated = [...journalForm.items];
        updated.splice(index, 1);
        setJournalForm({ ...journalForm, items: updated });
    };

    const handleItemChange = (index: number, field: 'account_id' | 'debit' | 'credit', value: string) => {
        const updated = [...journalForm.items];

        if (field === 'debit' && parseFloat(value) > 0) {
            updated[index] = { ...updated[index], debit: value, credit: '0.00' };
        } else if (field === 'credit' && parseFloat(value) > 0) {
            updated[index] = { ...updated[index], debit: '0.00', credit: value };
        } else {
            updated[index] = { ...updated[index], [field]: value };
        }

        setJournalForm({ ...journalForm, items: updated });
    };

    // React-side double entry verification
    const sumDebits = journalForm.items.reduce((acc, item) => acc + (parseFloat(item.debit) || 0), 0);
    const sumCredits = journalForm.items.reduce((acc, item) => acc + (parseFloat(item.credit) || 0), 0);
    const difference = Math.abs(sumDebits - sumCredits);
    const isBalanced = difference < 0.0001 && sumDebits > 0;

    const handleCreateJournalEntry = (e: React.FormEvent) => {
        e.preventDefault();
        if (!isBalanced) return;

        router.post('/accounting/journal', journalForm, {
            onSuccess: () => {
                setShowJournalModal(false);
                setJournalForm({
                    date: new Date().toISOString().split('T')[0],
                    description: '',
                    items: [
                        { account_id: '', debit: '0.00', credit: '0.00' },
                        { account_id: '', debit: '0.00', credit: '0.00' }
                    ]
                });
                setErrors({});
            },
            onError: (err) => setErrors(err),
        });
    };

    return (
        <AppLayout title="Double-Entry General Ledger">
            {/* Top overview statistics grid */}
            <div className="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-6 gap-4 mb-8 text-center font-mono">
                <div className="glass-panel p-4 rounded-xl">
                    <span className="text-[10px] text-gray-500 uppercase tracking-wider">Total Assets (1xxx)</span>
                    <p className="text-base font-bold text-gray-900 mt-1">
                        {formatPKR(metrics.totalAssets)}
                    </p>
                </div>
                <div className="glass-panel p-4 rounded-xl">
                    <span className="text-[10px] text-gray-500 uppercase tracking-wider">Liabilities (2xxx)</span>
                    <p className="text-base font-bold text-red-400 mt-1">
                        {formatPKR(metrics.totalLiabilities)}
                    </p>
                </div>
                <div className="glass-panel p-4 rounded-xl">
                    <span className="text-[10px] text-gray-500 uppercase tracking-wider">Equity (3xxx)</span>
                    <p className="text-base font-bold text-amber-800 mt-1">
                        {formatPKR(metrics.totalEquity)}
                    </p>
                </div>
                <div className="glass-panel p-4 rounded-xl">
                    <span className="text-[10px] text-gray-500 uppercase tracking-wider">Revenue (4xxx)</span>
                    <p className="text-base font-bold text-emerald-800 mt-1">
                        {formatPKR(metrics.totalRevenue)}
                    </p>
                </div>
                <div className="glass-panel p-4 rounded-xl">
                    <span className="text-[10px] text-gray-500 uppercase tracking-wider">Expenses (5xxx)</span>
                    <p className="text-base font-bold text-orange-400 mt-1">
                        {formatPKR(metrics.totalExpense)}
                    </p>
                </div>
                <div className="glass-panel p-4 rounded-xl border-[#10B981]/20 bg-[#10B981]/5">
                    <span className="text-[10px] text-gray-700 uppercase tracking-wider">Net Income P&L</span>
                    <p className={`text-base font-bold mt-1 ${metrics.netIncome >= 0 ? 'text-[#10B981]' : 'text-red-400'}`}>
                        {formatPKR(metrics.netIncome)}
                    </p>
                </div>
            </div>

            {/* Top row actions */}
            <div className="flex justify-between items-center mb-8">
                <button
                    onClick={() => setShowJournalModal(true)}
                    className="flex items-center gap-2 px-5 py-3 bg-[#B45309] hover:bg-[#92400E] text-white rounded-xl font-semibold shadow-xs transition-all duration-200"
                >
                    <Plus size={16} />
                    Post Journal Entry
                </button>
            </div>

            {/* Main accounting structures layout */}
            <div className="grid grid-cols-1 xl:grid-cols-3 gap-8">
                {/* Journal entries log (2/3 width) */}
                <div className="xl:col-span-2 glass-panel rounded-2xl p-6">
                    <div className="flex items-center gap-2 mb-6">
                        <BookOpen size={18} className="text-amber-800" />
                        <h3 className="font-bold text-lg text-gray-900">General Journal Log</h3>
                    </div>
                    <div className="space-y-6 max-h-[600px] overflow-y-auto pr-2">
                        {journalEntries.length === 0 ? (
                            <p className="text-sm text-gray-600 font-mono text-center py-12">No transactions recorded in ledger.</p>
                        ) : (
                            journalEntries.map((entry) => (
                                <div key={entry.id} className="p-4 rounded-xl bg-gray-50 border border-gray-200 space-y-3">
                                    <div className="flex justify-between items-center text-xs font-mono text-gray-700 border-b border-gray-200 pb-2">
                                        <span className="font-bold text-gray-900">{entry.entry_number}</span>
                                        <span>{formatDate(entry.date)}</span>
                                    </div>
                                    <p className="text-sm text-gray-800 font-sans">{entry.description}</p>

                                    {/* Entry items debits/credits list */}
                                    <div className="space-y-1 bg-black/20 p-2 rounded-lg border border-gray-200 font-mono text-xs">
                                        <div className="grid grid-cols-4 text-gray-500 border-b border-gray-200 pb-1 font-semibold">
                                            <span>Account</span>
                                            <span className="col-span-2 text-right">Debit</span>
                                            <span className="text-right">Credit</span>
                                        </div>
                                        {entry.items.map((item) => (
                                            <div key={item.id} className="grid grid-cols-4 py-1 text-gray-800 border-b border-white/1 last:border-0">
                                                <span className="truncate" title={`${item.account.code} - ${item.account.name}`}>
                                                    {item.account.code}
                                                </span>
                                                <span className="col-span-2 text-right text-gray-900 font-medium">
                                                    {item.debit > 0 ? formatPKR(item.debit) : '-'}
                                                </span>
                                                <span className="text-right text-gray-900 font-medium">
                                                    {item.credit > 0 ? formatPKR(item.credit) : '-'}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </div>

                {/* Chart of accounts index (1/3 width) */}
                <div className="glass-panel rounded-2xl p-6 h-fit">
                    <div className="flex items-center gap-2 mb-6">
                        <FileSpreadsheet size={18} className="text-amber-800" />
                        <h3 className="font-bold text-lg text-gray-900">Chart of Accounts</h3>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse font-mono text-xs">
                            <thead>
                                <tr className="border-b border-gray-200 text-gray-500 uppercase font-semibold">
                                    <th className="pb-2">Code</th>
                                    <th className="pb-2">Name</th>
                                    <th className="pb-2 text-right">Balance</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200">
                                {accounts.map((acc) => (
                                    <tr key={acc.id} className="hover:bg-gray-50 transition duration-150">
                                        <td className="py-2.5 font-bold text-gray-700">{acc.code}</td>
                                        <td className="py-2.5 text-gray-900 truncate max-w-[120px]" title={acc.name}>{acc.name}</td>
                                        <td className="py-2.5 text-right font-bold text-gray-900">
                                            {formatPKR(acc.balance)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Modals */}
            {/* Post Journal Entry Modal */}
            {showJournalModal && (
                <div className="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="glass-panel w-full max-w-2xl rounded-2xl overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-200">
                        <div className="p-6 border-b border-gray-200 flex items-center justify-between">
                            <h3 className="font-bold text-lg text-gray-900">Post General Ledger Journal Entry</h3>
                            <button onClick={() => setShowJournalModal(false)} className="text-gray-500 hover:text-gray-900 transition">
                                <Plus size={20} className="rotate-45" />
                            </button>
                        </div>
                        <form onSubmit={handleCreateJournalEntry} className="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Entry Date</label>
                                    <input
                                        type="date"
                                        required
                                        value={journalForm.date}
                                        onChange={(e) => setJournalForm({ ...journalForm, date: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Description</label>
                                    <input
                                        type="text"
                                        required
                                        placeholder="e.g. Accrued monthly salary, office rent deposit"
                                        value={journalForm.description}
                                        onChange={(e) => setJournalForm({ ...journalForm, description: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    />
                                    {errors.items && <p className="text-xs text-red-400 mt-1">{errors.items}</p>}
                                </div>
                            </div>

                            {/* Dynamic transaction item rows */}
                            <div className="space-y-3 pt-2">
                                <div className="flex justify-between items-center">
                                    <label className="block text-xs font-semibold uppercase text-gray-700">Debit / Credit Line Items</label>
                                    <button
                                        type="button"
                                        onClick={handleAddItemRow}
                                        className="text-xs text-amber-800 hover:text-cyan-300 font-medium font-mono flex items-center gap-1"
                                    >
                                        <Plus size={12} /> Add Ledger Row
                                    </button>
                                </div>

                                <div className="space-y-3">
                                    {journalForm.items.map((row, index) => (
                                        <div key={index} className="flex gap-3 items-end">
                                            <div className="flex-1">
                                                <label className="block text-[10px] uppercase text-gray-500 mb-1">Ledger Account</label>
                                                <select
                                                    required
                                                    value={row.account_id}
                                                    onChange={(e) => handleItemChange(index, 'account_id', e.target.value)}
                                                    className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                                >
                                                    <option value="">Choose account...</option>
                                                    {accounts.map(acc => (
                                                        <option key={acc.id} value={acc.id}>{acc.code} - {acc.name}</option>
                                                    ))}
                                                </select>
                                            </div>
                                            <div className="w-32">
                                                <label className="block text-[10px] uppercase text-gray-500 mb-1">Debit (Rs.)</label>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    value={row.debit}
                                                    onChange={(e) => handleItemChange(index, 'debit', e.target.value)}
                                                    className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                                />
                                            </div>
                                            <div className="w-32">
                                                <label className="block text-[10px] uppercase text-gray-500 mb-1">Credit (Rs.)</label>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    value={row.credit}
                                                    onChange={(e) => handleItemChange(index, 'credit', e.target.value)}
                                                    className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                                />
                                            </div>
                                            {journalForm.items.length > 2 && (
                                                <button
                                                    type="button"
                                                    onClick={() => handleRemoveItemRow(index)}
                                                    className="p-2 text-red-400 hover:text-red-300 hover:bg-gray-50 rounded-lg border border-transparent hover:border-gray-200 transition mb-0.5"
                                                >
                                                    <Trash2 size={16} />
                                                </button>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </div>

                            {/* Validation alerts */}
                            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center p-3 rounded-xl border font-mono text-xs gap-2
                                ${isBalanced
                                    ? 'bg-[#10B981]/5 border-[#10B981]/25 text-[#10B981]'
                                    : 'bg-amber-400/5 border-amber-400/25 text-amber-800'
                                }
                            ">
                                <div className="flex items-center gap-2">
                                    {isBalanced ? <CheckCircle2 size={16} /> : <AlertTriangle size={16} />}
                                    <span>
                                        {isBalanced
                                            ? 'Double-entry transaction balances successfully!'
                                            : `Transaction unbalanced: Debit (${sumDebits.toFixed(2)}) != Credit (${sumCredits.toFixed(2)}). Diff: ${difference.toFixed(2)}`
                                        }
                                    </span>
                                </div>
                                <div className="text-right">
                                    Total Value: <span className="font-bold">{sumDebits.toFixed(2)}</span>
                                </div>
                            </div>

                            <div className="border-t border-gray-200 pt-4 flex gap-4 justify-end">
                                <button
                                    type="button"
                                    onClick={() => setShowJournalModal(false)}
                                    className="px-4 py-2 rounded-xl text-gray-500 hover:text-gray-900 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={!isBalanced}
                                    className={`
                                        px-5 py-2.5 rounded-xl font-medium transition
                                        ${isBalanced
                                            ? 'bg-[#2563EB] hover:bg-blue-700 text-white shadow-lg shadow-blue-500/10 cursor-pointer'
                                            : 'bg-gray-50 text-gray-500 cursor-not-allowed border border-gray-200'
                                        }
                                    `}
                                >
                                    Post Ledger Entry
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
