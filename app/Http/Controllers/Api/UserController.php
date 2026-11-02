<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $companyId = $request->user()->company_id;

        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'in:admin,viewer'],
        ]);

        $users = User::query()
            ->where('company_id', $companyId)
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->string('role')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search');
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return UserResource::collection($users);
    }

    public function store(Request $request): UserResource
    {
        $companyId = $request->user()->company_id;

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role'     => ['required', 'string', Rule::in(['admin', 'viewer'])],
        ]);

        // Only the primary admin can add new admins; secondary admins can only add viewers
        if ($data['role'] === 'admin' && ! $request->user()->is_primary_admin) {
            abort(403, 'Only the primary admin can create new admins.');
        }

        $user = User::create([
            'company_id'       => $companyId,
            'name'             => $data['name'],
            'email'            => $data['email'],
            'password'         => $data['password'],
            'role'             => $data['role'],
            'is_primary_admin' => false,
        ]);

        return new UserResource($user);
    }

    public function show(Request $request, int $id): UserResource
    {
        $user = $this->findCompanyUser($request, $id);

        return new UserResource($user);
    }

    public function update(Request $request, int $id): UserResource
    {
        $companyId = $request->user()->company_id;
        $target = $this->findCompanyUser($request, $id);

        $data = $request->validate([
            'name'     => ['sometimes', 'required', 'string', 'max:255'],
            'email'    => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role'     => ['sometimes', 'required', 'string', Rule::in(['admin', 'viewer'])],
        ]);

        // Primary admin cannot be demoted
        if ($target->is_primary_admin && isset($data['role']) && $data['role'] !== 'admin') {
            abort(422, 'The primary admin cannot be demoted.');
        }

        // Only the primary admin can change roles (promote to admin or demote secondary admins)
        if (isset($data['role']) && $data['role'] !== $target->role && ! $request->user()->is_primary_admin) {
            abort(403, 'Only the primary admin can modify member roles.');
        }

        // Block demoting the last admin
        if (isset($data['role']) && $data['role'] !== 'admin' && $target->role === 'admin') {
            $adminCount = User::where('company_id', $companyId)->where('role', 'admin')->count();
            if ($adminCount <= 1) {
                abort(422, 'A company must keep at least one admin.');
            }
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $target->update($data);

        return new UserResource($target->fresh());
    }

    public function destroy(Request $request, int $id): Response
    {
        $companyId = $request->user()->company_id;
        $target = $this->findCompanyUser($request, $id);

        if ($target->id === $request->user()->id) {
            abort(422, 'You cannot delete yourself.');
        }

        // Primary admin cannot be deleted
        if ($target->is_primary_admin) {
            abort(422, 'The primary admin cannot be removed.');
        }

        // Only primary admin can delete another admin
        if ($target->role === 'admin' && ! $request->user()->is_primary_admin) {
            abort(403, 'Only the primary admin can remove other admins.');
        }

        if ($target->role === 'admin') {
            $adminCount = User::where('company_id', $companyId)->where('role', 'admin')->count();
            if ($adminCount <= 1) {
                abort(422, 'A company must keep at least one admin.');
            }
        }

        $target->tokens()->delete();
        $target->delete();

        return response()->noContent();
    }

    private function findCompanyUser(Request $request, int $id): User
    {
        return User::where('company_id', $request->user()->company_id)->findOrFail($id);
    }
}

