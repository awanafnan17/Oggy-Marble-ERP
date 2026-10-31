<?php

namespace App\Http\Controllers;

use App\Models\AccountingPeriod;
use App\Models\BankReconciliation;
use App\Models\InventoryBatch;
use App\Models\InventoryTransfer;
use App\Models\JournalEntry;
use App\Models\ProductionOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountingPeriodController extends Controller
{
    /**
     * Display accounting periods management page.
     */
    public function index()
    {
        $periods = AccountingPeriod::with('closedByUser')
            ->orderByDesc('start_date')
            ->get()
            ->map(function ($period) {
                return [
                    'id' => $period->id,
                    'period_name' => $period->period_name,
                    'start_date' => $period->start_date->format('Y-m-d'),
                    'end_date' => $period->end_date->format('Y-m-d'),
                    'is_closed' => $period->is_closed,
                    'closed_by' => $period->closedByUser?->name,
                    'closed_at' => $period->closed_at?->format('Y-m-d H:i'),
                    'created_at' => $period->created_at->format('Y-m-d H:i'),
                ];
            });

        return Inertia::render('Accounting/Periods', [
            'periods' => $periods,
        ]);
    }

    /**
     * Store a new accounting period.
     * Validates that no overlapping date ranges exist.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'period_name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        // Validate no overlapping periods
        $overlapping = AccountingPeriod::where(function ($query) use ($validated) {
            $query->where('start_date', '<=', $validated['end_date'])
                ->where('end_date', '>=', $validated['start_date']);
        })->exists();

        if ($overlapping) {
            return redirect()->back()->withErrors([
                'start_date' => 'This period overlaps with an existing accounting period.',
            ]);
        }

        AccountingPeriod::create([
            'period_name' => $validated['period_name'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'is_closed' => false,
        ]);

        return redirect()->back()->with('success', 'Accounting period created successfully.');
    }

    /**
     * Close an accounting period.
     * Records the closing user and timestamp.
     */
    public function close($id)
    {
        $period = AccountingPeriod::findOrFail($id);

        if ($period->is_closed) {
            return redirect()->back()->withErrors([
                'period' => 'This period is already closed.',
            ]);
        }

        // Run Pre-Close Governance Checks
        $blockers = [];

        // 1. Unbalanced Journal Entries
        $entries = JournalEntry::with('items')
            ->whereBetween('date', [$period->start_date, $period->end_date])
            ->get();
        foreach ($entries as $entry) {
            $debits = (float) $entry->items->sum('debit');
            $credits = (float) $entry->items->sum('credit');
            if (abs($debits - $credits) > 0.01) {
                $blockers[] = "Journal Entry {$entry->entry_number} is unbalanced (Debits: Rs. ".number_format($debits, 2).', Credits: Rs. '.number_format($credits, 2).').';
            }
        }

        // 2. Unreconciled Bank Reconciliations
        $unreconciledBanks = BankReconciliation::with('bankAccount')
            ->whereBetween('statement_date', [$period->start_date, $period->end_date])
            ->where(function ($query) {
                $query->where('is_closed', false)
                    ->orWhere('difference', '!=', 0);
            })->get();
        foreach ($unreconciledBanks as $br) {
            $blockers[] = "Bank Reconciliation for '{$br->bankAccount->account_name}' on {$br->statement_date->toDateString()} is unreconciled or has a difference of Rs. ".number_format((float) $br->difference, 2).'.';
        }

        // 3. Open Production Orders
        $openProductionOrders = ProductionOrder::whereIn('status', ['pending', 'in_progress'])
            ->where(function ($query) use ($period) {
                $query->whereBetween('start_date', [$period->start_date, $period->end_date])
                    ->orWhereBetween('created_at', [$period->start_date, $period->end_date]);
            })->get();
        foreach ($openProductionOrders as $po) {
            $blockers[] = "Production Order #{$po->id} is incomplete (Status: '{$po->status}').";
        }

        // 4. Incomplete Warehouse Transfers
        $incompleteTransfers = InventoryTransfer::whereBetween('transfer_date', [$period->start_date, $period->end_date])
            ->whereNull('destination_batch_id')
            ->get();
        foreach ($incompleteTransfers as $it) {
            $blockers[] = "Inventory Transfer #{$it->id} is incomplete (destination batch is missing).";
        }

        // 5. Negative Inventory Batches
        $negativeBatches = InventoryBatch::with('product')
            ->where('quantity_remaining', '<', 0)
            ->get();
        foreach ($negativeBatches as $nb) {
            $blockers[] = "Inventory Batch #{$nb->id} ({$nb->product->name}) has negative quantity remaining ({$nb->quantity_remaining}).";
        }

        if (count($blockers) > 0) {
            return redirect()->back()->withErrors([
                'pre_close_checks' => $blockers,
            ]);
        }

        $period->update([
            'is_closed' => true,
            'closed_by' => auth()->id(),
            'closed_at' => now(),
        ]);

        return redirect()->back()->with('success', "Period '{$period->period_name}' has been closed.");
    }

    /**
     * Reopen a previously closed accounting period.
     * Only admin role is allowed to reopen.
     */
    public function reopen($id)
    {
        $user = auth()->user();

        // Only admin role can reopen periods
        if (! $user->hasRole('admin')) {
            return redirect()->back()->withErrors([
                'period' => 'Only administrators can reopen closed periods.',
            ]);
        }

        $period = AccountingPeriod::findOrFail($id);

        if (! $period->is_closed) {
            return redirect()->back()->withErrors([
                'period' => 'This period is already open.',
            ]);
        }

        $period->update([
            'is_closed' => false,
            'closed_by' => null,
            'closed_at' => null,
        ]);

        return redirect()->back()->with('success', "Period '{$period->period_name}' has been reopened.");
    }
}
