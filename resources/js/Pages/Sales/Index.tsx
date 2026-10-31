import React, { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { formatPKR, formatDate } from '../../format';
import {
    CircleDollarSign,
    Plus,
    Trash2,
    Users,
    ClipboardList,
    AlertCircle,
    Coins,
    Building2,
    Percent,
    Truck,
    Printer,
    X
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
    price: number;
    product_type: string;
    unit_of_measure: string;
    size?: string;
    inventory_account_id?: number;
    cogs_account_id?: number;
    revenue_account_id?: number;
}

interface ActiveBatch {
    id: number;
    product_id: number;
    sku: string;
    quantity_remaining: number;
    unit_cost: number;
    unit_of_measure: string;
    quality_grade: string;
    batch_reference: string;
    size?: string;
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
        size?: string;
    };
    quantity: number;
    unit_price: number;
    total_price: number;
}

interface CustomerInvoiceInfo {
    id: number;
    invoice_number?: string;
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
    customerInvoice?: CustomerInvoiceInfo | null;
    customer_invoice?: CustomerInvoiceInfo | null;
}

interface SalesProps {
    customers: Contact[];
    products: Product[];
    salesOrders: Order[];
    bankAccounts: BankAccount[];
    activeBatches: ActiveBatch[];
}

export default function Sales({
    customers = [],
    products = [],
    salesOrders = [],
    bankAccounts = [],
    activeBatches = []
}: SalesProps) {
    const safeCustomers = Array.isArray(customers) ? customers : [];
    const safeProducts = Array.isArray(products) ? products : [];
    const safeSalesOrders = Array.isArray(salesOrders) ? salesOrders : [];
    const safeBankAccounts = Array.isArray(bankAccounts) ? bankAccounts : [];
    const safeActiveBatches = Array.isArray(activeBatches) ? activeBatches : [];

    const { auth } = usePage().props as any;
    const userPermissions = auth?.user?.permissions || [];

    const [showInvoiceModal, setShowInvoiceModal] = useState(false);
    const [voidingOrder, setVoidingOrder] = useState<Order | null>(null);
    const [voidReason, setVoidReason] = useState('');
    const [voidError, setVoidError] = useState('');

    // Invoice form state
    const [invoiceForm, setInvoiceForm] = useState({
        contact_id: '',
        order_date: new Date().toISOString().split('T')[0],
        due_date: '',
        warehouse_id: '1',
        items: [
            { product_id: '', quantity: '1', unit_price: '', selected_batch_id: '' }
        ],
        discount_amount: '0',
        delivery_charges: '0',
        cash_received: '0',
        bank_received: '0',
        bank_account_id: ''
    });

    const [errors, setErrors] = useState<any>({});

    const handleAddItemRow = () => {
        setInvoiceForm({
            ...invoiceForm,
            items: [...invoiceForm.items, { product_id: '', quantity: '1', unit_price: '', selected_batch_id: '' }]
        });
    };

    const handleRemoveItemRow = (index: number) => {
        const updated = [...invoiceForm.items];
        updated.splice(index, 1);
        setInvoiceForm({ ...invoiceForm, items: updated });
    };

    const handleItemChange = (index: number, field: string, value: string) => {
        const updated = [...invoiceForm.items];

        if (field === 'product_id') {
            const product = products.find(p => p.id === parseInt(value));
            updated[index] = {
                ...updated[index],
                product_id: value,
                unit_price: product ? product.price.toString() : '',
                selected_batch_id: '' // reset batch on product change
            };
        } else {
            updated[index] = {
                ...updated[index],
                [field]: value
            };
        }

        setInvoiceForm({ ...invoiceForm, items: updated });
    };

    const hasIncompleteProductSelected = invoiceForm.items.some(item => {
        if (!item.product_id) return false;
        const p = products.find(prod => prod.id === parseInt(item.product_id));
        return p && (!p.inventory_account_id || !p.cogs_account_id || !p.revenue_account_id);
    });

    const calculateGrossSubtotal = () => {
        return invoiceForm.items.reduce((acc, item) => {
            const qty = parseFloat(item.quantity) || 0;
            const price = parseFloat(item.unit_price) || 0;
            return acc + (qty * price);
        }, 0);
    };

    const calculateNetInvoiceAmount = () => {
        const gross = calculateGrossSubtotal();
        const discount = parseFloat(invoiceForm.discount_amount) || 0;
        const delivery = parseFloat(invoiceForm.delivery_charges) || 0;
        return gross - discount + delivery;
    };

    const calculateRemainingReceivable = () => {
        const net = calculateNetInvoiceAmount();
        const cash = parseFloat(invoiceForm.cash_received) || 0;
        const bank = parseFloat(invoiceForm.bank_received) || 0;
        return net - (cash + bank);
    };

    const handleCreateInvoice = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/sales/store', invoiceForm, {
            onSuccess: () => {
                setShowInvoiceModal(false);
                setInvoiceForm({
                    contact_id: '',
                    order_date: new Date().toISOString().split('T')[0],
                    due_date: '',
                    warehouse_id: '1',
                    items: [{ product_id: '', quantity: '1', unit_price: '', selected_batch_id: '' }],
                    discount_amount: '0',
                    delivery_charges: '0',
                    cash_received: '0',
                    bank_received: '0',
                    bank_account_id: ''
                });
                setErrors({});
            },
            onError: (err) => setErrors(err),
        });
    };

    const handleVoidInvoice = (e: React.FormEvent) => {
        e.preventDefault();
        setVoidError('');
        if (!voidReason || voidReason.length < 5) {
            setVoidError('Void reason must be at least 5 characters long.');
            return;
        }
        const invoiceId = voidingOrder?.customer_invoice?.id || (voidingOrder as any)?.customerInvoice?.id;
        if (!invoiceId) {
            setVoidError('No linked invoice found for this order.');
            return;
        }

        router.post(`/sales/invoice/${invoiceId}/void`, {
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

    // Local formatPKR removed

    return (
        <AppLayout title="Accounts Receivable & Invoicing (PKR)">
            <div className="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center mb-8">
                <button
                    onClick={() => setShowInvoiceModal(true)}
                    className="flex items-center gap-2 px-5 py-3 bg-cyan-600 hover:bg-cyan-700 text-white rounded-xl font-medium shadow-[0_0_15px_rgba(6,182,212,0.3)] transition-all duration-300"
                >
                    <Plus size={16} />
                    Issue Sales Invoice
                </button>

                <div className="flex gap-6 text-sm font-mono text-gray-700">
                    <div>
                        Active Customers: <span className="text-amber-800 font-bold">{customers.length}</span>
                    </div>
                    <div>
                        Total Invoiced Revenue: <span className="text-emerald-800 font-bold">
                            {formatPKR(salesOrders.reduce((acc, o) => acc + o.total_amount, 0))}
                        </span>
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div className="lg:col-span-2 bg-white shadow-sm border border-gray-200  rounded-2xl p-6">
                    <div className="flex items-center gap-2 mb-6">
                        <ClipboardList size={18} className="text-amber-800" />
                        <h3 className="font-bold text-lg text-gray-900">Commercial Invoice Register</h3>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="border-b border-gray-200 text-gray-600 text-xs uppercase font-semibold font-mono">
                                    <th className="pb-3">Invoice ID</th>
                                    <th className="pb-3">Customer Client</th>
                                    <th className="pb-3">Billing Date</th>
                                    <th className="pb-3">Items Count</th>
                                    <th className="pb-3 text-right">Invoiced Net</th>
                                    <th className="pb-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 text-sm">
                                {salesOrders.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="py-6 text-center text-gray-600 font-mono">
                                            No commercial invoices issued. Issue an invoice to begin.
                                        </td>
                                    </tr>
                                ) : (
                                    salesOrders.map((order) => (
                                        <tr key={order.id} className="hover:bg-gray-50 transition duration-150">
                                            <td className="py-4 font-mono font-medium text-gray-900">{order.order_number}</td>
                                            <td className="py-4 text-gray-800">{order.contact.name}</td>
                                            <td className="py-4 text-gray-700 font-mono">{formatDate(order.order_date)}</td>
                                            <td className="py-4 text-gray-700 font-mono">{order.items.length} items</td>
                                            <td className="py-4 text-right font-mono font-bold text-amber-800">
                                                {formatPKR(order.total_amount)}
                                            </td>
                                            <td className="py-4 text-right space-x-3">
                                                {order.status === 'voided' ? (
                                                    <span className="px-2 py-0.5 bg-red-500/15 border border-red-500/20 text-red-400 text-[10px] uppercase font-semibold font-mono rounded">
                                                        Voided
                                                    </span>
                                                ) : (
                                                    <div className="inline-flex items-center gap-3">
                                                        {(order.customer_invoice?.id || (order as any).customerInvoice?.id) && (
                                                            <a
                                                                href={`/sales/invoice/${order.customer_invoice?.id || (order as any).customerInvoice?.id}/print`}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="inline-flex items-center gap-1 text-xs text-gray-500 hover:text-gray-900 transition font-semibold"
                                                            >
                                                                <Printer size={12} /> Print
                                                            </a>
                                                        )}
                                                        {userPermissions.includes('manage_sales') && (order.customer_invoice?.id || (order as any).customerInvoice?.id) && (
                                                            <button
                                                                onClick={() => {
                                                                    setVoidingOrder(order);
                                                                    setVoidReason('');
                                                                    setVoidError('');
                                                                }}
                                                                className="px-2.5 py-1 bg-red-600/10 hover:bg-red-600 text-red-400 hover:text-white border border-red-500/15 hover:border-red-650 text-xs font-semibold rounded-lg transition duration-150"
                                                            >
                                                                Void
                                                            </button>
                                                        )}
                                                    </div>
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
                        <Users size={18} className="text-amber-800" />
                        <h3 className="font-bold text-lg text-gray-900">Registered Clients</h3>
                    </div>
                    <div className="space-y-4 max-h-[450px] overflow-y-auto pr-2">
                        {customers.length === 0 ? (
                            <p className="text-sm text-gray-600 font-mono text-center py-6">No customers registered.</p>
                        ) : (
                            customers.map((c) => (
                                <div key={c.id} className="p-4 rounded-xl bg-gray-50 border border-gray-200 hover:border-[#06B6D4]/30 transition">
                                    <h4 className="font-semibold text-sm text-gray-900">{c.name}</h4>
                                    <p className="text-xs text-gray-700 mt-1 font-mono">{c.email}</p>
                                    <p className="text-xs text-gray-700 font-mono">{c.phone}</p>
                                    <p className="text-[10px] text-gray-500 mt-2 font-mono truncate">{c.address}</p>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            </div>

            {showInvoiceModal && (
                <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white shadow-2xl border border-gray-300 w-full max-w-2xl rounded-2xl overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-200">
                        <div className="p-6 border-b border-gray-200 flex items-center justify-between">
                            <h3 className="font-bold text-lg text-gray-900">Draft Commercial Invoice</h3>
                            <button onClick={() => setShowInvoiceModal(false)} className="text-gray-500 hover:text-gray-900 transition">
                                <Plus size={20} className="rotate-45" />
                            </button>
                        </div>
                        <form onSubmit={handleCreateInvoice} className="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                            {Object.keys(errors).length > 0 && (
                                <div className="p-3 bg-red-950/40 border border-red-500/20 text-red-400 text-xs rounded-xl space-y-1">
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
                                    <label className="block text-[10px] font-semibold uppercase text-gray-700 mb-1">Customer / Client</label>
                                    <select
                                        required
                                        value={invoiceForm.contact_id}
                                        onChange={(e) => setInvoiceForm({ ...invoiceForm, contact_id: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    >
                                        <option value="">Select customer...</option>
                                        {customers.map(c => (
                                            <option key={c.id} value={c.id}>{c.name}</option>
                                        ))}
                                    </select>
                                    {errors.contact_id && <p className="text-xs text-red-400 mt-1 font-mono">{errors.contact_id}</p>}
                                </div>
                                <div>
                                    <label className="block text-[10px] font-semibold uppercase text-gray-700 mb-1">Billing Date</label>
                                    <input
                                        type="date"
                                        required
                                        value={invoiceForm.order_date}
                                        onChange={(e) => setInvoiceForm({ ...invoiceForm, order_date: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    />
                                    {errors.order_date && <p className="text-xs text-red-400 mt-1 font-mono">{errors.order_date}</p>}
                                </div>
                            </div>

                            <div className="space-y-3 pt-2">
                                <div className="flex justify-between items-center">
                                    <label className="block text-[10px] font-semibold uppercase text-gray-700">Invoice Items</label>
                                    <button
                                        type="button"
                                        onClick={handleAddItemRow}
                                        className="text-xs text-amber-800 hover:text-cyan-300 font-medium font-mono flex items-center gap-1"
                                    >
                                        <Plus size={12} /> Add Item Row
                                    </button>
                                </div>
                                {errors.items && <p className="text-xs text-red-400 font-mono">{errors.items}</p>}

                                <div className="space-y-4">
                                    {invoiceForm.items.map((row, index) => (
                                        <div key={index} className="p-3 bg-gray-50 border border-gray-200 rounded-xl space-y-3">
                                            <div className="flex flex-col sm:flex-row gap-3 sm:items-end">
                                                <div className="flex-1">
                                                    <label className="block text-[9px] uppercase text-gray-500 mb-1 font-mono">Select Product</label>
                                                    <select
                                                        required
                                                        value={row.product_id}
                                                        onChange={(e) => handleItemChange(index, 'product_id', e.target.value)}
                                                        className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                                    >
                                                        <option value="">Select marble type...</option>
                                                        {products.map(p => (
                                                            <option key={p.id} value={p.id}>{p.sku} - {p.name} {p.size ? `(${p.size})` : ''} [{p.product_type} - {p.unit_of_measure}]</option>
                                                        ))}
                                                    </select>
                                                    {(() => {
                                                        const p = products.find(prod => prod.id === parseInt(row.product_id));
                                                        if (p && p.size) {
                                                            return (
                                                                <div className="mt-1 flex items-center">
                                                                    <span className="inline-flex text-[10px] font-mono text-amber-800 bg-cyan-950/20 px-2 py-0.5 rounded border border-cyan-800/30">
                                                                        Size: {p.size}
                                                                    </span>
                                                                </div>
                                                            );
                                                        }
                                                        return null;
                                                    })()}
                                                    {(() => {
                                                        const p = products.find(prod => prod.id === parseInt(row.product_id));
                                                        if (p && (!p.inventory_account_id || !p.cogs_account_id || !p.revenue_account_id)) {
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
                                                        <label className="block text-[9px] uppercase text-gray-500 mb-1 font-mono">Qty</label>
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
                                                        <label className="block text-[9px] uppercase text-gray-500 mb-1 font-mono">Rate (PKR)</label>
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
                                                    {invoiceForm.items.length > 1 && (
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

                                            {/* Batch selection dropdown */}
                                            {row.product_id && (
                                                <div className="grid grid-cols-1 gap-2">
                                                    <label className="block text-[9px] uppercase text-gray-500 font-mono">Manual Batch Selection Override (FIFO used if empty)</label>
                                                    <select
                                                        value={row.selected_batch_id}
                                                        onChange={(e) => handleItemChange(index, 'selected_batch_id', e.target.value)}
                                                        className="w-full bg-gray-500 border border-gray-200 rounded-xl px-3 py-2 text-xs text-gray-800 focus:border-[#06B6D4] focus:outline-none transition"
                                                    >
                                                        <option value="">Auto depletion (Oldest batch first)</option>
                                                        {activeBatches
                                                            .filter(b => b.product_id === parseInt(row.product_id))
                                                            .map(b => (
                                                                <option key={b.id} value={b.id}>
                                                                    Batch #{b.id} ({b.batch_reference || 'No Ref'}) {b.size ? `[Size: ${b.size}]` : ''} - Grade: {b.quality_grade || 'None'} - Stock: {b.quantity_remaining} {b.unit_of_measure} - Cost: Rs. {b.unit_cost}
                                                                </option>
                                                            ))}
                                                    </select>
                                                    {(() => {
                                                        const b = activeBatches.find(batch => batch.id === parseInt(row.selected_batch_id));
                                                        if (b && b.size) {
                                                            return (
                                                                <div className="mt-1 flex items-center">
                                                                    <span className="inline-flex text-[10px] font-mono text-amber-800 bg-amber-950/20 px-2 py-0.5 rounded border border-amber-800/30">
                                                                        Batch Size: {b.size}
                                                                    </span>
                                                                </div>
                                                            );
                                                        }
                                                        return null;
                                                    })()}
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </div>

                            {/* Deductions & Delivery */}
                            <div className="border-t border-gray-200 pt-4">
                                <h4 className="font-semibold text-xs text-gray-700 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                    <Truck size={14} className="text-amber-800" /> Invoice Overhead / Adjustments
                                </h4>
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-[9px] uppercase text-gray-500 mb-1 font-mono">Discount / Rebate (PKR)</label>
                                        <input
                                            type="number"
                                            value={invoiceForm.discount_amount}
                                            onChange={(e) => setInvoiceForm({ ...invoiceForm, discount_amount: e.target.value })}
                                            className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                        />
                                        {errors.discount_amount && <p className="text-[10px] text-red-400 mt-1 font-mono">{errors.discount_amount}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-[9px] uppercase text-gray-500 mb-1 font-mono">Delivery / Freight Charges (PKR)</label>
                                        <input
                                            type="number"
                                            value={invoiceForm.delivery_charges}
                                            onChange={(e) => setInvoiceForm({ ...invoiceForm, delivery_charges: e.target.value })}
                                            className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                        />
                                        {errors.delivery_charges && <p className="text-[10px] text-red-400 mt-1 font-mono">{errors.delivery_charges}</p>}
                                    </div>
                                </div>
                            </div>

                            {/* Cash Collections */}
                            <div className="border-t border-gray-200 pt-4">
                                <h4 className="font-semibold text-xs text-gray-700 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                    <Coins size={14} className="text-amber-800" /> Customer Payments / Collections
                                </h4>
                                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <label className="block text-[9px] uppercase text-gray-500 mb-1 font-mono">Cash Collected (PKR)</label>
                                        <input
                                            type="number"
                                            value={invoiceForm.cash_received}
                                            onChange={(e) => setInvoiceForm({ ...invoiceForm, cash_received: e.target.value })}
                                            className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                        />
                                        {errors.cash_received && <p className="text-[10px] text-red-400 mt-1 font-mono">{errors.cash_received}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-[9px] uppercase text-gray-500 mb-1 font-mono">Bank Received (PKR)</label>
                                        <input
                                            type="number"
                                            value={invoiceForm.bank_received}
                                            onChange={(e) => setInvoiceForm({ ...invoiceForm, bank_received: e.target.value })}
                                            className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                        />
                                        {errors.bank_received && <p className="text-[10px] text-red-400 mt-1 font-mono">{errors.bank_received}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-[9px] uppercase text-gray-500 mb-1 font-mono flex items-center gap-1"><Building2 size={10} /> Deposit to Account</label>
                                        <select
                                            value={invoiceForm.bank_account_id}
                                            onChange={(e) => setInvoiceForm({ ...invoiceForm, bank_account_id: e.target.value })}
                                            className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                        >
                                            <option value="">Select deposit account...</option>
                                            {bankAccounts.map(b => (
                                                <option key={b.id} value={b.id}>{b.account_name} ({b.type})</option>
                                            ))}
                                        </select>
                                        {errors.bank_account_id && <p className="text-[10px] text-red-400 mt-1 font-mono">{errors.bank_account_id}</p>}
                                    </div>
                                </div>
                            </div>

                            {/* Total calculations Summary */}
                            <div className="border-t border-gray-200 pt-4 space-y-2 font-mono text-sm">
                                <div className="flex justify-between text-gray-700">
                                    <span>Gross Product Value:</span>
                                    <span>{formatPKR(calculateGrossSubtotal())}</span>
                                </div>
                                <div className="flex justify-between text-gray-700">
                                    <span>Discount / Delivery adjustments:</span>
                                    <span>- {formatPKR(parseFloat(invoiceForm.discount_amount) || 0)} / + {formatPKR(parseFloat(invoiceForm.delivery_charges) || 0)}</span>
                                </div>
                                <div className="flex justify-between text-gray-900 font-bold border-t border-gray-200 pt-2">
                                    <span>Net Commercial Invoice Value:</span>
                                    <span className="text-amber-800">{formatPKR(calculateNetInvoiceAmount())}</span>
                                </div>
                                <div className="flex justify-between text-gray-700">
                                    <span>Amount Received:</span>
                                    <span>{formatPKR((parseFloat(invoiceForm.cash_received) || 0) + (parseFloat(invoiceForm.bank_received) || 0))}</span>
                                </div>
                                <div className="flex justify-between text-rose-400 font-semibold border-t border-gray-200 pt-2">
                                    <span>Remaining Account Receivable (A/R):</span>
                                    <span>{formatPKR(calculateRemainingReceivable())}</span>
                                </div>
                            </div>

                            <div className="p-3 bg-red-500/5 border border-red-500/15 rounded-xl flex gap-3 text-[11px] text-red-400">
                                <AlertCircle size={16} className="shrink-0" />
                                <p>Issuing this commercial invoice performs FIFO batch depletions, logs product COGS under respective codes, creates A/R subledger balances, and credits cash/bank accounts dynamically.</p>
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
                                        onClick={() => setShowInvoiceModal(false)}
                                        className="px-4 py-2 rounded-xl text-gray-500 hover:text-gray-900 transition"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={hasIncompleteProductSelected}
                                        className={`px-5 py-2.5 text-white rounded-xl font-medium transition ${hasIncompleteProductSelected ? 'bg-gray-600 cursor-not-allowed opacity-50' : 'bg-cyan-600 hover:bg-cyan-700 shadow-[0_0_15px_rgba(6,182,212,0.2)]'}`}
                                    >
                                        Log Invoice & Ledger Stock
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
                            <h3 className="font-bold text-lg text-gray-900">Void Posted Sales Invoice</h3>
                            <button onClick={() => setVoidingOrder(null)} className="text-gray-500 hover:text-gray-900 transition">
                                <X size={20} />
                            </button>
                        </div>
                        <form onSubmit={handleVoidInvoice} className="p-6 space-y-4">
                            <p className="text-sm text-gray-800">
                                Are you sure you want to void invoice <span className="font-mono text-amber-800 font-bold">{voidingOrder.order_number}</span>?
                                This will reverse the general ledger entries, restore the depleted stock, and reverse any immediate cash/bank deposits.
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
                                    Void Invoice
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
