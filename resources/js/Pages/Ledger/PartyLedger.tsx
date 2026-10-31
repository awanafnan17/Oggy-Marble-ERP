import React, { useState, useEffect } from 'react';
import { COMPANY_NAME } from '../../brand';
import { router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { formatPKR, formatDate } from '../../format';
import GlassCard from '../../components/GlassCard';
import DataTable, { Column } from '../../components/DataTable';
import SummaryCard from '../../components/SummaryCard';
import StatusBadge from '../../components/StatusBadge';
import {
    Calendar,
    Download,
    Printer,
    ArrowRightLeft,
    Filter,
    User,
    DollarSign,
    BookOpen,
    Plus
} from 'lucide-react';

interface Contact {
    id: number;
    name: string;
    type: 'customer' | 'vendor' | 'both';
    phone?: string;
    email?: string;
    address?: string;
}

interface LedgerItem {
    date: string;
    reference: string;
    description: string;
    debit: number;
    credit: number;
    running_balance: number;
}

interface AgingSummary {
    current: number;
    thirty: number;
    sixty: number;
    ninety: number;
    total: number;
}

interface PartyLedgerProps {
    contacts: Contact[];
    selectedContact: Contact | null;
    ledgerData: LedgerItem[];
    agingSummary: AgingSummary | null;
    filters: {
        contact_id: string | null;
        start_date: string | null;
        end_date: string | null;
    };
}

export default function PartyLedger({
    contacts,
    selectedContact,
    ledgerData,
    agingSummary,
    filters
}: PartyLedgerProps) {
    const [partyType, setPartyType] = useState<'customer' | 'supplier'>(
        selectedContact && (selectedContact.type === 'vendor' || selectedContact.type === 'both') ? 'supplier' : 'customer'
    );
    const [contactId, setContactId] = useState(filters.contact_id || '');
    const [startDate, setStartDate] = useState(filters.start_date || '');
    const [endDate, setEndDate] = useState(filters.end_date || '');
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [successMessage, setSuccessMessage] = useState<string | null>(null);

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        name: '',
        type: 'customer' as 'customer' | 'vendor',
        email: '',
        phone: '',
        address: '',
        opening_balance: '0.00',
        opening_balance_type: 'debit' as 'debit' | 'credit',
    });

    const handleOpenModal = (type: 'customer' | 'vendor') => {
        clearErrors();
        reset();
        setData({
            name: '',
            type: type,
            email: '',
            phone: '',
            address: '',
            opening_balance: '0.00',
            opening_balance_type: type === 'customer' ? 'debit' : 'credit',
        });
        setShowCreateModal(true);
    };

    const handleCreateContact = (e: React.FormEvent) => {
        e.preventDefault();
        post('/party-ledgers', {
            onSuccess: () => {
                setShowCreateModal(false);
                setSuccessMessage(`${data.type === 'customer' ? 'Customer' : 'Supplier'} created successfully!`);
                setTimeout(() => setSuccessMessage(null), 5000);
                reset();
            }
        });
    };

    // Reset contact if partyType changes and contact doesn't match
    useEffect(() => {
        if (contactId) {
            const currentContact = contacts.find(c => c.id === Number(contactId));
            if (currentContact) {
                const isMatch = partyType === 'customer'
                    ? (currentContact.type === 'customer' || currentContact.type === 'both')
                    : (currentContact.type === 'vendor' || currentContact.type === 'both');
                if (!isMatch) {
                    setContactId('');
                }
            }
        }
    }, [partyType]);

    // Submit filters on change of contact or date
    const handleFilterChange = (newContactId = contactId, newStart = startDate, newEnd = endDate) => {
        router.get('/party-ledgers', {
            contact_id: newContactId || null,
            start_date: newStart || null,
            end_date: newEnd || null
        }, {
            preserveState: true,
            replace: true
        });
    };

    // Filter contacts based on toggle choice
    const filteredContacts = contacts.filter(c => {
        if (partyType === 'customer') {
            return c.type === 'customer' || c.type === 'both';
        } else {
            return c.type === 'vendor' || c.type === 'both';
        }
    });

    // Local formatPKR removed

    // Table calculations
    const totalDebit = ledgerData.reduce((sum, item) => sum + item.debit, 0);
    const totalCredit = ledgerData.reduce((sum, item) => sum + item.credit, 0);
    const closingBalance = ledgerData.length > 0 ? ledgerData[ledgerData.length - 1].running_balance : 0;
    const openingBalance = ledgerData.length > 0 ? (ledgerData[0].running_balance - (ledgerData[0].debit - ledgerData[0].credit)) : 0;

    // CSV Export Handler
    const handleExportCSV = () => {
        if (ledgerData.length === 0 || !selectedContact) return;

        const headers = ['Date', 'Reference', 'Description', 'Debit (PKR)', 'Credit (PKR)', 'Running Balance (PKR)'];
        const csvRows = [
            [`Ledger Statement for ${selectedContact.name}`],
            [`Period: ${startDate || 'All-Time'} to ${endDate || 'Present'}`],
            [],
            headers.join(','),
            ...ledgerData.map(row => [
                row.date,
                `"${row.reference}"`,
                `"${row.description}"`,
                row.debit,
                row.credit,
                row.running_balance
            ].join(','))
        ];

        const csvString = csvRows.join('\n');
        const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.setAttribute('href', url);
        link.setAttribute('download', `${selectedContact.name.replace(/\s+/g, '_')}_Ledger_${Date.now()}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    // Print Handler
    const handlePrint = () => {
        window.print();
    };

    const tableColumns: Column<LedgerItem>[] = [
        { header: 'Date', accessor: 'date', className: 'text-gray-800' },
        { header: 'Reference', accessor: (row) => <span className="text-gray-900 font-medium">{row.reference}</span> },
        { header: 'Description', accessor: 'description', className: 'text-gray-400 font-sans' },
        {
            header: 'Debit (Dr)',
            accessor: (row) => row.debit > 0 ? formatPKR(row.debit) : '-',
            align: 'right',
            className: 'text-red-400'
        },
        {
            header: 'Credit (Cr)',
            accessor: (row) => row.credit > 0 ? formatPKR(row.credit) : '-',
            align: 'right',
            className: 'text-emerald-800'
        },
        {
            header: 'Running Balance',
            accessor: (row) => (
                <span className={`font-bold ${row.running_balance >= 0 ? 'text-amber-800' : 'text-red-400'}`}>
                    {formatPKR(row.running_balance)}
                </span>
            ),
            align: 'right'
        }
    ];

    return (
        <AppLayout title="Subledger Party Ledger Accounts">
            {successMessage && (
                <div className="mb-6 p-4 rounded-xl border border-emerald-500/20 bg-emerald-500/10 text-emerald-800 text-xs font-mono flex items-center justify-between animate-in fade-in slide-in-from-top-4 duration-200 print:hidden">
                    <span>{successMessage}</span>
                    <button onClick={() => setSuccessMessage(null)} className="hover:text-white font-bold ml-4">✕</button>
                </div>
            )}
            {/* Top Filter Panel */}
            <GlassCard className="p-6 mb-8 print:hidden" hoverGlow glowColor="blue">
                <div className="flex flex-col xl:flex-row gap-6 items-end justify-between">
                    {/* Filter Elements */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-6 w-full">
                        {/* Customer/Supplier Selector Toggle */}
                        <div>
                            <label className="block text-xs font-semibold uppercase text-gray-700 mb-2 font-mono">
                                Ledger Sub-Category
                            </label>
                            <div className="flex rounded-xl bg-white p-[3px] border border-gray-200">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setPartyType('customer');
                                    }}
                                    className={`
                                        flex-1 text-center py-2 text-xs font-semibold rounded-lg transition-all duration-300
                                        ${partyType === 'customer'
                                            ? 'bg-[#2563EB] text-white shadow-[0_0_10px_rgba(37,99,235,0.2)]'
                                            : 'text-gray-500 hover:text-gray-900'
                                        }
                                    `}
                                >
                                    Customer Accounts
                                </button>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setPartyType('supplier');
                                    }}
                                    className={`
                                        flex-1 text-center py-2 text-xs font-semibold rounded-lg transition-all duration-300
                                        ${partyType === 'supplier'
                                            ? 'bg-[#2563EB] text-white shadow-[0_0_10px_rgba(37,99,235,0.2)]'
                                            : 'text-gray-500 hover:text-gray-900'
                                        }
                                    `}
                                >
                                    Supplier Accounts
                                </button>
                            </div>
                        </div>

                        {/* Contact Selector */}
                        <div>
                            <label className="block text-xs font-semibold uppercase text-gray-700 mb-2 font-mono">
                                Select Ledger Party
                            </label>
                            <select
                                value={contactId}
                                onChange={(e) => {
                                    setContactId(e.target.value);
                                    handleFilterChange(e.target.value);
                                }}
                                className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition h-10"
                            >
                                <option value="">Choose party account...</option>
                                {filteredContacts.map(c => (
                                    <option key={c.id} value={c.id}>{c.name}</option>
                                ))}
                            </select>
                        </div>

                        {/* Date From */}
                        <div>
                            <label className="block text-xs font-semibold uppercase text-gray-700 mb-2 font-mono">
                                Start Date
                            </label>
                            <input
                                type="date"
                                value={startDate}
                                onChange={(e) => {
                                    setStartDate(e.target.value);
                                    handleFilterChange(contactId, e.target.value);
                                }}
                                className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition h-10 font-mono"
                            />
                        </div>

                        {/* Date To */}
                        <div>
                            <label className="block text-xs font-semibold uppercase text-gray-700 mb-2 font-mono">
                                End Date
                            </label>
                            <input
                                type="date"
                                value={endDate}
                                onChange={(e) => {
                                    setEndDate(e.target.value);
                                    handleFilterChange(contactId, startDate, e.target.value);
                                }}
                                className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition h-10 font-mono"
                            />
                        </div>
                    </div>

                    {/* Export / Print / Add Buttons */}
                    <div className="flex flex-wrap gap-3 shrink-0">
                        <button
                            onClick={handleExportCSV}
                            disabled={ledgerData.length === 0}
                            className="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-300 hover:border-[#06B6D4]/40 bg-gray-50 hover:bg-[#06B6D4]/5 text-xs text-gray-800 hover:text-white transition duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <Download size={14} />
                            Export CSV
                        </button>
                        <button
                            onClick={handlePrint}
                            disabled={ledgerData.length === 0}
                            className="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-300 hover:border-blue-500/40 bg-gray-50 hover:bg-blue-500/5 text-xs text-gray-800 hover:text-white transition duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <Printer size={14} />
                            Print
                        </button>
                        <button
                            onClick={() => handleOpenModal('customer')}
                            className="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-xs font-semibold text-white shadow-lg hover:shadow-blue-500/20 cursor-pointer transition duration-200"
                        >
                            <Plus size={14} />
                            Add Customer
                        </button>
                        <button
                            onClick={() => handleOpenModal('vendor')}
                            className="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-cyan-600 hover:bg-cyan-700 text-xs font-semibold text-white shadow-lg hover:shadow-cyan-500/20 cursor-pointer transition duration-200"
                        >
                            <Plus size={14} />
                            Add Supplier
                        </button>
                    </div>
                </div>
            </GlassCard>

            {/* Print Header */}
            {selectedContact && (
                <div className="hidden print:block mb-8 text-black border-b pb-4">
                    <h1 className="text-2xl font-bold">{COMPANY_NAME} ERP Ledger Statement</h1>
                    <div className="grid grid-cols-2 gap-4 mt-4 text-sm">
                        <div>
                            <p><strong>Party Name:</strong> {selectedContact.name}</p>
                            <p><strong>Type:</strong> {partyType === 'customer' ? 'Customer' : 'Supplier'}</p>
                            {selectedContact.phone && <p><strong>Phone:</strong> {selectedContact.phone}</p>}
                            {selectedContact.address && <p><strong>Address:</strong> {selectedContact.address}</p>}
                        </div>
                        <div className="text-right">
                            <p><strong>Period:</strong> {startDate || 'All-Time'} to {endDate || 'Present'}</p>
                            <p><strong>Print Date:</strong> {formatDate(new Date().toISOString())}</p>
                        </div>
                    </div>
                </div>
            )}

            {/* Statement Split Layout */}
            {selectedContact ? (
                <div className="grid grid-cols-1 xl:grid-cols-4 gap-8">
                    {/* Left: Ledger Statement Table */}
                    <div className="xl:col-span-3 space-y-6">
                        <div className="flex justify-between items-center print:hidden">
                            <h3 className="text-lg font-bold text-gray-900 flex items-center gap-2">
                                <BookOpen size={18} className="text-amber-800" />
                                Statement of Account
                            </h3>
                            <span className="text-xs text-gray-700 font-mono">
                                Total Entries: {ledgerData.length}
                            </span>
                        </div>

                        <DataTable
                            columns={tableColumns}
                            data={ledgerData}
                            keyExtractor={(row, idx) => `${row.reference}-${idx}`}
                            emptyMessage="No transactions yet for this party."
                        />

                        {/* Sticky Totals Footer */}
                        <div className="p-4 rounded-xl border border-gray-200 bg-[#F9FAFB] flex flex-wrap gap-6 justify-end font-mono text-xs text-gray-700">
                            <div className="flex gap-2 items-center">
                                <span>Total Debits:</span>
                                <span className="font-bold text-red-400 text-sm">{formatPKR(totalDebit)}</span>
                            </div>
                            <div className="flex gap-2 items-center">
                                <span>Total Credits:</span>
                                <span className="font-bold text-emerald-800 text-sm">{formatPKR(totalCredit)}</span>
                            </div>
                            <div className="flex gap-2 items-center border-l border-gray-300 pl-6">
                                <span>Closing balance:</span>
                                <span className={`font-bold text-sm ${closingBalance >= 0 ? 'text-amber-800' : 'text-red-400'}`}>
                                    {formatPKR(closingBalance)}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Right: Balance Metrics Summary */}
                    <div className="space-y-6 print:hidden">
                        <SummaryCard
                            openingBalance={openingBalance}
                            currentBalance={closingBalance}
                            agingSummary={agingSummary}
                            isCustomer={partyType === 'customer'}
                        />
                    </div>
                </div>
            ) : (
                <GlassCard className="py-16 text-center" hoverGlow glowColor="cyan">
                    <User size={40} className="mx-auto text-gray-600 mb-4 animate-pulse" />
                    <h3 className="text-lg font-bold text-gray-900 mb-1">No Party Selected</h3>
                    <p className="text-sm text-gray-700 max-w-md mx-auto">
                        Please choose a customer or supplier from the top selector dropdown to view their statement, ledger transactions, and aging buckets analysis.
                    </p>
                </GlassCard>
            )}

            {showCreateModal && (
            <div className="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div className="glass-panel w-full max-w-lg rounded-2xl overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-200">
                    <div className="p-6 border-b border-gray-200 flex items-center justify-between">
                        <h3 className="font-bold text-lg text-gray-900">
                            Add New {data.type === 'customer' ? 'Customer' : 'Supplier'}
                        </h3>
                        <button onClick={() => setShowCreateModal(false)} className="text-gray-500 hover:text-gray-900 transition">
                            <Plus size={20} className="rotate-45" />
                        </button>
                    </div>
                    <form onSubmit={handleCreateContact} className="p-6 space-y-4 max-h-[85vh] overflow-y-auto">
                        <div>
                            <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Name *</label>
                            <input
                                type="text"
                                required
                                placeholder="Enter company or individual name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                            />
                            {errors.name && <p className="text-xs text-red-400 mt-1">{errors.name}</p>}
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Phone</label>
                                <input
                                    type="text"
                                    placeholder="e.g. +92 300 1234567"
                                    value={data.phone}
                                    onChange={(e) => setData('phone', e.target.value)}
                                    className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                />
                                {errors.phone && <p className="text-xs text-red-400 mt-1">{errors.phone}</p>}
                            </div>
                            <div>
                                <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Email</label>
                                <input
                                    type="email"
                                    placeholder="e.g. contact@example.test"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                />
                                {errors.email && <p className="text-xs text-red-400 mt-1">{errors.email}</p>}
                            </div>
                        </div>

                        <div>
                            <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Address</label>
                            <textarea
                                placeholder="Enter physical address"
                                value={data.address}
                                onChange={(e) => setData('address', e.target.value)}
                                className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition h-20 resize-none"
                            />
                            {errors.address && <p className="text-xs text-red-400 mt-1">{errors.address}</p>}
                        </div>

                        <div className="grid grid-cols-2 gap-4 border-t border-gray-200 pt-4">
                            <div>
                                <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Opening Balance (PKR)</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.opening_balance}
                                    onChange={(e) => setData('opening_balance', e.target.value)}
                                    className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                />
                                {errors.opening_balance && <p className="text-xs text-red-400 mt-1">{errors.opening_balance}</p>}
                            </div>
                            <div>
                                <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Balance Type</label>
                                <select
                                    value={data.opening_balance_type}
                                    onChange={(e) => setData('opening_balance_type', e.target.value as 'debit' | 'credit')}
                                    className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition h-[42px]"
                                >
                                    <option value="debit">Debit (Receivable)</option>
                                    <option value="credit">Credit (Payable)</option>
                                </select>
                                {errors.opening_balance_type && <p className="text-xs text-red-400 mt-1">{errors.opening_balance_type}</p>}
                            </div>
                        </div>

                        <div className="border-t border-gray-200 pt-4 flex gap-4 justify-end">
                            <button
                                type="button"
                                onClick={() => setShowCreateModal(false)}
                                className="px-4 py-2 rounded-xl text-gray-500 hover:text-gray-900 transition"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={processing}
                                className="px-5 py-2.5 bg-[#2563EB] hover:bg-blue-700 disabled:bg-white shadow-sm text-white rounded-xl font-medium shadow-lg hover:shadow-blue-500/10 cursor-pointer transition"
                            >
                                {processing ? 'Saving...' : 'Save Contact'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        )}
        </AppLayout>
    );
}
