<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyApprovalAndLoginTest extends TestCase
{
    use RefreshDatabase;

    protected Company $pendingCompany;
    protected Company $activeCompany;
    protected Company $rejectedCompany;
    protected Company $suspendedCompany;
    protected User $pendingAdmin;
    protected User $activeAdmin;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.multi_tenant' => true]);

        $this->superAdmin = User::create([
            'name'             => 'Super Admin',
            'email'            => 'superadmin@test.com',
            'password'         => 'SuperSecret123!',
            'role'             => 'super_admin',
            'company_id'       => null,
            'is_primary_admin' => false,
        ]);

        $this->pendingCompany = Company::create(['name' => 'Pending Corp', 'status' => 'pending']);
        $this->pendingAdmin = User::create([
            'name'             => 'Pending Admin',
            'email'            => 'pending@test.com',
            'password'         => 'Password123!',
            'role'             => 'admin',
            'company_id'       => $this->pendingCompany->id,
            'is_primary_admin' => true,
        ]);

        $this->activeCompany = Company::create(['name' => 'Active Corp', 'status' => 'active']);
        $this->activeAdmin = User::create([
            'name'             => 'Active Admin',
            'email'            => 'active@test.com',
            'password'         => 'Password123!',
            'role'             => 'admin',
            'company_id'       => $this->activeCompany->id,
            'is_primary_admin' => true,
        ]);

        $this->rejectedCompany = Company::create([
            'name'          => 'Rejected Corp',
            'status'        => 'rejected',
            'status_reason' => 'Invalid documents',
        ]);
        User::create([
            'name'             => 'Rejected Admin',
            'email'            => 'rejected@test.com',
            'password'         => 'Password123!',
            'role'             => 'admin',
            'company_id'       => $this->rejectedCompany->id,
            'is_primary_admin' => true,
        ]);

        $this->suspendedCompany = Company::create([
            'name'          => 'Suspended Corp',
            'status'        => 'suspended',
            'status_reason' => 'Policy violation',
        ]);
        User::create([
            'name'             => 'Suspended Admin',
            'email'            => 'suspended@test.com',
            'password'         => 'Password123!',
            'role'             => 'admin',
            'company_id'       => $this->suspendedCompany->id,
            'is_primary_admin' => true,
        ]);
    }

    public function test_pending_company_admin_gets_403_on_login(): void
    {
        $response = $this->postJson('/api/login', [
            'email'    => 'pending@test.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'code'    => 'company_pending',
                'message' => 'Your company is awaiting approval.',
            ]);
    }

    public function test_rejected_company_admin_gets_403_on_login(): void
    {
        $response = $this->postJson('/api/login', [
            'email'    => 'rejected@test.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'code'    => 'company_rejected',
                'message' => 'Your company request was rejected.',
            ]);
    }

    public function test_suspended_company_admin_gets_403_on_login(): void
    {
        $response = $this->postJson('/api/login', [
            'email'    => 'suspended@test.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'code'    => 'company_suspended',
                'message' => 'Your company account is suspended.',
            ]);
    }

    public function test_active_company_admin_can_login_successfully(): void
    {
        $response = $this->postJson('/api/login', [
            'email'    => 'active@test.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user'])
            ->assertJsonFragment([
                'email'            => 'active@test.com',
                'role'             => 'admin',
                'is_primary_admin' => true,
            ]);
    }

    public function test_super_admin_can_login_without_company(): void
    {
        $response = $this->postJson('/api/login', [
            'email'    => 'superadmin@test.com',
            'password' => 'SuperSecret123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user'])
            ->assertJsonFragment([
                'email'      => 'superadmin@test.com',
                'role'       => 'super_admin',
                'company_id' => null,
            ]);
    }

    public function test_non_super_admin_cannot_access_super_admin_routes(): void
    {
        Sanctum::actingAs($this->activeAdmin);

        $response = $this->getJson('/api/admin/companies');

        $response->assertStatus(403);
    }

    public function test_super_admin_can_approve_pending_company(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->postJson("/api/admin/companies/{$this->pendingCompany->id}/approve");

        $response->assertStatus(200);
        $this->assertSame('active', $this->pendingCompany->fresh()->status);
        $this->assertNotNull($this->pendingCompany->fresh()->approved_at);
        $this->assertSame($this->superAdmin->id, $this->pendingCompany->fresh()->approved_by);

        // After approval, the pending admin can now log in
        $loginResponse = $this->postJson('/api/login', [
            'email'    => 'pending@test.com',
            'password' => 'Password123!',
        ]);

        $loginResponse->assertStatus(200);
    }

    public function test_cannot_approve_non_pending_company(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->postJson("/api/admin/companies/{$this->activeCompany->id}/approve");

        $response->assertStatus(422);
    }

    public function test_super_admin_can_reject_pending_company(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->postJson("/api/admin/companies/{$this->pendingCompany->id}/reject", [
            'reason' => 'Fraudulent business name.',
        ]);

        $response->assertStatus(200);
        $this->assertSame('rejected', $this->pendingCompany->fresh()->status);
        $this->assertSame('Fraudulent business name.', $this->pendingCompany->fresh()->status_reason);
    }

    public function test_super_admin_can_suspend_active_company_and_revokes_tokens(): void
    {
        // Active admin generates a token
        $token = $this->activeAdmin->createToken('test')->plainTextToken;

        Sanctum::actingAs($this->superAdmin);

        $response = $this->postJson("/api/admin/companies/{$this->activeCompany->id}/suspend", [
            'reason' => 'Billing default.',
        ]);

        $response->assertStatus(200);
        $this->assertSame('suspended', $this->activeCompany->fresh()->status);

        // Verify tokens were deleted from database
        $this->assertDatabaseEmpty('personal_access_tokens');

        // Clear actingAs so subsequent request uses the Bearer token header
        $this->app['auth']->forgetGuards();

        $clientResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/clients');

        $clientResponse->assertStatus(401);
    }
}
