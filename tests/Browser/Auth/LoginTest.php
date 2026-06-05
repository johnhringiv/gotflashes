<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('logs in with valid credentials and redirects to /logbook', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $page = visit('/login');
    $page->fill('email', $user->email)
        ->fill('password', 'password')
        ->submit()
        ->assertPathIs('/logbook');
});

it('rejects login with wrong password and stays on /login', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $page = visit('/login');
    $page->fill('email', $user->email)
        ->fill('password', 'WrongPassword!')
        ->submit()
        ->assertSee('Incorrect password.')
        ->assertPathIs('/login');
});

it('rejects login with unknown email', function () {
    $page = visit('/login');
    $page->fill('email', 'nobody@resend.dev')
        ->fill('password', 'Password123!')
        ->submit()
        ->assertSee('No account found with this email address.')
        ->assertPathIs('/login');
});

it('persists session across reloads', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user);

    $page = visit('/logbook');
    $page->assertPathIs('/logbook')
        ->assertSee('Lightning Log');

    $page2 = visit('/logbook');
    $page2->assertPathIs('/logbook')
        ->assertSee('Lightning Log');
});
