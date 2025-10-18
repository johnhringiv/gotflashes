<?php

namespace Tests\Unit;

use App\Models\Flash;
use App\Models\User;
use App\Policies\FlashPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_their_own_flash(): void
    {
        $user = User::factory()->create();
        $flash = Flash::factory()->forUser($user)->create();

        $policy = new FlashPolicy;

        $this->assertTrue($policy->update($user, $flash));
    }

    public function test_user_cannot_update_others_flash(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $flash = Flash::factory()->forUser($user1)->create();

        $policy = new FlashPolicy;

        $this->assertFalse($policy->update($user2, $flash));
    }

    public function test_user_can_delete_their_own_flash(): void
    {
        $user = User::factory()->create();
        $flash = Flash::factory()->forUser($user)->create();

        $policy = new FlashPolicy;

        $this->assertTrue($policy->delete($user, $flash));
    }

    public function test_user_cannot_delete_others_flash(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $flash = Flash::factory()->forUser($user1)->create();

        $policy = new FlashPolicy;

        $this->assertFalse($policy->delete($user2, $flash));
    }
}
