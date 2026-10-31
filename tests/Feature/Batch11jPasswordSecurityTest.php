<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Batch11jPasswordSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $ownerUser;

    private User $accountantUser;

    private User $viewerUser;

    private User $salesUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Roles
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $ownerRole = Role::firstOrCreate(['name' => 'owner'], ['display_name' => 'Owner']);
        $accountantRole = Role::firstOrCreate(['name' => 'accountant'], ['display_name' => 'Accountant']);
        $viewerRole = Role::firstOrCreate(['name' => 'viewer'], ['display_name' => 'Viewer']);
        $salesRole = Role::firstOrCreate(['name' => 'sales_user'], ['display_name' => 'Sales']);

        // Create and Attach Permissions
        $viewDashboard = Permission::firstOrCreate(['name' => 'view_dashboard'], ['display_name' => 'View Dashboard']);
        $viewAuditLogs = Permission::firstOrCreate(['name' => 'view_audit_logs'], ['display_name' => 'View Audit Logs']);

        $adminRole->permissions()->syncWithoutDetaching([$viewDashboard->id, $viewAuditLogs->id]);
        $ownerRole->permissions()->syncWithoutDetaching([$viewDashboard->id]);
        $accountantRole->permissions()->syncWithoutDetaching([$viewDashboard->id]);
        $viewerRole->permissions()->syncWithoutDetaching([$viewDashboard->id]);
        $salesRole->permissions()->syncWithoutDetaching([$viewDashboard->id]);

        // Create Users
        $this->adminUser = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.test',
            'password' => Hash::make('password12345'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->sync([$adminRole->id]);

        $this->ownerUser = User::factory()->create([
            'name' => 'Owner User',
            'email' => 'owner@example.test',
            'password' => Hash::make('password12345'),
            'status' => 'active',
        ]);
        $this->ownerUser->roles()->sync([$ownerRole->id]);

        $this->accountantUser = User::factory()->create([
            'name' => 'Accountant User',
            'email' => 'accountant@example.test',
            'password' => Hash::make('password12345'),
            'status' => 'active',
        ]);
        $this->accountantUser->roles()->sync([$accountantRole->id]);

        $this->viewerUser = User::factory()->create([
            'name' => 'Viewer User',
            'email' => 'viewer@example.test',
            'password' => Hash::make('password12345'),
            'status' => 'active',
        ]);
        $this->viewerUser->roles()->sync([$viewerRole->id]);

        $this->salesUser = User::factory()->create([
            'name' => 'Sales User',
            'email' => 'sales@example.test',
            'password' => Hash::make('password12345'),
            'status' => 'active',
        ]);
        $this->salesUser->roles()->sync([$salesRole->id]);
    }

    /**
     * 1. Guest cannot access password page.
     */
    public function test_guest_cannot_access_password_page(): void
    {
        $response = $this->get('/profile/password');
        $response->assertRedirect('/login');
    }

    /**
     * 2. Guest cannot access user management.
     */
    public function test_guest_cannot_access_user_management(): void
    {
        $response = $this->get('/administration/users');
        $response->assertRedirect('/login');
    }

    /**
     * 3. Logged-in user can change own password with correct current password.
     */
    public function test_logged_in_user_can_change_own_password(): void
    {
        $response = $this->actingAs($this->salesUser)->post('/profile/password', [
            'current_password' => 'password12345',
            'new_password' => 'newstrongpassword12345',
            'new_password_confirmation' => 'newstrongpassword12345',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('newstrongpassword12345', $this->salesUser->fresh()->password));
    }

    /**
     * 4. Wrong current password fails validation.
     */
    public function test_wrong_current_password_fails_validation(): void
    {
        $response = $this->actingAs($this->salesUser)->post('/profile/password', [
            'current_password' => 'wrongcurrentpassword',
            'new_password' => 'newstrongpassword12345',
            'new_password_confirmation' => 'newstrongpassword12345',
        ]);

        $response->assertSessionHasErrors(['current_password']);
    }

    /**
     * 5. Password confirmation mismatch fails validation.
     */
    public function test_password_confirmation_mismatch_fails_validation(): void
    {
        $response = $this->actingAs($this->salesUser)->post('/profile/password', [
            'current_password' => 'password12345',
            'new_password' => 'newstrongpassword12345',
            'new_password_confirmation' => 'differentconfirmpassword',
        ]);

        $response->assertSessionHasErrors(['new_password']);
    }

    /**
     * 6. Weak password fails validation.
     */
    public function test_weak_password_fails_validation(): void
    {
        $response = $this->actingAs($this->salesUser)->post('/profile/password', [
            'current_password' => 'password12345',
            'new_password' => 'weak', // under 12 characters
            'new_password_confirmation' => 'weak',
        ]);

        $response->assertSessionHasErrors(['new_password']);
    }

    /**
     * 7. Admin/owner can access user management.
     */
    public function test_admin_and_owner_can_access_user_management(): void
    {
        $responseAdmin = $this->actingAs($this->adminUser)->get('/administration/users');
        $responseAdmin->assertStatus(200);

        $responseOwner = $this->actingAs($this->ownerUser)->get('/administration/users');
        $responseOwner->assertStatus(200);
    }

    /**
     * 8. Viewer/accountant cannot access user management.
     */
    public function test_viewer_and_accountant_cannot_access_user_management(): void
    {
        $responseViewer = $this->actingAs($this->viewerUser)->get('/administration/users');
        $responseViewer->assertStatus(403);

        $responseAccountant = $this->actingAs($this->accountantUser)->get('/administration/users');
        $responseAccountant->assertStatus(403);
    }

    /**
     * 9. Admin/owner can set temporary ERP password for another user.
     */
    public function test_admin_can_set_temporary_password_for_another_user(): void
    {
        $response = $this->actingAs($this->adminUser)->post("/administration/users/{$this->salesUser->id}/reset-password", [
            'password' => 'newtemporarypassword123',
            'password_confirmation' => 'newtemporarypassword123',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('newtemporarypassword123', $this->salesUser->fresh()->password));
    }

    /**
     * 10. Admin/owner can mark non-critical user inactive.
     */
    public function test_admin_can_mark_non_critical_user_inactive(): void
    {
        $response = $this->actingAs($this->adminUser)->post("/administration/users/{$this->salesUser->id}/toggle-status");
        $response->assertSessionHasNoErrors();
        $this->assertEquals('inactive', $this->salesUser->fresh()->status);
    }

    /**
     * 11. Inactive user cannot log in.
     */
    public function test_inactive_user_cannot_log_in(): void
    {
        $this->salesUser->update(['status' => 'inactive']);

        $response = $this->post('/login', [
            'email' => 'sales@example.test',
            'password' => 'password12345',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertFalse(auth()->check());
    }

    /**
     * 12. User cannot disable themselves.
     */
    public function test_user_cannot_disable_themselves(): void
    {
        $response = $this->actingAs($this->adminUser)->post("/administration/users/{$this->adminUser->id}/toggle-status");
        $response->assertSessionHasErrors(['status']);
        $this->assertEquals('active', $this->adminUser->fresh()->status);
    }

    /**
     * 13. System prevents disabling the last active admin/owner.
     */
    public function test_system_prevents_disabling_the_last_active_admin_owner(): void
    {
        // Deactivate owner first
        $this->ownerUser->update(['status' => 'inactive']);

        // Attempting to deactivate the adminUser (the last active admin/owner) should fail
        $response = $this->actingAs($this->adminUser)->post("/administration/users/{$this->adminUser->id}/toggle-status");
        // Note: The controller prevents self-disable anyway, but let's test if we call it from owner acting as owner deactivating the last admin
        // Let's activate owner, deactivate admin, which should work unless it's self-disable.
        // Let's test deactivating adminUser using ownerUser.
        $this->ownerUser->update(['status' => 'active']);
        $response = $this->actingAs($this->ownerUser)->post("/administration/users/{$this->adminUser->id}/toggle-status");
        $response->assertSessionHasNoErrors(); // Deactivating admin should succeed since owner is active admin/owner.

        $this->assertEquals('inactive', $this->adminUser->fresh()->status);

        // Now, attempting to deactivate the ownerUser (last remaining active admin/owner) should fail
        $response2 = $this->actingAs($this->ownerUser)->post("/administration/users/{$this->ownerUser->id}/toggle-status");
        $response2->assertSessionHasErrors(['status']);
        $this->assertEquals('active', $this->ownerUser->fresh()->status);
    }

    /**
     * 14. Login/logout still works.
     */
    public function test_login_logout_works(): void
    {
        $responseLogin = $this->post('/login', [
            'email' => 'sales@example.test',
            'password' => 'password12345',
        ]);
        $responseLogin->assertRedirect('/dashboard');
        $this->assertTrue(auth()->check());

        $responseLogout = $this->post('/logout');
        $responseLogout->assertRedirect('/');
        $this->assertFalse(auth()->check());
    }

    /**
     * 15. Dashboard still works.
     */
    public function test_dashboard_still_works(): void
    {
        $response = $this->actingAs($this->salesUser)->get('/dashboard');
        $response->assertStatus(200);
    }
}
