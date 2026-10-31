<?php

namespace App\Traits;

use App\Services\AuditService;

trait Auditable
{
    protected static function bootAuditable()
    {
        static::created(function ($model) {
            app(AuditService::class)
                ->log('created', $model, null, $model->toArray());
        });

        static::updating(function ($model) {

            $dirty = $model->getDirty();

            if (empty($dirty)) {
                return;
            }

            $before = [];
            $after = [];

            foreach ($dirty as $key => $value) {
                $before[$key] = $model->getOriginal($key);
                $after[$key] = $value;
            }

            app(AuditService::class)
                ->log('updated', $model, $before, $after);
        });

        static::deleted(function ($model) {
            app(AuditService::class)
                ->log('deleted', $model, $model->toArray(), null);
        });
    }
}
