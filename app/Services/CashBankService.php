<?php

namespace App\Services;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use Exception;
use Illuminate\Support\Facades\DB;

class CashBankService
{
    /**
     * Record a deposit to a bank or cash account (with G/L double entry).
     */
    public function deposit(
        int $bankAccountId,
        float $amount,
        string $date,
        ?string $refType = null,
        ?int $refId = null,
        ?string $description = null,
        ?string $proofImagePath = null,
        ?int $proofImageSize = null,
        ?string $proofImageMime = null
    ): BankTransaction {
        return DB::transaction(function () use ($bankAccountId, $amount, $date, $refType, $refId, $description, $proofImagePath, $proofImageSize, $proofImageMime) {
            $bankAccount = BankAccount::findOrFail($bankAccountId);

            // 1. Record sub-ledger transaction
            $tx = BankTransaction::create([
                'bank_account_id' => $bankAccount->id,
                'date' => $date,
                'type' => 'deposit',
                'amount' => $amount,
                'reference_type' => $refType,
                'reference_id' => $refId,
                'description' => $description ?: 'Deposit to '.$bankAccount->account_name,
                'proof_image_path' => $proofImagePath,
                'proof_image_size' => $proofImageSize,
                'proof_image_mime' => $proofImageMime,
            ]);

            return $tx;
        });
    }

    /**
     * Record a withdrawal from a bank or cash account.
     */
    public function withdraw(
        int $bankAccountId,
        float $amount,
        string $date,
        ?string $refType = null,
        ?int $refId = null,
        ?string $description = null,
        ?string $proofImagePath = null,
        ?int $proofImageSize = null,
        ?string $proofImageMime = null
    ): BankTransaction {
        return DB::transaction(function () use ($bankAccountId, $amount, $date, $refType, $refId, $description, $proofImagePath, $proofImageSize, $proofImageMime) {
            $bankAccount = BankAccount::findOrFail($bankAccountId);

            // 1. Record sub-ledger transaction
            $tx = BankTransaction::create([
                'bank_account_id' => $bankAccount->id,
                'date' => $date,
                'type' => 'withdrawal',
                'amount' => $amount,
                'reference_type' => $refType,
                'reference_id' => $refId,
                'description' => $description ?: 'Withdrawal from '.$bankAccount->account_name,
                'proof_image_path' => $proofImagePath,
                'proof_image_size' => $proofImageSize,
                'proof_image_mime' => $proofImageMime,
            ]);

            return $tx;
        });
    }

    /**
     * Record a bank transfer from one cash/bank account to another (with G/L double entry).
     */
    public function transfer(
        int $fromBankAccountId,
        int $toBankAccountId,
        float $amount,
        string $date,
        ?string $description = null,
        ?string $proofImagePath = null,
        ?int $proofImageSize = null,
        ?string $proofImageMime = null
    ): array {
        return DB::transaction(function () use ($fromBankAccountId, $toBankAccountId, $amount, $date, $description, $proofImagePath, $proofImageSize, $proofImageMime) {
            $from = BankAccount::findOrFail($fromBankAccountId);
            $to = BankAccount::findOrFail($toBankAccountId);

            if ($from->id === $to->id) {
                throw new Exception('Cannot transfer to the same account.');
            }

            // 1. Sub-ledger transaction for withdrawal
            $txOut = BankTransaction::create([
                'bank_account_id' => $from->id,
                'date' => $date,
                'type' => 'transfer_out',
                'amount' => $amount,
                'description' => $description ?: 'Transfer to '.$to->account_name,
                'proof_image_path' => $proofImagePath,
                'proof_image_size' => $proofImageSize,
                'proof_image_mime' => $proofImageMime,
            ]);

            // 2. Sub-ledger transaction for deposit
            $txIn = BankTransaction::create([
                'bank_account_id' => $to->id,
                'date' => $date,
                'type' => 'transfer_in',
                'amount' => $amount,
                'description' => $description ?: 'Transfer from '.$from->account_name,
                'proof_image_path' => $proofImagePath,
                'proof_image_size' => $proofImageSize,
                'proof_image_mime' => $proofImageMime,
            ]);

            // 3. Double-entry G/L journal entry
            // Debit Receiving Bank Account, Credit Sending Bank Account
            if ($from->account_id && $to->account_id) {
                $entry = JournalEntry::create([
                    'entry_number' => 'TRF-'.time().'-'.rand(100, 999),
                    'date' => $date,
                    'description' => $description ?: 'Bank Transfer from '.$from->account_name.' to '.$to->account_name,
                    'reference_type' => 'bank_transaction',
                    'reference_id' => $txOut->id,
                ]);

                // Debit destination bank account (asset increase)
                JournalItem::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $to->account_id,
                    'debit' => $amount,
                    'credit' => 0,
                ]);

                // Credit source bank account (asset decrease)
                JournalItem::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $from->account_id,
                    'debit' => 0,
                    'credit' => $amount,
                ]);
            }

            return [
                'txOut' => $txOut,
                'txIn' => $txIn,
                'out' => $txOut,
                'in' => $txIn,
            ];
        });
    }

    /**
     * Compute running balance for cash/bank account.
     */
    public function getBalance(int $bankAccountId): float
    {
        $deposits = BankTransaction::where('bank_account_id', $bankAccountId)
            ->whereIn('type', ['deposit', 'transfer_in', 'receipt'])
            ->sum('amount');

        $withdrawals = BankTransaction::where('bank_account_id', $bankAccountId)
            ->whereIn('type', ['withdrawal', 'transfer_out', 'payment'])
            ->sum('amount');

        return (float) ($deposits - $withdrawals);
    }
}
