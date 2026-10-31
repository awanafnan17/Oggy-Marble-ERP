<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Contact;
use App\Models\CustomerInvoice;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\PakistanBank;
use App\Models\SupplierBill;
use App\Services\CashBankService;
use App\Services\ImageOptimizationService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CashBankController extends Controller
{
    protected CashBankService $cashBankService;

    protected ImageOptimizationService $imageOptimizationService;

    public function __construct(
        CashBankService $cashBankService,
        ImageOptimizationService $imageOptimizationService
    ) {
        $this->cashBankService = $cashBankService;
        $this->imageOptimizationService = $imageOptimizationService;
    }

    public function index()
    {
        $bankAccounts = BankAccount::with('account')->get()->map(function ($acc) {
            return [
                'id' => $acc->id,
                'account_name' => $acc->account_name,
                'account_number' => $acc->account_number,
                'bank_name' => $acc->bank_name,
                'type' => $acc->type,
                'gl_account_code' => $acc->account ? $acc->account->code : null,
                'balance' => $this->cashBankService->getBalance($acc->id),
            ];
        });

        $recentTransactions = BankTransaction::with('bankAccount')
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        $accounts = Account::orderBy('code')->get();
        $contacts = Contact::orderBy('name')->get();
        $unpaidInvoices = CustomerInvoice::with('contact')->where('status', '!=', 'paid')->get();
        $unpaidBills = SupplierBill::with('contact')->where('status', '!=', 'paid')->get();

        return Inertia::render('CashBank/Index', [
            'bankAccounts' => $bankAccounts,
            'recentTransactions' => $recentTransactions,
            'accounts' => $accounts,
            'contacts' => $contacts,
            'unpaidInvoices' => $unpaidInvoices,
            'unpaidBills' => $unpaidBills,
            'pakistanBanks' => PakistanBank::getGroupedForDropdown(),
        ]);
    }

    public function storeAccount(Request $request)
    {
        $validated = $request->validate([
            'account_name' => 'required|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'type' => 'required|in:bank,cash',
            'gl_code' => 'required|string|unique:accounts,code|max:50',
        ]);

        DB::transaction(function () use ($validated) {
            // 1. Create matching G/L account first
            $glAccount = Account::create([
                'code' => $validated['gl_code'],
                'name' => $validated['account_name'].' (Bank Subledger)',
                'type' => 'asset',
            ]);

            // 2. Create bank account record
            BankAccount::create([
                'account_id' => $glAccount->id,
                'account_name' => $validated['account_name'],
                'account_number' => $validated['account_number'] ?? null,
                'bank_name' => $validated['bank_name'] ?? null,
                'type' => $validated['type'],
            ]);
        });

        return redirect()->back()->with('success', 'Cash/Bank account and matching G/L account created successfully.');
    }

    public function storeTransaction(Request $request)
    {
        $validated = $request->validate([
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'date' => 'required|date',
            'type' => 'required|in:deposit,withdrawal',
            'amount' => 'required|numeric|gt:0',
            'description' => 'nullable|string|max:255',
            'contra_account_id' => 'required|exists:accounts,id',
            'proof_image' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        $storedProof = null;
        if ($request->hasFile('proof_image') && $request->file('proof_image')->isValid()) {
            try {
                $storedProof = $this->imageOptimizationService->optimizeAndStore($request->file('proof_image'));
            } catch (Exception $e) {
                return redirect()->back()->withErrors(['proof_image' => $e->getMessage()]);
            }
        }

        try {
            DB::transaction(function () use ($validated, $storedProof) {
                $bankAccount = BankAccount::findOrFail($validated['bank_account_id']);
                $contraAccount = Account::findOrFail($validated['contra_account_id']);
                $amount = (float) $validated['amount'];
                $type = $validated['type'];

                $proofPath = $storedProof['path'] ?? null;
                $proofSize = $storedProof['size'] ?? null;
                $proofMime = $storedProof['mime'] ?? null;

                // 1. Log sub-ledger transaction with optional proof
                if ($type === 'deposit') {
                    $tx = $this->cashBankService->deposit(
                        $bankAccount->id,
                        $amount,
                        $validated['date'],
                        'manual',
                        null,
                        $validated['description'] ?? null,
                        $proofPath,
                        $proofSize,
                        $proofMime
                    );
                } else {
                    $tx = $this->cashBankService->withdraw(
                        $bankAccount->id,
                        $amount,
                        $validated['date'],
                        'manual',
                        null,
                        $validated['description'] ?? null,
                        $proofPath,
                        $proofSize,
                        $proofMime
                    );
                }

                // 2. Double-entry G/L posting
                $entry = JournalEntry::create([
                    'entry_number' => 'JV-BANK-'.time().'-'.rand(100, 999),
                    'date' => $validated['date'],
                    'description' => $validated['description'] ?? ucfirst($type).' transaction',
                    'reference_type' => 'bank_transaction',
                    'reference_id' => $tx->id,
                ]);

                // Debit Cash/Bank for deposit, Credit for withdrawal
                if ($bankAccount->account_id) {
                    JournalItem::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $bankAccount->account_id,
                        'debit' => $type === 'deposit' ? $amount : 0,
                        'credit' => $type === 'withdrawal' ? $amount : 0,
                    ]);

                    // Credit Contra account for deposit, Debit for withdrawal
                    JournalItem::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $contraAccount->id,
                        'debit' => $type === 'withdrawal' ? $amount : 0,
                        'credit' => $type === 'deposit' ? $amount : 0,
                    ]);
                }
            });
        } catch (Exception $e) {
            if ($storedProof && isset($storedProof['path'])) {
                $this->imageOptimizationService->deleteImage($storedProof['path']);
            }

            return redirect()->back()->withErrors(['amount' => 'Transaction failed: '.$e->getMessage()]);
        }

        return redirect()->back()->with('success', 'Transaction posted successfully.');
    }

    public function storeTransfer(Request $request)
    {
        $validated = $request->validate([
            'from_bank_account_id' => 'required|exists:bank_accounts,id',
            'to_bank_account_id' => 'required|exists:bank_accounts,id|different:from_bank_account_id',
            'amount' => 'required|numeric|gt:0',
            'date' => 'required|date',
            'description' => 'nullable|string|max:255',
            'proof_image' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        $storedProof = null;
        if ($request->hasFile('proof_image') && $request->file('proof_image')->isValid()) {
            try {
                $storedProof = $this->imageOptimizationService->optimizeAndStore($request->file('proof_image'));
            } catch (Exception $e) {
                return redirect()->back()->withErrors(['proof_image' => $e->getMessage()]);
            }
        }

        try {
            $proofPath = $storedProof['path'] ?? null;
            $proofSize = $storedProof['size'] ?? null;
            $proofMime = $storedProof['mime'] ?? null;

            $this->cashBankService->transfer(
                $validated['from_bank_account_id'],
                $validated['to_bank_account_id'],
                (float) $validated['amount'],
                $validated['date'],
                $validated['description'] ?? null,
                $proofPath,
                $proofSize,
                $proofMime
            );
        } catch (Exception $e) {
            if ($storedProof && isset($storedProof['path'])) {
                $this->imageOptimizationService->deleteImage($storedProof['path']);
            }

            return redirect()->back()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()->back()->with('success', 'Funds transferred successfully.');
    }

    /**
     * Authenticated and authorized streaming of transaction proof image from private storage.
     */
    public function showProof(BankTransaction $bankTransaction)
    {
        if (empty($bankTransaction->proof_image_path)) {
            abort(404, 'No proof image attached to this transaction.');
        }

        $fullPath = Storage::disk('local')->path($bankTransaction->proof_image_path);
        if (! file_exists($fullPath)) {
            abort(404, 'Proof image file not found in storage.');
        }

        return response()->file($fullPath, [
            'Content-Type' => $bankTransaction->proof_image_mime ?: 'image/webp',
            'Cache-Control' => 'private, max-age=86400',
            'Content-Disposition' => 'inline; filename="transaction_proof_'.$bankTransaction->id.'.webp"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
