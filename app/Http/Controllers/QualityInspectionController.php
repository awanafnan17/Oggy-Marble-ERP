<?php

namespace App\Http\Controllers;

use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\ProductionSchedule;
use App\Models\QualityInspection;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QualityInspectionController extends Controller
{
    public function index(Request $request)
    {
        $query = QualityInspection::with(['product', 'inventoryBatch', 'productionSchedule']);

        // Filters
        if ($request->filled('start_date')) {
            $query->whereDate('inspection_date', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('inspection_date', '<=', $request->input('end_date'));
        }
        if ($request->filled('inspection_type')) {
            $query->where('inspection_type', $request->input('inspection_type'));
        }
        if ($request->filled('grade')) {
            $query->where('grade', $request->input('grade'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $inspections = $query->orderBy('inspection_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $products = Product::orderBy('sku')->get();

        $batches = InventoryBatch::with('product')
            ->where('quantity_remaining', '>', 0)
            ->get();

        $schedules = ProductionSchedule::whereIn('status', ['in_progress', 'completed'])
            ->orderBy('id', 'desc')
            ->get();

        return Inertia::render('Quality/Index', [
            'inspections' => $inspections,
            'products' => $products,
            'batches' => $batches,
            'schedules' => $schedules,
            'filters' => $request->only(['start_date', 'end_date', 'inspection_type', 'grade', 'status']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'inspection_date' => 'required|date',
            'product_id' => 'required|exists:products,id',
            'inventory_batch_id' => 'nullable|exists:inventory_batches,id',
            'production_schedule_id' => 'nullable|exists:production_schedules,id',
            'inspection_type' => 'required|string|in:production_qc,pre_dispatch_qc,return_qc,stock_qc',
            'grade' => 'required|string|in:A,B,C,reject',
            'defect_type' => 'nullable|string|in:cracks,size_variation,color_variation,polish_issue,thickness_issue,breakage,edge_damage,other',
            'checked_quantity' => 'required|numeric|gt:0',
            'rejected_quantity' => 'required|numeric|min:0',
            'accepted_quantity' => 'required|numeric|min:0',
            'inspector_name' => 'required|string|max:255',
            'remarks' => 'nullable|string',
            'status' => 'required|string|in:draft,passed,failed,partially_passed',
        ]);

        $checked = (float) $validated['checked_quantity'];
        $rejected = (float) $validated['rejected_quantity'];
        $accepted = (float) $validated['accepted_quantity'];

        if ($rejected > $checked) {
            return back()->withErrors(['rejected_quantity' => 'Rejected quantity cannot exceed checked quantity.']);
        }

        if (($accepted + $rejected) > $checked) {
            return back()->withErrors(['accepted_quantity' => 'Sum of accepted and rejected quantities cannot exceed checked quantity.']);
        }

        $qcNumber = 'QC-'.now()->format('Ymd').'-'.rand(1000, 9999);

        QualityInspection::create(array_merge($validated, [
            'qc_number' => $qcNumber,
        ]));

        return back()->with('success', 'Quality inspection log registered successfully.');
    }

    public function update(Request $request, $id)
    {
        $inspection = QualityInspection::findOrFail($id);

        if ($inspection->status !== 'draft') {
            return back()->withErrors(['status' => 'Cannot edit a final Quality Control inspection log.']);
        }

        $validated = $request->validate([
            'inspection_date' => 'required|date',
            'product_id' => 'required|exists:products,id',
            'inventory_batch_id' => 'nullable|exists:inventory_batches,id',
            'production_schedule_id' => 'nullable|exists:production_schedules,id',
            'inspection_type' => 'required|string|in:production_qc,pre_dispatch_qc,return_qc,stock_qc',
            'grade' => 'required|string|in:A,B,C,reject',
            'defect_type' => 'nullable|string|in:cracks,size_variation,color_variation,polish_issue,thickness_issue,breakage,edge_damage,other',
            'checked_quantity' => 'required|numeric|gt:0',
            'rejected_quantity' => 'required|numeric|min:0',
            'accepted_quantity' => 'required|numeric|min:0',
            'inspector_name' => 'required|string|max:255',
            'remarks' => 'nullable|string',
            'status' => 'required|string|in:draft,passed,failed,partially_passed',
        ]);

        $checked = (float) $validated['checked_quantity'];
        $rejected = (float) $validated['rejected_quantity'];
        $accepted = (float) $validated['accepted_quantity'];

        if ($rejected > $checked) {
            return back()->withErrors(['rejected_quantity' => 'Rejected quantity cannot exceed checked quantity.']);
        }

        if (($accepted + $rejected) > $checked) {
            return back()->withErrors(['accepted_quantity' => 'Sum of accepted and rejected quantities cannot exceed checked quantity.']);
        }

        $inspection->update($validated);

        return back()->with('success', 'Quality inspection log updated.');
    }

    public function destroy($id)
    {
        $inspection = QualityInspection::findOrFail($id);

        if ($inspection->status !== 'draft') {
            return back()->withErrors(['status' => 'Cannot delete a finalized Quality Control inspection log.']);
        }

        $inspection->delete();

        return back()->with('success', 'Quality inspection log deleted.');
    }
}
