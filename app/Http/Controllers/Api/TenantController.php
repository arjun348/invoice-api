<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TenantController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255', 'unique:companies,name'],
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'     => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'company_name.unique' => 'A company with this name has already been registered.',
        ]);

        DB::transaction(function () use ($data) {
            $company = Company::create([
                'name' => $data['company_name'],
                'status' => 'pending',
            ]);

            User::create([
                'company_id'       => $company->id,
                'name'             => $data['name'],
                'email'            => $data['email'],
                'password'         => $data['password'],
                'role'             => 'admin',
                'is_primary_admin' => true,
            ]);
        });

        return response()->json(['message' => 'Your company is awaiting approval.'], 202);
    }
}

