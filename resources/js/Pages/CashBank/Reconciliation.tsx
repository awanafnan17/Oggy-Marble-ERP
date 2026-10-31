import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { formatPKR, formatDate } from '../../format';
import {
    Activity,
    DollarSign,
    CheckCircle2,
    AlertTriangle,
    RotateCcw,
    FileText,
    CheckSquare,
    Square
} from 'lucide-react';

interface BankAccount {
    id: number;
    account_name: string;
    bank_name: string;
}

interface ActiveReconciliation {
    id: number;
    statement_date: string;
    statement_balance: number;
    system_balance: number;
    difference: number;
}

interface Transaction {
    id: number;
    date: string;
    reference: string;
    description: string;
    debit: number;
    credit: number;
    is_reconciled: boolean;
}

interface ReconciliationProps {
    bankAccounts: BankAccount[];
    activeReconciliation: ActiveReconciliation | null;
    transactions: Transaction[];
    filters: {
        bank_account_id: string | null;
    };
    errors?: any;
}

export default function Reconciliation({
    bankAccounts,
    activeReconciliation,
    transactions,
    filters,
    errors,
}: ReconciliationProps) {
    const [bankAccountId, setBankAccountId] = useState(filters.bank_account_id || '');
    const [statementDate, setStatementDate] = useState('');
    const [statementBalance, setStatementBalance] = useState('');

    const fmt = formatPKR;

    const handleFilter = (accountId: string) => {
        setBankAccountId(accountId);
        router.get('/cash-bank/reconciliation', { bank_account_id: accountId }, { preserveState: true });
    };

    const handleOpenReconciliation = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/cash-bank/reconciliation/open', {
            bank_account_id: bankAccountId,
            statement_date: statementDate,
            statement_balance: statementBalance,
        });
    };

    const handleBulkReconcile = () => {
        if (!activeReconciliation) return;
        if (confirm('Reconcile every pending transaction through the statement date? Review the result before closing.')) {
            router.post('/cash-bank/reconciliation/bulk-reconcile-to-date', {
                reconciliation_id: activeReconciliation.id,
                statement_date: activeReconciliation.statement_date,
            }, { preserveScroll: true });
        }
    };

    const handleToggleReconcile = (transactionId: number) => {
        if (!activeReconciliation) return;
        router.post('/cash-bank/reconciliation/reconcile-transaction', {
            transaction_id: transactionId,
            reconciliation_id: activeReconciliation.id,
            statement_date: activeReconciliation.statement_date,
        }, { preserveScroll: true });
    };

    const handleCloseReconciliation = () => {
        if (!activeReconciliation) return;
        if (Math.abs(activeReconciliation.difference) > 0.0001) {
            alert('Cannot close reconciliation until difference is exactly 0.00');
            return;
        }
        if (confirm('Are you sure you want to close this reconciliation?')) {
            router.post('/cash-bank/reconciliation/close', {
                reconciliation_id: activeReconciliation.id,
                statement_date: activeReconciliation.statement_date,
            });
        }
    };

    const reconciledCount = transactions.filter(t => t.is_reconciled).length;
    const unreconciledCount = transactions.filter(t => !t.is_reconciled).length;

    return (
        <AppLayout title="Bank Statement Reconciliation">
            <div className="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
                {/* Account Selection */}
                <div className="lg:col-span-1 glass-panel rounded-2xl p-5">
                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-2">Select Bank Account</label>
                    <select
                        value={bankAccountId}
                        onChange={(e) => handleFilter(e.target.value)}
                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition mb-4"
                    >
                        <option value="">-- Choose Account --</option>
                        {bankAccounts.map((a) => (
                            <option key={a.id} value={a.id}>{a.account_name} ({a.bank_name})</option>
                        ))}
                    </select>

                    {bankAccountId && !activeReconciliation && (
                        <form onSubmit={handleOpenReconciliation} className="border-t border-gray-300 pt-4 mt-4">
                            <h3 className="text-sm font-bold text-gray-900 mb-4 flex items-center gap-2">
                                <FileText size={16} className="text-amber-800" />
                                New Reconciliation
                            </h3>
                            <div className="space-y-4">
                                <div>
                                    <label className="block text-xs text-gray-700 mb-1">Statement Date</label>
                                    <input
                                        type="date"
                                        required
                                        value={statementDate}
                                        onChange={(e) => setStatementDate(e.target.value)}
                                        className="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm text-slate-900"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs text-gray-700 mb-1">Statement Balance</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        required
                                        value={statementBalance}
                                        onChange={(e) => setStatementBalance(e.target.value)}
                                        className="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm text-slate-900"
                                    />
                                </div>
                                <button
                                    type="submit"
                                    className="w-full py-2 bg-[#06B6D4] hover:bg-[#0891B2] text-white rounded-lg font-medium shadow-lg transition"
                                >
                                    Start Reconciliation
                                </button>
                                {errors?.reconciliation && (
                                    <p className="text-xs text-red-400 mt-2">{errors.reconciliation}</p>
                                )}
                            </div>
                        </form>
                    )}
                </div>

                {/* KPI Cards (Only show if active reconciliation) */}
                {activeReconciliation && (
                    <div className="lg:col-span-3 grid grid-cols-1 sm:grid-cols-3 gap-6">
                        <div className="glass-panel p-5 rounded-2xl">
                            <span className="text-xs text-gray-500 uppercase font-mono font-semibold">Statement Balance</span>
                            <p className="text-2xl font-bold text-gray-900 mt-1">{fmt(activeReconciliation.statement_balance)}</p>
                            <p className="text-xs text-gray-500 mt-1">As of {activeReconciliation.statement_date}</p>
                        </div>
                        <div className="glass-panel p-5 rounded-2xl">
                            <span className="text-xs text-gray-500 uppercase font-mono font-semibold">System Balance</span>
                            <p className="text-2xl font-bold text-gray-900 mt-1">{fmt(activeReconciliation.system_balance)}</p>
                            <p className="text-xs text-gray-500 mt-1">Sum of reconciled items</p>
                        </div>
                        <div className={`glass-panel p-5 rounded-2xl border ${Math.abs(activeReconciliation.difference) > 0.0001 ? 'border-red-500/50' : 'border-[#10B981]/50'}`}>
                            <span className="text-xs text-gray-500 uppercase font-mono font-semibold">Difference</span>
                            <p className={`text-2xl font-bold mt-1 ${Math.abs(activeReconciliation.difference) > 0.0001 ? 'text-red-400' : 'text-[#10B981]'}`}>
                                {fmt(activeReconciliation.difference)}
                            </p>
                            <p className="text-xs mt-1 flex items-center gap-1">
                                {Math.abs(activeReconciliation.difference) > 0.0001 ? (
                                    <><AlertTriangle size={12} className="text-red-400"/> Must be zero to close</>
                                ) : (
                                    <><CheckCircle2 size={12} className="text-[#10B981]"/> Ready to close</>
                                )}
                            </p>
                        </div>
                    </div>
                )}
            </div>

            {/* Transactions Table */}
            {bankAccountId && (
                <div className="glass-panel rounded-2xl p-6">
                    <div className="flex flex-wrap items-center justify-between gap-4 mb-6">
                        <div className="flex items-center gap-3">
                            <Activity size={20} className="text-amber-800" />
                            <h3 className="font-bold text-lg text-gray-900">Transactions</h3>
                            <div className="flex gap-2 ml-4">
                                <span className="px-2 py-1 rounded bg-[#10B981]/10 text-[#10B981] text-xs font-mono">{reconciledCount} Reconciled</span>
                                <span className="px-2 py-1 rounded bg-gray-500/10 text-gray-700 text-xs font-mono">{unreconciledCount} Pending</span>
                            </div>
                        </div>

                        {activeReconciliation && (
                            <div className="flex gap-3">
                                <button
                                    onClick={handleBulkReconcile}
                                    className="flex items-center gap-2 px-4 py-2 bg-white hover:bg-slate-100 border border-slate-300 text-slate-800 rounded-lg text-sm font-medium transition"
                                >
                                    <RotateCcw size={16} />
                                    Reconcile All Through Date
                                </button>
                                <button
                                    onClick={handleCloseReconciliation}
                                    disabled={Math.abs(activeReconciliation.difference) > 0.0001}
                                    className={`flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition ${
                                        Math.abs(activeReconciliation.difference) <= 0.0001
                                            ? 'bg-[#10B981] hover:bg-[#059669] text-white shadow-lg shadow-[#10B981]/20'
                                            : 'bg-gray-600 text-gray-400 cursor-not-allowed'
                                    }`}
                                >
                                    <CheckCircle2 size={16} />
                                    Close Reconciliation
                                </button>
                            </div>
                        )}
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="border-b border-gray-200 text-gray-600 text-xs uppercase font-semibold font-mono">
                                    <th className="pb-3 w-12 text-center">Status</th>
                                    <th className="pb-3">Date</th>
                                    <th className="pb-3">Reference</th>
                                    <th className="pb-3">Description</th>
                                    <th className="pb-3 text-right">Debit</th>
                                    <th className="pb-3 text-right">Credit</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 text-sm">
                                {transactions.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="py-8 text-center text-gray-600 font-mono">
                                            No transactions found.
                                        </td>
                                    </tr>
                                ) : (
                                    transactions.map((row) => (
                                        <tr key={row.id} className={`transition duration-150 ${row.is_reconciled ? 'bg-[#10B981]/5' : 'hover:bg-white/[0.01]'}`}>
                                            <td className="py-3 text-center">
                                                <button
                                                    onClick={() => handleToggleReconcile(row.id)}
                                                    disabled={!activeReconciliation}
                                                    className={`transition ${!activeReconciliation ? 'cursor-not-allowed opacity-50' : 'hover:scale-110'}`}
                                                >
                                                    {row.is_reconciled ? (
                                                        <CheckSquare size={18} className="text-[#10B981] mx-auto" />
                                                    ) : (
                                                        <Square size={18} className="text-gray-500 mx-auto" />
                                                    )}
                                                </button>
                                            </td>
                                            <td className="py-3 text-gray-800 font-mono">{formatDate(row.date)}</td>
                                            <td className="py-3 text-gray-900 font-medium">{row.reference}</td>
                                            <td className="py-3 text-gray-700">{row.description}</td>
                                            <td className="py-3 text-right font-mono text-gray-900">
                                                {row.debit > 0 ? fmt(row.debit) : '-'}
                                            </td>
                                            <td className="py-3 text-right font-mono text-gray-900">
                                                {row.credit > 0 ? fmt(row.credit) : '-'}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
