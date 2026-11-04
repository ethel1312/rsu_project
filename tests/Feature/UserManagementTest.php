<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\UserPermissions;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => str_repeat('a', 32)]);
        $this->withoutVite();
    }

    private function photo(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jXioAAAAASUVORK5CYII='));
    }

    private function admin(): User
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);
        return $admin;
    }

    private function profile(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Carlos', 'last_name' => 'Díaz Vidarte', 'dni' => '42331348',
            'birth_date' => '1990-02-10', 'email' => 'carlos@example.test', 'phone' => '999123456',
            'license' => 'Q12345678', 'user_type' => 'Operador', 'address' => 'Chiclayo',
            'is_active' => 1, 'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ], $overrides);
    }

    public function test_guests_and_users_without_permission_cannot_manage_users(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect('/login');
        $this->actingAs(User::factory()->create());
        $this->getJson(route('admin.users.index'))->assertForbidden();
        $this->postJson(route('admin.users.store'), $this->profile())->assertForbidden();
    }

    public function test_admin_can_render_users_create_edit_and_permission_pages(): void
    {
        $this->admin();
        $user = User::factory()->create();
        $this->get(route('admin.users.index'))->assertOk()->assertSee('Lista de Usuarios');
        $this->get(route('admin.users.create'))->assertOk()->assertSee('Fecha de nacimiento');
        $this->get(route('admin.users.edit', $user))->assertOk()->assertSee($user->email);
        $this->get(route('admin.users.permissions', $user))->assertOk()
            ->assertSee('Seleccionar todos')->assertSee('Módulo aún no disponible');
    }

    public function test_creation_saves_profile_and_hashes_password_without_accepting_privilege_injection(): void
    {
        $this->admin();
        $this->postJson(route('admin.users.store'), $this->profile([
            'is_admin' => true, 'permissions' => ['users.manage'], 'user_type' => 'Administrador',
        ]))->assertCreated();
        $user = User::where('email', 'carlos@example.test')->sole();
        $this->assertTrue(Hash::check('Password123!', $user->password));
        $this->assertFalse($user->is_admin);
        $this->assertEmpty($user->permissions);
        $this->assertSame('42331348', $user->dni);
        $this->assertSame('Díaz Vidarte', $user->last_name);
        $this->assertFalse($user->hasPermission('users.manage'));
    }

    public function test_validations_reject_duplicates_invalid_dni_date_and_password(): void
    {
        $this->admin();
        $this->postJson(route('admin.users.store'), $this->profile())->assertCreated();
        $this->postJson(route('admin.users.store'), $this->profile())->assertUnprocessable()
            ->assertJsonValidationErrors(['dni', 'email']);
        $this->postJson(route('admin.users.store'), $this->profile([
            'dni' => 'ABC', 'birth_date' => now()->addDay()->toDateString(), 'password_confirmation' => 'different',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['dni', 'birth_date', 'password']);
    }

    public function test_edit_can_keep_password_and_cannot_elevate_privileges(): void
    {
        $this->admin();
        $user = User::factory()->create();
        $hash = $user->password;
        $this->putJson(route('admin.users.update', $user), $this->profile([
            'password' => '', 'password_confirmation' => '', 'is_admin' => true, 'permissions' => ['users.manage'],
        ]))->assertOk();
        $user->refresh();
        $this->assertSame($hash, $user->password);
        $this->assertFalse($user->is_admin);
        $this->assertEmpty($user->permissions);
    }

    public function test_permissions_grant_and_revoke_real_route_access(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $this->putJson(route('admin.users.permissions.update', $user), ['permissions' => ['shifts.view']])->assertOk();
        $this->actingAs($user->fresh());
        $this->get(route('admin.programming.shifts.index'))->assertOk();
        $this->postJson(route('admin.programming.shifts.store'), ['name' => 'Forbidden'])->assertForbidden();
        $this->getJson(route('admin.colors.index'))->assertForbidden();
        $this->actingAs($admin);
        $this->putJson(route('admin.users.permissions.update', $user), [])->assertOk();
        $this->actingAs($user->fresh());
        $this->getJson(route('admin.programming.shifts.index'))->assertForbidden();
    }

    public function test_unknown_or_unavailable_permissions_are_rejected(): void
    {
        $this->admin();
        $user = User::factory()->create();
        $this->putJson(route('admin.users.permissions.update', $user), ['permissions' => ['super-admin', 'ubigeo.edit']])
            ->assertUnprocessable()->assertJsonValidationErrors(['permissions.0', 'permissions.1']);
    }

    public function test_user_edit_permission_does_not_grant_permission_management_or_access_to_principal_admin(): void
    {
        $admin = $this->admin();
        $editor = User::factory()->create(['permissions' => ['users.edit']]);
        $other = User::factory()->create();
        $this->actingAs($editor);
        $this->getJson(route('admin.users.permissions', $other))->assertForbidden();
        $this->putJson(route('admin.users.permissions.update', $other), ['permissions' => ['users.manage']])->assertForbidden();
        $this->putJson(route('admin.users.update', $admin), $this->profile())->assertForbidden();
    }

    public function test_inactive_users_cannot_login_or_keep_an_existing_session(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($user);
        $this->getJson(route('admin.home'))->assertForbidden();
        $this->assertGuest();
    }

    public function test_self_deletion_and_self_deactivation_are_prevented(): void
    {
        $admin = $this->admin();
        $this->deleteJson(route('admin.users.destroy', $admin))->assertStatus(409);
        $this->putJson(route('admin.users.update', $admin), $this->profile(['is_active' => 0]))->assertStatus(409);
        $this->assertTrue($admin->fresh()->is_active);
        $this->putJson(route('admin.users.permissions.update', $admin), [])->assertUnprocessable();
    }

    public function test_profile_delete_cannot_bypass_principal_admin_protection(): void
    {
        $user = User::factory()->administrator()->create();
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Actions\Jetstream\DeleteUser::class)->delete($user);
    }

    public function test_another_admin_can_delete_user_and_revoke_tokens_and_sessions(): void
    {
        $this->admin();
        $user = User::factory()->create();
        $user->createToken('test');
        \Illuminate\Support\Facades\DB::table('sessions')->insert([
            'id' => 'test-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time(),
        ]);
        $this->deleteJson(route('admin.users.destroy', $user))->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    }

    public function test_profile_photo_upload_replacement_and_removal(): void
    {
        Storage::fake('public');
        $this->admin();
        $this->postJson(route('admin.users.store'), $this->profile([
            'photo' => $this->photo('avatar.png'),
        ]))->assertCreated();
        $user = User::where('email', 'carlos@example.test')->sole();
        $path = $user->profile_photo_path;
        Storage::disk('public')->assertExists($path);
        $this->putJson(route('admin.users.update', $user), $this->profile([
            'photo' => $this->photo('replacement.png'),
        ]))->assertOk();
        Storage::disk('public')->assertMissing($path);
        $path = $user->fresh()->profile_photo_path;
        Storage::disk('public')->assertExists($path);
        $this->putJson(route('admin.users.update', $user), $this->profile(['remove_photo' => 1]))->assertOk();
        Storage::disk('public')->assertMissing($path);
        $this->assertNull($user->fresh()->profile_photo_path);
    }

    public function test_listing_does_not_expose_credentials_and_escapes_user_input(): void
    {
        $this->admin();
        User::factory()->create(['name' => '<script>alert(1)</script>']);
        $response = $this->getJson(route('admin.users.index'), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
        foreach ($response->json('data') as $row) {
            $this->assertArrayNotHasKey('password', $row);
            $this->assertArrayNotHasKey('two_factor_secret', $row);
            $this->assertStringNotContainsString('<script>', $row['name']);
        }
    }

    public function test_every_admin_route_has_a_known_permission_mapping(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (!str_starts_with($route->uri(), 'admin/') || $route->getName() === 'admin.home') continue;
            $permissions = UserPermissions::forRoute((string) $route->getName());
            $this->assertNotEmpty($permissions, $route->uri());
            $this->assertNotEmpty(array_intersect($permissions, UserPermissions::keys()), (string) $route->getName());
        }
    }

    public function test_home_routes_user_to_allowed_module_and_menu_hides_other_modules(): void
    {
        $user = User::factory()->create(['permissions' => ['shifts.view']]);
        $this->actingAs($user);
        $this->get(route('admin.home'))->assertRedirect(route('admin.programming.shifts.index'));
        $this->get(route('admin.programming.shifts.index'))->assertOk()
            ->assertDontSee('href="'.route('admin.users.index').'"', false);
        $user->forceFill(['permissions' => []])->save();
        $this->get(route('admin.home'))->assertOk()->assertSee('aún no tiene permisos');
    }

    public function test_public_registration_never_accepts_administrator_privileges(): void
    {
        $this->post('/register', [
            'name' => 'Público', 'email' => 'public@example.test', 'password' => 'password123',
            'password_confirmation' => 'password123', 'terms' => true,
            'is_admin' => true, 'permissions' => ['users.manage'],
        ])->assertRedirect();
        $user = User::where('email', 'public@example.test')->sole();
        $this->assertFalse($user->is_admin);
        $this->assertEmpty($user->permissions);
    }

    public function test_setup_command_explicitly_promotes_existing_account(): void
    {
        $user = User::factory()->create();
        $this->artisan('users:make-admin', ['email' => $user->email])->assertSuccessful();
        $this->assertTrue($user->fresh()->is_admin);
    }

    public function test_migration_promotes_only_oldest_existing_user_and_preserves_accounts(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $password = $first->password;
        $migration = require database_path('migrations/2026_11_01_000000_add_user_management_fields.php');
        $migration->down();
        $migration->up();
        $this->assertTrue($first->fresh()->is_admin);
        $this->assertFalse($second->fresh()->is_admin);
        $this->assertSame($password, $first->fresh()->password);
        $this->assertSame(2, User::count());
    }

    public function test_newly_granted_permission_allows_only_its_specific_action(): void
    {
        $user = User::factory()->create(['permissions' => ['shifts.create']]);
        $this->actingAs($user);
        $this->postJson(route('admin.programming.shifts.store'), [
            'name' => 'Mañana', 'start_time' => '08:00', 'end_time' => '16:00',
        ])->assertOk();
        $shift = \App\Models\Programming\Shift::sole();
        $this->deleteJson(route('admin.programming.shifts.destroy', $shift))->assertForbidden();
        $this->putJson(route('admin.programming.shifts.update', $shift), [
            'name' => 'Otro', 'start_time' => '08:00', 'end_time' => '16:00',
        ])->assertForbidden();
    }
}
