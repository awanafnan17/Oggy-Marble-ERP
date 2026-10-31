<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\CustomerInvoice;
use App\Models\Payment;
use App\Models\RentalJob;
use App\Models\SupplierBill;
use App\Services\SubledgerService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PartyLedgerController extends Controller
{
    protected SubledgerService $subledgerService;

    public function __construct(SubledgerService $subledgerService)
    {
        $this->subledgerService = $subledgerService;
    }

    public function index(Request $request)
    {
        $contacts = Contact::orderBy('name')->get();
        $contactId = $request->input('contact_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $ledgerData = [];
        $selectedContact = null;
        $agingSummary = null;

        if ($contactId) {
            $selectedContact = Contact::findOrFail($contactId);

            // Fetch transactions
            $txs = [];

            // Add Opening Balance if positive
            if ((float) $selectedContact->opening_balance > 0.0001) {
                $isDebit = $selectedContact->opening_balance_type === 'debit';
                $opDate = $selectedContact->created_at ? $selectedContact->created_at->toDateString() : '2026-01-01';
                $txs[] = [
                    'date' => $opDate,
                    'reference' => 'OP-BAL',
                    'description' => 'Opening Balance',
                    'debit' => $isDebit ? (float) $selectedContact->opening_balance : 0.0,
                    'credit' => ! $isDebit ? (float) $selectedContact->opening_balance : 0.0,
                ];
            }

            if ($selectedContact->type === 'customer' || $selectedContact->type === 'both') {
                // 1. Customer Invoices
                $invoicesQuery = CustomerInvoice::where('contact_id', $contactId)->where('status', '!=', 'voided');
                if ($startDate) {
                    $invoicesQuery->where('issue_date', '>=', $startDate);
                }
                if ($endDate) {
                    $invoicesQuery->where('issue_date', '<=', $endDate);
                }
                foreach ($invoicesQuery->get() as $inv) {
                    $txs[] = [
                        'date' => $inv->issue_date->toDateString(),
                        'reference' => $inv->invoice_number,
                        'description' => 'Sales Invoice (Product Sale)',
                        'debit' => (float) $inv->net_amount ?: (float) $inv->total_amount,
                        'credit' => 0.0,
                    ];
                }

                // 2. Machine Rental Invoices (customers only)
                $rentalQuery = RentalJob::where('contact_id', $contactId);
                if ($startDate) {
                    $rentalQuery->where('job_date', '>=', $startDate);
                }
                if ($endDate) {
                    $rentalQuery->where('job_date', '<=', $endDate);
                }
                foreach ($rentalQuery->get() as $job) {
                    $txs[] = [
                        'date' => $job->job_date->toDateString(),
                        'reference' => $job->job_number,
                        'description' => 'Machine Rental Bill: Hours Logged',
                        'debit' => (float) $job->total_amount,
                        'credit' => 0.0,
                    ];
                }

                // 3. Customer Payments (receipts)
                $paymentsQuery = Payment::where('contact_id', $contactId)->where('type', 'receipt')->whereNull('voided_at');
                if ($startDate) {
                    $paymentsQuery->where('payment_date', '>=', $startDate);
                }
                if ($endDate) {
                    $paymentsQuery->where('payment_date', '<=', $endDate);
                }
                foreach ($paymentsQuery->get() as $pay) {
                    $txs[] = [
                        'date' => $pay->payment_date->toDateString(),
                        'reference' => $pay->payment_number,
                        'description' => 'Cash/Bank Payment Collection ['.ucfirst($pay->payment_method).']',
                        'debit' => 0.0,
                        'credit' => (float) $pay->amount,
                    ];
                }
            }

            if ($selectedContact->type === 'vendor' || $selectedContact->type === 'both') {
                // 1. Supplier Bills (credits supplier balance)
                $billsQuery = SupplierBill::where('contact_id', $contactId)->where('status', '!=', 'voided');
                if ($startDate) {
                    $billsQuery->where('issue_date', '>=', $startDate);
                }
                if ($endDate) {
                    $billsQuery->where('issue_date', '<=', $endDate);
                }
                foreach ($billsQuery->get() as $bill) {
                    $txs[] = [
                        'date' => $bill->issue_date->toDateString(),
                        'reference' => $bill->bill_number,
                        'description' => 'Supplier Bill (Purchase Block)',
                        'debit' => 0.0,
                        'credit' => (float) $bill->net_amount ?: (float) $bill->total_amount,
                    ];
                }

                // 2. Supplier Payments (debits supplier balance)
                $paymentsQuery = Payment::where('contact_id', $contactId)->where('type', 'payment')->whereNull('voided_at');
                if ($startDate) {
                    $paymentsQuery->where('payment_date', '>=', $startDate);
                }
                if ($endDate) {
                    $paymentsQuery->where('payment_date', '<=', $endDate);
                }
                foreach ($paymentsQuery->get() as $pay) {
                    $txs[] = [
                        'date' => $pay->payment_date->toDateString(),
                        'reference' => $pay->payment_number,
                        'description' => 'Supplier Disbursement Paid ['.ucfirst($pay->payment_method).']',
                        'debit' => (float) $pay->amount,
                        'credit' => 0.0,
                    ];
                }
            }

            // Sort all transactions chronologically
            usort($txs, function ($a, $b) {
                return strcmp($a['date'], $b['date']);
            });

            // Calculate running balances
            $runningBalance = 0.0;
            $ledgerData = [];

            foreach ($txs as $tx) {
                if ($selectedContact->type === 'customer') {
                    // Customer: Debit increases balance, Credit decreases
                    $runningBalance += ($tx['debit'] - $tx['credit']);
                } else {
                    // Supplier: Credit increases balance (payable), Debit decreases
                    $runningBalance += ($tx['credit'] - $tx['debit']);
                }

                $tx['running_balance'] = $runningBalance;
                $ledgerData[] = $tx;
            }

            // Fetch contact aging statistics
            if ($selectedContact->type === 'customer') {
                $aging = $this->subledgerService->getAgingReport('customer');
            } else {
                $aging = $this->subledgerService->getAgingReport('vendor');
            }

            // Filter details for this contact only
            $filteredDetails = collect($aging['details'])->where('contact', $selectedContact->name)->values();
            $agingSummary = [
                'current' => $filteredDetails->where('bucket', '0-30 days')->sum('remaining_balance'),
                'thirty' => $filteredDetails->where('bucket', '31-60 days')->sum('remaining_balance'),
                'sixty' => $filteredDetails->where('bucket', '61-90 days')->sum('remaining_balance'),
                'ninety' => $filteredDetails->where('bucket', '90+ days')->sum('remaining_balance'),
                'total' => $filteredDetails->sum('remaining_balance'),
            ];
        }

        return Inertia::render('Ledger/PartyLedger', [
            'contacts' => $contacts,
            'selectedContact' => $selectedContact,
            'ledgerData' => $ledgerData,
            'agingSummary' => $agingSummary,
            'filters' => [
                'contact_id' => $contactId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:customer,vendor',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'opening_balance' => 'nullable|numeric|min:0',
            'opening_balance_type' => 'nullable|in:debit,credit',
        ]);

        $validated['opening_balance'] = $validated['opening_balance'] ?? 0;
        $validated['opening_balance_type'] = $validated['opening_balance_type'] ?? 'debit';
        $validated['status'] = 'active';

        Contact::create($validated);

        return redirect()->back()->with('success', ucfirst($validated['type'] === 'vendor' ? 'supplier' : $validated['type']).' created successfully.');
    }
}
