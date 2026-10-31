<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankTransaction;
use Exception;
use Illuminate\Support\Facades\DB;

class BankReconciliationService
{
    /**
     * Open a new bank reconciliation record.
     *
     * @throws Exception
     */
    public function openReconciliation(int $bankAccountId, string $statementDate, float $statementBalance): BankReconciliation
    {
        return DB::transaction(function () use ($bankAccountId, $statementDate, $statementBalance) {
            $bankAccount = BankAccount::findOrFail($bankAccountId);

            // Calculate system_balance: sum of all bank_transactions for that account up to the statement date
            $systemBalance = BankTransaction::where('bank_account_id', $bankAccountId)
                ->whereDate('date', '<=', $statementDate)
                ->sum('amount');

            $difference = $statementBalance - $systemBalance;

            $reconciliation = BankReconciliation::create([
                'bank_account_id' => $bankAccountId,
                'statement_date' => $statementDate,
                'statement_balance' => $statementBalance,
                'system_balance' => $systemBalance,
                'difference' => $difference,
                'is_closed' => false,
            ]);

            return $reconciliation;
        });
    }

    /**
     * Reconcile every pending transaction through the statement date.
     *
     * This is an explicit bulk action, not statement-line matching. The UI requires
     * confirmation and users can still toggle individual transactions before closing.
     */
    public function bulkReconcileToStatementDate(int $reconciliationId): int
    {
        return DB::transaction(function () use ($reconciliationId) {
            $reconciliation = BankReconciliation::findOrFail($reconciliationId);

            if ($reconciliation->is_closed) {
                throw new Exception('Cannot bulk reconcile a closed reconciliation.');
            }

            $updatedCount = BankTransaction::where('bank_account_id', $reconciliation->bank_account_id)
                ->where('is_reconciled', false)
                ->whereDate('date', '<=', $reconciliation->statement_date)
                ->update([
                    'is_reconciled' => true,
                    'bank_reconciliation_id' => $reconciliation->id,
                ]);

            $this->recalculateDifference($reconciliation);

            return $updatedCount;
        });
    }

    /**
     * Manually toggle a transaction's reconciliation status.
     *
     * @throws Exception
     */
    public function reconcileTransaction(int $transactionId, int $reconciliationId): BankTransaction
    {
        return DB::transaction(function () use ($transactionId, $reconciliationId) {
            $reconciliation = BankReconciliation::findOrFail($reconciliationId);

            if ($reconciliation->is_closed) {
                throw new Exception('Cannot modify a closed reconciliation.');
            }

            $transaction = BankTransaction::where('bank_account_id', $reconciliation->bank_account_id)
                ->findOrFail($transactionId);

            if ($transaction->is_reconciled) {
                // Unreconcile
                $transaction->update([
                    'is_reconciled' => false,
                    'bank_reconciliation_id' => null,
                ]);
            } else {
                // Reconcile
                $transaction->update([
                    'is_reconciled' => true,
                    'bank_reconciliation_id' => $reconciliation->id,
                ]);
            }

            $this->recalculateDifference($reconciliation);

            return $transaction;
        });
    }

    /**
     * Close a reconciliation.
     *
     * @throws Exception
     */
    public function closeReconciliation(int $reconciliationId): BankReconciliation
    {
        return DB::transaction(function () use ($reconciliationId) {
            $reconciliation = BankReconciliation::findOrFail($reconciliationId);

            if ($reconciliation->is_closed) {
                throw new Exception('Reconciliation is already closed.');
            }

            $this->recalculateDifference($reconciliation);

            if (abs($reconciliation->difference) > 0.0001) {
                throw new Exception('Cannot close reconciliation. Difference is not zero.');
            }

            $reconciliation->update([
                'is_closed' => true,
                'closed_by' => auth()->id(),
                'closed_at' => now(),
            ]);

            return $reconciliation;
        });
    }

    /**
     * Recalculates the system balance based on reconciled transactions and updates the difference.
     * In a typical reconciliation, the "system_balance" for the reconciliation purpose
     * is the sum of RECONCILED transactions, or the difference is based on statement - reconciled.
     * Let's look at standard practice:
     * Adjusted System Balance = (Beginning Balance + Reconciled Transactions)
     * Here we can just calculate system balance as the sum of all reconciled transactions
     * up to the statement date + any prior balances, but for simplicity:
     * The system_balance initially is ALL transactions.
     * But the difference to zero out usually means:
     * Statement Balance - Sum of all RECONCILED transactions = 0.
     * Let's adjust system_balance to be the sum of all reconciled transactions for this account.
     */
    private function recalculateDifference(BankReconciliation $reconciliation): void
    {
        // System balance is the sum of all reconciled transactions up to now
        $systemBalance = BankTransaction::where('bank_account_id', $reconciliation->bank_account_id)
            ->where('is_reconciled', true)
            ->sum('amount');

        $difference = $reconciliation->statement_balance - $systemBalance;

        $reconciliation->update([
            'system_balance' => $systemBalance,
            'difference' => $difference,
        ]);
    }
}
