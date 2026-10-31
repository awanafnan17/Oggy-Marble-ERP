<?php

namespace App\Services;

use App\Models\CustomerInvoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\SupplierBill;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SubledgerService
{
    /**
     * Allocate customer payment to invoice.
     */
    public function recordInvoicePayment(int $paymentId, int $invoiceId, float $amount): PaymentAllocation
    {
        return DB::transaction(function () use ($paymentId, $invoiceId, $amount) {
            $invoice = CustomerInvoice::findOrFail($invoiceId);

            $invoice->remaining_balance -= $amount;

            if ($invoice->remaining_balance <= 0.0001) {
                $invoice->status = 'paid';
                $invoice->remaining_balance = 0;
            } else {
                $invoice->status = 'partial';
            }
            $invoice->save();

            return PaymentAllocation::create([
                'payment_id' => $paymentId,
                'allocatable_type' => CustomerInvoice::class,
                'allocatable_id' => $invoiceId,
                'amount' => $amount,
            ]);
        });
    }

    /**
     * Allocate supplier payment to bill.
     */
    public function recordBillPayment(int $paymentId, int $billId, float $amount): PaymentAllocation
    {
        return DB::transaction(function () use ($paymentId, $billId, $amount) {
            $bill = SupplierBill::findOrFail($billId);

            $bill->remaining_balance -= $amount;

            if ($bill->remaining_balance <= 0.0001) {
                $bill->status = 'paid';
                $bill->remaining_balance = 0;
            } else {
                $bill->status = 'partial';
            }
            $bill->save();

            return PaymentAllocation::create([
                'payment_id' => $paymentId,
                'allocatable_type' => SupplierBill::class,
                'allocatable_id' => $billId,
                'amount' => $amount,
            ]);
        });
    }

    /**
     * Generate AR or AP Aging Report.
     */
    public function getAgingReport(string $type = 'customer'): array
    {
        $today = Carbon::now();

        if ($type === 'customer') {
            $items = CustomerInvoice::with('contact')->where('remaining_balance', '>', 0)->get();
        } else {
            $items = SupplierBill::with('contact')->where('remaining_balance', '>', 0)->get();
        }

        $buckets = [
            'current' => 0.0, // 0-30 days
            'thirty' => 0.0, // 31-60 days
            'sixty' => 0.0, // 61-90 days
            'ninety' => 0.0, // 90+ days
        ];

        $details = [];

        foreach ($items as $item) {
            $issueDate = Carbon::parse($item->issue_date);
            $diffInDays = $issueDate->diffInDays($today, false);

            // If the issue date is in the future, count as current
            if ($diffInDays < 0) {
                $diffInDays = 0;
            }

            $bal = (float) $item->remaining_balance;

            if ($diffInDays <= 30) {
                $buckets['current'] += $bal;
                $bucketLabel = '0-30 days';
            } elseif ($diffInDays <= 60) {
                $buckets['thirty'] += $bal;
                $bucketLabel = '31-60 days';
            } elseif ($diffInDays <= 90) {
                $buckets['sixty'] += $bal;
                $bucketLabel = '61-90 days';
            } else {
                $buckets['ninety'] += $bal;
                $bucketLabel = '90+ days';
            }

            $details[] = [
                'id' => $item->id,
                'number' => $type === 'customer' ? $item->invoice_number : $item->bill_number,
                'contact' => $item->contact->name,
                'issue_date' => $item->issue_date->toDateString(),
                'due_date' => $item->due_date ? $item->due_date->toDateString() : null,
                'total_amount' => (float) $item->total_amount,
                'remaining_balance' => $bal,
                'age_days' => $diffInDays,
                'bucket' => $bucketLabel,
            ];
        }

        return [
            'summary' => $buckets,
            'details' => $details,
        ];
    }
}
