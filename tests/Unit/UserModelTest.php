<?php

namespace Tests\Unit;

use App\Models\Flash;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_name_attribute(): void
    {
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $this->assertEquals('John Doe', $user->name);
    }

    public function test_user_has_many_flashes(): void
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $user->flashes());
    }

    public function test_user_can_have_multiple_flashes(): void
    {
        $user = User::factory()->create();

        $flash1 = Flash::factory()->forUser($user)->onDate('2025-01-01')->create();
        $flash2 = Flash::factory()->forUser($user)->onDate('2025-01-02')->create();

        $this->assertCount(2, $user->flashes);
        $this->assertTrue($user->flashes->contains($flash1));
        $this->assertTrue($user->flashes->contains($flash2));
    }

    public function test_password_is_hidden(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret')]);

        $array = $user->toArray();

        $this->assertArrayNotHasKey('password', $array);
    }

    public function test_remember_token_is_hidden(): void
    {
        $user = User::factory()->create();

        $array = $user->toArray();

        $this->assertArrayNotHasKey('remember_token', $array);
    }

    public function test_date_of_birth_is_cast_to_date(): void
    {
        $user = User::factory()->create([
            'date_of_birth' => '1990-01-01',
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $user->date_of_birth);
    }
}
