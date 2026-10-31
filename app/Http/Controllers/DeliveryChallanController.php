<?php

namespace App\Http\Controllers;

use App\Models\CustomerInvoice;
use App\Models\DeliveryChallan;
use App\Models\DeliveryChallanItem;
use App\Models\InventoryBatch;
use App\Models\Order;
use App\Models\Product;
use App\Models\Warehouse;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DeliveryChallanController extends Controller
{
    public function index(Request $request)
    {
        $query = DeliveryChallan::with([
            'order.contact',
            'customerInvoice.contact',
            'items.product',
            'items.inventoryBatch',
            'items.warehouse',
        ]);

        if ($request->filled('start_date')) {
            $query->whereDate('challan_date', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('challan_date', '<=', $request->input('end_date'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $challans = $query->orderBy('challan_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // Get active sales orders (orders of type 'sales')
        $orders = Order::with(['contact', 'items.product'])
            ->where('type', 'sales')
            ->orderBy('created_at', 'desc')
            ->get();

        $invoices = CustomerInvoice::with(['contact', 'order'])
            ->orderBy('created_at', 'desc')
            ->get();

        $products = Product::orderBy('sku')->get();
        $warehouses = Warehouse::orderBy('name')->get();

        $batches = InventoryBatch::with('product')
            ->where('quantity_remaining', '>', 0)
            ->orderBy('received_at', 'asc')
            ->get();

        return Inertia::render('Sales/Challans', [
            'challans' => $challans,
            'orders' => $orders,
            'invoices' => $invoices,
            'products' => $products,
            'warehouses' => $warehouses,
            'batches' => $batches,
            'filters' => $request->only(['start_date', 'end_date', 'status']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'challan_date' => 'required|date',
            'order_id' => 'required|exists:orders,id',
            'customer_invoice_id' => 'nullable|exists:customer_invoices,id',
            'vehicle_number' => 'required|string|max:255',
            'driver_name' => 'required|string|max:255',
            'receiver_name' => 'nullable|string|max:255',
            'prepared_by' => 'required|string|max:255',
            'authorized_by' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.inventory_batch_id' => 'nullable|exists:inventory_batches,id',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.unit' => 'required|string|max:255',
            'items.*.warehouse_id' => 'required|exists:warehouses,id',
        ]);

        $order = Order::with('items')->findOrFail($validated['order_id']);

        // Validate each item
        foreach ($validated['items'] as $index => $item) {
            $prodId = $item['product_id'];
            $reqQty = (float) $item['quantity'];

            $product = Product::find($prodId);
            if (! $product) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => 'Selected product does not exist.',
                ]);
            }

            if (empty($product->unit_of_measure) && empty($product->unit)) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => "Product '{$product->name}' (SKU: {$product->sku}) is missing required unit of measure.",
                ]);
            }

            $orderItem = $order->items->firstWhere('product_id', $prodId);
            if (! $orderItem) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => "Product '{$product->name}' (SKU: {$product->sku}) is not in the linked Sales Order.",
                ]);
            }

            $orderedQty = (float) $orderItem->quantity;
            $alreadyDispatched = DeliveryChallanItem::whereHas('deliveryChallan', function ($q) use ($order) {
                $q->where('order_id', $order->id)
                    ->where('status', '!=', 'cancelled');
            })->where('product_id', $prodId)->sum('quantity');

            if (($alreadyDispatched + $reqQty) > $orderedQty) {
                throw ValidationException::withMessages([
                    'items' => "Requested quantity ({$reqQty}) for product '{$product->name}' exceeds the remaining ordered quantity. Ordered: {$orderedQty}, Dispatched: {$alreadyDispatched}.",
                    "items.{$index}.quantity" => "Requested quantity ({$reqQty}) for product '{$product->name}' exceeds the remaining ordered quantity. Ordered: {$orderedQty}, Dispatched: {$alreadyDispatched}.",
                ]);
            }
        }

        try {
            $challan = DB::transaction(function () use ($validated) {
                $prefix = config('invoice.delivery_challan_prefix', 'DC-');
                $challanNumber = $prefix.now()->format('Ymd').'-'.rand(1000, 9999);

                $challan = DeliveryChallan::create([
                    'challan_number' => $challanNumber,
                    'challan_date' => $validated['challan_date'],
                    'customer_invoice_id' => $validated['customer_invoice_id'] ?? null,
                    'order_id' => $validated['order_id'],
                    'vehicle_number' => $validated['vehicle_number'],
                    'driver_name' => $validated['driver_name'],
                    'receiver_name' => $validated['receiver_name'] ?? null,
                    'prepared_by' => $validated['prepared_by'],
                    'authorized_by' => $validated['authorized_by'] ?? null,
                    'remarks' => $validated['remarks'] ?? null,
                    'status' => 'pending',
                ]);

                foreach ($validated['items'] as $item) {
                    DeliveryChallanItem::create([
                        'delivery_challan_id' => $challan->id,
                        'product_id' => $item['product_id'],
                        'inventory_batch_id' => $item['inventory_batch_id'] ?? null,
                        'quantity' => $item['quantity'],
                        'unit' => $item['unit'],
                        'warehouse_id' => $item['warehouse_id'],
                    ]);
                }

                return $challan;
            });
        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            $businessMessages = ['quantity exceeds', 'closed', 'ledger account not found'];
            $isBusiness = false;
            foreach ($businessMessages as $msg) {
                if (stripos($e->getMessage(), $msg) !== false) {
                    $isBusiness = true;
                    break;
                }
            }
            if ($isBusiness && ! ($e instanceof QueryException) && ! ($e instanceof \TypeError) && ! ($e instanceof \Error)) {
                throw ValidationException::withMessages([
                    'items' => $e->getMessage(),
                ]);
            }
            throw $e;
        }

        return back()->with('success', "Delivery challan {$challan->challan_number} created successfully.");
    }

    public function updateStatus(Request $request, $id)
    {
        $challan = DeliveryChallan::findOrFail($id);
        $newStatus = $request->input('status');

        $allowedStatuses = ['pending', 'packed', 'dispatched', 'delivered', 'returned', 'cancelled'];
        if (! in_array($newStatus, $allowedStatuses)) {
            return back()->withErrors(['status' => 'Invalid status.']);
        }

        $currentStatus = $challan->status;
        $isTransitionAllowed = false;

        // Transition logic:
        if ($currentStatus === 'pending') {
            if (in_array($newStatus, ['packed', 'cancelled'])) {
                $isTransitionAllowed = true;
            }
        } elseif ($currentStatus === 'packed') {
            if (in_array($newStatus, ['dispatched', 'cancelled'])) {
                $isTransitionAllowed = true;
            }
        } elseif ($currentStatus === 'dispatched') {
            if (in_array($newStatus, ['delivered', 'returned'])) {
                $isTransitionAllowed = true;
            }
        }

        if (! $isTransitionAllowed) {
            return back()->withErrors(['status' => "Status transition from '{$currentStatus}' to '{$newStatus}' is not allowed."]);
        }

        $updateData = ['status' => $newStatus];

        if ($newStatus === 'dispatched') {
            $updateData['dispatched_at'] = now();
        } elseif ($newStatus === 'delivered') {
            $updateData['delivered_at'] = now();
        }

        $challan->update($updateData);

        return back()->with('success', "Delivery challan status updated to {$newStatus}.");
    }

    public function print($id)
    {
        $challan = DeliveryChallan::with([
            'order.contact',
            'customerInvoice.contact',
            'items.product',
            'items.inventoryBatch',
            'items.warehouse',
        ])->findOrFail($id);

        return Inertia::render('Sales/ChallanPrint', [
            'challan' => $challan,
        ]);
    }
}
