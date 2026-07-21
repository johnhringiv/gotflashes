<?php

namespace Tests\Feature;

use App\Http\Middleware\SuperAdminMiddleware;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SuperAdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // An isolated route guarded only by the super_admin middleware, so we
        // exercise the middleware itself rather than /admin/settings' full
        // auth + super_admin stack (where 'auth' would short-circuit a guest).
        Route::get('/__super_admin_probe', fn () => 'ok')
            ->middleware(['web', SuperAdminMiddleware::class]);
    }

    public function test_guest_is_denied(): void
    {
        $this->get('/__super_admin_probe')->assertForbidden();
    }

    public function test_regular_user_is_denied(): void
    {
        $user = User::factory()->create(['is_admin' => false, 'is_super_admin' => false]);

        $this->actingAs($user)->get('/__super_admin_probe')->assertForbidden();
    }

    public function test_award_admin_without_super_flag_is_denied(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);

        $this->actingAs($admin)->get('/__super_admin_probe')->assertForbidden();
    }

    public function test_super_admin_is_allowed(): void
    {
        $super = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($super)->get('/__super_admin_probe')->assertOk();
    }
}
