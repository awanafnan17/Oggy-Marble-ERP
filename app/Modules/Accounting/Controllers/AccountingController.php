<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Services\AccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AccountingController extends Controller
{
    protected AccountService $accountService;

    public function __construct(AccountService $accountService)
    {
        $this->accountService = $accountService;
    }

    public function index()
    {
        $accounts = Account::orderBy('code')->get();

        $journalEntries = JournalEntry::with(['items.account'])
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // Calculate basic financial metrics from AccountService
        $trialBalance = $this->accountService->getTrialBalance();
        $balanceSheet = $this->accountService->getBalanceSheet();
        $profitLoss = $this->accountService->getProfitLoss();

        // Map accounts with calculated balances for frontend table view
        $accountsWithBalances = collect($trialBalance['accounts'])->map(function ($acc) {
            return (object) $acc;
        });

        return Inertia::render('Accounting/Index', [
            'accounts' => $accountsWithBalances,
            'journalEntries' => $journalEntries,
            'metrics' => [
                'totalAssets' => $balanceSheet['total_assets'],
                'totalLiabilities' => $balanceSheet['total_liabilities'],
                'totalEquity' => $balanceSheet['total_equity'],
                'totalRevenue' => $profitLoss['total_revenue'],
                'totalExpense' => $profitLoss['total_expense'],
                'netIncome' => $profitLoss['net_income'],
            ],
        ]);
    }

    public function storeJournalEntry(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'description' => 'required|string|max:255',
            'items' => 'required|array|min:2',
            'items.*.account_id' => 'required|exists:accounts,id',
            'items.*.debit' => 'required|numeric|min:0',
            'items.*.credit' => 'required|numeric|min:0',
        ]);

        // Validate double-entry logic: sum(debit) must equal sum(credit)
        $totalDebit = collect($validated['items'])->sum('debit');
        $totalCredit = collect($validated['items'])->sum('credit');

        // Allow for minor floating point diffs
        if (abs($totalDebit - $totalCredit) > 0.0001) {
            return back()->withErrors(['items' => 'Unbalanced entry: Total debits ('.$totalDebit.') must equal total credits ('.$totalCredit.').']);
        }

        DB::transaction(function () use ($validated) {
            $entryNumber = 'JE-'.time().'-'.rand(100, 999);

            $entry = JournalEntry::create([
                'entry_number' => $entryNumber,
                'date' => $validated['date'],
                'description' => $validated['description'],
            ]);

            foreach ($validated['items'] as $item) {
                JournalItem::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $item['account_id'],
                    'debit' => $item['debit'],
                    'credit' => $item['credit'],
                ]);
                // Balances are now derived, no column updates needed here
            }
        });

        return redirect()->back()->with('success', 'Journal entry recorded successfully.');
    }
}
