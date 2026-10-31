import React, { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { formatPKR, formatDate } from '../../format';
import {
    ShoppingCart,
    Plus,
    Trash2,
    Briefcase,
    Receipt,
    AlertCircle,
    Truck,
    Coins,
    Building2,
    X,
    Printer
} from 'lucide-react';

interface Contact {
    id: number;
    name: string;
    email: string;
    phone: string;
    address: string;
}

interface Product {
    id: number;
    sku: string;
    name: string;
    cost: number;
    product_type: string;
    unit_of_measure: string;
    inventory_account_id?: number;
}

interface BankAccount {
    id: number;
    account_name: string;
    type: string;
}

interface OrderItem {
    id: number;
    product: {
        sku: string;
        name: string;
    };
    quantity: number;
    unit_price: number;
    total_price: number;
}

interface Order {
    id: number;
    order_number: string;
    contact: {
        name: string;
    };
    order_date: string;
    total_amount: number;
    status: string;
    items: OrderItem[];
    supplier_bill?: { id: number; bill_number?: string } | null;
    supplierBill?: { id: number; bill_number?: string } | null;
}

interface PurchasesProps {
    vendors: Contact[];
    products: Product[];
    purchaseOrders: Order[];
    bankAccounts: BankAccount[];
}

export default function Purchases({
    vendors = [],
    products = [],
    purchaseOrders = [],
    bankAccounts = []
}: PurchasesProps) {
    const safeVendors = Array.isArray(vendors) ? vendors : [];
    const safeProducts = Array.isArray(products) ? products : [];
    const safePurchaseOrders = Array.isArray(purchaseOrders) ? purchaseOrders : [];
    const safeBankAccounts = Array.isArray(bankAccounts) ? bankAccounts : [];

    const { auth } = usePage().props as any;
    const userPermissions = auth?.user?.permissions || [];

    const [showBillModal, setShowBillModal] = useState(false);
    const [voidingOrder, setVoidingOrder] = useState<Order | null>(null);
    const [voidReason, setVoidReason] = useState('');
    const [voidError, setVoidError] = useState('');

    const [billForm, setBillForm] = useState({
        contact_id: '',
        order_date: new Date().toISOString().split('T')[0],
        due_date: '',
        warehouse_id: '1',
        items: [
            { product_id: '', quantity: '1', unit_price: '', landed_cost_manual: '0' }
        ],
        transportation_cost: '0',
        loading_cost: '0',
        other_direct_cost: '0',
        cash_paid: '0',
        bank_paid: '0',
        bank_account_id: '',
        allocation_method: 'value'
    });

    const [errors, setErrors] = useState<any>({});

    const handleAddItemRow = () => {
        setBillForm({
            ...billForm,
            items: [...billForm.items, { product_id: '', quantity: '1', unit_price: '', landed_cost_manual: '0' }]
        });
    };

    const handleRemoveItemRow = (index: number) => {
        const updated = [...billForm.items];
        updated.splice(index, 1);
        setBillForm({ ...billForm, items: updated });
    };

    const handleItemChange = (index: number, field: string, value: string) => {
        const updated = [...billForm.items];

        if (field === 'product_id') {
            const product = products.find(p => p.id === parseInt(value));
            updated[index] = {
                ...updated[index],
                product_id: value,
                unit_price: product ? product.cost.toString() : '',
                landed_cost_manual: '0'
            };
        } else {
            updated[index] = {
                ...updated[index],
                [field]: value
            };
        }

        setBillForm({ ...billForm, items: updated });
    };

    const calculateBaseSubtotal = () => {
        return billForm.items.reduce((acc, item) => {
            const qty = parseFloat(item.quantity) || 0;
            const price = parseFloat(item.unit_price) || 0;
            return acc + (qty * price);
        }, 0);
    };

    const hasIncompleteProductSelected = billForm.items.some(item => {
        if (!item.product_id) return false;
        const p = products.find(prod => prod.id === parseInt(item.product_id));
        return p && !p.inventory_account_id;
    });

    const calculateTotalLandedCost = () => {
        const base = calculateBaseSubtotal();
        const transport = parseFloat(billForm.transportation_cost) || 0;
        const loading = parseFloat(billForm.loading_cost) || 0;
        const other = parseFloat(billForm.other_direct_cost) || 0;
        return base + transport + loading + other;
    };

    const calculateRemainingPayable = () => {
        const total = calculateTotalLandedCost();
        const cash = parseFloat(billForm.cash_paid) || 0;
        const bank = parseFloat(billForm.bank_paid) || 0;
        return total - (cash + bank);
    };

    const handleCreateBill = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/purchases/store', billForm, {
            onSuccess: () => {
                setShowBillModal(false);
                setBillForm({
                    contact_id: '',
                    order_date: new Date().toISOString().split('T')[0],
                    due_date: '',
                    warehouse_id: '1',
                    items: [{ product_id: '', quantity: '1', unit_price: '', landed_cost_manual: '0' }],
                    transportation_cost: '0',
                    loading_cost: '0',
                    other_direct_cost: '0',
                    cash_paid: '0',
                    bank_paid: '0',
                    bank_account_id: '',
                    allocation_method: 'value'
                });
                setErrors({});
            },
            onError: (err) => setErrors(err),
        });
    };

    const handleVoidBill = (e: React.FormEvent) => {
        e.preventDefault();
        setVoidError('');
        if (!voidReason || voidReason.length < 5) {
            setVoidError('Void reason must be at least 5 characters long.');
            return;
        }
        const billId = voidingOrder?.supplier_bill?.id || (voidingOrder as any)?.supplierBill?.id;
        if (!billId) {
            setVoidError('No linked bill found for this purchase order.');
            return;
        }

        router.post(`/purchases/bill/${billId}/void`, {
            void_reason: voidReason
        }, {
            onSuccess: () => {
                setVoidingOrder(null);
                setVoidReason('');
                setVoidError('');
            },
            onError: (err: any) => {
                setVoidError(err.error || err.void_reason || 'Reversal failed.');
            }
        });
    };

    // Local formatters removed

    return (
        <AppLayout title="Purchases & Vendor Bills (Landed Cost)">
            <div className="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center mb-8">
                <button
                    onClick={() => setShowBillModal(true)}
                    className="flex items-center gap-2 px-5 py-3 bg-amber-600 hover:bg-amber-700 text-white rounded-xl font-medium shadow-md transition-all duration-300"
                >
                    <Plus size={16} />
                    Record Purchase (Landed Cost)
                </button>

                <div className="flex gap-6 text-sm font-mono text-gray-700">
                    <div>
                        Suppliers: <span className="text-amber-800 font-bold">{vendors.length}</span>
                    </div>
                    <div>
                        Total Invoiced Costs: <span className="text-emerald-800 font-bold">
                            {formatPKR(purchaseOrders.reduce((acc, o) => acc + o.total_amount, 0))}
                        </span>
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div className="lg:col-span-2 bg-white shadow-sm border border-gray-200  rounded-2xl p-6">
                    <div className="flex items-center gap-2 mb-6">
                        <Receipt size={18} className="text-amber-800" />
                        <h3 className="font-bold text-lg text-gray-900">Purchases & Vendor Bills Register</h3>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="border-b border-gray-200 text-gray-600 text-xs uppercase font-semibold font-mono">
                                    <th className="pb-3">Bill ID</th>
                                    <th className="pb-3">Supplier / Vendor</th>
                                    <th className="pb-3">Received Date</th>
                                    <th className="pb-3">Items Logged</th>
                                    <th className="pb-3 text-right">Landed Cost</th>
                                    <th className="pb-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 text-sm">
                                {purchaseOrders.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="py-6 text-center text-gray-600 font-mono">
                                            No supplier invoices found. Record a purchase with landed costs to begin.
                                        </td>
                                    </tr>
                                ) : (
                                    purchaseOrders.map((order) => (
                                        <tr key={order.id} className="hover:bg-gray-50 transition duration-150">
                                            <td className="py-4 font-mono font-medium text-gray-900">{order.order_number}</td>
                                            <td className="py-4 text-gray-800">{order.contact.name}</td>
                                            <td className="py-4 text-gray-700 font-mono">{formatDate(order.order_date)}</td>
                                            <td className="py-4 text-gray-700 font-mono">{order.items.length} product lines</td>
                                            <td className="py-4 text-right font-mono font-bold text-amber-800">
                                                {formatPKR(order.total_amount)}
                                            </td>
                                            <td className="py-4 text-right">
                                                {order.status === 'voided' ? (
                                                    <span className="px-2 py-0.5 bg-red-500/15 border border-red-500/20 text-red-400 text-[10px] uppercase font-semibold font-mono rounded">
                                                        Voided
                                                    </span>
                                                ) : (
                                                    userPermissions.includes('manage_purchases') && (order.supplier_bill?.id || (order as any).supplierBill?.id) && (
                                                        <button
                                                            onClick={() => {
                                                                    setVoidingOrder(order);
                                                                    setVoidReason('');
                                                                    setVoidError('');
                                                            }}
                                                            className="px-2.5 py-1 bg-red-50 hover:bg-red-600 text-red-600 hover:text-white border border-red-200 hover:border-red-650 text-xs font-semibold rounded-lg transition duration-155"
                                                        >
                                                            Void
                                                        </button>
                                                    )
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="bg-white shadow-sm border border-gray-200  rounded-2xl p-6 h-fit">
                    <div className="flex items-center gap-2 mb-6">
                        <Briefcase size={18} className="text-amber-800" />
                        <h3 className="font-bold text-lg text-gray-900">Suppliers & Vendors</h3>
                    </div>
                    <div className="space-y-4 max-h-[450px] overflow-y-auto pr-2">
                        {vendors.length === 0 ? (
                            <p className="text-sm text-gray-600 font-mono text-center py-6">No suppliers registered.</p>
                        ) : (
                            vendors.map((v) => (
                                <div key={v.id} className="p-4 rounded-xl bg-gray-50 border border-gray-200 hover:border-[#06B6D4]/30 transition">
                                    <h4 className="font-semibold text-sm text-gray-900">{v.name}</h4>
                                    <p className="text-xs text-gray-700 mt-1 font-mono">{v.email}</p>
                                    <p className="text-xs text-gray-700 font-mono">{v.phone}</p>
                                    <p className="text-[10px] text-gray-500 mt-2 font-mono truncate">{v.address}</p>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            </div>

            {showBillModal && (
                <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white shadow-2xl border border-gray-300 w-full max-w-2xl rounded-2xl overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-200">
                        <div className="p-6 border-b border-gray-200 flex items-center justify-between">
                            <h3 className="font-bold text-lg text-gray-900">Record Purchase Bill (Landed Cost)</h3>
                            <button onClick={() => setShowBillModal(false)} className="text-gray-500 hover:text-gray-900 transition">
                                <Plus size={20} className="rotate-45" />
                            </button>
                        </div>
                        <form onSubmit={handleCreateBill} className="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                            {Object.keys(errors).length > 0 && (
                                <div className="p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl space-y-1">
                                    <div className="flex gap-2 items-center font-bold">
                                        <AlertCircle size={14} className="shrink-0" />
                                        <span>Please correct the following errors:</span>
                                    </div>
                                    <ul className="list-disc list-inside space-y-1 font-mono text-[10px]">
                                        {Object.entries(errors).map(([key, msg]: any) => (
                                            <li key={key}>
                                                <span className="capitalize">{key.replace(/\./g, ' ').replace(/_/g, ' ')}</span>: {msg}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-[10px] font-semibold uppercase text-gray-700 mb-1">Supplier / Vendor</label>
                                    <select
                                        required
                                        value={billForm.contact_id}
                                        onChange={(e) => setBillForm({ ...billForm, contact_id: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    >
                                        <option value="">Select supplier...</option>
                                        {vendors.map(v => (
                                            <option key={v.id} value={v.id}>{v.name}</option>
                                        ))}
                                    </select>
                                    {errors.contact_id && <p className="text-xs text-red-400 mt-1 font-mono">{errors.contact_id}</p>}
                                </div>
                                <div>
                                    <label className="block text-[10px] font-semibold uppercase text-gray-700 mb-1">Bill Ingest Date</label>
                                    <input
                                        type="date"
                                        required
                                        value={billForm.order_date}
                                        onChange={(e) => setBillForm({ ...billForm, order_date: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    />
                                    {errors.order_date && <p className="text-xs text-red-400 mt-1 font-mono">{errors.order_date}</p>}
                                </div>
                            </div>

                            <div className="space-y-3 pt-2">
                                <div className="flex justify-between items-center">
                                    <label className="block text-[10px] font-semibold uppercase text-gray-700">Purchased Inventory Items</label>
                                    <button
                                        type="button"
                                        onClick={handleAddItemRow}
                                        className="text-xs text-amber-800 hover:text-cyan-300 font-medium font-mono flex items-center gap-1"
                                    >
                                        <Plus size={12} /> Add Item Row
                                    </button>
                                </div>

                                <div className="space-y-3">
                                    {billForm.items.map((row, index) => (
                                        <div key={index} className="flex flex-col sm:flex-row gap-3 sm:items-end">
                                            <div className="flex-1">
                                                <label className="block text-[9px] uppercase text-gray-500 mb-1">Basalt Rock / Product</label>
                                                <select
                                                    required
                                                    value={row.product_id}
                                                    onChange={(e) => handleItemChange(index, 'product_id', e.target.value)}
                                                    className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                                >
                                                    <option value="">Select basalt type...</option>
                                                    {products.map(p => (
                                                        <option key={p.id} value={p.id}>{p.sku} - {p.name} [{p.product_type} - {p.unit_of_measure}]</option>
                                                    ))}
                                                </select>
                                                {(() => {
                                                     const p = products.find(prod => prod.id === parseInt(row.product_id));
                                                     if (p && !p.inventory_account_id) {
                                                         return (
                                                             <p className="text-[10px] text-amber-800 mt-1 font-mono flex items-center gap-1">
                                                                 ⚠️ Incomplete accounting mapping. Please run repair command or update database.
                                                             </p>
                                                         );
                                                     }
                                                     return null;
                                                 })()}
                                                {errors[`items.${index}.product_id`] && <p className="text-[10px] text-red-400 mt-1 font-mono">{errors[`items.${index}.product_id`]}</p>}
                                            </div>
                                            <div className="flex gap-3 items-end">
                                                <div className="flex-1 sm:w-24 sm:flex-none">
                                                    <label className="block text-[9px] uppercase text-gray-500 mb-1">Qty (MT/Unit)</label>
                                                    <input
                                                        type="number"
                                                        required
                                                        min="0.001"
                                                        step="any"
                                                        value={row.quantity}
                                                        onChange={(e) => handleItemChange(index, 'quantity', e.target.value)}
                                                        className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                                    />
                                                    {errors[`items.${index}.quantity`] && <p className="text-[10px] text-red-400 mt-1 font-mono">{errors[`items.${index}.quantity`]}</p>}
                                                </div>
                                                <div className="flex-1 sm:w-28 sm:flex-none">
                                                    <label className="block text-[9px] uppercase text-gray-500 mb-1">Rate (PKR/Unit)</label>
                                                    <input
                                                        type="number"
                                                        required
                                                        step="any"
                                                        value={row.unit_price}
                                                        onChange={(e) => handleItemChange(index, 'unit_price', e.target.value)}
                                                        className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                                    />
                                                    {errors[`items.${index}.unit_price`] && <p className="text-[10px] text-red-400 mt-1 font-mono">{errors[`items.${index}.unit_price`]}</p>}
                                                </div>
                                            {billForm.allocation_method === 'manual' && (
                                                <div className="w-28">
                                                    <label className="block text-[9px] uppercase text-amber-800 mb-1">Landed Share (PKR)</label>
                                                    <input
                                                        type="number"
                                                        required
                                                        step="any"
                                                        value={row.landed_cost_manual || '0'}
                                                        onChange={(e) => handleItemChange(index, 'landed_cost_manual', e.target.value)}
                                                        className="w-full bg-white border border-[#06B6D4]/30 rounded-xl px-3 py-2 text-sm text-amber-800 focus:border-[#06B6D4] focus:outline-none transition font-mono animate-in fade-in"
                                                    />
                                                </div>
                                            )}
                                            {billForm.items.length > 1 && (
                                                    <button
                                                        type="button"
                                                        onClick={() => handleRemoveItemRow(index)}
                                                        className="p-3 min-w-[44px] min-h-[44px] flex items-center justify-center text-red-400 hover:text-red-300 hover:bg-gray-50 rounded-xl transition"
                                                    >
                                                    <Trash2 size={16} />
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            {/* Landed Expense Section */}
                            <div className="border-t border-gray-200 pt-4">
                                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-3">
                                    <h4 className="font-semibold text-xs text-gray-700 uppercase tracking-wider flex items-center gap-1.5">
                                        <Truck size={14} className="text-amber-800" /> Landed Costs (PKR Acquisition Overhead)
                                    </h4>
                                    <div className="flex items-center gap-2">
                                        <label className="text-[10px] uppercase text-gray-500 font-semibold">Method:</label>
                                        <select
                                            value={billForm.allocation_method}
                                            onChange={(e) => setBillForm({ ...billForm, allocation_method: e.target.value })}
                                            className="bg-white border border-gray-300 rounded-lg px-2.5 py-1 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                        >
                                            <option value="value">By Value</option>
                                            <option value="quantity">By Quantity</option>
                                            <option value="manual">Manual allocation</option>
                                        </select>
                                    </div>
                                </div>
                                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <label className="block text-[9px] uppercase text-gray-500 mb-1">Freight / Transport</label>
                                        <input
                                            type="number"
                                            value={billForm.transportation_cost}
                                            onChange={(e) => setBillForm({ ...billForm, transportation_cost: e.target.value })}
                                            className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-[9px] uppercase text-gray-500 mb-1">Loading/Unloading</label>
                                        <input
                                            type="number"
                                            value={billForm.loading_cost}
                                            onChange={(e) => setBillForm({ ...billForm, loading_cost: e.target.value })}
                                            className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-[9px] uppercase text-gray-500 mb-1">Other Direct Charges</label>
                                        <input
                                            type="number"
                                            value={billForm.other_direct_cost}
                                            onChange={(e) => setBillForm({ ...billForm, other_direct_cost: e.target.value })}
                                            className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* Payment Allocations */}
                            <div className="border-t border-gray-200 pt-4">
                                <h4 className="font-semibold text-xs text-gray-700 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                    <Coins size={14} className="text-amber-800" /> Cash / Bank Payments
                                </h4>
                                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <label className="block text-[9px] uppercase text-gray-500 mb-1">Cash Paid (PKR)</label>
                                        <input
                                            type="number"
                                            value={billForm.cash_paid}
                                            onChange={(e) => setBillForm({ ...billForm, cash_paid: e.target.value })}
                                            className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-[9px] uppercase text-gray-500 mb-1">Bank Paid (PKR)</label>
                                        <input
                                            type="number"
                                            value={billForm.bank_paid}
                                            onChange={(e) => setBillForm({ ...billForm, bank_paid: e.target.value })}
                                            className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-[9px] uppercase text-gray-500 mb-1 flex items-center gap-1"><Building2 size={10} /> Paid From Account</label>
                                        <select
                                            value={billForm.bank_account_id}
                                            onChange={(e) => setBillForm({ ...billForm, bank_account_id: e.target.value })}
                                            className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                        >
                                            <option value="">Select cash/bank account...</option>
                                            {bankAccounts.map(b => (
                                                <option key={b.id} value={b.id}>{b.account_name} ({b.type})</option>
                                            ))}
                                        </select>
                                        {errors.bank_account_id && <p className="text-xs text-red-400 mt-1 font-mono">{errors.bank_account_id}</p>}
                                    </div>
                                </div>
                            </div>

                            {/* Total calculations Summary */}
                            <div className="border-t border-gray-200 pt-4 space-y-2 font-mono text-sm">
                                <div className="flex justify-between text-gray-700">
                                    <span>Base Purchase Amount:</span>
                                    <span>{formatPKR(calculateBaseSubtotal())}</span>
                                </div>
                                <div className="flex justify-between text-gray-700">
                                    <span>Overhead Freight & Loading:</span>
                                    <span>{formatPKR(parseFloat(billForm.transportation_cost) + parseFloat(billForm.loading_cost) + parseFloat(billForm.other_direct_cost))}</span>
                                </div>
                                <div className="flex justify-between text-gray-900 font-bold border-t border-gray-200 pt-2">
                                    <span>Total Landed Block Value:</span>
                                    <span className="text-amber-800">{formatPKR(calculateTotalLandedCost())}</span>
                                </div>
                                <div className="flex justify-between text-gray-700">
                                    <span>Total Amount Settled:</span>
                                    <span>{formatPKR(parseFloat(billForm.cash_paid) + parseFloat(billForm.bank_paid))}</span>
                                </div>
                                <div className="flex justify-between text-rose-400 font-semibold border-t border-gray-200 pt-2">
                                    <span>Remaining Account Payable (A/P):</span>
                                    <span>{formatPKR(calculateRemainingPayable())}</span>
                                </div>
                            </div>

                            <div className="p-3 bg-[#06B6D4]/5 border border-[#06B6D4]/15 rounded-xl flex gap-3 text-[11px] text-gray-800">
                                <AlertCircle size={16} className="shrink-0 text-amber-800" />
                                <p>Ingesting this bill creates a dynamic FIFO inventory batch for the materials at their **Landed Cost per Unit**, records stock movement, drafts subledger invoices, and logs balancing entries.</p>
                            </div>

                             <div className="border-t border-gray-200 pt-4 flex flex-col sm:flex-row gap-4 items-center justify-end">
                                 {hasIncompleteProductSelected && (
                                     <span className="text-xs text-amber-800 font-mono text-center sm:text-right">
                                         ⚠️ Post disabled: Repair selected product mappings.
                                     </span>
                                 )}
                                 <div className="flex gap-4">
                                     <button
                                         type="button"
                                         onClick={() => setShowBillModal(false)}
                                         className="px-4 py-2 rounded-xl text-gray-500 hover:text-gray-900 transition"
                                     >
                                         Cancel
                                     </button>
                                     <button
                                         type="submit"
                                         disabled={hasIncompleteProductSelected}
                                         className={`px-5 py-2.5 text-white rounded-xl font-medium transition ${hasIncompleteProductSelected ? 'bg-gray-600 cursor-not-allowed opacity-50' : 'bg-amber-600 hover:bg-amber-700 shadow-[0_0_15px_rgba(6,182,212,0.2)]'}`}
                                     >
                                         Log Landed Cost Bill
                                     </button>
                                 </div>
                             </div>
                        </form>
                    </div>
                </div>
            )}

            {voidingOrder && (
                <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white shadow-2xl border border-gray-300 w-full max-w-md rounded-2xl overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-200">
                        <div className="p-6 border-b border-gray-200 flex items-center justify-between">
                            <h3 className="font-bold text-lg text-gray-900">Void Posted Supplier Bill</h3>
                            <button onClick={() => setVoidingOrder(null)} className="text-gray-500 hover:text-gray-900 transition">
                                <X size={20} />
                            </button>
                        </div>
                        <form onSubmit={handleVoidBill} className="p-6 space-y-4">
                            <p className="text-sm text-gray-800">
                                Are you sure you want to void bill <span className="font-mono text-amber-800 font-bold">{voidingOrder.order_number}</span>?
                                This will reverse the general ledger entries, deduct the inwarded stock, and reverse any immediate cash/bank payments.
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
                                    onClick={() => setVoidingOrder(null)}
                                    className="px-4 py-2 rounded-xl text-gray-500 hover:text-gray-900 transition text-xs font-semibold"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-semibold shadow-lg transition"
                                >
                                    Void Bill
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
