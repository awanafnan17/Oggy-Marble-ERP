<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\CustomerInvoice;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Payment;
use App\Models\SupplierBill;
use App\Services\CashBankService;
use App\Services\PeriodService;
use App\Services\SubledgerService;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentsController extends Controller
{
    protected SubledgerService $subledgerService;

    protected CashBankService $cashBankService;

    protected PeriodService $periodService;

    public function __construct(SubledgerService $subledgerService, CashBankService $cashBankService, PeriodService $periodService)
    {
        $this->subledgerService = $subledgerService;
        $this->cashBankService = $cashBankService;
        $this->periodService = $periodService;
    }

    /**
     * Store customer payment (receipt).
     */
    public function storeCustomerPayment(Request $request)
    {
        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|gt:0',
            'payment_method' => 'required|string|max:255',
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'invoice_id' => 'required|exists:customer_invoices,id',
            'notes' => 'nullable|string',
        ]);

        $bankAccount = BankAccount::findOrFail($validated['bank_account_id']);
        if (empty($bankAccount->account_id)) {
            throw ValidationException::withMessages([
                'bank_account_id' => "Selected bank/cash account '{$bankAccount->account_name}' is missing dynamic G/L account mapping.",
            ]);
        }

        $arAccount = Account::where('type', 'asset')->where('name', 'like', '%Receivable%')->first();
        if (! $arAccount) {
            throw ValidationException::withMessages([
                'amount' => 'Accounts Receivable ledger account not found in database.',
            ]);
        }

        try {
            DB::transaction(function () use ($validated, $bankAccount, $arAccount) {
                $bankAccount = BankAccount::findOrFail($validated['bank_account_id']);
                $amount = (float) $validated['amount'];

                // Overpayment validation
                $invoice = CustomerInvoice::findOrFail($validated['invoice_id']);
                if ($amount > $invoice->remaining_balance) {
                    throw ValidationException::withMessages([
                        'amount' => 'Overpayment is not allowed. Allocation amount (Rs. '.number_format($amount, 2).') exceeds remaining invoice balance (Rs. '.number_format($invoice->remaining_balance, 2).').',
                    ]);
                }

                // 1. Create Payment
                $paymentNumber = 'PAY-REC-'.time().'-'.rand(100, 999);
                $payment = Payment::create([
                    'payment_number' => $paymentNumber,
                    'contact_id' => $validated['contact_id'],
                    'payment_date' => $validated['payment_date'],
                    'amount' => $amount,
                    'type' => 'receipt',
                    'payment_method' => $validated['payment_method'],
                    'notes' => $validated['notes'] ?? null,
                    'bank_account_id' => $bankAccount->id,
                ]);

                // 2. Allocate to invoice using SubledgerService
                $this->subledgerService->recordInvoicePayment($payment->id, $validated['invoice_id'], $amount);

                // 3. Deposit to cash bank subledger
                $this->cashBankService->deposit(
                    $bankAccount->id,
                    $amount,
                    $validated['payment_date'],
                    'payment',
                    $payment->id,
                    ($validated['notes'] ?? null) ?: 'Inward collection from customer'
                );

                // 4. Post double-entry G/L journal
                $arAccount = Account::where('type', 'asset')->where('name', 'like', '%Receivable%')->first();
                if (! $arAccount) {
                    throw new Exception('Accounts Receivable ledger account not found.');
                }

                $entry = JournalEntry::create([
                    'entry_number' => 'JV-REC-'.time().'-'.rand(100, 999),
                    'date' => $validated['payment_date'],
                    'description' => ($validated['notes'] ?? null) ?: 'Customer payment collection',
                    'reference_type' => 'payment',
                    'reference_id' => $payment->id,
                ]);

                // Debit Cash/Bank Account (Asset Increase)
                if ($bankAccount->account_id) {
                    JournalItem::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $bankAccount->account_id,
                        'debit' => $amount,
                        'credit' => 0.0,
                    ]);
                }

                // Credit Accounts Receivable (Asset Decrease)
                JournalItem::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $arAccount->id,
                    'debit' => 0.0,
                    'credit' => $amount,
                ]);
            });
        } catch (Exception $e) {
            $businessMessages = ['ledger account not found', 'closed'];
            $isBusiness = false;
            foreach ($businessMessages as $msg) {
                if (stripos($e->getMessage(), $msg) !== false) {
                    $isBusiness = true;
                    break;
                }
            }
            if ($isBusiness && ! ($e instanceof QueryException) && ! ($e instanceof \TypeError) && ! ($e instanceof \Error)) {
                throw ValidationException::withMessages([
                    'amount' => $e->getMessage(),
                ]);
            }
            throw $e;
        }

        return redirect()->back()->with('success', 'Customer payment processed successfully.');
    }

    /**
     * Store supplier payment (disbursement).
     */
    public function storeSupplierPayment(Request $request)
    {
        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|gt:0',
            'payment_method' => 'required|string|max:255',
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'bill_id' => 'required|exists:supplier_bills,id',
            'notes' => 'nullable|string',
        ]);

        $bankAccount = BankAccount::findOrFail($validated['bank_account_id']);
        if (empty($bankAccount->account_id)) {
            throw ValidationException::withMessages([
                'bank_account_id' => "Selected bank/cash account '{$bankAccount->account_name}' is missing dynamic G/L account mapping.",
            ]);
        }

        $apAccount = Account::where('type', 'liability')->where('name', 'like', '%Payable%')->first();
        if (! $apAccount) {
            throw ValidationException::withMessages([
                'amount' => 'Accounts Payable ledger account not found in database.',
            ]);
        }

        try {
            DB::transaction(function () use ($validated, $bankAccount, $apAccount) {
                $bankAccount = BankAccount::findOrFail($validated['bank_account_id']);
                $amount = (float) $validated['amount'];

                // Overpayment validation
                $bill = SupplierBill::findOrFail($validated['bill_id']);
                if ($amount > $bill->remaining_balance) {
                    throw ValidationException::withMessages([
                        'amount' => 'Overpayment is not allowed. Payment amount (Rs. '.number_format($amount, 2).') exceeds remaining bill balance (Rs. '.number_format($bill->remaining_balance, 2).').',
                    ]);
                }

                // 1. Create Payment
                $paymentNumber = 'PAY-DISB-'.time().'-'.rand(100, 999);
                $payment = Payment::create([
                    'payment_number' => $paymentNumber,
                    'contact_id' => $validated['contact_id'],
                    'payment_date' => $validated['payment_date'],
                    'amount' => $amount,
                    'type' => 'payment',
                    'payment_method' => $validated['payment_method'],
                    'notes' => $validated['notes'] ?? null,
                    'bank_account_id' => $bankAccount->id,
                ]);

                // 2. Allocate to bill using SubledgerService
                $this->subledgerService->recordBillPayment($payment->id, $validated['bill_id'], $amount);

                // 3. Withdraw from cash bank subledger
                $this->cashBankService->withdraw(
                    $bankAccount->id,
                    $amount,
                    $validated['payment_date'],
                    'payment',
                    $payment->id,
                    ($validated['notes'] ?? null) ?: 'Disbursement paid to vendor'
                );

                // 4. Post double-entry G/L journal
                $apAccount = Account::where('type', 'liability')->where('name', 'like', '%Payable%')->first();
                if (! $apAccount) {
                    throw new Exception('Accounts Payable ledger account not found.');
                }

                $entry = JournalEntry::create([
                    'entry_number' => 'JV-DISB-'.time().'-'.rand(100, 999),
                    'date' => $validated['payment_date'],
                    'description' => ($validated['notes'] ?? null) ?: 'Vendor payment disbursement',
                    'reference_type' => 'payment',
                    'reference_id' => $payment->id,
                ]);

                // Debit Accounts Payable (Liability Decrease)
                JournalItem::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $apAccount->id,
                    'debit' => $amount,
                    'credit' => 0.0,
                ]);

                // Credit Cash/Bank Account (Asset Decrease)
                if ($bankAccount->account_id) {
                    JournalItem::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $bankAccount->account_id,
                        'debit' => 0.0,
                        'credit' => $amount,
                    ]);
                }
            });
        } catch (Exception $e) {
            $businessMessages = ['ledger account not found', 'closed'];
            $isBusiness = false;
            foreach ($businessMessages as $msg) {
                if (stripos($e->getMessage(), $msg) !== false) {
                    $isBusiness = true;
                    break;
                }
            }
            if ($isBusiness && ! ($e instanceof QueryException) && ! ($e instanceof \TypeError) && ! ($e instanceof \Error)) {
                throw ValidationException::withMessages([
                    'amount' => $e->getMessage(),
                ]);
            }
            throw $e;
        }

        return redirect()->back()->with('success', 'Supplier payment processed successfully.');
    }

    public function voidPayment(Request $request, $id)
    {
        $request->validate([
            'void_reason' => 'required|string|min:5',
        ]);

        $payment = Payment::findOrFail($id);

        if ($payment->type === 'receipt') {
            if (! $request->user() || ! $request->user()->hasPermission('manage_sales')) {
                abort(403, 'Unauthorized action.');
            }
        } else {
            if (! $request->user() || ! $request->user()->hasPermission('manage_purchases')) {
                abort(403, 'Unauthorized action.');
            }
        }

        if ($payment->voided_at !== null) {
            throw ValidationException::withMessages(['error' => 'This payment has already been voided.']);
        }

        try {
            // Period lock check
            $this->periodService->ensurePeriodOpen(Carbon::parse($payment->payment_date));

            DB::transaction(function () use ($payment, $request) {
                $user = auth()->user();
                $reason = $request->void_reason;
                $now = now();

                // 1. Mark Payment as voided
                $payment->update([
                    'voided_at' => $now,
                    'voided_by' => $user->id,
                    'void_reason' => $reason,
                ]);

                // 2. Restore Invoice/Bill Remaining Balance & Update status
                foreach ($payment->allocations as $alloc) {
                    $allocatable = $alloc->allocatable;
                    if ($allocatable) {
                        $allocatable->remaining_balance += $alloc->amount;
                        // Recompute status
                        if ($allocatable->remaining_balance <= 0.0001) {
                            $allocatable->status = 'paid';
                        } elseif ($allocatable->remaining_balance >= $allocatable->total_amount - 0.0001) {
                            $allocatable->status = 'unpaid';
                        } else {
                            $allocatable->status = 'partial';
                        }
                        $allocatable->save();
                    }

                    $alloc->update([
                        'voided_at' => $now,
                        'voided_by' => $user->id,
                        'void_reason' => $reason,
                    ]);
                }

                // 3. Find original JournalEntry
                $origEntry = JournalEntry::where('reference_type', 'payment')
                    ->where('reference_id', $payment->id)
                    ->whereNull('voided_at')
                    ->first();

                if ($origEntry) {
                    // Create Reversal Journal Entry
                    $revEntry = JournalEntry::create([
                        'entry_number' => 'REV-'.$origEntry->entry_number,
                        'date' => now()->toDateString(),
                        'description' => 'Reversal of '.$origEntry->description.' - Reason: '.$reason,
                        'reference_type' => 'payment',
                        'reference_id' => $payment->id,
                        'original_reference_type' => 'journal_entries',
                        'original_reference_id' => $origEntry->id,
                        'voided_at' => $now,
                        'voided_by' => $user->id,
                        'void_reason' => $reason,
                    ]);

                    // Swapped Journal Items
                    foreach ($origEntry->items as $item) {
                        JournalItem::create([
                            'journal_entry_id' => $revEntry->id,
                            'account_id' => $item->account_id,
                            'debit' => $item->credit,
                            'credit' => $item->debit,
                        ]);
                    }

                    // Link entries
                    $origEntry->update([
                        'reversed_by_entry_id' => $revEntry->id,
                        'voided_at' => $now,
                        'voided_by' => $user->id,
                        'void_reason' => $reason,
                    ]);

                    $revEntry->update([
                        'reversal_journal_entry_id' => $origEntry->id,
                    ]);
                }

                // 4. Reverse BankTransaction
                $bankTransactions = BankTransaction::where('reference_type', 'payment')
                    ->where('reference_id', $payment->id)
                    ->whereNull('voided_at')
                    ->get();

                foreach ($bankTransactions as $bt) {
                    // Create reversing BankTransaction
                    BankTransaction::create([
                        'bank_account_id' => $bt->bank_account_id,
                        'date' => now()->toDateString(),
                        'type' => $bt->type === 'deposit' ? 'withdrawal' : 'deposit',
                        'amount' => $bt->amount,
                        'reference_type' => 'payment',
                        'reference_id' => $payment->id,
                        'description' => 'Reversal: '.$bt->description.' - Reason: '.$reason,
                        'voided_at' => $now,
                        'voided_by' => $user->id,
                        'void_reason' => $reason,
                    ]);

                    $bt->update([
                        'voided_at' => $now,
                        'voided_by' => $user->id,
                        'void_reason' => $reason,
                    ]);
                }
            });
        } catch (Exception $e) {
            throw ValidationException::withMessages(['error' => $e->getMessage()]);
        }

        return redirect()->back()->with('success', 'Payment voided successfully.');
    }
}
