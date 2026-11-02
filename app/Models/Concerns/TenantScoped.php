<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait TenantScoped
{
    protected static function bootTenantScoped(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            if (config('app.multi_tenant') && auth()->check()) {
                $builder->where(
                    $builder->getModel()->getTable().'.company_id',
                    auth()->user()->company_id
                );
            }
        });
    }
}