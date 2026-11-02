<?php

namespace App\Models\Concerns;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;

trait TenantScoped
{
    protected static function bootTenantScoped(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            if (config('app.multi_tenant')) {
                // Console commands, seeders, and migrations running without auth are exempt
                if (app()->runningInConsole() && ! auth()->check()) {
                    return;
                }

                if (! auth()->check()) {
                    throw new AuthenticationException('A tenant context requires an authenticated user.');
                }

                // Super admins manage the platform across all companies and are unscoped
                if (auth()->user()->role === 'super_admin') {
                    return;
                }

                $companyId = auth()->user()->company_id;

                if (! $companyId) {
                    throw new AuthenticationException('Authenticated user does not belong to a company.');
                }

                $builder->where(
                    $builder->getModel()->getTable().'.company_id',
                    $companyId
                );
            }
        });
    }
}
