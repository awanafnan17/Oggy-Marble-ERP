<?php

namespace App\Http\Middleware;

use App\Services\PeriodService;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class EnsurePeriodOpen
{
    protected PeriodService $periodService;

    public function __construct(PeriodService $periodService)
    {
        $this->periodService = $periodService;
    }

    /**
     * Handle an incoming request.
     *
     * Detect the financial date field from the request and ensure
     * that the accounting period for that date is open.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only intercept POST/PUT/PATCH (state-changing) requests
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            return $next($request);
        }

        // Detect financial date from known field names
        $dateFields = ['date', 'order_date', 'production_date', 'job_date', 'payment_date', 'adjustment_date', 'statement_date', 'transfer_date'];
        $financialDate = null;

        foreach ($dateFields as $field) {
            if ($request->filled($field)) {
                try {
                    $financialDate = Carbon::parse($request->input($field));
                    break;
                } catch (\Exception $e) {
                    // If parsing fails, skip this field
                    continue;
                }
            }
        }

        // If no financial date detected, allow through
        if (! $financialDate) {
            return $next($request);
        }

        try {
            $this->periodService->ensurePeriodOpen($financialDate);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'period' => $e->getMessage(),
            ]);
        }

        return $next($request);
    }
}
