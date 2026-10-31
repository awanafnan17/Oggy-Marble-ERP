<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditService
{
    /**
     * Log an action for a model.
     *
     * @param  string  $action  'created', 'updated', or 'deleted'
     * @param  mixed  $model  The Eloquent model
     * @param  array|null  $before
     * @param  array|null  $after
     */
    private function normalize($data)
    {
        if (! $data) {
            return $data;
        }

        return collect($data)->map(function ($value) {
            if (is_numeric($value)) {
                return (float) $value;
            }

            return $value;
        })->toArray();
    }

    public function log($action, $model, $before = null, $after = null)
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'before_data' => $this->normalize($before),
            'after_data' => $this->normalize($after),
            'ip_address' => request()->ip(),
        ]);
    }
}
