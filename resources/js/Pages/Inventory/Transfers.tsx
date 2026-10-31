import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { formatPKR, formatDate } from '../../format';
import {
    Truck,
    ArrowRightLeft,
    CheckCircle2,
    Calendar,
    Box,
    MapPin,
    AlertTriangle
} from 'lucide-react';

interface Warehouse {
    id: number;
    name: string;
}

interface ActiveBatch {
    id: number;
    product_name: string;
    warehouse_name: string;
    quantity_remaining: number;
    unit_cost: number;
    unit_of_measure: string;
    batch_reference: string | null;
    label: string;
}

interface Transfer {
    id: number;
    date: string;
    product: string;
    from: string;
    to: string;
    quantity: number;
    unit_cost: number;
    total_value: number;
    created_by: string;
}

interface TransfersProps {
    warehouses: Warehouse[];
    activeBatches: ActiveBatch[];
    transfers: Transfer[];
    errors?: any;
}

export default function Transfers({
    warehouses,
    activeBatches,
    transfers,
    errors,
}: TransfersProps) {
    const [sourceBatchId, setSourceBatchId] = useState('');
    const [toWarehouseId, setToWarehouseId] = useState('');
    const [quantity, setQuantity] = useState('');
    const [transferDate, setTransferDate] = useState('');
    const [vehicleNumber, setVehicleNumber] = useState('');
    const [driverName, setDriverName] = useState('');
    const [receiverName, setReceiverName] = useState('');
    const [authorizedByName, setAuthorizedByName] = useState('');
    const [remarks, setRemarks] = useState('');

    const fmt = formatPKR;

    const selectedBatch = activeBatches.find(b => b.id.toString() === sourceBatchId);

    const handleTransfer = (e: React.FormEvent) => {
        e.preventDefault();

        if (!selectedBatch) return;

        const transferQty = parseFloat(quantity);
        if (transferQty > selectedBatch.quantity_remaining) {
            alert('Transfer quantity cannot exceed available quantity.');
            return;
        }

        const toWarehouse = warehouses.find(w => w.id.toString() === toWarehouseId);

        if (confirm(`Transfer ${transferQty} ${selectedBatch.unit_of_measure} from ${selectedBatch.warehouse_name} to ${toWarehouse?.name}?\n\nTotal Value: ${fmt(transferQty * selectedBatch.unit_cost)}`)) {
            router.post('/inventory/transfers', {
                source_batch_id: sourceBatchId,
                to_warehouse_id: toWarehouseId,
                quantity: transferQty,
                transfer_date: transferDate,
                vehicle_number: vehicleNumber,
                driver_name: driverName,
                receiver_name: receiverName,
                authorized_by_name: authorizedByName,
                remarks: remarks,
            }, {
                onSuccess: () => {
                    setSourceBatchId('');
                    setToWarehouseId('');
                    setQuantity('');
                    setTransferDate('');
                    setVehicleNumber('');
                    setDriverName('');
                    setReceiverName('');
                    setAuthorizedByName('');
                    setRemarks('');
                }
            });
        }
    };

    return (
        <AppLayout title="Multi-Warehouse Transfers">
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                {/* Transfer Form */}
                <div className="lg:col-span-1 glass-panel rounded-2xl p-6">
                    <div className="flex items-center gap-3 mb-6">
                        <ArrowRightLeft size={20} className="text-amber-800" />
                        <h3 className="font-bold text-lg text-gray-900">New Transfer</h3>
                    </div>

                    <form onSubmit={handleTransfer} className="space-y-4">
                        <div>
                            <label className="block text-xs font-semibold text-gray-700 mb-1 uppercase">Source Batch</label>
                            <select
                                required
                                value={sourceBatchId}
                                onChange={(e) => setSourceBatchId(e.target.value)}
                                className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                            >
                                <option value="">-- Select Batch --</option>
                                {activeBatches.map(b => (
                                    <option key={b.id} value={b.id}>{b.label}</option>
                                ))}
                            </select>
                        </div>

                        {selectedBatch && (
                            <div className="bg-[#10B981]/10 border border-[#10B981]/20 rounded-lg p-3 text-sm">
                                <div className="flex justify-between text-[#10B981] mb-1">
                                    <span className="font-medium">Available</span>
                                    <span className="font-mono">{selectedBatch.quantity_remaining} {selectedBatch.unit_of_measure}</span>
                                </div>
                                <div className="flex justify-between text-[#10B981]/80 text-xs">
                                    <span>Unit Cost</span>
                                    <span className="font-mono">{fmt(selectedBatch.unit_cost)}</span>
                                </div>
                            </div>
                        )}

                        <div>
                            <label className="block text-xs font-semibold text-gray-700 mb-1 uppercase">Destination</label>
                            <select
                                required
                                value={toWarehouseId}
                                onChange={(e) => setToWarehouseId(e.target.value)}
                                className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                            >
                                <option value="">-- Select Warehouse --</option>
                                {warehouses.map(w => (
                                    <option key={w.id} value={w.id}>{w.name}</option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-gray-700 mb-1 uppercase">Quantity to Transfer</label>
                            <input
                                type="number"
                                required
                                min="0.0001"
                                step="0.0001"
                                max={selectedBatch?.quantity_remaining}
                                value={quantity}
                                onChange={(e) => setQuantity(e.target.value)}
                                className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-gray-700 mb-1 uppercase">Transfer Date</label>
                            <input
                                type="date"
                                required
                                value={transferDate}
                                onChange={(e) => setTransferDate(e.target.value)}
                                className="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                            />
                        </div>

                        {/* Gate Pass Dispatch Info */}
                        <div className="border-t border-gray-200 pt-4 space-y-3">
                            <h4 className="text-xs font-semibold text-amber-800 uppercase tracking-wider flex items-center gap-1.5 font-mono">
                                <Truck size={14} /> Dispatch & Gate Pass
                            </h4>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-[9px] font-semibold text-gray-500 mb-1 uppercase">Vehicle No.</label>
                                    <input
                                        type="text"
                                        placeholder="e.g. LES-1234"
                                        value={vehicleNumber}
                                        onChange={(e) => setVehicleNumber(e.target.value)}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    />
                                </div>
                                <div>
                                    <label className="block text-[9px] font-semibold text-gray-500 mb-1 uppercase">Driver Name</label>
                                    <input
                                        type="text"
                                        placeholder="e.g. Muhammad Ali"
                                        value={driverName}
                                        onChange={(e) => setDriverName(e.target.value)}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-[9px] font-semibold text-gray-500 mb-1 uppercase">Receiver Name</label>
                                    <input
                                        type="text"
                                        placeholder="e.g. Siddique"
                                        value={receiverName}
                                        onChange={(e) => setReceiverName(e.target.value)}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    />
                                </div>
                                <div>
                                    <label className="block text-[9px] font-semibold text-gray-500 mb-1 uppercase">Authorized By</label>
                                    <input
                                        type="text"
                                        placeholder="e.g. Manager"
                                        value={authorizedByName}
                                        onChange={(e) => setAuthorizedByName(e.target.value)}
                                        className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition"
                                    />
                                </div>
                            </div>
                            <div>
                                <label className="block text-[9px] font-semibold text-gray-500 mb-1 uppercase">Remarks</label>
                                <textarea
                                    placeholder="Enter any dispatch remarks..."
                                    value={remarks}
                                    onChange={(e) => setRemarks(e.target.value)}
                                    rows={2}
                                    className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-[#06B6D4] focus:outline-none transition resize-none"
                                />
                            </div>
                        </div>

                        {errors?.transfer && (
                            <div className="bg-red-500/10 border border-red-500/20 rounded-lg p-3 text-red-400 text-sm flex items-start gap-2">
                                <AlertTriangle size={16} className="shrink-0 mt-0.5" />
                                <span>{errors.transfer}</span>
                            </div>
                        )}
                        {errors?.period && (
                            <div className="bg-red-500/10 border border-red-500/20 rounded-lg p-3 text-red-400 text-sm flex items-start gap-2">
                                <AlertTriangle size={16} className="shrink-0 mt-0.5" />
                                <span>{errors.period}</span>
                            </div>
                        )}

                        <button
                            type="submit"
                            className="w-full py-3 bg-[#06B6D4] hover:bg-[#0891B2] text-white rounded-xl font-medium shadow-lg transition flex justify-center items-center gap-2 mt-4"
                        >
                            <Truck size={18} />
                            Execute Transfer
                        </button>
                    </form>
                </div>

                {/* Transfer History */}
                <div className="lg:col-span-2 glass-panel rounded-2xl p-6">
                    <div className="flex items-center justify-between mb-6">
                        <div className="flex items-center gap-3">
                            <Truck size={20} className="text-[#10B981]" />
                            <h3 className="font-bold text-lg text-gray-900">Transfer History</h3>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="border-b border-gray-200 text-gray-600 text-xs uppercase font-semibold font-mono">
                                    <th className="pb-3">Date</th>
                                    <th className="pb-3">Product</th>
                                    <th className="pb-3">From &rarr; To</th>
                                    <th className="pb-3 text-right">Quantity</th>
                                    <th className="pb-3 text-right">Total Value</th>
                                    <th className="pb-3 text-right">User</th>
                                    <th className="pb-3 text-center">Gate Pass</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 text-sm">
                                {transfers.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="py-8 text-center text-gray-600 font-mono">
                                            No transfers recorded yet.
                                        </td>
                                    </tr>
                                ) : (
                                    transfers.map((t) => (
                                        <tr key={t.id} className="hover:bg-white/[0.02] transition duration-150">
                                            <td className="py-3 text-gray-800 font-mono whitespace-nowrap">
                                                <div className="flex items-center gap-2">
                                                    <Calendar size={12} className="text-gray-500" />
                                                    {t.date}
                                                </div>
                                            </td>
                                            <td className="py-3">
                                                <div className="flex items-center gap-2 text-gray-900 font-medium">
                                                    <Box size={14} className="text-amber-800" />
                                                    {t.product}
                                                </div>
                                            </td>
                                            <td className="py-3">
                                                <div className="flex flex-col gap-1">
                                                    <span className="text-gray-700 text-xs flex items-center gap-1">
                                                        <MapPin size={10}/> {t.from}
                                                    </span>
                                                    <span className="text-gray-900 font-medium flex items-center gap-1">
                                                        <ArrowRightLeft size={12} className="text-[#10B981]"/> {t.to}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="py-3 text-right font-mono text-gray-900 font-medium">
                                                {t.quantity}
                                            </td>
                                            <td className="py-3 text-right font-mono text-gray-800">
                                                {fmt(t.total_value)}
                                            </td>
                                            <td className="py-3 text-right text-xs text-gray-500 uppercase">
                                                {t.created_by}
                                            </td>
                                            <td className="py-3 text-center">
                                                <a
                                                    href={`/inventory/transfers/${t.id}/gate-pass`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-cyan-600/10 text-amber-800 hover:bg-cyan-600/20 border border-cyan-500/20 text-xs font-semibold font-mono tracking-wide transition shadow-sm"
                                                >
                                                    <Truck size={12} />
                                                    Print GP
                                                </a>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
