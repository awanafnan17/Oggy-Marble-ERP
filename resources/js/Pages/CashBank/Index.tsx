import React, { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { formatPKR, formatDate } from '../../format';
import GlassCard from '../../components/GlassCard';
import KPICard from '../../components/KPICard';
import DataTable, { Column } from '../../components/DataTable';
import SlideModal from '../../components/SlideModal';
import StatusBadge from '../../components/StatusBadge';
import {
    Wallet,
    Building2,
    TrendingUp,
    TrendingDown,
    ArrowLeftRight,
    ArrowDownLeft,
    ArrowUpRight,
    Plus,
    Calendar,
    Coins,
    UserCheck,
    Truck,
    X,
    AlertCircle,
    Upload,
    Trash2,
    ExternalLink,
    Image as ImageIcon
} from 'lucide-react';

interface BankAccount {
    id: number;
    account_name: string;
    account_number?: string;
    bank_name?: string;
    type: 'cash' | 'bank';
    gl_account_code?: string;
    balance: number;
}

interface BankTransaction {
    id: number;
    bank_account_id: number;
    bank_account?: {
        account_name: string;
        type: 'cash' | 'bank';
    };
    amount: number;
    type: 'deposit' | 'withdrawal';
    date: string;
    reference_type: string;
    reference_id?: number;
    notes?: string;
    status?: string;
    proof_image_path?: string;
    proof_image_size?: number;
    proof_image_mime?: string;
    voided_at?: string;
    void_reason?: string;
}

interface Account {
    id: number;
    code: string;
    name: string;
    type: string;
}

interface Contact {
    id: number;
    name: string;
    type: 'customer' | 'vendor' | 'both';
}

interface UnpaidInvoice {
    id: number;
    invoice_number: string;
    contact_id: number;
    contact: Contact;
    net_amount: number;
    remaining_balance: number;
    issue_date: string;
}

interface UnpaidBill {
    id: number;
    bill_number: string;
    contact_id: number;
    contact: Contact;
    net_amount: number;
    remaining_balance: number;
    issue_date: string;
}

interface PakistanBankItem {
    id: number;
    name: string;
    short_name?: string;
    is_islamic: boolean;
}

interface PakistanBankGroup {
    label: string;
    banks: PakistanBankItem[];
}

interface CashBankProps {
    bankAccounts: BankAccount[];
    recentTransactions: BankTransaction[];
    accounts: Account[];
    contacts: Contact[];
    unpaidInvoices: UnpaidInvoice[];
    unpaidBills: UnpaidBill[];
    pakistanBanks: PakistanBankGroup[];
}

export default function CashBankIndex({
    bankAccounts = [],
    recentTransactions = [],
    accounts = [],
    contacts = [],
    unpaidInvoices = [],
    unpaidBills = [],
    pakistanBanks = []
}: CashBankProps) {
    const { auth } = usePage().props as any;
    const userPermissions = auth?.user?.permissions || [];

    const safeBankAccounts = Array.isArray(bankAccounts) ? bankAccounts : [];
    const safeRecentTransactions = Array.isArray(recentTransactions) ? recentTransactions : [];
    const safeAccounts = Array.isArray(accounts) ? accounts : [];
    const safeContacts = Array.isArray(contacts) ? contacts : [];
    const safeUnpaidInvoices = Array.isArray(unpaidInvoices) ? unpaidInvoices : [];
    const safeUnpaidBills = Array.isArray(unpaidBills) ? unpaidBills : [];
    const safePakistanBanks = Array.isArray(pakistanBanks) ? pakistanBanks : [];

    const [activeModal, setActiveModal] = useState<'deposit' | 'withdrawal' | 'transfer' | 'collection' | 'payment' | 'newAccount' | null>(null);
    const [errors, setErrors] = useState<any>({});
    const [voidingTransaction, setVoidingTransaction] = useState<BankTransaction | null>(null);
    const [voidReason, setVoidReason] = useState('');
    const [voidError, setVoidError] = useState('');
    const [viewingProofUrl, setViewingProofUrl] = useState<string | null>(null);

    // Transaction proof preview states
    const [txProofPreview, setTxProofPreview] = useState<string | null>(null);
    const [transferProofPreview, setTransferProofPreview] = useState<string | null>(null);

    // New Account form state
    const [newAccountForm, setNewAccountForm] = useState({
        account_name: '',
        account_number: '',
        bank_name: '',
        type: 'bank' as 'bank' | 'cash',
        gl_code: '',
    });
    const [bankSearchTerm, setBankSearchTerm] = useState('');
    const [showBankDropdown, setShowBankDropdown] = useState(false);

    // Transaction form state (Deposit / Withdrawal)
    const [txForm, setTxForm] = useState<{
        bank_account_id: string;
        date: string;
        amount: string;
        description: string;
        contra_account_id: string;
        proof_image: File | null;
    }>({
        bank_account_id: '',
        date: new Date().toISOString().substring(0, 10),
        amount: '',
        description: '',
        contra_account_id: '',
        proof_image: null
    });

    // Transfer form state
    const [transferForm, setTransferForm] = useState<{
        from_bank_account_id: string;
        to_bank_account_id: string;
        amount: string;
        date: string;
        description: string;
        proof_image: File | null;
    }>({
        from_bank_account_id: '',
        to_bank_account_id: '',
        amount: '',
        date: new Date().toISOString().substring(0, 10),
        description: '',
        proof_image: null
    });

    // Customer Collection form state
    const [collectionForm, setCollectionForm] = useState({
        contact_id: '',
        payment_date: new Date().toISOString().substring(0, 10),
        amount: '',
        payment_method: 'bank_transfer',
        bank_account_id: '',
        invoice_id: '',
        notes: ''
    });

    // Supplier Payment form state
    const [paymentForm, setPaymentForm] = useState({
        contact_id: '',
        payment_date: new Date().toISOString().substring(0, 10),
        amount: '',
        payment_method: 'bank_transfer',
        bank_account_id: '',
        bill_id: '',
        notes: ''
    });

    // Calculate dynamic KPIs
    const cashInHand = safeBankAccounts.filter(a => a.type === 'cash').reduce((sum, a) => sum + Number(a.balance || 0), 0);
    const totalBankBalance = safeBankAccounts.filter(a => a.type === 'bank').reduce((sum, a) => sum + Number(a.balance || 0), 0);

    const todayStr = new Date().toISOString().substring(0, 10);
    const todayCollections = safeRecentTransactions
        .filter(t => t.date === todayStr && t.type === 'deposit')
        .reduce((sum, t) => sum + Number(t.amount || 0), 0);

    const todayPayments = safeRecentTransactions
        .filter(t => t.date === todayStr && t.type === 'withdrawal')
        .reduce((sum, t) => sum + Number(t.amount || 0), 0);

    // Dynamic filtering for collections / payments
    const filteredInvoices = safeUnpaidInvoices.filter(inv => inv.contact_id === Number(collectionForm.contact_id));
    const filteredBills = safeUnpaidBills.filter(bill => bill.contact_id === Number(paymentForm.contact_id));

    // Handle form submissions
    const handleTxSubmit = (e: React.FormEvent, type: 'deposit' | 'withdrawal') => {
        e.preventDefault();
        router.post('/cash-bank/transactions', { ...txForm, type }, {
            forceFormData: true,
            onSuccess: () => {
                setActiveModal(null);
                if (txProofPreview) URL.revokeObjectURL(txProofPreview);
                setTxProofPreview(null);
                setTxForm({ bank_account_id: '', date: new Date().toISOString().substring(0, 10), amount: '', description: '', contra_account_id: '', proof_image: null });
                setErrors({});
            },
            onError: (err) => setErrors(err)
        });
    };

    const handleTransferSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/cash-bank/transfers', transferForm, {
            forceFormData: true,
            onSuccess: () => {
                setActiveModal(null);
                if (transferProofPreview) URL.revokeObjectURL(transferProofPreview);
                setTransferProofPreview(null);
                setTransferForm({ from_bank_account_id: '', to_bank_account_id: '', amount: '', date: new Date().toISOString().substring(0, 10), description: '', proof_image: null });
                setErrors({});
            },
            onError: (err) => setErrors(err)
        });
    };

    const handleCollectionSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/payments/customer', collectionForm, {
            onSuccess: () => {
                setActiveModal(null);
                setCollectionForm({ contact_id: '', payment_date: new Date().toISOString().substring(0, 10), amount: '', payment_method: 'bank_transfer', bank_account_id: '', invoice_id: '', notes: '' });
                setErrors({});
            },
            onError: (err) => setErrors(err)
        });
    };

    const handlePaymentSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/payments/supplier', paymentForm, {
            onSuccess: () => {
                setActiveModal(null);
                setPaymentForm({ contact_id: '', payment_date: new Date().toISOString().substring(0, 10), amount: '', payment_method: 'bank_transfer', bank_account_id: '', bill_id: '', notes: '' });
                setErrors({});
            },
            onError: (err) => setErrors(err)
        });
    };

    const handleVoidPayment = (e: React.FormEvent) => {
        e.preventDefault();
        setVoidError('');
        if (!voidReason || voidReason.length < 5) {
            setVoidError('Void reason must be at least 5 characters long.');
            return;
        }
        if (!voidingTransaction || voidingTransaction.reference_type !== 'payment') {
            setVoidError('Only payment cashflows can be voided.');
            return;
        }

        router.post(`/payments/${voidingTransaction.reference_id}/void`, {
            void_reason: voidReason
        }, {
            onSuccess: () => {
                setVoidingTransaction(null);
                setVoidReason('');
                setVoidError('');
            },
            onError: (err: any) => {
                setVoidError(err.error || err.void_reason || 'Reversal failed.');
            }
        });
    };

    // New Account form submit
    const handleNewAccountSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/cash-bank/accounts', newAccountForm, {
            onSuccess: () => {
                setActiveModal(null);
                setNewAccountForm({ account_name: '', account_number: '', bank_name: '', type: 'bank', gl_code: '' });
                setBankSearchTerm('');
                setErrors({});
            },
            onError: (err) => setErrors(err)
        });
    };

    // Filtered Pakistan banks based on search term
    const filteredBankGroups = safePakistanBanks?.map(group => ({
        ...group,
        banks: (group.banks || []).filter(b =>
            b.name.toLowerCase().includes(bankSearchTerm.toLowerCase()) ||
            (b.short_name && b.short_name.toLowerCase().includes(bankSearchTerm.toLowerCase()))
        )
    })).filter(group => group.banks.length > 0) || [];

    const txTableColumns: Column<BankTransaction>[] = [
        { header: 'Date', accessor: (row) => formatDate(row.date), className: 'text-gray-800' },
        {
            header: 'Account',
            accessor: (row) => row.bank_account?.account_name || 'N/A',
            className: 'text-white font-medium'
        },
        {
            header: 'Type',
            accessor: (row) => <StatusBadge status={row.type} />
        },
        { header: 'Reference ID', accessor: (row) => row.reference_type ? `${row.reference_type} #${row.reference_id || ''}` : 'Manual Entry' },
        { header: 'Description', accessor: 'notes', className: 'text-gray-400 font-sans' },
        {
            header: 'Debit (Inflow)',
            accessor: (row) => row.type === 'deposit' ? formatPKR(row.amount) : '-',
            align: 'right',
            className: 'text-emerald-800 font-bold'
        },
        {
            header: 'Credit (Outflow)',
            accessor: (row) => row.type === 'withdrawal' ? formatPKR(row.amount) : '-',
            align: 'right',
            className: 'text-red-400 font-bold'
        },
        {
            header: 'Proof',
            accessor: (row) => {
                if (!row.proof_image_path) {
                    return <span className="text-gray-400 text-xs">-</span>;
                }
                return (
                    <button
                        type="button"
                        onClick={() => setViewingProofUrl(`/cash-bank/transactions/${row.id}/proof`)}
                        className="inline-flex items-center gap-1.5 px-2.5 py-1 bg-cyan-50 hover:bg-cyan-100 text-cyan-700 border border-cyan-200 rounded-lg text-xs font-semibold transition"
                        title="View transaction proof screenshot"
                    >
                        <ImageIcon size={13} />
                        <span>Proof</span>
                    </button>
                );
            },
            align: 'center'
        },
        {
            header: 'Actions',
            accessor: (row) => {
                const isVoided = !!row.voided_at;
                const isPayment = row.reference_type === 'payment';
                const paymentId = row.reference_id;

                if (isVoided) {
                    return (
                        <span className="px-2 py-0.5 bg-red-500/15 border border-red-500/20 text-red-400 text-[10px] uppercase font-semibold font-mono rounded">
                            Voided
                        </span>
                    );
                }

                if (!isPayment || !paymentId) {
                    return null;
                }

                const hasPermission = row.type === 'deposit'
                    ? userPermissions.includes('manage_sales')
                    : userPermissions.includes('manage_purchases');

                if (!hasPermission) {
                    return null;
                }

                return (
                    <button
                        onClick={() => {
                            setVoidingTransaction(row);
                            setVoidReason('');
                            setVoidError('');
                        }}
                        className="px-2.5 py-1 bg-red-600/10 hover:bg-red-600 text-red-400 hover:text-white border border-red-500/15 hover:border-red-650 text-xs font-semibold rounded-lg transition duration-150"
                    >
                        Void
                    </button>
                );
            },
            align: 'right'
        }
    ];

    return (
        <AppLayout title="Cash & Bank Accounts Subledger">
            {/* Top Metrics Row */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <KPICard
                    label="Cash in Hand"
                    value={formatPKR(cashInHand)}
                    glowColor="emerald"
                    icon={Wallet}
                    subLabel="Cash balances across counters"
                />
                <KPICard
                    label="Total Bank Balance"
                    value={formatPKR(totalBankBalance)}
                    glowColor="blue"
                    icon={Building2}
                    subLabel="Total funds in clearing accounts"
                />
                <KPICard
                    label="Today Collections"
                    value={formatPKR(todayCollections)}
                    glowColor="cyan"
                    icon={TrendingUp}
                    subLabel="Deposits and AR receipts today"
                />
                <KPICard
                    label="Today Payments"
                    value={formatPKR(todayPayments)}
                    glowColor="red"
                    icon={TrendingDown}
                    subLabel="Disbursements and bills paid today"
                />
            </div>

            {/* Quick Action Toolbar */}
            <div className="flex flex-wrap gap-4 mb-8">
                <button
                    onClick={() => {
                        setNewAccountForm({ account_name: '', account_number: '', bank_name: '', type: 'bank', gl_code: '' });
                        setBankSearchTerm('');
                        setActiveModal('newAccount');
                    }}
                    className="flex items-center gap-2 px-5 py-3 border border-purple-500/20 bg-purple-500/5 hover:bg-purple-500/15 text-purple-400 rounded-xl text-sm font-semibold transition-all duration-300"
                >
                    <Plus size={16} />
                    New Account
                </button>
                <button
                    onClick={() => {
                        setTxForm(prev => ({ ...prev, contra_account_id: '' }));
                        setActiveModal('deposit');
                    }}
                    className="flex items-center gap-2 px-5 py-3 border border-[#10B981]/20 bg-[#10B981]/5 hover:bg-[#10B981]/15 text-[#10B981] rounded-xl text-sm font-semibold transition-all duration-300"
                >
                    <ArrowDownLeft size={16} />
                    Cash Deposit
                </button>
                <button
                    onClick={() => {
                        setTxForm(prev => ({ ...prev, contra_account_id: '' }));
                        setActiveModal('withdrawal');
                    }}
                    className="flex items-center gap-2 px-5 py-3 border border-red-500/20 bg-red-500/5 hover:bg-red-500/15 text-red-400 rounded-xl text-sm font-semibold transition-all duration-300"
                >
                    <ArrowUpRight size={16} />
                    Cash Withdrawal
                </button>
                <button
                    onClick={() => setActiveModal('transfer')}
                    className="flex items-center gap-2 px-5 py-3 border border-blue-500/20 bg-blue-500/5 hover:bg-blue-500/15 text-amber-700 rounded-xl text-sm font-semibold transition-all duration-300"
                >
                    <ArrowLeftRight size={16} />
                    Inter-Bank Transfer
                </button>
                <button
                    onClick={() => setActiveModal('collection')}
                    className="flex items-center gap-2 px-5 py-3 border border-[#06B6D4]/20 bg-[#06B6D4]/5 hover:bg-[#06B6D4]/15 text-amber-800 rounded-xl text-sm font-semibold transition-all duration-300 shadow-[0_0_15px_rgba(6,182,212,0.1)]"
                >
                    <UserCheck size={16} />
                    Customer Collection
                </button>
                <button
                    onClick={() => setActiveModal('payment')}
                    className="flex items-center gap-2 px-5 py-3 border border-amber-500/20 bg-amber-500/5 hover:bg-amber-500/15 text-amber-800 rounded-xl text-sm font-semibold transition-all duration-300"
                >
                    <Truck size={16} />
                    Supplier Payment
                </button>
            </div>

            {/* Bank Accounts Grid */}
            <div className="mb-8">
                <h3 className="text-lg font-bold text-gray-900 mb-4">Cash / Bank Accounts Registry</h3>
                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                    {safeBankAccounts.map((acc) => (
                        <GlassCard key={acc.id} hoverGlow glowColor={acc.type === 'cash' ? 'emerald' : 'blue'} className="p-6">
                            <div className="flex justify-between items-start mb-4">
                                <div>
                                    <h4 className="font-bold text-gray-900 text-base">{acc.account_name}</h4>
                                    <span className="text-xs text-gray-500 font-mono">
                                        {acc.bank_name || 'Physical Cash Counter'}
                                    </span>
                                </div>
                                <span className={`
                                    px-2.5 py-0.5 rounded text-[10px] uppercase font-semibold font-mono border
                                    ${acc.type === 'cash' ? 'bg-emerald-500/15 border-emerald-500/20 text-emerald-800' : 'bg-blue-500/15 border-blue-500/20 text-amber-700'}
                                `}>
                                    {acc.type}
                                </span>
                            </div>

                            <div className="border-t border-gray-200 pt-4 flex justify-between items-end">
                                <div>
                                    <span className="text-[10px] text-gray-500 uppercase tracking-wider">Account Number</span>
                                    <p className="text-xs text-gray-800 font-mono mt-0.5">{acc.account_number || 'N/A'}</p>
                                </div>
                                <div className="text-right">
                                    <span className="text-[10px] text-gray-500 uppercase tracking-wider">Available Balance</span>
                                    <p className="text-lg font-bold text-gray-900 font-mono mt-0.5">{formatPKR(acc.balance)}</p>
                                </div>
                            </div>
                        </GlassCard>
                    ))}
                </div>
            </div>

            {/* Transactions Table */}
            <div>
                <h3 className="text-lg font-bold text-gray-900 mb-4">Recent Subledger Cashflow Logs</h3>
                <DataTable
                    columns={txTableColumns}
                    data={safeRecentTransactions}
                    keyExtractor={(row) => row.id}
                    emptyMessage="No banking logs found. Perform a cash/bank transaction or receive collections to view."
                />
            </div>

            {/* Modals */}
            {/* Deposit / Withdrawal Drawer */}
            <SlideModal
                isOpen={activeModal === 'deposit' || activeModal === 'withdrawal'}
                onClose={() => setActiveModal(null)}
                title={activeModal === 'deposit' ? 'Cash / Bank Deposit Entry' : 'Cash / Bank Withdrawal Entry'}
            >
                <form onSubmit={(e) => handleTxSubmit(e, activeModal === 'deposit' ? 'deposit' : 'withdrawal')} className="space-y-6">
                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Target Account</label>
                        <select
                            required
                            value={txForm.bank_account_id}
                            onChange={(e) => setTxForm({ ...txForm, bank_account_id: e.target.value })}
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                        >
                            <option value="">Select cash/bank account...</option>
                            {safeBankAccounts.map((acc) => (
                                <option key={acc.id} value={acc.id}>
                                    {acc.account_name} ({acc.bank_name || 'Cash'})
                                </option>
                            ))}
                        </select>
                        {errors.bank_account_id && <p className="text-xs text-red-400 mt-1">{errors.bank_account_id}</p>}
                    </div>

                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Transaction Date</label>
                        <input
                            type="date"
                            required
                            value={txForm.date}
                            onChange={(e) => setTxForm({ ...txForm, date: e.target.value })}
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                        />
                        {errors.date && <p className="text-xs text-red-400 mt-1">{errors.date}</p>}
                    </div>

                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Amount (PKR)</label>
                        <input
                            type="number"
                            required
                            min="0.01"
                            step="0.01"
                            value={txForm.amount}
                            onChange={(e) => setTxForm({ ...txForm, amount: e.target.value })}
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                        />
                        {errors.amount && <p className="text-xs text-red-400 mt-1">{errors.amount}</p>}
                    </div>

                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Offset (Contra) G/L Account</label>
                        <select
                            required
                            value={txForm.contra_account_id}
                            onChange={(e) => setTxForm({ ...txForm, contra_account_id: e.target.value })}
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition text-xs"
                        >
                            <option value="">Choose contra double-entry account...</option>
                            {safeAccounts.map((acc) => (
                                <option key={acc.id} value={acc.id}>
                                    {acc.code} - {acc.name} ({acc.type})
                                </option>
                            ))}
                        </select>
                        {errors.contra_account_id && <p className="text-xs text-red-400 mt-1">{errors.contra_account_id}</p>}
                    </div>

                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Description / Memo</label>
                        <textarea
                            value={txForm.description}
                            onChange={(e) => setTxForm({ ...txForm, description: e.target.value })}
                            rows={3}
                            placeholder="Details of the deposit or expense..."
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                        />
                        {errors.description && <p className="text-xs text-red-400 mt-1">{errors.description}</p>}
                    </div>

                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">
                            Screenshot / Payment Proof <span className="text-gray-400 font-normal lowercase">(optional)</span>
                        </label>
                        {!txProofPreview ? (
                            <div className="flex items-center gap-3">
                                <label className="flex items-center gap-2 px-4 py-2.5 bg-gray-50 hover:bg-gray-100 border border-dashed border-gray-300 rounded-xl text-xs font-medium text-gray-700 cursor-pointer transition">
                                    <Upload size={14} className="text-gray-500" />
                                    <span>Choose Image / Screenshot</span>
                                    <input
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        onChange={(e) => {
                                            const file = e.target.files?.[0];
                                            if (file) {
                                                setTxForm({ ...txForm, proof_image: file });
                                                setTxProofPreview(URL.createObjectURL(file));
                                            }
                                        }}
                                        className="hidden"
                                    />
                                </label>
                                <span className="text-[11px] text-gray-500">JPG, PNG, WebP (max 5MB)</span>
                            </div>
                        ) : (
                            <div className="flex items-center gap-3 p-3 bg-gray-50 border border-gray-200 rounded-xl">
                                <img
                                    src={txProofPreview}
                                    alt="Proof preview"
                                    className="w-14 h-14 object-cover rounded-lg border border-gray-300"
                                />
                                <div className="flex-1 min-w-0">
                                    <p className="text-xs font-medium text-gray-800 truncate">{txForm.proof_image?.name}</p>
                                    <p className="text-[10px] text-gray-500">{((txForm.proof_image?.size || 0) / 1024).toFixed(1)} KB original</p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => {
                                        if (txProofPreview) URL.revokeObjectURL(txProofPreview);
                                        setTxProofPreview(null);
                                        setTxForm({ ...txForm, proof_image: null });
                                    }}
                                    className="p-1.5 text-red-500 hover:bg-red-50 rounded-lg transition"
                                    title="Remove attachment"
                                >
                                    <Trash2 size={16} />
                                </button>
                            </div>
                        )}
                        {errors.proof_image && <p className="text-xs text-red-500 mt-1">{errors.proof_image}</p>}
                    </div>

                    <div className="border-t border-gray-200 pt-4 flex gap-4 justify-end">
                        <button
                            type="button"
                            onClick={() => {
                                if (txProofPreview) URL.revokeObjectURL(txProofPreview);
                                setTxProofPreview(null);
                                setTxForm({ bank_account_id: '', date: new Date().toISOString().substring(0, 10), amount: '', description: '', contra_account_id: '', proof_image: null });
                                setActiveModal(null);
                            }}
                            className="px-4 py-2 rounded-xl text-gray-500 hover:text-gray-900 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            className="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-medium transition"
                        >
                            Post Entry
                        </button>
                    </div>
                </form>
            </SlideModal>

            {/* Inter-bank Transfer Drawer */}
            <SlideModal
                isOpen={activeModal === 'transfer'}
                onClose={() => {
                    if (transferProofPreview) URL.revokeObjectURL(transferProofPreview);
                    setTransferProofPreview(null);
                    setTransferForm({ from_bank_account_id: '', to_bank_account_id: '', amount: '', date: new Date().toISOString().substring(0, 10), description: '', proof_image: null });
                    setActiveModal(null);
                }}
                title="Inter-Bank Transfer Entry"
            >
                <form onSubmit={handleTransferSubmit} className="space-y-6">
                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Source Account (Transfer Out)</label>
                        <select
                            required
                            value={transferForm.from_bank_account_id}
                            onChange={(e) => setTransferForm({ ...transferForm, from_bank_account_id: e.target.value })}
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                        >
                            <option value="">Select source account...</option>
                            {safeBankAccounts.map((acc) => (
                                <option key={acc.id} value={acc.id}>
                                    {acc.account_name} ({formatPKR(acc.balance)})
                                </option>
                            ))}
                        </select>
                        {errors.from_bank_account_id && <p className="text-xs text-red-400 mt-1">{errors.from_bank_account_id}</p>}
                    </div>

                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Destination Account (Transfer In)</label>
                        <select
                            required
                            value={transferForm.to_bank_account_id}
                            onChange={(e) => setTransferForm({ ...transferForm, to_bank_account_id: e.target.value })}
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                        >
                            <option value="">Select destination account...</option>
                            {safeBankAccounts.filter(acc => acc.id !== Number(transferForm.from_bank_account_id)).map((acc) => (
                                <option key={acc.id} value={acc.id}>
                                    {acc.account_name} ({formatPKR(acc.balance)})
                                </option>
                            ))}
                        </select>
                        {errors.to_bank_account_id && <p className="text-xs text-red-400 mt-1">{errors.to_bank_account_id}</p>}
                    </div>

                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Transfer Date</label>
                        <input
                            type="date"
                            required
                            value={transferForm.date}
                            onChange={(e) => setTransferForm({ ...transferForm, date: e.target.value })}
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                        />
                        {errors.date && <p className="text-xs text-red-400 mt-1">{errors.date}</p>}
                    </div>

                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Amount (PKR)</label>
                        <input
                            type="number"
                            required
                            min="0.01"
                            step="0.01"
                            value={transferForm.amount}
                            onChange={(e) => setTransferForm({ ...transferForm, amount: e.target.value })}
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                        />
                        {errors.amount && <p className="text-xs text-red-400 mt-1">{errors.amount}</p>}
                    </div>

                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Description</label>
                        <textarea
                            value={transferForm.description}
                            onChange={(e) => setTransferForm({ ...transferForm, description: e.target.value })}
                            rows={3}
                            placeholder="Transfer memo details..."
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                        />
                        {errors.description && <p className="text-xs text-red-400 mt-1">{errors.description}</p>}
                    </div>

                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">
                            Screenshot / Transfer Proof <span className="text-gray-400 font-normal lowercase">(optional)</span>
                        </label>
                        {!transferProofPreview ? (
                            <div className="flex items-center gap-3">
                                <label className="flex items-center gap-2 px-4 py-2.5 bg-gray-50 hover:bg-gray-100 border border-dashed border-gray-300 rounded-xl text-xs font-medium text-gray-700 cursor-pointer transition">
                                    <Upload size={14} className="text-gray-500" />
                                    <span>Choose Image / Screenshot</span>
                                    <input
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        onChange={(e) => {
                                            const file = e.target.files?.[0];
                                            if (file) {
                                                setTransferForm({ ...transferForm, proof_image: file });
                                                setTransferProofPreview(URL.createObjectURL(file));
                                            }
                                        }}
                                        className="hidden"
                                    />
                                </label>
                                <span className="text-[11px] text-gray-500">JPG, PNG, WebP (max 5MB)</span>
                            </div>
                        ) : (
                            <div className="flex items-center gap-3 p-3 bg-gray-50 border border-gray-200 rounded-xl">
                                <img
                                    src={transferProofPreview}
                                    alt="Proof preview"
                                    className="w-14 h-14 object-cover rounded-lg border border-gray-300"
                                />
                                <div className="flex-1 min-w-0">
                                    <p className="text-xs font-medium text-gray-800 truncate">{transferForm.proof_image?.name}</p>
                                    <p className="text-[10px] text-gray-500">{((transferForm.proof_image?.size || 0) / 1024).toFixed(1)} KB original</p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => {
                                        if (transferProofPreview) URL.revokeObjectURL(transferProofPreview);
                                        setTransferProofPreview(null);
                                        setTransferForm({ ...transferForm, proof_image: null });
                                    }}
                                    className="p-1.5 text-red-500 hover:bg-red-50 rounded-lg transition"
                                    title="Remove attachment"
                                >
                                    <Trash2 size={16} />
                                </button>
                            </div>
                        )}
                        {errors.proof_image && <p className="text-xs text-red-500 mt-1">{errors.proof_image}</p>}
                    </div>

                    <div className="border-t border-gray-200 pt-4 flex gap-4 justify-end">
                        <button
                            type="button"
                            onClick={() => setActiveModal(null)}
                            className="px-4 py-2 rounded-xl text-gray-500 hover:text-gray-900 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            className="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-medium transition"
                        >
                            Execute Transfer
                        </button>
                    </div>
                </form>
            </SlideModal>

            {/* Customer Collection Modal */}
            <SlideModal
                isOpen={activeModal === 'collection'}
                onClose={() => setActiveModal(null)}
                title="Inward Customer Collection Receipt"
            >
                <form onSubmit={handleCollectionSubmit} className="space-y-6">
                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Customer</label>
                        <select
                            required
                            value={collectionForm.contact_id}
                            onChange={(e) => setCollectionForm({ ...collectionForm, contact_id: e.target.value, invoice_id: '' })}
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                        >
                            <option value="">Select customer...</option>
                            {safeContacts.filter(c => c.type === 'customer' || c.type === 'both').map((c) => (
                                <option key={c.id} value={c.id}>{c.name}</option>
                            ))}
                        </select>
                        {errors.contact_id && <p className="text-xs text-red-400 mt-1">{errors.contact_id}</p>}
                    </div>

                    {collectionForm.contact_id && (
                        <div>
                            <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Select Outstanding Invoice</label>
                            <select
                                required
                                value={collectionForm.invoice_id}
                                onChange={(e) => {
                                    const inv = filteredInvoices.find(i => i.id === Number(e.target.value));
                                    setCollectionForm({
                                        ...collectionForm,
                                        invoice_id: e.target.value,
                                        amount: inv ? inv.remaining_balance.toString() : ''
                                    });
                                }}
                                className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                            >
                                <option value="">Select invoice to allocate...</option>
                                {filteredInvoices.map((inv) => (
                                    <option key={inv.id} value={inv.id}>
                                        {inv.invoice_number} (Outstanding: {formatPKR(inv.remaining_balance)})
                                    </option>
                                ))}
                            </select>
                            {errors.invoice_id && <p className="text-xs text-red-400 mt-1">{errors.invoice_id}</p>}
                        </div>
                    )}

                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Collection Date</label>
                        <input
                            type="date"
                            required
                            value={collectionForm.payment_date}
                            onChange={(e) => setCollectionForm({ ...collectionForm, payment_date: e.target.value })}
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                        />
                        {errors.payment_date && <p className="text-xs text-red-400 mt-1">{errors.payment_date}</p>}
                    </div>

                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Payment Method</label>
                        <select
                            required
                            value={collectionForm.payment_method}
                            onChange={(e) => setCollectionForm({ ...collectionForm, payment_method: e.target.value })}
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                        >
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cheque">Cheque</option>
                        </select>
                        {errors.payment_method && <p className="text-xs text-red-400 mt-1">{errors.payment_method}</p>}
                    </div>

                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Deposit To Cash/Bank Account</label>
                        <select
                            required
                            value={collectionForm.bank_account_id}
                            onChange={(e) => setCollectionForm({ ...collectionForm, bank_account_id: e.target.value })}
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                        >
                            <option value="">Select deposit target...</option>
                            {safeBankAccounts.map((acc) => (
                                <option key={acc.id} value={acc.id}>{acc.account_name}</option>
                            ))}
                        </select>
                        {errors.bank_account_id && <p className="text-xs text-red-400 mt-1">{errors.bank_account_id}</p>}
                    </div>

                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Amount Collected (PKR)</label>
                        <input
                            type="number"
                            required
                            min="0.01"
                            step="0.01"
                            value={collectionForm.amount}
                            onChange={(e) => setCollectionForm({ ...collectionForm, amount: e.target.value })}
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                        />
                        {errors.amount && <p className="text-xs text-red-400 mt-1">{errors.amount}</p>}
                    </div>

                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Notes / Remarks</label>
                        <textarea
                            value={collectionForm.notes}
                            onChange={(e) => setCollectionForm({ ...collectionForm, notes: e.target.value })}
                            rows={3}
                            placeholder="Cheque number or reference logs..."
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                        />
                        {errors.notes && <p className="text-xs text-red-400 mt-1">{errors.notes}</p>}
                    </div>

                    <div className="border-t border-gray-200 pt-4 flex gap-4 justify-end">
                        <button
                            type="button"
                            onClick={() => setActiveModal(null)}
                            className="px-4 py-2 rounded-xl text-gray-500 hover:text-gray-900 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            className="px-5 py-2.5 bg-[#06B6D4] hover:bg-cyan-600 text-white rounded-xl font-medium transition"
                        >
                            Post Receipt
                        </button>
                    </div>
                </form>
            </SlideModal>

            {/* Supplier Payment Modal */}
            <SlideModal
                isOpen={activeModal === 'payment'}
                onClose={() => setActiveModal(null)}
                title="Outward Supplier Payment Disbursement"
            >
                <form onSubmit={handlePaymentSubmit} className="space-y-6">
                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Supplier / Vendor</label>
                        <select
                            required
                            value={paymentForm.contact_id}
                            onChange={(e) => setPaymentForm({ ...paymentForm, contact_id: e.target.value, bill_id: '' })}
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                        >
                            <option value="">Select supplier...</option>
                            {safeContacts.filter(c => c.type === 'vendor' || c.type === 'both').map((c) => (
                                <option key={c.id} value={c.id}>{c.name}</option>
                            ))}
                        </select>
                        {errors.contact_id && <p className="text-xs text-red-400 mt-1">{errors.contact_id}</p>}
                    </div>

                    {paymentForm.contact_id && (
                        <div>
                            <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Select Outstanding Bill</label>
                            <select
                                required
                                value={paymentForm.bill_id}
                                onChange={(e) => {
                                    const bill = filteredBills.find(b => b.id === Number(e.target.value));
                                    setPaymentForm({
                                        ...paymentForm,
                                        bill_id: e.target.value,
                                        amount: bill ? bill.remaining_balance.toString() : ''
                                    });
                                }}
                                className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                            >
                                <option value="">Select bill to allocate...</option>
                                {filteredBills.map((bill) => (
                                    <option key={bill.id} value={bill.id}>
                                        {bill.bill_number} (Outstanding: {formatPKR(bill.remaining_balance)})
                                    </option>
                                ))}
                            </select>
                            {errors.bill_id && <p className="text-xs text-red-400 mt-1">{errors.bill_id}</p>}
                        </div>
                    )}

                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Payment Date</label>
                        <input
                            type="date"
                            required
                            value={paymentForm.payment_date}
                            onChange={(e) => setPaymentForm({ ...paymentForm, payment_date: e.target.value })}
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                        />
                        {errors.payment_date && <p className="text-xs text-red-400 mt-1">{errors.payment_date}</p>}
                    </div>

                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Payment Method</label>
                        <select
                            required
                            value={paymentForm.payment_method}
                            onChange={(e) => setPaymentForm({ ...paymentForm, payment_method: e.target.value })}
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                        >
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cheque">Cheque</option>
                        </select>
                        {errors.payment_method && <p className="text-xs text-red-400 mt-1">{errors.payment_method}</p>}
                    </div>

                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Pay From Cash/Bank Account</label>
                        <select
                            required
                            value={paymentForm.bank_account_id}
                            onChange={(e) => setPaymentForm({ ...paymentForm, bank_account_id: e.target.value })}
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                        >
                            <option value="">Select source account...</option>
                            {safeBankAccounts.map((acc) => (
                                <option key={acc.id} value={acc.id}>
                                    {acc.account_name} ({formatPKR(acc.balance)})
                                </option>
                            ))}
                        </select>
                        {errors.bank_account_id && <p className="text-xs text-red-400 mt-1">{errors.bank_account_id}</p>}
                    </div>

                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Amount Paid (PKR)</label>
                        <input
                            type="number"
                            required
                            min="0.01"
                            step="0.01"
                            value={paymentForm.amount}
                            onChange={(e) => setPaymentForm({ ...paymentForm, amount: e.target.value })}
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                        />
                        {errors.amount && <p className="text-xs text-red-400 mt-1">{errors.amount}</p>}
                    </div>

                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Notes / Remarks</label>
                        <textarea
                            value={paymentForm.notes}
                            onChange={(e) => setPaymentForm({ ...paymentForm, notes: e.target.value })}
                            rows={3}
                            placeholder="Cheque number or reference logs..."
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                        />
                        {errors.notes && <p className="text-xs text-red-400 mt-1">{errors.notes}</p>}
                    </div>

                    <div className="border-t border-gray-200 pt-4 flex gap-4 justify-end">
                        <button
                            type="button"
                            onClick={() => setActiveModal(null)}
                            className="px-4 py-2 rounded-xl text-gray-500 hover:text-gray-900 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            className="px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl font-medium transition"
                        >
                            Post Payment
                        </button>
                    </div>
                </form>
            </SlideModal>

            {/* New Account Modal */}
            <SlideModal
                isOpen={activeModal === 'newAccount'}
                onClose={() => setActiveModal(null)}
                title="Register New Cash / Bank Account"
            >
                <form onSubmit={handleNewAccountSubmit} className="space-y-6">
                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Account Type</label>
                        <select
                            required
                            value={newAccountForm.type}
                            onChange={(e) => setNewAccountForm({ ...newAccountForm, type: e.target.value as 'bank' | 'cash' })}
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                        >
                            <option value="bank">Bank Account</option>
                            <option value="cash">Cash Counter</option>
                        </select>
                        {errors.type && <p className="text-xs text-red-400 mt-1">{errors.type}</p>}
                    </div>

                    {newAccountForm.type === 'bank' && (
                        <div className="relative">
                            <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Bank Name (Pakistan Banks)</label>
                            <input
                                type="text"
                                value={bankSearchTerm || newAccountForm.bank_name}
                                onChange={(e) => {
                                    setBankSearchTerm(e.target.value);
                                    setShowBankDropdown(true);
                                    if (!e.target.value) {
                                        setNewAccountForm({ ...newAccountForm, bank_name: '' });
                                    }
                                }}
                                onFocus={() => setShowBankDropdown(true)}
                                placeholder="Search bank or type manually..."
                                className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                            />
                            {showBankDropdown && (bankSearchTerm || !newAccountForm.bank_name) && (
                                <div className="absolute z-50 top-full left-0 right-0 mt-1 bg-white shadow-xl border border-gray-300 rounded-xl shadow-2xl max-h-64 overflow-y-auto">
                                    {/* Other / Manual option */}
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setNewAccountForm({ ...newAccountForm, bank_name: bankSearchTerm || 'Other' });
                                            setShowBankDropdown(false);
                                        }}
                                        className="w-full text-left px-4 py-2.5 text-sm text-amber-800 hover:bg-gray-50 border-b border-gray-200 font-medium"
                                    >
                                        ✏️ Other / Manual Bank{bankSearchTerm ? `: "${bankSearchTerm}"` : ''}
                                    </button>
                                    {filteredBankGroups.map((group, gi) => (
                                        <div key={gi}>
                                            <div className="px-4 py-1.5 text-[10px] font-bold uppercase text-gray-500 bg-gray-500 tracking-wider border-b border-gray-200">
                                                {group.label}
                                            </div>
                                            {group.banks.map((bank) => (
                                                <button
                                                    key={bank.id}
                                                    type="button"
                                                    onClick={() => {
                                                        setNewAccountForm({ ...newAccountForm, bank_name: bank.name });
                                                        setBankSearchTerm('');
                                                        setShowBankDropdown(false);
                                                    }}
                                                    className="w-full text-left px-4 py-2 text-sm text-white hover:bg-gray-50 flex items-center gap-2"
                                                >
                                                    <span className="flex-1">{bank.name}</span>
                                                    {bank.short_name && <span className="text-[10px] text-gray-500 font-mono">{bank.short_name}</span>}
                                                    {bank.is_islamic && <span className="text-[9px] text-emerald-800 font-mono border border-emerald-500/20 px-1 rounded">Islamic</span>}
                                                </button>
                                            ))}
                                        </div>
                                    ))}
                                    {filteredBankGroups.length === 0 && (
                                        <div className="px-4 py-3 text-xs text-gray-500 text-center">No matching banks. Use "Other / Manual Bank" above.</div>
                                    )}
                                </div>
                            )}
                            {errors.bank_name && <p className="text-xs text-red-400 mt-1">{errors.bank_name}</p>}
                            {newAccountForm.bank_name && (
                                <p className="text-xs text-emerald-800 mt-1">Selected: {newAccountForm.bank_name}</p>
                            )}
                        </div>
                    )}

                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Account Title / Name</label>
                        <input
                            type="text"
                            required
                            value={newAccountForm.account_name}
                            onChange={(e) => setNewAccountForm({ ...newAccountForm, account_name: e.target.value })}
                            placeholder="e.g. HBL Current Account, Main Cash Counter"
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                        />
                        {errors.account_name && <p className="text-xs text-red-400 mt-1">{errors.account_name}</p>}
                    </div>

                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Account Number (IBAN / Local)</label>
                        <input
                            type="text"
                            value={newAccountForm.account_number}
                            onChange={(e) => setNewAccountForm({ ...newAccountForm, account_number: e.target.value })}
                            placeholder="PK00ABCD0000000000000000"
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                        />
                        {errors.account_number && <p className="text-xs text-red-400 mt-1">{errors.account_number}</p>}
                    </div>

                    <div>
                        <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">G/L Account Code</label>
                        <input
                            type="text"
                            required
                            value={newAccountForm.gl_code}
                            onChange={(e) => setNewAccountForm({ ...newAccountForm, gl_code: e.target.value })}
                            placeholder="e.g. 1201, BANK-HBL-01"
                            className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                        />
                        {errors.gl_code && <p className="text-xs text-red-400 mt-1">{errors.gl_code}</p>}
                    </div>

                    <div className="border-t border-gray-200 pt-4 flex gap-4 justify-end">
                        <button
                            type="button"
                            onClick={() => setActiveModal(null)}
                            className="px-4 py-2 rounded-xl text-gray-500 hover:text-gray-900 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            className="px-5 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl font-medium transition"
                        >
                            Create Account
                        </button>
                    </div>
                </form>
            </SlideModal>

            {voidingTransaction && (
                <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white shadow-2xl border border-gray-300 w-full max-w-md rounded-2xl overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-200">
                        <div className="p-6 border-b border-gray-200 flex items-center justify-between">
                            <h3 className="font-bold text-lg text-gray-900">Void Posted Payment</h3>
                            <button onClick={() => setVoidingTransaction(null)} className="text-gray-500 hover:text-gray-900 transition">
                                <X size={20} />
                            </button>
                        </div>
                        <form onSubmit={handleVoidPayment} className="p-6 space-y-4">
                            <p className="text-sm text-gray-800">
                                Are you sure you want to void this payment transaction?
                                This will restore the remaining outstanding balance of the associated invoices/bills, and reverse the corresponding bank cashflow and general ledger balances.
                            </p>

                            <div className="p-3 bg-red-500/10 border border-red-500/20 text-red-400 text-xs rounded-xl flex gap-2 font-semibold">
                                <AlertCircle size={16} className="shrink-0" />
                                <span>⚠️ Warning: This action is irreversible!</span>
                            </div>

                            {voidError && (
                                <p className="text-xs text-red-400 font-mono bg-red-950/20 border border-red-500/10 p-2 rounded-lg">
                                    {voidError}
                                </p>
                            )}

                            <div>
                                <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">
                                    Reason for Voiding <span className="text-red-400">*</span>
                                </label>
                                <textarea
                                    required
                                    value={voidReason}
                                    onChange={(e) => setVoidReason(e.target.value)}
                                    placeholder="Enter reason (minimum 5 characters)..."
                                    className="w-full h-24 bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-red-500 focus:outline-none transition resize-none font-sans"
                                />
                            </div>

                            <div className="border-t border-gray-200 pt-4 flex justify-end gap-3">
                                <button
                                    type="button"
                                    onClick={() => setVoidingTransaction(null)}
                                    className="px-4 py-2 rounded-xl text-gray-500 hover:text-gray-900 transition text-xs font-semibold"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-semibold shadow-lg transition"
                                >
                                    Void Payment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {viewingProofUrl && (
                <div className="fixed inset-0 bg-slate-900/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white shadow-2xl border border-gray-200 w-full max-w-2xl rounded-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                        <div className="p-4 border-b border-gray-200 flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <ImageIcon size={18} className="text-cyan-600" />
                                <h3 className="font-bold text-base text-gray-900">Transaction Screenshot / Proof</h3>
                            </div>
                            <div className="flex items-center gap-2">
                                <a
                                    href={viewingProofUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="p-1.5 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition"
                                    title="Open full size in new tab"
                                >
                                    <ExternalLink size={18} />
                                </a>
                                <button
                                    onClick={() => setViewingProofUrl(null)}
                                    className="p-1.5 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition"
                                >
                                    <X size={18} />
                                </button>
                            </div>
                        </div>
                        <div className="p-4 bg-slate-900 flex items-center justify-center max-h-[75vh] overflow-auto">
                            <img
                                src={viewingProofUrl}
                                alt="Transaction Proof"
                                className="max-w-full max-h-[70vh] object-contain rounded-lg shadow-lg"
                            />
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
