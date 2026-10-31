import React, { useEffect } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { COMPANY_NAME, COMPANY_ADDRESS, COMPANY_PHONE, COMPANY_NTN } from '../../brand';
import { Printer, ArrowLeft } from 'lucide-react';
import { formatDate } from '../../format';

interface Product {
    id: number;
    sku: string;
    name: string;
    stone_color?: string;
    quality_grade?: string;
    thickness?: string;
    size?: string;
}

interface InventoryBatch {
    id: number;
    quality_grade?: string;
}

interface Warehouse {
    id: number;
    name: string;
}

interface ChallanItem {
    id: number;
    product: Product;
    inventoryBatch?: InventoryBatch | null;
    quantity: number;
    unit: string;
    warehouse: Warehouse;
}

interface Contact {
    id: number;
    name: string;
    phone?: string;
    address?: string;
}

interface Order {
    id: number;
    order_number: string;
    contact: Contact;
}

interface CustomerInvoice {
    id: number;
    invoice_number: string;
    contact?: Contact;
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
    status: string;
    items: ChallanItem[];
}

interface Props {
    challan: DeliveryChallan;
}

export default function ChallanPrint({ challan }: Props) {
    const { invoiceSettings } = usePage().props as any;

    const companyName = invoiceSettings?.company_name || COMPANY_NAME;
    const companyAddress = invoiceSettings?.company_address || COMPANY_ADDRESS;
    const companyPhone = invoiceSettings?.company_phone || COMPANY_PHONE;
    const companyNtn = invoiceSettings?.company_ntn || COMPANY_NTN;
    const companyStrn = invoiceSettings?.company_strn || '';
    const handlePrint = () => {
        window.print();
    };

    const customer = challan.order?.contact || challan.customerInvoice?.contact || null;

    return (
        <div className="min-h-screen bg-gray-50 text-gray-800 p-4 md:p-8 font-sans antialiased">
            <Head title={`Delivery Challan ${challan.challan_number}`} />

            {/* Print Friendly Styling Injection */}
            <style dangerouslySetInnerHTML={{__html: `
                @media print {
                    body {
                        background-color: white !important;
                        color: black !important;
                        padding: 0 !important;
                        margin: 0 !important;
                    }
                    .no-print {
                        display: none !important;
                    }
                    .print-border-black {
                        border-color: #000000 !important;
                    }
                    .print-text-black {
                        color: #000000 !important;
                    }
                    .print-bg-gray {
                        background-color: #f3f4f6 !important;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                }
            `}} />

            {/* Actions Bar */}
            <div className="max-w-4xl mx-auto mb-6 flex justify-between items-center no-print">
                <Link
                    href="/delivery-challans"
                    className="flex items-center gap-2 text-xs font-semibold text-gray-500 hover:text-gray-900 transition"
                >
                    <ArrowLeft size={14} />
                    Back to Delivery Challans
                </Link>
                <button
                    onClick={handlePrint}
                    className="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold shadow transition"
                >
                    <Printer size={14} />
                    Print Gate Pass / Challan
                </button>
            </div>

            {/* Printable Canvas */}
            <div className="max-w-4xl mx-auto bg-white border border-gray-200 shadow-lg rounded-xl p-8 md:p-12 print:border-none print:shadow-none print:p-0">

                {/* Brand Header */}
                <div className="flex flex-col md:flex-row justify-between items-start md:items-center pb-8 border-b border-gray-200 mb-8 print-border-black">
                    <div>
                        <h1 className="text-3xl font-extrabold tracking-wide text-gray-900 uppercase">
                            {companyName}
                        </h1>
                        <p className="text-xs text-gray-500 mt-1 font-mono">
                            {companyAddress}
                        </p>
                        <p className="text-xs text-gray-500 font-mono">
                            Phone: {companyPhone} | NTN: {companyNtn} {companyStrn ? `| STRN: ${companyStrn}` : ''}
                        </p>
                    </div>
                    <div className="mt-4 md:mt-0 text-left md:text-right font-mono text-xs">
                        <span className="inline-block px-3 py-1 rounded bg-amber-100 text-amber-800 font-bold uppercase mb-2 print-bg-gray print-text-black">
                            Delivery Challan & Gate Pass
                        </span>
                        <p className="text-gray-500">Challan Number</p>
                        <p className="text-base font-bold text-gray-900 mb-2">{challan.challan_number}</p>
                        <p className="text-gray-500">Date & Time</p>
                        <p className="font-semibold text-gray-800">{new Date(challan.challan_date).toLocaleString()}</p>
                    </div>
                </div>

                {/* Logistics & Dispatch Info Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                    <div>
                        <h3 className="text-xs font-bold uppercase tracking-wider text-gray-700 mb-3 font-mono">
                            Consignee / Customer Details
                        </h3>
                        {customer ? (
                            <>
                                <p className="text-base font-bold text-gray-900">{customer.name}</p>
                                {customer.address && (
                                    <p className="text-xs text-gray-500 mt-1 max-w-sm">
                                        {customer.address}
                                    </p>
                                )}
                                {customer.phone && (
                                    <p className="text-xs text-gray-500 mt-1 font-mono">
                                        Phone: {customer.phone}
                                    </p>
                                )}
                            </>
                        ) : (
                            <p className="text-sm font-bold text-gray-900">Direct Cash/Retail Sale</p>
                        )}

                        {challan.order && (
                            <div className="mt-3">
                                <p className="text-[10px] text-gray-700 uppercase font-mono">Linked Sales Order</p>
                                <p className="text-xs font-semibold text-gray-700 font-mono">{challan.order.order_number}</p>
                            </div>
                        )}
                    </div>
                    <div className="md:text-right">
                        <h3 className="text-xs font-bold uppercase tracking-wider text-gray-700 mb-3 font-mono">
                            Dispatch & Vehicle Info
                        </h3>
                        <div className="text-xs space-y-1.5 font-mono text-gray-600 md:inline-block md:text-left">
                            <div className="flex justify-between md:gap-8">
                                <span className="text-gray-700">Vehicle Number:</span>
                                <span className="font-bold text-gray-900">{challan.vehicle_number}</span>
                            </div>
                            <div className="flex justify-between md:gap-8">
                                <span className="text-gray-700">Driver Name:</span>
                                <span className="font-bold text-gray-900">{challan.driver_name}</span>
                            </div>
                            <div className="flex justify-between md:gap-8">
                                <span className="text-gray-700">Shipment Status:</span>
                                <span className="font-bold uppercase text-gray-900">{challan.status}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Items Table */}
                <div className="mb-8 overflow-x-auto">
                    <table className="w-full text-left border-collapse table-auto text-xs">
                        <thead>
                            <tr className="border-b-2 border-gray-300 text-gray-600 uppercase font-mono tracking-wider font-bold print-border-black">
                                <th className="py-3 pr-4">Product SKU</th>
                                <th className="py-3 px-2">Product Name</th>
                                <th className="py-3 px-2">Thickness/Grade</th>
                                <th className="py-3 px-2">Size</th>
                                <th className="py-3 px-2">Batch Reference</th>
                                <th className="py-3 px-2">Warehouse</th>
                                <th className="py-3 pl-4 text-right">Quantity</th>
                                <th className="py-3 px-2 text-right">Unit</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 text-gray-700 font-mono print-border-black">
                            {challan.items.map((item, idx) => (
                                <tr key={item.id || idx} className="align-middle">
                                    <td className="py-3 pr-4 font-semibold text-gray-900">
                                        {item.product.sku}
                                    </td>
                                    <td className="py-3 px-2 font-sans text-gray-800">
                                        {item.product.name}
                                    </td>
                                    <td className="py-3 px-2 text-gray-500">
                                        {item.product.thickness || '-'} / {item.inventoryBatch?.quality_grade || 'Standard'}
                                    </td>
                                    <td className="py-3 px-2 text-gray-500 font-sans">
                                        {item.product.size || '-'}
                                    </td>
                                    <td className="py-3 px-2 text-gray-500">
                                        {item.inventoryBatch ? `Batch #${item.inventoryBatch.id}` : 'General Stock'}
                                    </td>
                                    <td className="py-3 px-2 text-gray-500">
                                        {item.warehouse.name}
                                    </td>
                                    <td className="py-3 pl-4 text-right text-gray-900 font-bold">
                                        {Number(item.quantity).toFixed(2)}
                                    </td>
                                    <td className="py-3 px-2 text-right text-gray-500">
                                        {item.unit}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Remarks & Conditions */}
                <div className="flex flex-col md:flex-row justify-between items-start pt-6 border-t border-gray-200 print-border-black">
                    <div className="max-w-md text-xs text-gray-700 leading-relaxed font-sans mb-4 md:mb-0">
                        <h4 className="font-bold text-gray-500 mb-1">Gate Pass Terms:</h4>
                        <p>1. Factory security is authorized to inspect vehicle cargo match against this challan.</p>
                        <p>2. Customer signature represents complete materials inspection check receipt confirmation.</p>
                        <p>3. Product size and polish variance claims must be logged before dispatch crossing.</p>
                    </div>

                    {challan.remarks && (
                        <div className="w-full md:w-80 p-3 bg-gray-50 border border-gray-200 rounded-lg text-xs print-bg-gray print-border-black">
                            <span className="font-bold text-gray-500 block mb-1 font-mono">Remarks / Instructions:</span>
                            <p className="text-gray-700">{challan.remarks}</p>
                        </div>
                    )}
                </div>

                {/* Triple Signature Lines */}
                <div className="grid grid-cols-3 gap-8 mt-20 pt-8 border-t border-gray-200 font-mono text-xs text-gray-700 print-border-black text-center">
                    <div>
                        <div className="w-full border-b border-gray-300 mb-2 print-border-black" />
                        <p>Prepared By: {challan.prepared_by}</p>
                    </div>
                    <div>
                        <div className="w-full border-b border-gray-300 mb-2 print-border-black" />
                        <p>Authorized By: {challan.authorized_by || '________________'}</p>
                    </div>
                    <div>
                        <div className="w-full border-b border-gray-300 mb-2 print-border-black" />
                        <p>Customer Representative Signature</p>
                    </div>
                </div>

            </div>
        </div>
    );
}
