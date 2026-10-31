import React, { useEffect } from 'react';
import { COMPANY_NAME, COMPANY_ADDRESS, COMPANY_PHONE, COMPANY_NTN } from '../../brand';
import { Head, Link, usePage } from '@inertiajs/react';
import { Printer, ArrowLeft } from 'lucide-react';
import { formatPKR, formatDate } from '../../format';

interface Product {
    id: number;
    sku: string;
    name: string;
    stone_color?: string;
    quality_grade?: string;
    thickness?: string;
    size?: string;
    unit_of_measure?: string;
}

interface OrderItem {
    id: number;
    product_id: number;
    product: Product;
    quantity: number;
    unit_price: number;
    total_price: number;
}

interface Contact {
    id: number;
    name: string;
    phone?: string;
    email?: string;
    address?: string;
}

interface Order {
    id: number;
    order_number: string;
    items: OrderItem[];
}

interface CustomerInvoice {
    id: number;
    invoice_number: string;
    issue_date: string;
    due_date?: string;
    gross_amount: number;
    discount_amount: number;
    delivery_charges: number;
    net_amount: number;
    cash_received: number;
    bank_received: number;
    remaining_balance: number;
    status: string;
    contact: Contact;
    order: Order;
}

interface InvoicePrintProps {
    invoice: CustomerInvoice;
}

export default function InvoicePrint({ invoice }: InvoicePrintProps) {
    const { invoiceSettings } = usePage().props as any;

    const companyName = invoiceSettings?.company_name || COMPANY_NAME;
    const companyAddress = invoiceSettings?.company_address || COMPANY_ADDRESS;
    const companyPhone = invoiceSettings?.company_phone || COMPANY_PHONE;
    const companyNtn = invoiceSettings?.company_ntn || COMPANY_NTN;
    const companyStrn = invoiceSettings?.company_strn || '';
    const salesTaxPercent = Number(invoiceSettings?.sales_tax_percentage || 0);
    const withholdingTaxPercent = Number(invoiceSettings?.withholding_tax_percentage || 0);
    const footerNotes = invoiceSettings?.invoice_footer_notes || '';
    const paymentTerms = invoiceSettings?.payment_terms || 'Net 30 Days';
    const bankDetails = invoiceSettings?.bank_account_details || '';
    const currency = invoiceSettings?.default_currency || 'PKR';
    // Local formatPKR removed

    const handlePrint = () => {
        window.print();
    };

    // Auto open print dialog when page loads (optional, but manual trigger is safer)
    useEffect(() => {
        // We can let the user trigger it, but a small delay auto-trigger is nice.
    }, []);

    const totalPaid = Number(invoice.cash_received) + Number(invoice.bank_received);

    return (
        <div className="min-h-screen bg-gray-50 text-gray-800 p-4 md:p-8 font-sans antialiased">
            <Head title={`Invoice ${invoice.invoice_number}`} />

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
                    href="/sales"
                    className="flex items-center gap-2 text-xs font-semibold text-gray-500 hover:text-gray-900 transition"
                >
                    <ArrowLeft size={14} />
                    Back to Sales Registers
                </Link>
                <button
                    onClick={handlePrint}
                    className="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold shadow transition"
                >
                    <Printer size={14} />
                    Print Invoice
                </button>
            </div>

            {/* Printable Invoice Page Canvas */}
            <div className="max-w-4xl mx-auto bg-white border border-gray-200 shadow-lg rounded-xl p-8 md:p-12 print:border-none print:shadow-none print:p-0 relative overflow-hidden">
                {invoice.status === 'voided' && (
                    <div className="absolute inset-0 flex items-center justify-center pointer-events-none select-none z-50">
                        <div className="text-red-500 opacity-20 text-5xl md:text-7xl font-black border-[10px] border-red-500 border-dashed rounded-2xl p-4 md:p-8 rotate-[-25deg] uppercase tracking-wider text-center">
                            Voided / Cancelled
                        </div>
                    </div>
                )}
                {/* Invoice Header */}
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
                        <span className="inline-block px-3 py-1 rounded bg-blue-100 text-blue-800 font-bold uppercase mb-2 print-bg-gray print-text-black">
                            Tax Invoice
                        </span>
                        <p className="text-gray-500">Invoice Number</p>
                        <p className="text-base font-bold text-gray-900 mb-2">{invoice.invoice_number}</p>
                        <p className="text-gray-500">Date of Issue</p>
                        <p className="font-semibold text-gray-800">{formatDate(invoice.issue_date)}</p>
                        {invoice.due_date && (
                            <>
                                <p className="text-gray-500 mt-1">Payment Due</p>
                                <p className="font-semibold text-gray-800">{formatDate(invoice.due_date)}</p>
                            </>
                        )}
                    </div>
                </div>

                {/* Billing details info grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                    <div>
                        <h3 className="text-xs font-bold uppercase tracking-wider text-gray-700 mb-3 font-mono">
                            Bill To Customer
                        </h3>
                        <p className="text-base font-bold text-gray-900">{invoice.contact.name}</p>
                        {invoice.contact.address && (
                            <p className="text-xs text-gray-500 mt-1 max-w-sm">
                                {invoice.contact.address}
                            </p>
                        )}
                        {invoice.contact.phone && (
                            <p className="text-xs text-gray-500 mt-1 font-mono">
                                Phone: {invoice.contact.phone}
                            </p>
                        )}
                        {invoice.contact.email && (
                            <p className="text-xs text-gray-500 mt-0.5 font-mono">
                                Email: {invoice.contact.email}
                            </p>
                        )}
                    </div>
                    <div className="md:text-right">
                        <h3 className="text-xs font-bold uppercase tracking-wider text-gray-700 mb-3 font-mono">
                            Payment Info
                        </h3>
                        <p className="text-xs text-gray-500">Payment Status</p>
                        <p className="text-sm font-bold uppercase text-gray-800 tracking-wider mt-0.5">{invoice.status}</p>
                        <p className="text-xs text-gray-500 mt-2">Outstanding Receivable</p>
                        <p className="text-lg font-extrabold text-blue-600 font-mono mt-0.5 print-text-black">
                            {formatPKR(invoice.remaining_balance)}
                        </p>
                    </div>
                </div>

                {/* Items Table */}
                <div className="mb-8 overflow-x-auto">
                    <table className="w-full text-left border-collapse table-auto text-xs">
                        <thead>
                            <tr className="border-b-2 border-gray-300 text-gray-600 uppercase font-mono tracking-wider font-bold print-border-black">
                                <th className="py-3 pr-4">Product Name</th>
                                <th className="py-3 px-2">Type/Color</th>
                                <th className="py-3 px-2">Grade</th>
                                <th className="py-3 px-2">Thickness</th>
                                <th className="py-3 px-2">Size</th>
                                <th className="py-3 px-2 text-center">Unit</th>
                                <th className="py-3 px-2 text-right">Qty</th>
                                <th className="py-3 px-2 text-right">Rate</th>
                                <th className="py-3 pl-4 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 text-gray-700 font-mono print-border-black">
                            {invoice.order.items.map((item, idx) => (
                                <tr key={item.id || idx} className="align-middle">
                                    <td className="py-3 pr-4 font-sans font-semibold text-gray-900">
                                        {item.product.name}
                                    </td>
                                    <td className="py-3 px-2 text-gray-500">
                                        {item.product.stone_color || '-'}
                                    </td>
                                    <td className="py-3 px-2 text-gray-500">
                                        {item.product.quality_grade || '-'}
                                    </td>
                                    <td className="py-3 px-2 text-gray-500">
                                        {item.product.thickness || '-'}
                                    </td>
                                    <td className="py-3 px-2 text-gray-500">
                                        {item.product.size || '-'}
                                    </td>
                                    <td className="py-3 px-2 text-center text-gray-500">
                                        {item.product.unit_of_measure || 'pcs'}
                                    </td>
                                    <td className="py-3 px-2 text-right text-gray-900 font-bold">
                                        {Number(item.quantity).toFixed(2)}
                                    </td>
                                    <td className="py-3 px-2 text-right text-gray-500">
                                        {formatPKR(item.unit_price)}
                                    </td>
                                    <td className="py-3 pl-4 text-right text-gray-900 font-bold">
                                        {formatPKR(item.total_price)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Summary Section */}
                <div className="flex flex-col md:flex-row justify-between items-start pt-6 border-t border-gray-200 print-border-black">
                    <div className="max-w-md text-xs text-gray-700 leading-relaxed font-sans mb-4 md:mb-0">
                        <h4 className="font-bold text-gray-500 mb-1">Terms & Conditions (Payment Terms: {paymentTerms}):</h4>
                        {footerNotes ? (
                            <p className="whitespace-pre-line">{footerNotes}</p>
                        ) : (
                            <>
                                <p>1. Payments are ledger-credited only. Outstanding receivables accrue aging indices.</p>
                                <p>2. Goods once cut or sized in custom dimensions are non-returnable.</p>
                                <p>3. This represents a computerized statement of account voucher.</p>
                            </>
                        )}
                        {bankDetails && (
                            <p className="mt-2 text-[10px] text-gray-500 font-semibold font-mono">
                                Bank Details: {bankDetails}
                            </p>
                        )}
                        <p className="mt-2 text-[9px] text-gray-700 italic">
                            * Note: Tax display is for display and calculation configuration only. Accounting auto-posting is not enabled.
                        </p>
                    </div>

                    <div className="w-full md:w-80 font-mono text-xs text-gray-600 space-y-2">
                        <div className="flex justify-between">
                            <span>Subtotal (Gross):</span>
                            <span className="text-gray-800">{formatPKR(invoice.gross_amount)}</span>
                        </div>
                        <div className="flex justify-between text-red-500">
                            <span>Discount:</span>
                            <span>- {formatPKR(invoice.discount_amount)}</span>
                        </div>
                        <div className="flex justify-between">
                            <span>Delivery/Freight:</span>
                            <span className="text-gray-800">+ {formatPKR(invoice.delivery_charges)}</span>
                        </div>
                        {salesTaxPercent > 0 && (
                            <div className="flex justify-between text-gray-500 italic">
                                <span>Est. Sales Tax ({salesTaxPercent}%):</span>
                                <span>+ {formatPKR(Number(invoice.gross_amount) * (salesTaxPercent / 100))}</span>
                            </div>
                        )}
                        {withholdingTaxPercent > 0 && (
                            <div className="flex justify-between text-gray-500 italic">
                                <span>Est. Withholding Tax ({withholdingTaxPercent}%):</span>
                                <span>- {formatPKR(Number(invoice.gross_amount) * (withholdingTaxPercent / 100))}</span>
                            </div>
                        )}
                        <div className="flex justify-between font-bold text-sm text-gray-900 pt-2 border-t border-gray-200 print-border-black">
                            <span>Net Total:</span>
                            <span>{formatPKR(invoice.net_amount)}</span>
                        </div>
                        <div className="flex justify-between text-emerald-600 font-bold">
                            <span>Amount Received:</span>
                            <span>{formatPKR(totalPaid)}</span>
                        </div>
                        <div className="flex justify-between font-extrabold text-blue-600 text-sm pt-2 border-t-2 border-double border-gray-200 print-border-black print-text-black">
                            <span>Balance Remaining:</span>
                            <span>{formatPKR(invoice.remaining_balance)}</span>
                        </div>
                    </div>
                </div>

                {/* Signature footer lines */}
                <div className="grid grid-cols-2 gap-8 mt-16 pt-8 border-t border-gray-200 font-mono text-xs text-gray-700 print-border-black text-center">
                    <div>
                        <div className="w-48 border-b border-gray-300 mx-auto mb-2 print-border-black" />
                        <p>Prepared By</p>
                    </div>
                    <div>
                        <div className="w-48 border-b border-gray-300 mx-auto mb-2 print-border-black" />
                        <p>Authorized Signature</p>
                    </div>
                </div>
            </div>
        </div>
    );
}
