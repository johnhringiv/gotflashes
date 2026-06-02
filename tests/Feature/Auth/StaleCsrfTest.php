<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StaleCsrfTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Laravel skips CSRF validation under runningUnitTests(), so a bad _token
     * on a real route won't throw. We register a route that throws the
     * exception directly to exercise the bootstrap/app.php render callback.
     */
    private function registerThrowingRoute(): void
    {
        Route::middleware('web')->get('/__csrf_test', function () {
            throw new TokenMismatchException;
        });
    }

    public function test_stale_csrf_on_standard_request_redirects_back_with_warning(): void
    {
        $this->registerThrowingRoute();

        $response = $this->get('/__csrf_test');

        $response->assertRedirect();
        $response->assertSessionHas('warning', 'Your session expired. Please try again.');
    }

    public function test_stale_csrf_on_ajax_request_returns_json_419(): void
    {
        $this->registerThrowingRoute();

        $response = $this->getJson('/__csrf_test');

        $response->assertStatus(419);
        $response->assertJson([
            'success' => false,
            'message' => 'Your session expired. Please refresh the page and try again.',
        ]);
    }

    public function test_normal_login_still_succeeds(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('logbook.index'));
    }
}
