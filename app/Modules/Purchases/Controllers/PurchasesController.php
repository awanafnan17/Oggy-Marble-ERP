<?php

namespace App\Modules\Purchases\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Contact;
use App\Models\InventoryBatch;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockLedger;
use App\Models\SupplierBill;
use App\Services\CashBankService;
use App\Services\InventoryService;
use App\Services\PeriodService;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PurchasesController extends Controller
{
    protected InventoryService $inventoryService;

    protected CashBankService $cashBankService;

    protected PeriodService $periodService;

    public function __construct(InventoryService $inventoryService, CashBankService $cashBankService, PeriodService $periodService)
    {
        $this->inventoryService = $inventoryService;
        $this->cashBankService = $cashBankService;
        $this->periodService = $periodService;
    }

    public function index()
    {
        $vendors = Contact::whereIn('type', ['vendor', 'both'])->orderBy('name')->get();
        $products = Product::orderBy('name')->get();
        $purchaseOrders = Order::with(['contact', 'items.product', 'supplierBill'])
            ->where('type', 'purchase')
            ->orderBy('created_at', 'desc')
            ->get();
        $bankAccounts = BankAccount::orderBy('account_name')->get();

        return Inertia::render('Purchases/Index', [
            'vendors' => $vendors,
            'products' => $products,
            'purchaseOrders' => $purchaseOrders,
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function storePurchaseOrder(Request $request)
    {
        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'order_date' => 'required|date',
            'due_date' => 'nullable|date',
            'warehouse_id' => 'required|exists:warehouses,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.unit_price' => 'required|numeric|min:0', // base rate
            'items.*.landed_cost_manual' => 'nullable|numeric|min:0',
            'transportation_cost' => 'required|numeric|min:0',
            'loading_cost' => 'required|numeric|min:0',
            'other_direct_cost' => 'required|numeric|min:0',
            'cash_paid' => 'required|numeric|min:0',
            'bank_paid' => 'required|numeric|min:0',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'allocation_method' => 'nullable|string|in:quantity,value,manual',
        ]);

        // Server-side validation of master data readiness before transaction
        foreach ($validated['items'] as $index => $item) {
            $product = Product::find($item['product_id']);
            if (! $product) {
                continue;
            }
            if (! $product->inventory_account_id) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => "Product '{$product->name}' (SKU: {$product->sku}) is missing required Inventory Asset G/L accounting mapping and must be repaired before posting.",
                ]);
            }
        }

        try {
            DB::transaction(function () use ($validated) {
                $baseAmount = collect($validated['items'])->sum(function ($item) {
                    return $item['quantity'] * $item['unit_price'];
                });
                $totalQty = collect($validated['items'])->sum(function ($item) {
                    return $item['quantity'];
                });

                $transportationCost = (float) $validated['transportation_cost'];
                $loadingCost = (float) $validated['loading_cost'];
                $otherDirectCost = (float) $validated['other_direct_cost'];
                $totalOverheads = $transportationCost + $loadingCost + $otherDirectCost;

                $allocationMethod = $validated['allocation_method'] ?? 'value';

                if ($allocationMethod === 'manual') {
                    $manualSum = collect($validated['items'])->sum(function ($item) {
                        return (float) ($item['landed_cost_manual'] ?? 0.0);
                    });
                    if (abs($manualSum - $totalOverheads) > 0.01) {
                        throw new Exception('The sum of manual landed cost allocations (Rs. '.number_format($manualSum, 2).') must equal the total overhead costs (Rs. '.number_format($totalOverheads, 2).').');
                    }
                }

                $totalLandedCost = $baseAmount + $totalOverheads;
                $cashPaid = (float) $validated['cash_paid'];
                $bankPaid = (float) $validated['bank_paid'];
                $totalPaid = $cashPaid + $bankPaid;
                $remainingPayable = $totalLandedCost - $totalPaid;

                if ($totalPaid > $totalLandedCost) {
                    throw ValidationException::withMessages([
                        'cash_paid' => 'Overpayment is not allowed. Total paid (Rs. '.number_format($totalPaid, 2).') exceeds total bill value (Rs. '.number_format($totalLandedCost, 2).').',
                    ]);
                }

                if ($totalPaid > 0 && empty($validated['bank_account_id'])) {
                    throw ValidationException::withMessages([
                        'bank_account_id' => 'Please select a Cash/Bank account to record payments.',
                    ]);
                }

                $orderNumber = 'PO-'.time().'-'.rand(100, 999);

                $order = Order::create([
                    'contact_id' => $validated['contact_id'],
                    'type' => 'purchase',
                    'order_number' => $orderNumber,
                    'status' => 'completed',
                    'total_amount' => $totalLandedCost,
                    'discount_amount' => 0.0,
                    'tax_amount' => 0.0,
                    'order_date' => $validated['order_date'],
                    'due_date' => $validated['due_date'] ?? null,
                ]);

                // Create Supplier Bill in A/P Sub-ledger
                $supplierBill = SupplierBill::create([
                    'order_id' => $order->id,
                    'contact_id' => $validated['contact_id'],
                    'bill_number' => $orderNumber,
                    'issue_date' => $validated['order_date'],
                    'due_date' => $validated['due_date'] ?? null,
                    'total_amount' => $totalLandedCost,
                    'remaining_balance' => $remainingPayable,
                    'gross_amount' => $baseAmount,
                    'discount_amount' => 0.0,
                    'delivery_charges' => $transportationCost,
                    'net_amount' => $totalLandedCost,
                    'cash_paid' => $cashPaid,
                    'bank_paid' => $bankPaid,
                    'status' => $remainingPayable <= 0.0001 ? 'paid' : ($totalPaid > 0 ? 'partial' : 'unpaid'),
                ]);

                $inventoryInwards = []; // account_id => total value

                foreach ($validated['items'] as $item) {
                    $product = Product::findOrFail($item['product_id']);
                    $qty = (float) $item['quantity'];
                    $itemBasePrice = $qty * $item['unit_price'];

                    // Allocate landed cost based on selected method
                    $allocatedOverheadShare = 0.0;
                    if ($totalOverheads > 0.0001) {
                        if ($allocationMethod === 'quantity') {
                            $allocatedOverheadShare = $totalQty > 0 ? ($qty / $totalQty) * $totalOverheads : 0.0;
                        } elseif ($allocationMethod === 'value') {
                            $allocatedOverheadShare = $baseAmount > 0 ? ($itemBasePrice / $baseAmount) * $totalOverheads : 0.0;
                        } elseif ($allocationMethod === 'manual') {
                            $allocatedOverheadShare = (float) ($item['landed_cost_manual'] ?? 0.0);
                        }
                    }

                    $totalItemCost = $itemBasePrice + $allocatedOverheadShare;
                    $itemUnitCost = $qty > 0 ? ($totalItemCost / $qty) : 0.0;

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'quantity' => $qty,
                        'unit_price' => $item['unit_price'],
                        'total_price' => $itemBasePrice,
                    ]);

                    // Dynamic account mapping audit checks
                    $invAccId = $product->inventory_account_id;
                    if (empty($invAccId)) {
                        throw new Exception("Product {$product->sku} has no dynamic inventory asset ledger account mapped.");
                    }

                    // Log stock batch at dynamic landed cost
                    $this->inventoryService->createBatch(
                        $product->id,
                        $validated['warehouse_id'],
                        'purchase',
                        $order->id,
                        $qty,
                        $itemUnitCost,
                        $product->unit_of_measure ?: $product->unit,
                        $product->product_type,
                        $orderNumber
                    );

                    // Dynamic stock ledger tracking entry
                    StockLedger::create([
                        'product_id' => $product->id,
                        'warehouse_id' => $validated['warehouse_id'],
                        'quantity' => $qty,
                        'unit_cost' => $itemUnitCost,
                        'type' => 'IN',
                        'reference_type' => 'purchase_order',
                        'reference_id' => $order->id,
                        'notes' => 'Landed cost ingest PO #'.$orderNumber,
                    ]);

                    // Update product unit cost index
                    $product->cost = $itemUnitCost;
                    $product->save();

                    $inventoryInwards[$invAccId] = ($inventoryInwards[$invAccId] ?? 0.0) + $totalItemCost;
                }

                // General Ledger Voucher Posting
                $entry = JournalEntry::create([
                    'entry_number' => 'JV-PO-'.time().'-'.rand(100, 999),
                    'date' => $validated['order_date'],
                    'description' => 'Ingest Block Purchase '.$orderNumber,
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                ]);

                // Debit dynamic asset inventory accounts
                foreach ($inventoryInwards as $invAccId => $debitVal) {
                    if ($debitVal > 0) {
                        JournalItem::create([
                            'journal_entry_id' => $entry->id,
                            'account_id' => $invAccId,
                            'debit' => $debitVal,
                            'credit' => 0,
                        ]);
                    }
                }

                // A/P account matching
                $apAccount = Account::where('type', 'liability')->where('name', 'like', '%Payable%')->first();

                // Cash/Bank account payment logging
                if ($totalPaid > 0) {
                    $bankAccount = BankAccount::findOrFail($validated['bank_account_id']);

                    // Credit linked G/L cash/bank account
                    if ($bankAccount->account_id) {
                        JournalItem::create([
                            'journal_entry_id' => $entry->id,
                            'account_id' => $bankAccount->account_id,
                            'debit' => 0,
                            'credit' => $totalPaid,
                        ]);
                    }

                    // Register payment subledger transaction
                    $this->cashBankService->withdraw(
                        $bankAccount->id,
                        $totalPaid,
                        $validated['order_date'],
                        'supplier_bill',
                        $supplierBill->id,
                        'Payment for supplier bill '.$orderNumber
                    );
                }

                if ($remainingPayable > 0 && $apAccount) {
                    JournalItem::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $apAccount->id,
                        'debit' => 0,
                        'credit' => $remainingPayable,
                    ]);
                }
            });
        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            $businessMessages = ['sum of manual landed cost', 'ledger account not found', 'closed', 'missing dynamic accounting setup', 'depletion', 'negative'];
            $isBusiness = false;
            foreach ($businessMessages as $msg) {
                if (stripos($e->getMessage(), $msg) !== false) {
                    $isBusiness = true;
                    break;
                }
            }
            if ($isBusiness && ! ($e instanceof QueryException) && ! ($e instanceof \TypeError) && ! ($e instanceof \Error)) {
                throw ValidationException::withMessages([
                    'error' => $e->getMessage(),
                ]);
            }
            throw $e;
        }

        return redirect()->back()->with('success', 'Supplier invoice and Landed Cost purchase processed successfully.');
    }

    public function voidPurchaseBill(Request $request, $id)
    {
        $request->validate([
            'void_reason' => 'required|string|min:5',
        ]);

        $bill = SupplierBill::findOrFail($id);

        if ($bill->status === 'voided') {
            throw ValidationException::withMessages(['error' => 'This bill has already been voided.']);
        }

        // Check if there are active payments allocated to this bill
        $hasAllocations = $bill->allocations()->whereNull('voided_at')->exists();

        if ($hasAllocations) {
            throw ValidationException::withMessages(['error' => 'Cannot void bill with linked active payments. Please void the payments first.']);
        }

        // Find the batches created for this PO and check if any stock has been depleted!
        $batches = InventoryBatch::where('reference_type', 'purchase')
            ->where('reference_id', $bill->order_id)
            ->get();

        foreach ($batches as $batch) {
            if ($batch->quantity_remaining < $batch->quantity_received) {
                throw ValidationException::withMessages([
                    'error' => "Cannot void bill. Part of the purchased stock for SKU '{$batch->product->sku}' has already been depleted or sold.",
                ]);
            }
        }

        try {
            // Period lock check
            $this->periodService->ensurePeriodOpen(Carbon::parse($bill->issue_date));

            DB::transaction(function () use ($bill, $batches, $request) {
                $user = auth()->user();
                $reason = $request->void_reason;
                $now = now();

                // 1. Mark SupplierBill as voided
                $bill->update([
                    'status' => 'voided',
                    'voided_at' => $now,
                    'voided_by' => $user->id,
                    'void_reason' => $reason,
                ]);

                // 2. Mark corresponding Order as voided
                $order = $bill->order;
                if ($order) {
                    $order->update([
                        'status' => 'voided',
                        'voided_at' => $now,
                        'voided_by' => $user->id,
                        'void_reason' => $reason,
                    ]);
                }

                // 3. Deduct stock from batches & record reversing stock ledger entries
                foreach ($batches as $batch) {
                    $this->inventoryService->reverseInward(
                        $batch->id,
                        $batch->quantity_received,
                        'purchase_order',
                        $bill->order_id,
                        $reason
                    );
                }

                // 4. Find original JournalEntry
                $origEntry = JournalEntry::where('reference_type', 'order')
                    ->where('reference_id', $bill->order_id)
                    ->whereNull('voided_at')
                    ->first();

                if ($origEntry) {
                    // Create Reversal Journal Entry
                    $revEntry = JournalEntry::create([
                        'entry_number' => 'REV-'.$origEntry->entry_number,
                        'date' => now()->toDateString(),
                        'description' => 'Reversal of '.$origEntry->description.' - Reason: '.$reason,
                        'reference_type' => 'order',
                        'reference_id' => $bill->order_id,
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

                // 5. Reverse bank transactions (immediate payments)
                $bankTransactions = BankTransaction::where('reference_type', 'supplier_bill')
                    ->where('reference_id', $bill->id)
                    ->whereNull('voided_at')
                    ->get();

                foreach ($bankTransactions as $bt) {
                    // Create reversing BankTransaction
                    BankTransaction::create([
                        'bank_account_id' => $bt->bank_account_id,
                        'date' => now()->toDateString(),
                        'type' => $bt->type === 'deposit' ? 'withdrawal' : 'deposit',
                        'amount' => $bt->amount,
                        'reference_type' => 'supplier_bill',
                        'reference_id' => $bill->id,
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

        return redirect()->back()->with('success', 'Supplier Bill voided successfully.');
    }
}
