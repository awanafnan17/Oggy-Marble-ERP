<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\SlabToTileProduction;
use App\Models\StoneToSlabProduction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductionVarianceController extends Controller
{
    /**
     * Display the production variance report.
     */
    public function index(Request $request)
    {
        $products = Product::orderBy('name')->get(['id', 'sku', 'name', 'size']);

        // Fetch stone-to-slab productions with variance data
        $stsQuery = StoneToSlabProduction::with(['slabProduct:id,sku,name,size'])
            ->whereNotNull('variance_amount');

        $sttQuery = SlabToTileProduction::with(['tileProduct:id,sku,name,size'])
            ->whereNotNull('variance_amount');

        // Apply filters
        if ($request->filled('product_id')) {
            $stsQuery->where('slab_product_id', $request->product_id);
            $sttQuery->where('tile_product_id', $request->product_id);
        }
        if ($request->filled('date_from')) {
            $stsQuery->where('production_date', '>=', $request->date_from);
            $sttQuery->where('production_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $stsQuery->where('production_date', '<=', $request->date_to);
            $sttQuery->where('production_date', '<=', $request->date_to);
        }

        $stsProductions = $stsQuery->orderBy('production_date', 'desc')->get();
        $sttProductions = $sttQuery->orderBy('production_date', 'desc')->get();

        // Normalize into a unified collection for the frontend
        $varianceData = collect();

        foreach ($stsProductions as $p) {
            $varianceData->push([
                'id' => 'STS-'.$p->id,
                'production_number' => $p->production_order_number,
                'production_date' => $p->production_date->toDateString(),
                'type' => 'Stone → Slab',
                'product_name' => $p->slabProduct->name ?? '—',
                'product_sku' => $p->slabProduct->sku ?? '—',
                'product_size' => $p->slabProduct->size ?? '—',
                'expected_cost' => (float) $p->expected_total_cost,
                'actual_cost' => (float) $p->total_cost,
                'variance_amount' => (float) $p->variance_amount,
                'variance_percentage' => (float) $p->variance_percentage,
                'is_favorable' => (bool) $p->is_favorable,
            ]);
        }

        foreach ($sttProductions as $p) {
            $varianceData->push([
                'id' => 'STT-'.$p->id,
                'production_number' => $p->production_order_number,
                'production_date' => $p->production_date->toDateString(),
                'type' => 'Slab → Tile',
                'product_name' => $p->tileProduct->name ?? '—',
                'product_sku' => $p->tileProduct->sku ?? '—',
                'product_size' => $p->tileProduct->size ?? '—',
                'expected_cost' => (float) $p->expected_total_cost,
                'actual_cost' => (float) $p->total_cost,
                'variance_amount' => (float) $p->variance_amount,
                'variance_percentage' => (float) $p->variance_percentage,
                'is_favorable' => (bool) $p->is_favorable,
            ]);
        }

        // Sort unified data by date descending
        $sorted = $varianceData->sortByDesc('production_date')->values();

        // KPI calculations
        $totalExpected = $sorted->sum('expected_cost');
        $totalActual = $sorted->sum('actual_cost');
        $totalVariance = $sorted->sum('variance_amount');
        $avgVariancePct = $sorted->count() > 0 ? $sorted->avg('variance_percentage') : 0;

        return Inertia::render('Production/VarianceReport', [
            'products' => $products,
            'varianceData' => $sorted,
            'kpi' => [
                'total_expected' => $totalExpected,
                'total_actual' => $totalActual,
                'total_variance' => $totalVariance,
                'avg_variance_pct' => $avgVariancePct,
                'total_runs' => $sorted->count(),
                'favorable_count' => $sorted->where('is_favorable', true)->count(),
                'unfavorable_count' => $sorted->where('is_favorable', false)->count(),
            ],
            'filters' => [
                'product_id' => $request->product_id,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
            ],
        ]);
    }
}
