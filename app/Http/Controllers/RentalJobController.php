<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Contact;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\RentalJob;
use App\Services\CashBankService;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class RentalJobController extends Controller
{
    protected CashBankService $cashBankService;

    public function __construct(CashBankService $cashBankService)
    {
        $this->cashBankService = $cashBankService;
    }

    public function index()
    {
        $rentalJobs = RentalJob::with('contact')->orderBy('job_date', 'desc')->get();
        $customers = Contact::whereIn('type', ['customer', 'both'])->orderBy('name')->get();
        $bankAccounts = BankAccount::orderBy('account_name')->get();

        return Inertia::render('MachineRental/Index', [
            'rentalJobs' => $rentalJobs,
            'customers' => $customers,
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'job_date' => 'required|date',
            'machine_hours' => 'required|numeric|min:0',
            'machine_rate' => 'required|numeric|min:0',
            'labor_hours' => 'required|numeric|min:0',
            'labor_rate' => 'required|numeric|min:0',
            'other_charges' => 'required|numeric|min:0',
            'cash_received' => 'required|numeric|min:0',
            'bank_received' => 'required|numeric|min:0',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $machineHours = (float) $validated['machine_hours'];
                $machineRate = (float) $validated['machine_rate'];
                $laborHours = (float) $validated['labor_hours'];
                $laborRate = (float) $validated['labor_rate'];
                $otherCharges = (float) $validated['other_charges'];

                $totalAmount = ($machineHours * $machineRate) + ($laborHours * $laborRate) + $otherCharges;
                $cashReceived = (float) $validated['cash_received'];
                $bankReceived = (float) $validated['bank_received'];
                $totalReceived = $cashReceived + $bankReceived;
                $remainingReceivable = $totalAmount - $totalReceived;

                if ($totalReceived > $totalAmount) {
                    throw ValidationException::withMessages([
                        'cash_received' => 'Overpayment is not allowed. Total received (Rs. '.number_format($totalReceived, 2).') exceeds total amount (Rs. '.number_format($totalAmount, 2).').',
                    ]);
                }

                if ($totalReceived > 0 && empty($validated['bank_account_id'])) {
                    throw ValidationException::withMessages([
                        'bank_account_id' => 'Please select a Cash/Bank account to receive collections.',
                    ]);
                }

                $jobNumber = 'MRJ-'.time().'-'.rand(100, 999);
                $paymentStatus = $remainingReceivable <= 0.0001 ? 'paid' : ($totalReceived > 0 ? 'partial' : 'unpaid');

                // 1. Create G/L Journal entry voucher
                $entry = JournalEntry::create([
                    'entry_number' => 'MRV-'.time().'-'.rand(100, 999),
                    'date' => $validated['job_date'],
                    'description' => 'Machine Rental Revenue - Job '.$jobNumber,
                ]);

                // 2. Create the Rental Job record
                $job = RentalJob::create(array_merge($validated, [
                    'job_number' => $jobNumber,
                    'total_amount' => $totalAmount,
                    'remaining_receivable' => $remainingReceivable,
                    'payment_status' => $paymentStatus,
                    'journal_entry_id' => $entry->id,
                ]));

                $entry->update([
                    'reference_type' => 'rental_job',
                    'reference_id' => $job->id,
                ]);

                // 3. Double-entry G/L mapping:
                // Debit cash/bank account (asset) for totalReceived
                // Debit Accounts Receivable (asset) for remainingReceivable
                // Credit Machine Rental Revenue (revenue) for totalAmount
                $arAccount = Account::where('type', 'asset')->where('name', 'like', '%Receivable%')->first();
                $rentalRevAccount = Account::where('type', 'revenue')->where('name', 'like', '%Rental%')->first();

                // Credit Machine Rental Revenue
                if ($rentalRevAccount && $totalAmount > 0) {
                    JournalItem::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $rentalRevAccount->id,
                        'debit' => 0,
                        'credit' => $totalAmount,
                    ]);
                }

                // Debit Accounts Receivable
                if ($remainingReceivable > 0 && $arAccount) {
                    JournalItem::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $arAccount->id,
                        'debit' => $remainingReceivable,
                        'credit' => 0,
                    ]);
                }

                // Debit Cash/Bank Account G/L and add subledger transaction
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

                    $this->cashBankService->deposit(
                        $bankAccount->id,
                        $totalReceived,
                        $validated['job_date'],
                        'rental_job',
                        $job->id,
                        'Collection from Machine Rental Job '.$jobNumber
                    );
                }
            });
        } catch (Exception $e) {
            $businessMessages = ['Please select a Cash/Bank', 'closed'];
            $isBusiness = false;
            foreach ($businessMessages as $msg) {
                if (stripos($e->getMessage(), $msg) !== false) {
                    $isBusiness = true;
                    break;
                }
            }
            if ($isBusiness && ! ($e instanceof QueryException) && ! ($e instanceof \TypeError) && ! ($e instanceof \Error)) {
                throw ValidationException::withMessages([
                    'cash_received' => $e->getMessage(),
                ]);
            }
            throw $e;
        }

        return redirect()->back()->with('success', 'Machine rental job processed successfully.');
    }
}
