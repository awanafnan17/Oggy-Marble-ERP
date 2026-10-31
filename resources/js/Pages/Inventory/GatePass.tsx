import React from 'react';
import { Link } from '@inertiajs/react';
import { ArrowLeft, Printer, Truck, Calendar, ShieldCheck, MapPin } from 'lucide-react';
import { COMPANY_NAME, COMPANY_ADDRESS, COMPANY_PHONE } from '../../brand';

interface TransferDetails {
    id: number;
    gate_pass_number: string;
    transfer_number: string;
    date: string;
    from_warehouse: string;
    from_location: string;
    to_warehouse: string;
    to_location: string;
    product_sku: string;
    product_name: string;
    quantity: number;
    unit: string;
    vehicle_number: string;
    driver_name: string;
    receiver_name: string;
    prepared_by: string;
    authorized_by: string;
    remarks: string;
}

interface GatePassProps {
    transfer: TransferDetails;
}

export default function GatePass({ transfer }: GatePassProps) {
    const handlePrint = () => {
        window.print();
    };

    return (
        <div className="min-h-screen bg-[#F8FAFC] text-gray-100 flex flex-col p-4 sm:p-8 font-sans selection:bg-cyan-500/30 selection:text-gray-900">
            {/* Control Bar (Hidden on Print) */}
            <div className="max-w-3xl w-full mx-auto mb-6 flex justify-between items-center no-print">
                <Link
                    href="/inventory/transfers"
                    className="flex items-center gap-2 text-sm text-gray-500 hover:text-gray-900 transition"
                >
                    <ArrowLeft size={16} />
                    Back to Transfers
                </Link>
                <button
                    onClick={handlePrint}
                    className="flex items-center gap-2 px-5 py-2.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded-xl font-medium shadow-[0_0_15px_rgba(6,182,212,0.3)] transition"
                >
                    <Printer size={16} />
                    Print Gate Pass
                </button>
            </div>

            {/* Printable Document Container */}
            <div className="max-w-3xl w-full mx-auto bg-white shadow-sm border border-gray-200 shadow-2xl rounded-2xl p-8 print:p-0 print:border-0 print:bg-white print:text-black">

                {/* Document Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center border-b border-gray-300 print:border-black/10 pb-6 mb-6 gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-gray-900 print:text-black">{COMPANY_NAME.toUpperCase()}</h1>
                        <p className="text-xs text-gray-700 print:text-black/70 mt-1 font-mono">{COMPANY_ADDRESS}</p>
                        <p className="text-xs text-gray-700 print:text-black/70 font-mono">Tel: {COMPANY_PHONE}</p>
                    </div>
                    <div className="text-left sm:text-right">
                        <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-cyan-500/10 text-amber-800 border border-cyan-500/20 text-[10px] font-bold uppercase tracking-wider font-mono print:hidden">
                            Gate Dispatch
                        </div>
                        <h2 className="text-lg font-bold text-amber-800 print:text-black mt-2 tracking-wider font-mono">GATE PASS / DELIVERY CHALLAN</h2>
                    </div>
                </div>

                {/* Metadata Grid */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 bg-gray-50 print:bg-black/5 border border-gray-200 print:border-black/10 rounded-xl p-4 mb-6 text-xs font-mono text-gray-800 print:text-black">
                    <div>
                        <span className="text-[10px] text-gray-500 print:text-black/60 uppercase block">Gate Pass No</span>
                        <span className="font-bold text-gray-900 print:text-black text-sm">{transfer.gate_pass_number}</span>
                    </div>
                    <div>
                        <span className="text-[10px] text-gray-500 print:text-black/60 uppercase block">Transfer Ref</span>
                        <span className="font-semibold text-gray-900 print:text-black">{transfer.transfer_number}</span>
                    </div>
                    <div>
                        <span className="text-[10px] text-gray-500 print:text-black/60 uppercase block">Dispatch Date</span>
                        <span className="text-gray-900 print:text-black flex items-center gap-1 mt-0.5">
                            <Calendar size={12} className="text-gray-500 print:text-black/60" />
                            {transfer.date}
                        </span>
                    </div>
                    <div>
                        <span className="text-[10px] text-gray-500 print:text-black/60 uppercase block">Authorized By</span>
                        <span className="text-gray-900 print:text-black flex items-center gap-1 mt-0.5">
                            <ShieldCheck size={12} className="text-amber-800 print:text-black/80" />
                            {transfer.authorized_by}
                        </span>
                    </div>
                </div>

                {/* Route Information */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                    <div className="border border-gray-200 print:border-black/10 rounded-xl p-4 bg-white/[0.01] print:bg-transparent">
                        <span className="text-[10px] text-amber-800 print:text-black font-semibold uppercase tracking-wider block mb-2 font-mono flex items-center gap-1">
                            <MapPin size={12} /> Dispatch Location (From)
                        </span>
                        <h4 className="font-bold text-sm text-gray-900 print:text-black">{transfer.from_warehouse}</h4>
                        <p className="text-xs text-gray-700 print:text-black/70 mt-1 leading-relaxed font-mono">{transfer.from_location || 'SITE Industrial Yard'}</p>
                    </div>
                    <div className="border border-gray-200 print:border-black/10 rounded-xl p-4 bg-white/[0.01] print:bg-transparent">
                        <span className="text-[10px] text-emerald-800 print:text-black font-semibold uppercase tracking-wider block mb-2 font-mono flex items-center gap-1">
                            <MapPin size={12} /> Receiving Location (To)
                        </span>
                        <h4 className="font-bold text-sm text-gray-900 print:text-black">{transfer.to_warehouse}</h4>
                        <p className="text-xs text-gray-700 print:text-black/70 mt-1 leading-relaxed font-mono">{transfer.to_location || 'Rawalpindi Retail Depot'}</p>
                    </div>
                </div>

                {/* Product details Table */}
                <div className="border border-gray-200 print:border-black/10 rounded-xl overflow-hidden mb-6">
                    <table className="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr className="bg-gray-50 print:bg-black/5 border-b border-gray-200 print:border-black/10 text-gray-400 print:text-black font-mono font-semibold uppercase tracking-wider text-[10px]">
                                <th className="px-4 py-3">Basalt SKU</th>
                                <th className="px-4 py-3">Material Description</th>
                                <th className="px-4 py-3 text-right">Quantity</th>
                                <th className="px-4 py-3 text-right">Unit</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 print:divide-black/10 text-gray-800 print:text-black font-mono">
                            <tr>
                                <td className="px-4 py-4 font-bold text-gray-900 print:text-black">{transfer.product_sku}</td>
                                <td className="px-4 py-4">{transfer.product_name}</td>
                                <td className="px-4 py-4 text-right font-bold text-gray-900 print:text-black text-sm">{transfer.quantity}</td>
                                <td className="px-4 py-4 text-right uppercase">{transfer.unit}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {/* Transport & Driver Grid */}
                <div className="border border-gray-200 print:border-black/10 rounded-xl p-4 bg-white/[0.01] print:bg-transparent mb-6 text-xs space-y-3 font-mono">
                    <h4 className="text-xs font-bold text-gray-900 print:text-black uppercase tracking-wider flex items-center gap-1.5 border-b border-gray-200 print:border-black/5 pb-2">
                        <Truck size={14} className="text-amber-800 print:text-black" /> Transport Carrier Details
                    </h4>
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 text-gray-800 print:text-black">
                        <div>
                            <span className="text-[10px] text-gray-500 print:text-black/60 uppercase block">Vehicle No</span>
                            <span className="font-bold text-gray-900 print:text-black">{transfer.vehicle_number}</span>
                        </div>
                        <div>
                            <span className="text-[10px] text-gray-500 print:text-black/60 uppercase block">Driver Name</span>
                            <span className="font-bold text-gray-900 print:text-black">{transfer.driver_name}</span>
                        </div>
                        <div>
                            <span className="text-[10px] text-gray-500 print:text-black/60 uppercase block">Gate Receiver</span>
                            <span className="font-bold text-gray-900 print:text-black">{transfer.receiver_name}</span>
                        </div>
                    </div>
                    {transfer.remarks && (
                        <div className="pt-2 border-t border-gray-200 print:border-black/5">
                            <span className="text-[10px] text-gray-500 print:text-black/60 uppercase block mb-1">Dispatch Remarks</span>
                            <p className="text-gray-700 print:text-black/80 italic text-xs leading-relaxed">{transfer.remarks}</p>
                        </div>
                    )}
                </div>

                {/* Signatures section */}
                <div className="mt-16 grid grid-cols-2 sm:grid-cols-4 gap-8 text-center text-[10px] font-semibold uppercase text-gray-500 print:text-black tracking-wider">
                    <div className="space-y-12">
                        <div className="border-b border-gray-2000 print:border-black/30 w-3/4 mx-auto"></div>
                        <span>Prepared By<br/><span className="text-gray-700 print:text-black/70 font-mono text-[9px] lowercase">({transfer.prepared_by})</span></span>
                    </div>
                    <div className="space-y-12">
                        <div className="border-b border-gray-2000 print:border-black/30 w-3/4 mx-auto"></div>
                        <span>Driver Signature</span>
                    </div>
                    <div className="space-y-12">
                        <div className="border-b border-gray-2000 print:border-black/30 w-3/4 mx-auto"></div>
                        <span>Gate Officer / Security</span>
                    </div>
                    <div className="space-y-12">
                        <div className="border-b border-gray-2000 print:border-black/30 w-3/4 mx-auto"></div>
                        <span>Authorized By<br/><span className="text-gray-700 print:text-black/70 font-mono text-[9px] lowercase">({transfer.authorized_by})</span></span>
                    </div>
                </div>
            </div>

            {/* Print Styling */}
            <style dangerouslySetInnerHTML={{__html: `
                @media print {
                    body {
                        background-color: white !important;
                        color: black !important;
                    }
                    .no-print {
                        display: none !important;
                    }
                }
            `}} />
        </div>
    );
}
