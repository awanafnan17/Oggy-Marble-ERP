import React, { useState } from 'react';
import { router, Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { formatDate } from '../../format';
import GlassCard from '../../components/GlassCard';
import DataTable, { Column } from '../../components/DataTable';
import StatusBadge from '../../components/StatusBadge';
import {
    Truck,
    Plus,
    Printer,
    Calendar,
    User,
    Layers,
    AlertCircle,
    X,
    FileText,
    ArrowRight,
    Check
} from 'lucide-react';

interface Contact {
    id: number;
    name: string;
}

interface Product {
    id: number;
    sku: string;
    name: string;
    size?: string;
    unit_of_measure?: string;
}

interface InventoryBatch {
    id: number;
    product_id: number;
    product: Product;
    quantity_remaining: number;
    quality_grade?: string;
}

interface Warehouse {
    id: number;
    name: string;
}

interface OrderItem {
    product_id: number;
    product: Product;
    quantity: number;
}

interface Order {
    id: number;
    order_number: string;
    contact: Contact;
    items: OrderItem[];
}

interface CustomerInvoice {
    id: number;
    invoice_number: string;
    contact: Contact;
}

interface ChallanItem {
    id: number;
    product: Product;
    inventoryBatch?: InventoryBatch | null;
    quantity: number;
    unit: string;
    warehouse: Warehouse;
}

interface DeliveryChallan {
    id: number;
    challan_number: string;
    challan_date: string;
    order?: Order | null;
    customerInvoice?: CustomerInvoice | null;
    vehicle_number: string;
    driver_name: string;
    receiver_name: string | null;
    prepared_by: string;
    authorized_by: string | null;
    remarks: string | null;
    status: 'pending' | 'packed' | 'dispatched' | 'delivered' | 'returned' | 'cancelled';
    dispatched_at: string | null;
    delivered_at: string | null;
    items: ChallanItem[];
}

interface Props {
    challans: DeliveryChallan[];
    orders: Order[];
    invoices: CustomerInvoice[];
    products: Product[];
    warehouses: Warehouse[];
    batches: InventoryBatch[];
    filters: {
        start_date?: string;
        end_date?: string;
        status?: string;
    };
}

export default function Challans({ challans, orders, invoices, products, warehouses, batches, filters }: Props) {
    const [showFormModal, setShowFormModal] = useState(false);
    const [errors, setErrors] = useState<any>({});

    // Filters local state
    const [filterState, setFilterState] = useState({
        start_date: filters.start_date || '',
        end_date: filters.end_date || '',
        status: filters.status || '',
    });

    // Form builder state
    const [form, setForm] = useState({
        challan_date: new Date().toISOString().slice(0, 16),
        order_id: '',
        customer_invoice_id: '',
        vehicle_number: '',
        driver_name: '',
        receiver_name: '',
        prepared_by: '',
        authorized_by: '',
        remarks: '',
        items: [
            { product_id: '', inventory_batch_id: '', quantity: '', unit: 'MT', warehouse_id: warehouses[0]?.id.toString() || '1' }
        ]
    });

    const selectedOrder = orders.find(o => o.id === Number(form.order_id));

    // Handle filter transitions
    const handleFilterChange = (e: React.ChangeEvent<HTMLSelectElement | HTMLInputElement>) => {
        const { name, value } = e.target;
        setFilterState(prev => ({ ...prev, [name]: value }));
    };

    const applyFilters = () => {
        router.get('/delivery-challans', filterState, { preserveState: true });
    };

    const resetFilters = () => {
        const empty = { start_date: '', end_date: '', status: '' };
        setFilterState(empty);
        router.get('/delivery-challans', empty);
    };

    // Item line builders
    const addItemLine = () => {
        setForm(prev => ({
            ...prev,
            items: [...prev.items, { product_id: '', inventory_batch_id: '', quantity: '', unit: 'MT', warehouse_id: warehouses[0]?.id.toString() || '1' }]
        }));
    };

    const removeItemLine = (idx: number) => {
        const updated = [...form.items];
        updated.splice(idx, 1);
        setForm(prev => ({ ...prev, items: updated }));
    };

    const handleItemChange = (idx: number, field: string, value: string) => {
        const updated = [...form.items];
        updated[idx] = { ...updated[idx], [field]: value };
        setForm(prev => ({ ...prev, items: updated }));
    };

    const openCreateModal = () => {
        setForm({
            challan_date: new Date().toISOString().slice(0, 16),
            order_id: '',
            customer_invoice_id: '',
            vehicle_number: '',
            driver_name: '',
            receiver_name: '',
            prepared_by: '',
            authorized_by: '',
            remarks: '',
            items: [
                { product_id: '', inventory_batch_id: '', quantity: '', unit: 'MT', warehouse_id: warehouses[0]?.id.toString() || '1' }
            ]
        });
        setErrors({});
        setShowFormModal(true);
    };

    // Auto load invoice based on selected order
    const handleOrderChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const orderId = e.target.value;
        const linkedInvoice = invoices.find(inv => inv.contact.id === orders.find(o => o.id === Number(orderId))?.contact.id);

        // Auto fill items based on Sales Order
        const orderObj = orders.find(o => o.id === Number(orderId));
        const newItems = orderObj && orderObj.items.length > 0
            ? orderObj.items.map(item => {
                // Find matching product in products list to get its unit_of_measure
                const prod = products.find(p => p.id === item.product_id);
                return {
                    product_id: item.product_id.toString(),
                    inventory_batch_id: '',
                    quantity: item.quantity.toString(),
                    unit: prod?.unit_of_measure || 'MT',
                    warehouse_id: warehouses[0]?.id.toString() || '1'
                };
            })
            : [{ product_id: '', inventory_batch_id: '', quantity: '', unit: 'MT', warehouse_id: warehouses[0]?.id.toString() || '1' }];

        setForm(prev => ({
            ...prev,
            order_id: orderId,
            customer_invoice_id: linkedInvoice ? linkedInvoice.id.toString() : '',
            items: newItems
        }));
    };

    const handleFormSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/delivery-challans', form, {
            onSuccess: () => {
                setShowFormModal(false);
                setErrors({});
            },
            onError: (err) => setErrors(err),
        });
    };

    // Transition state triggers
    const transitionStatus = (id: number, currentStatus: string, nextStatus: string) => {
        if (!confirm(`Are you sure you want to transition this challan status to '${nextStatus}'?`)) return;
        router.patch(`/delivery-challans/${id}/status`, { status: nextStatus }, {
            onError: (err) => {
                if (err.status) alert(err.status);
            }
        });
    };

    // Helper to check valid transitions for UI buttons
    const getAvailableTransitions = (status: string) => {
        switch (status) {
            case 'pending': return ['packed', 'cancelled'];
            case 'packed': return ['dispatched', 'cancelled'];
            case 'dispatched': return ['delivered', 'returned'];
            default: return [];
        }
    };

    const columns: Column<DeliveryChallan>[] = [
        { header: 'Challan #', accessor: 'challan_number', className: 'text-white font-mono font-medium' },
        { header: 'Date', accessor: (row) => new Date(row.challan_date).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' }) },
        {
            header: 'Customer',
            accessor: (row) => {
                if (row.order && row.order.contact) return row.order.contact.name;
                if (row.customerInvoice && row.customerInvoice.contact) return row.customerInvoice.contact.name;
                return 'N/A';
            }
        },
        { header: 'Order Ref', accessor: (row) => row.order ? row.order.order_number : 'N/A', className: 'font-mono' },
        { header: 'Vehicle', accessor: 'vehicle_number', className: 'font-mono' },
        { header: 'Driver', accessor: 'driver_name' },
        {
            header: 'Status',
            accessor: (row) => <StatusBadge status={row.status} />
        },
        {
            header: 'Dispatch States',
            accessor: (row) => {
                const transitions = getAvailableTransitions(row.status);
                if (transitions.length === 0) {
                    return <span className="text-[10px] text-gray-500 font-mono italic">Final State</span>;
                }
                return (
                    <div className="flex gap-1.5 flex-wrap">
                        {transitions.map(t => (
                            <button
                                key={t}
                                onClick={() => transitionStatus(row.id, row.status, t)}
                                className={`px-2 py-0.5 rounded text-[9px] font-bold uppercase transition ${
                                    t === 'packed' ? 'bg-[#06B6D4]/20 hover:bg-[#06B6D4]/30 text-amber-800' :
                                    t === 'dispatched' ? 'bg-amber-500/20 hover:bg-amber-500/30 text-amber-800' :
                                    t === 'delivered' ? 'bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-800' :
                                    t === 'returned' ? 'bg-indigo-500/20 hover:bg-indigo-500/30 text-indigo-400' :
                                    'bg-red-500/10 hover:bg-red-500/20 text-red-400'
                                }`}
                            >
                                {t}
                            </button>
                        ))}
                    </div>
                );
            }
        },
        {
            header: 'Actions',
            accessor: (row) => (
                <div className="flex gap-2 justify-end">
                    <Link
                        href={`/delivery-challans/${row.id}/print`}
                        className="flex items-center gap-1 px-2.5 py-1 bg-gray-50 hover:bg-gray-500 text-white border border-gray-300 rounded text-[10px] font-semibold transition"
                    >
                        <Printer size={10} />
                        Print
                    </Link>
                </div>
            ),
            align: 'right'
        }
    ];

    return (
        <AppLayout title="Sales Delivery Challans & Dispatch Tracking">
            <div className="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center mb-8">
                <div>
                    <h2 className="text-lg font-bold text-gray-900">Delivery Challan & Dispatch Tracking</h2>
                    <p className="text-xs text-gray-700">Generate printable gatepasses and track shipment transition states.</p>
                </div>
                <button
                    onClick={openCreateModal}
                    className="flex items-center gap-2 px-5 py-3 bg-[#B45309] hover:bg-[#92400E] text-white rounded-xl font-semibold shadow-xs transition-all duration-200"
                >
                    <Plus size={16} />
                    New Challan (Gate Pass)
                </button>
            </div>

            {/* Filters panel */}
            <div className="glass-panel p-5 rounded-2xl mb-8 space-y-4">
                <h3 className="text-xs font-semibold text-gray-700 uppercase tracking-wider font-mono">Filters</h3>
                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <label className="block text-[10px] text-gray-700 mb-1">Start Date</label>
                        <input
                            type="date"
                            name="start_date"
                            value={filterState.start_date}
                            onChange={handleFilterChange}
                            className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                        />
                    </div>
                    <div>
                        <label className="block text-[10px] text-gray-700 mb-1">End Date</label>
                        <input
                            type="date"
                            name="end_date"
                            value={filterState.end_date}
                            onChange={handleFilterChange}
                            className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                        />
                    </div>
                    <div>
                        <label className="block text-[10px] text-gray-700 mb-1">Status</label>
                        <select
                            name="status"
                            value={filterState.status}
                            onChange={handleFilterChange}
                            className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                        >
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="packed">Packed</option>
                            <option value="dispatched">Dispatched</option>
                            <option value="delivered">Delivered</option>
                            <option value="returned">Returned</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div className="flex gap-2 justify-end pt-2 border-t border-gray-200">
                    <button
                        onClick={resetFilters}
                        className="px-4 py-2 border border-gray-300 rounded-xl text-xs text-gray-500 hover:text-gray-900 hover:bg-gray-50 transition"
                    >
                        Reset
                    </button>
                    <button
                        onClick={applyFilters}
                        className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold transition"
                    >
                        Apply Filters
                    </button>
                </div>
            </div>

            {/* Challans Table */}
            <div className="glass-panel rounded-2xl p-6">
                <div className="overflow-x-auto">
                    <DataTable
                        columns={columns}
                        data={challans}
                        keyExtractor={(row) => row.id}
                        emptyMessage="No delivery challans generated yet. Build one to process a gatepass."
                    />
                </div>
            </div>

            {/* Form modal */}
            {showFormModal && (
                <div className="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="glass-panel w-full max-w-2xl rounded-2xl overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-200">
                        <div className="p-6 border-b border-gray-200 flex items-center justify-between">
                            <h3 className="font-bold text-lg text-gray-900">Create Printable Delivery Challan</h3>
                            <button onClick={() => setShowFormModal(false)} className="text-gray-500 hover:text-gray-900 transition">
                                <X size={20} />
                            </button>
                        </div>
                        <form onSubmit={handleFormSubmit} className="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
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

                            <div className="p-3 rounded-xl bg-blue-500/10 border border-blue-500/20 text-amber-800 text-xs flex items-start gap-2.5">
                                <AlertCircle size={14} className="shrink-0 mt-0.5" />
                                <div>
                                    <span className="font-bold">Inventory Policy Note:</span>
                                    <p className="mt-0.5">Creating this challan generates official gate documentation only. Actual inventory levels were depleted during the Sales Invoice transaction step (prevents double depletion).</p>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Challan Date/Time</label>
                                    <input
                                        type="datetime-local"
                                        required
                                        value={form.challan_date}
                                        onChange={(e) => setForm({ ...form, challan_date: e.target.value })}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                    />
                                    {errors.challan_date && <p className="text-xs text-red-400 mt-1">{errors.challan_date}</p>}
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Link Sales Order</label>
                                    <select
                                        required
                                        value={form.order_id}
                                        onChange={handleOrderChange}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    >
                                        <option value="">Select Sales Order...</option>
                                        {orders.map(o => (
                                            <option key={o.id} value={o.id}>
                                                {o.order_number} - {o.contact.name}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.order_id && <p className="text-xs text-red-400 mt-1">{errors.order_id}</p>}
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Vehicle Number</label>
                                    <input
                                        type="text"
                                        required
                                        value={form.vehicle_number}
                                        onChange={(e) => setForm({ ...form, vehicle_number: e.target.value })}
                                        placeholder="e.g. LES-1234"
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition font-mono"
                                    />
                                    {errors.vehicle_number && <p className="text-xs text-red-400 mt-1">{errors.vehicle_number}</p>}
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Driver Name</label>
                                    <input
                                        type="text"
                                        required
                                        value={form.driver_name}
                                        onChange={(e) => setForm({ ...form, driver_name: e.target.value })}
                                        placeholder="Driver full name"
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    />
                                    {errors.driver_name && <p className="text-xs text-red-400 mt-1">{errors.driver_name}</p>}
                                </div>
                            </div>

                            <div className="grid grid-cols-3 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Prepared By</label>
                                    <input
                                        type="text"
                                        required
                                        value={form.prepared_by}
                                        onChange={(e) => setForm({ ...form, prepared_by: e.target.value })}
                                        placeholder="Staff name"
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    />
                                    {errors.prepared_by && <p className="text-xs text-red-400 mt-1">{errors.prepared_by}</p>}
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Authorized By</label>
                                    <input
                                        type="text"
                                        value={form.authorized_by}
                                        onChange={(e) => setForm({ ...form, authorized_by: e.target.value })}
                                        placeholder="Supervisor signature"
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    />
                                    {errors.authorized_by && <p className="text-xs text-red-400 mt-1">{errors.authorized_by}</p>}
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Receiver Name</label>
                                    <input
                                        type="text"
                                        value={form.receiver_name}
                                        onChange={(e) => setForm({ ...form, receiver_name: e.target.value })}
                                        placeholder="Customer rep (Optional)"
                                        className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    />
                                </div>
                            </div>

                            {/* Items Line Editor */}
                            <div className="border-t border-gray-200 pt-4">
                                <div className="flex justify-between items-center mb-3">
                                    <h4 className="text-xs font-bold text-gray-900 uppercase tracking-wider font-mono">Dispatched Slabs / Tiles Items</h4>
                                    <button
                                        type="button"
                                        onClick={addItemLine}
                                        className="flex items-center gap-1.5 px-3 py-1.5 bg-gray-50 hover:bg-gray-500 text-white rounded-lg text-xs font-semibold border border-gray-300 transition"
                                    >
                                        <Plus size={12} />
                                        Add Item
                                    </button>
                                </div>

                                {errors.items && <p className="text-xs text-red-400 mb-2 font-semibold">{errors.items}</p>}

                                <div className="space-y-3">
                                    {form.items.map((item, idx) => (
                                        <div key={idx} className="flex flex-col md:flex-row gap-3 p-3 bg-gray-50 border border-gray-200 rounded-xl relative items-end">

                                            {/* Product Select */}
                                            <div className="flex-1 w-full">
                                                <label className="block text-[9px] uppercase text-gray-500 mb-1">Product</label>
                                                <select
                                                    required
                                                    value={item.product_id}
                                                    onChange={(e) => handleItemChange(idx, 'product_id', e.target.value)}
                                                    className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none"
                                                >
                                                    <option value="">Select product...</option>
                                                    {selectedOrder ? (
                                                        selectedOrder.items.map(oi => (
                                                            <option key={oi.product_id} value={oi.product_id.toString()}>
                                                                {oi.product.sku} - {oi.product.name} {oi.product.size ? `(${oi.product.size})` : ''} (Ordered: {parseFloat(oi.quantity.toString()).toFixed(1)})
                                                            </option>
                                                        ))
                                                    ) : (
                                                        products.map(p => (
                                                            <option key={p.id} value={p.id.toString()}>{p.sku} - {p.name} {p.size ? `(${p.size})` : ''}</option>
                                                        ))
                                                    )}
                                                </select>
                                            </div>

                                            {/* Batch Select */}
                                            <div className="w-full md:w-36">
                                                <label className="block text-[9px] uppercase text-gray-500 mb-1">Batch (Optional)</label>
                                                <select
                                                    value={item.inventory_batch_id}
                                                    onChange={(e) => handleItemChange(idx, 'inventory_batch_id', e.target.value)}
                                                    className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none"
                                                >
                                                    <option value="">Select batch...</option>
                                                    {batches.filter(b => b.product_id === Number(item.product_id)).map(b => (
                                                        <option key={b.id} value={b.id.toString()}>
                                                            Batch #{b.id} (Grade: {b.quality_grade || 'Standard'})
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>

                                            {/* Quantity */}
                                            <div className="w-full md:w-24">
                                                <label className="block text-[9px] uppercase text-gray-500 mb-1">Qty</label>
                                                <input
                                                    type="number"
                                                    required
                                                    step="0.01"
                                                    min="0.01"
                                                    value={item.quantity}
                                                    onChange={(e) => handleItemChange(idx, 'quantity', e.target.value)}
                                                    placeholder="0.0"
                                                    className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none font-mono"
                                                />
                                            </div>

                                            {/* UOM */}
                                            <div className="w-full md:w-20">
                                                <label className="block text-[9px] uppercase text-gray-500 mb-1">Unit</label>
                                                <select
                                                    required
                                                    value={item.unit}
                                                    onChange={(e) => handleItemChange(idx, 'unit', e.target.value)}
                                                    className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none"
                                                >
                                                    <option value="MT">MT (Slabs)</option>
                                                    <option value="Sq Ft">Sq Ft (Tiles)</option>
                                                    <option value="Pcs">Pcs</option>
                                                    <option value="sq_ft">Sq Ft</option>
                                                    <option value="ton">Ton</option>
                                                    <option value="running_ft">Running Ft</option>
                                                </select>
                                            </div>

                                            {/* Warehouse */}
                                            <div className="w-full md:w-28">
                                                <label className="block text-[9px] uppercase text-gray-500 mb-1">Warehouse</label>
                                                <select
                                                    required
                                                    value={item.warehouse_id}
                                                    onChange={(e) => handleItemChange(idx, 'warehouse_id', e.target.value)}
                                                    className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none"
                                                >
                                                    {warehouses.map(w => (
                                                        <option key={w.id} value={w.id.toString()}>{w.name}</option>
                                                    ))}
                                                </select>
                                            </div>

                                            {/* Remove Button */}
                                            {form.items.length > 1 && (
                                                <button
                                                    type="button"
                                                    onClick={() => removeItemLine(idx)}
                                                    className="p-2 text-red-400 hover:bg-red-500/10 rounded-xl transition"
                                                >
                                                    <X size={16} />
                                                </button>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold uppercase text-gray-700 mb-1">Gate Pass / Remarks</label>
                                <textarea
                                    value={form.remarks}
                                    onChange={(e) => setForm({ ...form, remarks: e.target.value })}
                                    rows={2}
                                    placeholder="Driver license number or receiving company notes..."
                                    className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                ></textarea>
                            </div>

                            <button
                                type="submit"
                                className="w-full mt-4 py-3 bg-gradient-to-r from-blue-600 to-cyan-500 hover:from-blue-700 hover:to-cyan-600 text-white rounded-xl font-bold tracking-wide shadow-[0_0_15px_rgba(6,182,212,0.3)] hover:shadow-[0_0_20px_rgba(6,182,212,0.5)] transition duration-300"
                            >
                                Generate Gate Pass & Delivery Challan
                            </button>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
