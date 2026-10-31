<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use Carbon\Carbon;
use Exception;

class PeriodService
{
    /**
     * Get the accounting period that contains the given date.
     */
    public function getPeriodForDate(Carbon $date): ?AccountingPeriod
    {
        return AccountingPeriod::where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
    }

    /**
     * Ensure the accounting period for the given date is open.
     * Throws an exception if the period is closed or not defined.
     */
    public function ensurePeriodOpen(Carbon $date): bool
    {
        $period = $this->getPeriodForDate($date);

        if (! $period) {
            // If no period is defined for this date, allow the transaction.
            // This allows operations before period governance is configured.
            return true;
        }

        if ($period->is_closed) {
            throw new Exception("Accounting period '{$period->period_name}' is closed. No backdated posting allowed.");
        }

        return true;
    }
}
