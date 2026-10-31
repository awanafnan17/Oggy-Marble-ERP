<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalItem;

class AccountService
{
    /**
     * Compute real-time balance of an account based on type rules.
     */
    public function getBalance(int $accountId, ?string $asOfDate = null): float
    {
        $account = Account::findOrFail($accountId);

        $query = JournalItem::where('account_id', $accountId);

        if ($asOfDate) {
            $query->whereHas('journalEntry', function ($q) use ($asOfDate) {
                $q->where('date', '<=', $asOfDate);
            });
        }

        $totals = $query->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        $debit = (float) ($totals->total_debit ?? 0);
        $credit = (float) ($totals->total_credit ?? 0);

        if (in_array($account->type, ['asset', 'expense'])) {
            return $debit - $credit;
        }

        return $credit - $debit;
    }

    /**
     * Get trial balance dataset.
     */
    public function getTrialBalance(): array
    {
        $accounts = Account::orderBy('code')->get();
        $records = [];
        $totalDebits = 0.0;
        $totalCredits = 0.0;

        foreach ($accounts as $account) {
            $totals = JournalItem::where('account_id', $account->id)
                ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
                ->first();

            $debit = (float) ($totals->total_debit ?? 0);
            $credit = (float) ($totals->total_credit ?? 0);

            $balance = 0.0;
            if (in_array($account->type, ['asset', 'expense'])) {
                $balance = $debit - $credit;
            } else {
                $balance = $credit - $debit;
            }

            $records[] = [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $balance,
            ];

            $totalDebits += $debit;
            $totalCredits += $credit;
        }

        return [
            'accounts' => $records,
            'total_debits' => $totalDebits,
            'total_credits' => $totalCredits,
            'is_balanced' => abs($totalDebits - $totalCredits) < 0.0001,
        ];
    }

    /**
     * Get Balance Sheet data.
     */
    public function getBalanceSheet(?string $asOfDate = null): array
    {
        $accounts = Account::all();

        $assets = 0.0;
        $liabilities = 0.0;
        $equity = 0.0;
        $revenue = 0.0;
        $expense = 0.0;

        foreach ($accounts as $account) {
            $balance = $this->getBalance($account->id, $asOfDate);

            switch ($account->type) {
                case 'asset':
                    $assets += $balance;
                    break;
                case 'liability':
                    $liabilities += $balance;
                    break;
                case 'equity':
                    $equity += $balance;
                    break;
                case 'revenue':
                    $revenue += $balance;
                    break;
                case 'expense':
                    $expense += $balance;
                    break;
            }
        }

        $netIncome = $revenue - $expense;
        $totalEquityAndLiabilities = $equity + $liabilities + $netIncome;

        return [
            'total_assets' => $assets,
            'total_liabilities' => $liabilities,
            'total_equity' => $equity,
            'net_income' => $netIncome,
            'total_equity_and_liabilities' => $totalEquityAndLiabilities,
            'is_balanced' => abs($assets - $totalEquityAndLiabilities) < 0.0001,
        ];
    }

    /**
     * Get Profit & Loss (Income Statement) data.
     */
    public function getProfitLoss(): array
    {
        $accounts = Account::all();

        $revenue = 0.0;
        $expense = 0.0;

        foreach ($accounts as $account) {
            $balance = $this->getBalance($account->id);

            if ($account->type === 'revenue') {
                $revenue += $balance;
            } elseif ($account->type === 'expense') {
                $expense += $balance;
            }
        }

        return [
            'total_revenue' => $revenue,
            'total_expense' => $expense,
            'net_income' => $revenue - $expense,
        ];
    }

    /**
     * Fetch general ledger transactions for an account with running balance.
     */
    public function getLedgerTransactions(int $accountId, ?string $startDate = null, ?string $endDate = null): array
    {
        $account = Account::findOrFail($accountId);

        $query = JournalItem::with('journalEntry')
            ->where('account_id', $accountId);

        if ($startDate) {
            $query->whereHas('journalEntry', function ($q) use ($startDate) {
                $q->where('date', '>=', $startDate);
            });
        }
        if ($endDate) {
            $query->whereHas('journalEntry', function ($q) use ($endDate) {
                $q->where('date', '<=', $endDate);
            });
        }

        $items = $query->get()->sortBy(function ($item) {
            return $item->journalEntry->date.'_'.$item->journalEntry->id.'_'.$item->id;
        });

        $transactions = [];
        $runningBalance = 0.0;

        foreach ($items as $item) {
            $debit = (float) $item->debit;
            $credit = (float) $item->credit;

            if (in_array($account->type, ['asset', 'expense'])) {
                $runningBalance += ($debit - $credit);
            } else {
                $runningBalance += ($credit - $debit);
            }

            $transactions[] = [
                'id' => $item->id,
                'date' => $item->journalEntry->date,
                'entry_number' => $item->journalEntry->entry_number,
                'description' => $item->journalEntry->description,
                'debit' => $debit,
                'credit' => $credit,
                'running_balance' => $runningBalance,
            ];
        }

        return $transactions;
    }
}
