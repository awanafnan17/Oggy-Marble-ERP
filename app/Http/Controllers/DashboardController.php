<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function index(Request $request)
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $financialKPIs = $this->dashboardService->getFinancialKPIs($dateFrom, $dateTo);
        $cashPosition = $this->dashboardService->getCashPosition();
        $inventoryValuation = $this->dashboardService->getInventoryValuation();
        $productionMetrics = $this->dashboardService->getProductionMetrics($dateFrom, $dateTo);
        $salesAnalytics = $this->dashboardService->getSalesAnalytics($dateFrom, $dateTo);
        $receivableAging = $this->dashboardService->getReceivableAgingSummary();

        return Inertia::render('Dashboard', [
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'financialKPIs' => $financialKPIs,
            'cashPosition' => $cashPosition,
            'inventoryValuation' => $inventoryValuation,
            'productionMetrics' => $productionMetrics,
            'salesAnalytics' => $salesAnalytics,
            'receivableAging' => $receivableAging,
        ]);
    }
}
