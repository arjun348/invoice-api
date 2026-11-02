<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $adminA;
    protected User $adminB;
    protected Client $clientA;
    protected Client $clientB;
    protected Invoice $invoiceA;
    protected Invoice $invoiceB;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.multi_tenant' => true]);

        // Create Company A with admin, client and invoice
        $this->companyA = Company::create(['name' => 'Company Alpha', 'status' => 'active']);
        $this->adminA = User::create([
            'name'             => 'Admin Alpha',
            'email'            => 'admin@alpha.test',
            'password'         => 'Password123!',
            'role'             => 'admin',
            'company_id'       => $this->companyA->id,
            'is_primary_admin' => true,
        ]);

        // Create Company B with admin, client and invoice
        $this->companyB = Company::create(['name' => 'Company Beta', 'status' => 'active']);
        $this->adminB = User::create([
            'name'             => 'Admin Beta',
            'email'            => 'admin@beta.test',
            'password'         => 'Password123!',
            'role'             => 'admin',
            'company_id'       => $this->companyB->id,
            'is_primary_admin' => true,
        ]);

        $this->clientA = Client::create([
            'company_id'   => $this->companyA->id,
            'name'         => 'Client of Alpha',
            'email'        => 'client@alpha.test',
            'phone'        => '111-111',
            'company_name' => 'Alpha Client Corp',
        ]);

        $this->clientB = Client::create([
            'company_id'   => $this->companyB->id,
            'name'         => 'Client of Beta',
            'email'        => 'client@beta.test',
            'phone'        => '222-222',
            'company_name' => 'Beta Client Corp',
        ]);

        $this->invoiceA = Invoice::create([
            'company_id' => $this->companyA->id,
            'client_id'  => $this->clientA->id,
            'number'     => 'INV-100',
            'amount'     => 500.00,
            'status'     => 'draft',
            'due_date'   => '2026-12-01',
        ]);

        $this->invoiceB = Invoice::create([
            'company_id' => $this->companyB->id,
            'client_id'  => $this->clientB->id,
            'number'     => 'INV-200',
            'amount'     => 1200.00,
            'status'     => 'sent',
            'due_date'   => '2026-12-15',
        ]);
    }

    /** 1. Company A’s admin lists clients and invoices and sees only A’s */
    public function test_company_a_admin_lists_clients_and_sees_only_own(): void
    {
        Sanctum::actingAs($this->adminA);

        $response = $this->getJson('/api/clients');

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Client of Alpha']);
        $response->assertJsonMissing(['name' => 'Client of Beta']);
    }

    public function test_company_a_admin_lists_invoices_and_sees_only_own(): void
    {
        Sanctum::actingAs($this->adminA);

        $response = $this->getJson('/api/invoices');

        $response->assertStatus(200);
        $response->assertJsonFragment(['number' => 'INV-100']);
        $response->assertJsonMissing(['number' => 'INV-200']);
    }

    /** 2. A’s admin requests B’s invoice, client and user by ID and gets 404 */
    public function test_company_a_admin_gets_404_viewing_company_b_client(): void
    {
        Sanctum::actingAs($this->adminA);

        $response = $this->getJson("/api/clients/{$this->clientB->id}");

        $response->assertStatus(404);
    }

    public function test_company_a_admin_gets_404_viewing_company_b_invoice(): void
    {
        Sanctum::actingAs($this->adminA);

        $response = $this->getJson("/api/invoices/{$this->invoiceB->id}");

        $response->assertStatus(404);
    }

    public function test_company_a_admin_gets_404_viewing_company_b_user(): void
    {
        Sanctum::actingAs($this->adminA);

        $response = $this->getJson("/api/users/{$this->adminB->id}");

        $response->assertStatus(404);
    }

    /** 3. A’s admin tries to update or delete B’s records and gets 404 */
    public function test_company_a_admin_gets_404_updating_company_b_client(): void
    {
        Sanctum::actingAs($this->adminA);

        $response = $this->putJson("/api/clients/{$this->clientB->id}", [
            'name' => 'Hijacked Client',
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseMissing('clients', ['name' => 'Hijacked Client']);
    }

    public function test_company_a_admin_gets_404_deleting_company_b_client(): void
    {
        Sanctum::actingAs($this->adminA);

        $response = $this->deleteJson("/api/clients/{$this->clientB->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('clients', ['id' => $this->clientB->id]);
    }

    public function test_company_a_admin_gets_404_updating_company_b_invoice(): void
    {
        Sanctum::actingAs($this->adminA);

        $response = $this->putJson("/api/invoices/{$this->invoiceB->id}", [
            'client_id' => $this->clientA->id,
            'number'    => 'INV-HACKED',
            'amount'    => 1.00,
            'status'    => 'paid',
            'due_date'  => '2026-12-31',
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseMissing('invoices', ['number' => 'INV-HACKED']);
    }

    public function test_company_a_admin_gets_404_deleting_company_b_invoice(): void
    {
        Sanctum::actingAs($this->adminA);

        $response = $this->deleteJson("/api/invoices/{$this->invoiceB->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('invoices', ['id' => $this->invoiceB->id]);
    }

    public function test_company_a_admin_gets_404_updating_company_b_user(): void
    {
        Sanctum::actingAs($this->adminA);

        $response = $this->putJson("/api/users/{$this->adminB->id}", [
            'name' => 'Modified Name',
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseMissing('users', ['name' => 'Modified Name']);
    }

    public function test_company_a_admin_gets_404_deleting_company_b_user(): void
    {
        Sanctum::actingAs($this->adminA);

        $response = $this->deleteJson("/api/users/{$this->adminB->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('users', ['id' => $this->adminB->id]);
    }

    /** 4. Creating an invoice for B’s client from A fails validation */
    public function test_creating_invoice_for_company_b_client_from_company_a_fails_validation(): void
    {
        Sanctum::actingAs($this->adminA);

        $response = $this->postJson('/api/invoices', [
            'client_id' => $this->clientB->id, // belongs to Beta!
            'number'    => 'INV-CROSS-01',
            'amount'    => 350.00,
            'status'    => 'draft',
            'due_date'  => '2026-12-01',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['client_id']);

        $this->assertDatabaseMissing('invoices', ['number' => 'INV-CROSS-01']);
    }

    /** 5. The same invoice number can exist in both companies, but not twice in one */
    public function test_same_invoice_number_can_exist_in_both_companies(): void
    {
        Sanctum::actingAs($this->adminB);

        // Company Beta creates 'INV-100' which already exists in Company Alpha
        $response = $this->postJson('/api/invoices', [
            'client_id' => $this->clientB->id,
            'number'    => 'INV-100',
            'amount'    => 750.00,
            'status'    => 'draft',
            'due_date'  => '2026-12-25',
        ]);

        $response->assertStatus(201);
        $this->assertSame(2, Invoice::withoutGlobalScopes()->where('number', 'INV-100')->count());
    }

    public function test_duplicate_invoice_number_in_same_company_fails_validation(): void
    {
        Sanctum::actingAs($this->adminA);

        // Company Alpha tries to create 'INV-100' again
        $response = $this->postJson('/api/invoices', [
            'client_id' => $this->clientA->id,
            'number'    => 'INV-100',
            'amount'    => 900.00,
            'status'    => 'draft',
            'due_date'  => '2026-12-25',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['number']);
    }

    /** 6. A company admin can’t call /admin/companies (403), and can’t remove the last admin or themselves */
    public function test_company_admin_cannot_call_super_admin_companies_endpoint(): void
    {
        Sanctum::actingAs($this->adminA);

        $response = $this->getJson('/api/admin/companies');

        $response->assertStatus(403);
    }

    public function test_company_admin_cannot_delete_themselves(): void
    {
        Sanctum::actingAs($this->adminA);

        $response = $this->deleteJson("/api/users/{$this->adminA->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'You cannot delete yourself.',
            ]);

        $this->assertDatabaseHas('users', ['id' => $this->adminA->id]);
    }

    public function test_company_admin_cannot_delete_last_admin(): void
    {
        // Create a secondary admin in Company A
        $secondAdmin = User::create([
            'name'             => 'Second Admin',
            'email'            => 'second@alpha.test',
            'password'         => 'Password123!',
            'role'             => 'admin',
            'company_id'       => $this->companyA->id,
            'is_primary_admin' => false,
        ]);

        Sanctum::actingAs($this->adminA);

        // Primary admin deletes secondAdmin -> succeeds because primary admin remains
        $delResponse = $this->deleteJson("/api/users/{$secondAdmin->id}");
        $delResponse->assertStatus(204);

        // Now only adminA remains. Trying to delete adminA fails (even if attempted via API)
        $failResponse = $this->deleteJson("/api/users/{$this->adminA->id}");
        $failResponse->assertStatus(422);
    }

    /** 7. A suspended company’s existing token gets 403 on the next request */
    public function test_suspended_company_existing_token_gets_403_on_next_request(): void
    {
        // Admin A generates an active personal access token
        $token = $this->adminA->createToken('active-token')->plainTextToken;

        // Verify token works before suspension
        $preResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/clients');
        $preResponse->assertStatus(200);

        // Company A is suspended (e.g. by super admin)
        $this->companyA->update(['status' => 'suspended']);

        // Clear in-memory auth guard cache so the next request re-resolves the user & company from DB
        $this->app['auth']->forgetGuards();

        // Next request with the same token gets 403 with company_suspended code
        $postResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/clients');

        $postResponse->assertStatus(403)
            ->assertJson([
                'code'    => 'company_suspended',
                'message' => 'Your company account is not active.',
            ]);
    }

    public function test_mass_assignment_protection_on_company_id(): void
    {
        Sanctum::actingAs($this->adminA);

        $response = $this->postJson('/api/clients', [
            'name'         => 'Infiltrator Client',
            'company_id'   => $this->companyB->id,
            'email'        => 'infiltrator@test.com',
            'phone'        => '000-000',
            'company_name' => 'Infiltrator Inc',
        ]);

        $response->assertStatus(201);

        $client = Client::withoutGlobalScopes()->where('name', 'Infiltrator Client')->first();
        $this->assertNotNull($client);
        $this->assertSame($this->companyA->id, $client->company_id, 'company_id must remain forced to caller company');
    }
}
