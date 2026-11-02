<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompanyAdminController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', 'string', 'in:pending,active,rejected,suspended'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $companies = Company::query()
            ->with(['users' => fn ($q) => $q->where('role', 'admin')])
            ->withCount(['users', 'clients', 'invoices'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search');
                $q->where('name', 'like', "%{$search}%");
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return CompanyResource::collection($companies);
    }

    public function approve(Company $company): CompanyResource
    {
        abort_unless($company->status === 'pending', 422, 'Only pending companies can be approved.');

        $company->update([
            'status' => 'active',
            'status_reason' => null,
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        return new CompanyResource($company->fresh()->loadCount(['users', 'clients', 'invoices']));
    }

    public function reject(Request $request, Company $company): CompanyResource
    {
        $request->validate(['reason' => 'required|string|max:500']);

        abort_unless($company->status === 'pending', 422, 'Only pending companies can be rejected.');

        $company->update([
            'status' => 'rejected',
            'status_reason' => $request->string('reason'),
        ]);

        return new CompanyResource($company->fresh()->loadCount(['users', 'clients', 'invoices']));
    }

    public function suspend(Request $request, Company $company): CompanyResource
    {
        $request->validate(['reason' => 'required|string|max:500']);

        abort_unless($company->status === 'active', 422, 'Only active companies can be suspended.');

        $company->update([
            'status' => 'suspended',
            'status_reason' => $request->string('reason'),
        ]);

        // Revoke all active user tokens for the company immediately
        $company->users->each(fn ($u) => $u->tokens()->delete());

        return new CompanyResource($company->fresh()->loadCount(['users', 'clients', 'invoices']));
    }

    public function reactivate(Company $company): CompanyResource
    {
        abort_unless($company->status === 'suspended', 422, 'Only suspended companies can be reactivated.');

        $company->update([
            'status' => 'active',
            'status_reason' => null,
        ]);

        return new CompanyResource($company->fresh()->loadCount(['users', 'clients', 'invoices']));
    }

    public function markPending(Company $company): CompanyResource
    {
        abort_unless(in_array($company->status, ['rejected', 'suspended'], true), 422, 'Only rejected or suspended companies can be moved to pending.');

        $company->update([
            'status' => 'pending',
            'status_reason' => null,
        ]);

        return new CompanyResource($company->fresh()->loadCount(['users', 'clients', 'invoices']));
    }
}

