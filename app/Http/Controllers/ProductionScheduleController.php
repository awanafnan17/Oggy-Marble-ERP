<?php

namespace App\Http\Controllers;

use App\Models\InventoryBatch;
use App\Models\Machine;
use App\Models\Product;
use App\Models\ProductionSchedule;
use App\Models\SlabToTileProduction;
use App\Models\StoneToSlabProduction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductionScheduleController extends Controller
{
    // Predefined list of normalized machines for factory production lines
    protected array $predefinedMachines = [
        'Gang Saw 1',
        'Gang Saw 2',
        'Block Cutter A',
        'Block Cutter B',
        'Polishing Line 1',
        'Edge Cutter A',
    ];

    public function index()
    {
        $schedules = ProductionSchedule::with(['plannedInputProduct', 'plannedInputBatch'])
            ->orderBy('planned_start_date', 'asc')
            ->get();

        $products = Product::orderBy('sku')->get();

        // Get batches with stock available
        $batches = InventoryBatch::with('product')
            ->where('quantity_remaining', '>', 0)
            ->get();

        $stoneToSlabProductions = StoneToSlabProduction::with(['inputBatch.product', 'slabProduct', 'scrapProduct'])
            ->orderBy('created_at', 'desc')
            ->get();

        $slabToTileProductions = SlabToTileProduction::with(['inputBatch.product', 'tileProduct', 'scrapProduct'])
            ->orderBy('created_at', 'desc')
            ->get();

        $dbMachines = Machine::orderBy('name')->get();

        return Inertia::render('Production/Schedules', [
            'schedules' => $schedules,
            'products' => $products,
            'batches' => $batches,
            'machines' => $dbMachines,
            'stoneToSlabProductions' => $stoneToSlabProductions,
            'slabToTileProductions' => $slabToTileProductions,
            'yieldStandards' => config('yield'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'production_order_reference' => 'nullable|string|max:255',
            'production_type' => 'required|string|in:stone_to_slab,slab_to_tile',
            'machine' => 'required|string|max:255',
            'planned_start_date' => 'required|date',
            'planned_end_date' => 'required|date',
            'priority' => 'required|string|in:low,normal,high,urgent',
            'planned_input_product_id' => 'required|exists:products,id',
            'planned_input_batch_id' => 'nullable|exists:inventory_batches,id',
            'planned_input_quantity' => 'required|numeric|gt:0',
            'expected_output_quantity' => 'required|numeric|gt:0',
            'expected_wastage_quantity' => 'nullable|numeric|min:0',
            'expected_wastage_percentage' => 'nullable|numeric|min:0|max:100',
            'assigned_operator' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
        ]);

        $machine = trim($validated['machine']);
        if (empty($machine)) {
            return back()->withErrors(['machine' => 'Machine name cannot be empty.']);
        }

        $dbMachine = Machine::where('name', $machine)->orWhere('machine_code', $machine)->first();
        if ($dbMachine && in_array($dbMachine->status, ['inactive', 'maintenance'])) {
            return back()->withErrors(['machine' => "Machine '{$machine}' is currently {$dbMachine->status} and cannot be scheduled."]);
        }

        $start = Carbon::parse($validated['planned_start_date']);
        $end = Carbon::parse($validated['planned_end_date']);

        if ($end->lte($start)) {
            return back()->withErrors(['planned_end_date' => 'Planned end date/time must be after planned start date/time.']);
        }

        // Overlap validation: active schedules (planned, queued, in_progress) only
        // Checking: S_existing < E_new AND E_existing > S_new
        $overlapping = ProductionSchedule::where('machine', $machine)
            ->whereIn('status', ['planned', 'queued', 'in_progress'])
            ->where('planned_start_date', '<', $end)
            ->where('planned_end_date', '>', $start)
            ->exists();

        if ($overlapping) {
            return back()->withErrors(['machine' => "Machine '{$machine}' is already scheduled/booked during this time interval."]);
        }

        $scheduleNumber = 'SCH-'.time().'-'.rand(100, 999);

        ProductionSchedule::create(array_merge($validated, [
            'machine' => $machine,
            'schedule_number' => $scheduleNumber,
            'status' => 'planned',
            'expected_wastage_quantity' => $validated['expected_wastage_quantity'] ?? 0.0,
            'expected_wastage_percentage' => $validated['expected_wastage_percentage'] ?? 0.0,
        ]));

        return back()->with('success', 'Production schedule created successfully.');
    }

    public function update(Request $request, $id)
    {
        $schedule = ProductionSchedule::findOrFail($id);

        // Edit allowed only if planned or queued
        if (in_array($schedule->status, ['completed', 'cancelled'])) {
            return back()->withErrors(['status' => 'Cannot edit a schedule that is completed or cancelled.']);
        }

        $validated = $request->validate([
            'production_order_reference' => 'nullable|string|max:255',
            'production_type' => 'required|string|in:stone_to_slab,slab_to_tile',
            'machine' => 'required|string|max:255',
            'planned_start_date' => 'required|date',
            'planned_end_date' => 'required|date',
            'priority' => 'required|string|in:low,normal,high,urgent',
            'planned_input_product_id' => 'required|exists:products,id',
            'planned_input_batch_id' => 'nullable|exists:inventory_batches,id',
            'planned_input_quantity' => 'required|numeric|gt:0',
            'expected_output_quantity' => 'required|numeric|gt:0',
            'expected_wastage_quantity' => 'nullable|numeric|min:0',
            'expected_wastage_percentage' => 'nullable|numeric|min:0|max:100',
            'assigned_operator' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
        ]);

        $machine = trim($validated['machine']);
        if (empty($machine)) {
            return back()->withErrors(['machine' => 'Machine name cannot be empty.']);
        }

        $dbMachine = Machine::where('name', $machine)->orWhere('machine_code', $machine)->first();
        if ($dbMachine && in_array($dbMachine->status, ['inactive', 'maintenance'])) {
            return back()->withErrors(['machine' => "Machine '{$machine}' is currently {$dbMachine->status} and cannot be scheduled."]);
        }

        $start = Carbon::parse($validated['planned_start_date']);
        $end = Carbon::parse($validated['planned_end_date']);

        if ($end->lte($start)) {
            return back()->withErrors(['planned_end_date' => 'Planned end date/time must be after planned start date/time.']);
        }

        // Machine double booking check: active schedules excluding self
        $overlapping = ProductionSchedule::where('machine', $machine)
            ->whereIn('status', ['planned', 'queued', 'in_progress'])
            ->where('planned_start_date', '<', $end)
            ->where('planned_end_date', '>', $start)
            ->where('id', '!=', $id)
            ->exists();

        if ($overlapping) {
            return back()->withErrors(['machine' => "Machine '{$machine}' is already scheduled/booked during this time interval."]);
        }

        $schedule->update(array_merge($validated, [
            'machine' => $machine,
            'expected_wastage_quantity' => $validated['expected_wastage_quantity'] ?? 0.0,
            'expected_wastage_percentage' => $validated['expected_wastage_percentage'] ?? 0.0,
        ]));

        return back()->with('success', 'Production schedule updated.');
    }

    public function updateStatus(Request $request, $id)
    {
        $schedule = ProductionSchedule::findOrFail($id);
        $newStatus = $request->input('status');

        $allowedStatuses = ['planned', 'queued', 'in_progress', 'completed', 'cancelled'];
        if (! in_array($newStatus, $allowedStatuses)) {
            return back()->withErrors(['status' => 'Invalid status transition.']);
        }

        $currentStatus = $schedule->status;

        // Transition Rules
        $isTransitionAllowed = false;

        if ($currentStatus === 'planned' && in_array($newStatus, ['queued', 'cancelled'])) {
            $isTransitionAllowed = true;
        } elseif ($currentStatus === 'queued' && in_array($newStatus, ['in_progress', 'cancelled'])) {
            $isTransitionAllowed = true;
        } elseif ($currentStatus === 'in_progress' && $newStatus === 'completed') {
            $isTransitionAllowed = true;
        }

        if (! $isTransitionAllowed) {
            return back()->withErrors(['status' => "Status transition from '{$currentStatus}' to '{$newStatus}' is not allowed."]);
        }

        $schedule->update(['status' => $newStatus]);

        return back()->with('success', "Schedule status updated to '{$newStatus}'.");
    }
}
