<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankTransaction;
use App\Services\BankReconciliationService;
use Exception;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BankReconciliationController extends Controller
{
    protected BankReconciliationService $reconciliationService;

    public function __construct(BankReconciliationService $reconciliationService)
    {
        $this->reconciliationService = $reconciliationService;
    }

    /**
     * Display the bank reconciliation UI.
     */
    public function index(Request $request)
    {
        $bankAccounts = BankAccount::orderBy('account_name')->get();
        $bankAccountId = $request->query('bank_account_id');

        $activeReconciliation = null;
        $transactions = [];

        if ($bankAccountId) {
            // Find active (unclosed) reconciliation for this account
            $activeReconciliation = BankReconciliation::where('bank_account_id', $bankAccountId)
                ->where('is_closed', false)
                ->first();

            // Load transactions (for UI, we show unreconciled + those reconciled in the current active reconciliation)
            $query = BankTransaction::where('bank_account_id', $bankAccountId)
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc');

            if ($activeReconciliation) {
                // Show unreconciled + reconciled in this session
                $query->where(function ($q) use ($activeReconciliation) {
                    $q->where('is_reconciled', false)
                        ->orWhere('bank_reconciliation_id', $activeReconciliation->id);
                });
            } else {
                // No active session: just show unreconciled so they can see what's pending
                $query->where('is_reconciled', false);
            }

            $transactions = $query->get()->map(function ($t) {
                return [
                    'id' => $t->id,
                    'date' => $t->date->toDateString(),
                    'reference' => $t->reference_number ?? ($t->reference_type.'-'.$t->reference_id),
                    'description' => $t->description,
                    'debit' => $t->amount > 0 ? $t->amount : 0,
                    'credit' => $t->amount < 0 ? abs($t->amount) : 0,
                    'is_reconciled' => $t->is_reconciled,
                ];
            });
        }

        return Inertia::render('CashBank/Reconciliation', [
            'bankAccounts' => $bankAccounts,
            'activeReconciliation' => $activeReconciliation,
            'transactions' => $transactions,
            'filters' => [
                'bank_account_id' => $bankAccountId,
            ],
        ]);
    }

    /**
     * Open a new bank reconciliation.
     */
    public function open(Request $request)
    {
        $validated = $request->validate([
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'statement_date' => 'required|date',
            'statement_balance' => 'required|numeric',
        ]);

        // Check if there is already an open reconciliation
        $existing = BankReconciliation::where('bank_account_id', $validated['bank_account_id'])
            ->where('is_closed', false)
            ->first();

        if ($existing) {
            return back()->withErrors(['reconciliation' => 'There is already an open reconciliation for this bank account. Please close it first.']);
        }

        try {
            $this->reconciliationService->openReconciliation(
                $validated['bank_account_id'],
                $validated['statement_date'],
                (float) $validated['statement_balance']
            );

            return redirect()->route('cashbank.reconciliation.index', ['bank_account_id' => $validated['bank_account_id']])
                ->with('success', 'Bank reconciliation opened successfully.');
        } catch (Exception $e) {
            return back()->withErrors(['reconciliation' => $e->getMessage()]);
        }
    }

    /**
     * Bulk reconcile transactions through the statement date.
     */
    public function bulkReconcileToDate(Request $request)
    {
        $validated = $request->validate([
            'reconciliation_id' => 'required|exists:bank_reconciliations,id',
            'statement_date' => 'required|date', // For period lock check middleware
        ]);

        try {
            $count = $this->reconciliationService->bulkReconcileToStatementDate($validated['reconciliation_id']);

            return back()->with('success', "Reconciled {$count} transactions through the statement date.");
        } catch (Exception $e) {
            return back()->withErrors(['reconciliation' => $e->getMessage()]);
        }
    }

    /**
     * Manually reconcile/unreconcile a transaction.
     */
    public function reconcileTransaction(Request $request)
    {
        $validated = $request->validate([
            'transaction_id' => 'required|exists:bank_transactions,id',
            'reconciliation_id' => 'required|exists:bank_reconciliations,id',
            'statement_date' => 'required|date', // For period lock check
        ]);

        try {
            $this->reconciliationService->reconcileTransaction($validated['transaction_id'], $validated['reconciliation_id']);

            return back();
        } catch (Exception $e) {
            return back()->withErrors(['reconciliation' => $e->getMessage()]);
        }
    }

    /**
     * Close a reconciliation.
     */
    public function close(Request $request)
    {
        $validated = $request->validate([
            'reconciliation_id' => 'required|exists:bank_reconciliations,id',
            'statement_date' => 'required|date', // For period lock check
        ]);

        try {
            $this->reconciliationService->closeReconciliation($validated['reconciliation_id']);

            return back()->with('success', 'Bank reconciliation closed successfully.');
        } catch (Exception $e) {
            return back()->withErrors(['reconciliation' => $e->getMessage()]);
        }
    }
}
