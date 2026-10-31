<?php

namespace App\Modules\Sales\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Contact;
use App\Models\CustomerInvoice;
use App\Models\InventoryBatch;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
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

class SalesController extends Controller
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
        $customers = Contact::whereIn('type', ['customer', 'both'])->orderBy('name')->get();
        $products = Product::orderBy('name')->get();
        $salesOrders = Order::with(['contact', 'items.product', 'customerInvoice'])
            ->where('type', 'sales')
            ->orderBy('created_at', 'desc')
            ->get();
        $bankAccounts = BankAccount::orderBy('account_name')->get();

        // Fetch active batches with stock for manual batch selection
        $activeBatches = InventoryBatch::with('product')
            ->where('quantity_remaining', '>', 0)
            ->orderBy('received_at', 'asc')
            ->get()
            ->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'product_id' => $batch->product_id,
                    'sku' => $batch->product ? $batch->product->sku : 'N/A',
                    'name' => $batch->product ? $batch->product->name : 'Unknown Product',
                    'size' => $batch->product ? $batch->product->size : null,
                    'quantity_remaining' => (float) $batch->quantity_remaining,
                    'unit_cost' => (float) $batch->unit_cost,
                    'unit_of_measure' => $batch->unit_of_measure,
                    'quality_grade' => $batch->quality_grade,
                    'batch_reference' => $batch->batch_reference,
                ];
            });

        return Inertia::render('Sales/Index', [
            'customers' => $customers,
            'products' => $products,
            'salesOrders' => $salesOrders,
            'bankAccounts' => $bankAccounts,
            'activeBatches' => $activeBatches,
        ]);
    }

    public function storeSalesOrder(Request $request)
    {
        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'order_date' => 'required|date',
            'due_date' => 'nullable|date',
            'warehouse_id' => 'required|exists:warehouses,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.selected_batch_id' => 'nullable|exists:inventory_batches,id',
            'discount_amount' => 'required|numeric|min:0',
            'delivery_charges' => 'required|numeric|min:0',
            'cash_received' => 'required|numeric|min:0',
            'bank_received' => 'required|numeric|min:0',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
        ]);

        // Server-side validation of master data readiness before transaction
        foreach ($validated['items'] as $index => $item) {
            $product = Product::find($item['product_id']);
            if (! $product) {
                continue;
            }
            if (! $product->inventory_account_id || ! $product->cogs_account_id || ! $product->revenue_account_id) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => "Product '{$product->name}' (SKU: {$product->sku}) is missing required G/L accounting setup (Inventory, COGS, or Revenue mapping) and must be repaired before posting.",
                ]);
            }

            // Check if stock is available
            $qty = (float) $item['quantity'];
            $selectedBatchId = ! empty($item['selected_batch_id']) ? (int) $item['selected_batch_id'] : null;
            try {
                $this->inventoryService->ensureStockAvailable($product->id, $qty, (int) $validated['warehouse_id'], $selectedBatchId);
            } catch (ValidationException $ex) {
                $msg = $ex->validator->errors()->first('items') ?: $ex->getMessage();
                throw ValidationException::withMessages([
                    'items' => $msg,
                    "items.{$index}.product_id" => $msg,
                ]);
            }
        }

        try {
            DB::transaction(function () use ($validated) {
                $grossAmount = collect($validated['items'])->sum(function ($item) {
                    return $item['quantity'] * $item['unit_price'];
                });

                $discountAmount = (float) $validated['discount_amount'];
                $deliveryCharges = (float) $validated['delivery_charges'];
                $netInvoiceAmount = $grossAmount - $discountAmount + $deliveryCharges;

                $cashReceived = (float) $validated['cash_received'];
                $bankReceived = (float) $validated['bank_received'];
                $totalReceived = $cashReceived + $bankReceived;
                $remainingReceivable = $netInvoiceAmount - $totalReceived;

                if ($totalReceived > $netInvoiceAmount) {
                    throw ValidationException::withMessages([
                        'cash_received' => 'Overpayment is not allowed. Total received (Rs. '.number_format($totalReceived, 2).') exceeds net invoice value (Rs. '.number_format($netInvoiceAmount, 2).').',
                    ]);
                }

                if ($totalReceived > 0 && empty($validated['bank_account_id'])) {
                    throw ValidationException::withMessages([
                        'bank_account_id' => 'Please select a Cash/Bank account to receive collections.',
                    ]);
                }

                $orderNumber = 'SO-'.time().'-'.rand(100, 999);

                $order = Order::create([
                    'contact_id' => $validated['contact_id'],
                    'type' => 'sales',
                    'order_number' => $orderNumber,
                    'status' => 'completed',
                    'total_amount' => $netInvoiceAmount,
                    'tax_amount' => 0.0,
                    'discount_amount' => $discountAmount,
                    'order_date' => $validated['order_date'],
                    'due_date' => $validated['due_date'] ?? null,
                ]);

                // Create subledger CustomerInvoice
                $customerInvoice = CustomerInvoice::create([
                    'order_id' => $order->id,
                    'contact_id' => $validated['contact_id'],
                    'invoice_number' => $orderNumber,
                    'issue_date' => $validated['order_date'],
                    'due_date' => $validated['due_date'] ?? null,
                    'total_amount' => $netInvoiceAmount,
                    'remaining_balance' => $remainingReceivable,
                    'gross_amount' => $grossAmount,
                    'discount_amount' => $discountAmount,
                    'delivery_charges' => $deliveryCharges,
                    'net_amount' => $netInvoiceAmount,
                    'cash_received' => $cashReceived,
                    'bank_received' => $bankReceived,
                    'status' => $remainingReceivable <= 0.0001 ? 'paid' : ($totalReceived > 0 ? 'partial' : 'unpaid'),
                ]);

                // Create general ledger entry
                $entry = JournalEntry::create([
                    'entry_number' => 'INV-'.time().'-'.rand(100, 999),
                    'date' => $validated['order_date'],
                    'description' => 'Sales Invoice '.$orderNumber,
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                ]);

                $cogsTotals = [];      // account_id => total amount
                $invTotals = [];       // account_id => total amount
                $revTotals = [];       // account_id => total amount

                foreach ($validated['items'] as $item) {
                    $product = Product::findOrFail($item['product_id']);
                    $qty = (float) $item['quantity'];
                    $itemTotal = $qty * $item['unit_price'];

                    // 2. Negative inventory guard
                    $selectedBatchId = ! empty($item['selected_batch_id']) ? (int) $item['selected_batch_id'] : null;
                    $this->inventoryService->ensureStockAvailable($product->id, $qty, (int) $validated['warehouse_id'], $selectedBatchId);

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $qty,
                        'unit_price' => $item['unit_price'],
                        'total_price' => $itemTotal,
                    ]);

                    // FIFO/Manual Stock Depletion
                    $actualCogs = $this->inventoryService->depleteStock(
                        $item['product_id'],
                        $validated['warehouse_id'],
                        $qty,
                        'sales_order',
                        $order->id,
                        'Sales invoice fulfillment '.$orderNumber,
                        $item['selected_batch_id'] ?? null
                    );

                    // Fetch dynamic accounts
                    $invAccId = $product->inventory_account_id;
                    $cogsAccId = $product->cogs_account_id;
                    $revAccId = $product->revenue_account_id;

                    if (! $invAccId || ! $cogsAccId || ! $revAccId) {
                        throw new Exception("Product {$product->sku} is missing dynamic accounting setup.");
                    }

                    $revTotals[$revAccId] = ($revTotals[$revAccId] ?? 0.0) + $itemTotal;

                    if ($actualCogs > 0) {
                        $cogsTotals[$cogsAccId] = ($cogsTotals[$cogsAccId] ?? 0.0) + $actualCogs;
                        $invTotals[$invAccId] = ($invTotals[$invAccId] ?? 0.0) + $actualCogs;
                    }
                }

                // G/L double entry posting:
                // Fetch Accounts Receivable (AR) dynamically by type and name pattern
                $arAccount = Account::where('type', 'asset')->where('name', 'like', '%Receivable%')->first();

                // 1. Post Receivable Debit
                if ($remainingReceivable > 0 && $arAccount) {
                    JournalItem::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $arAccount->id,
                        'debit' => $remainingReceivable,
                        'credit' => 0,
                    ]);
                }

                // Debit Cash/Bank account for collections
                if ($totalReceived > 0) {
                    $bankAccount = BankAccount::findOrFail($validated['bank_account_id']);
                    if ($bankAccount->account_id) {
                        JournalItem::create([
                            'journal_entry_id' => $entry->id,
                            'account_id' => $bankAccount->account_id,
                            'debit' => $totalReceived,
                            'credit' => 0,
                        ]);
                    }

                    // Register collection transaction
                    $this->cashBankService->deposit(
                        $bankAccount->id,
                        $totalReceived,
                        $validated['order_date'],
                        'customer_invoice',
                        $customerInvoice->id,
                        'Collection from invoice '.$orderNumber
                    );
                }

                // Proportional net revenue allocation per Revenue Account
                foreach ($revTotals as $revAccId => $grossVal) {
                    if ($grossVal > 0) {
                        $weight = $grossAmount > 0 ? ($grossVal / $grossAmount) : 1.0;
                        $netRevenue = $grossVal - ($discountAmount * $weight) + ($deliveryCharges * $weight);

                        JournalItem::create([
                            'journal_entry_id' => $entry->id,
                            'account_id' => $revAccId,
                            'debit' => 0,
                            'credit' => $netRevenue,
                        ]);
                    }
                }

                // Post COGS
                foreach ($cogsTotals as $cogsAccId => $val) {
                    if ($val > 0) {
                        JournalItem::create([
                            'journal_entry_id' => $entry->id,
                            'account_id' => $cogsAccId,
                            'debit' => $val,
                            'credit' => 0,
                        ]);
                    }
                }

                // Post Inventory Credit
                foreach ($invTotals as $invAccId => $val) {
                    if ($val > 0) {
                        JournalItem::create([
                            'journal_entry_id' => $entry->id,
                            'account_id' => $invAccId,
                            'debit' => 0,
                            'credit' => $val,
                        ]);
                    }
                }
            });
        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            $businessMessages = ['Insufficient', 'ledger account not found', 'closed', 'missing dynamic accounting setup', 'depletion', 'negative'];
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

        return redirect()->back()->with('success', 'Sales Order and Invoicing processed successfully.');
    }

    public function printInvoice($id)
    {
        $invoice = CustomerInvoice::with(['contact', 'order.items.product'])->findOrFail($id);

        return Inertia::render('Sales/InvoicePrint', [
            'invoice' => $invoice,
        ]);
    }

    public function voidSalesInvoice(Request $request, $id)
    {
        $request->validate([
            'void_reason' => 'required|string|min:5',
        ]);

        $invoice = CustomerInvoice::findOrFail($id);

        if ($invoice->status === 'voided') {
            throw ValidationException::withMessages(['error' => 'This invoice has already been voided.']);
        }

        // Check if there are other active payments allocated to this invoice
        $hasAllocations = $invoice->allocations()->whereNull('voided_at')->exists();

        if ($hasAllocations) {
            throw ValidationException::withMessages(['error' => 'Cannot void invoice with linked active payments. Please void the payments first.']);
        }

        try {
            // Period lock check
            $this->periodService->ensurePeriodOpen(Carbon::parse($invoice->issue_date));

            DB::transaction(function () use ($invoice, $request) {
                $user = auth()->user();
                $reason = $request->void_reason;
                $now = now();

                // 1. Mark CustomerInvoice as voided
                $invoice->update([
                    'status' => 'voided',
                    'voided_at' => $now,
                    'voided_by' => $user->id,
                    'void_reason' => $reason,
                ]);

                // 2. Mark corresponding Order as voided
                $order = $invoice->order;
                if ($order) {
                    $order->update([
                        'status' => 'voided',
                        'voided_at' => $now,
                        'voided_by' => $user->id,
                        'void_reason' => $reason,
                    ]);
                }

                // 3. Find original JournalEntry
                $origEntry = JournalEntry::where('reference_type', 'order')
                    ->where('reference_id', $invoice->order_id)
                    ->whereNull('voided_at')
                    ->first();

                if ($origEntry) {
                    // Create Reversal Journal Entry
                    $revEntry = JournalEntry::create([
                        'entry_number' => 'REV-'.$origEntry->entry_number,
                        'date' => now()->toDateString(), // reversal posted today
                        'description' => 'Reversal of '.$origEntry->description.' - Reason: '.$reason,
                        'reference_type' => 'order',
                        'reference_id' => $invoice->order_id,
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

                // 4. Reverse Stock Depletion
                $this->inventoryService->reverseDepletion('sales_order', $invoice->order_id, $reason);

                // 5. Reverse collections (immediate payments)
                $bankTransactions = BankTransaction::where('reference_type', 'customer_invoice')
                    ->where('reference_id', $invoice->id)
                    ->whereNull('voided_at')
                    ->get();

                foreach ($bankTransactions as $bt) {
                    // Create reversing BankTransaction
                    BankTransaction::create([
                        'bank_account_id' => $bt->bank_account_id,
                        'date' => now()->toDateString(),
                        'type' => $bt->type === 'deposit' ? 'withdrawal' : 'deposit',
                        'amount' => $bt->amount,
                        'reference_type' => 'customer_invoice',
                        'reference_id' => $invoice->id,
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

        return redirect()->back()->with('success', 'Sales Invoice voided successfully.');
    }
}
