<?php

describe('Password toggle on /login', function () {
    it('toggles password visibility on and off', function () {
        $page = visit('/login');
        $page->assertAttribute('input[type="password"]', 'type', 'password');
        $page->click('.password-toggle-btn');
        $page->assertScript(
            'document.querySelector(".password-input-wrapper input").type',
            'text'
        );
        $page->click('.password-toggle-btn');
        $page->assertScript(
            'document.querySelector(".password-input-wrapper input").type',
            'password'
        );
    });
});

describe('Password toggle on /register', function () {
    it('toggles password visibility on and off', function () {
        $page = visit('/register');
        $page->assertPresent('.password-toggle-btn');
        $page->script("document.querySelector('.password-toggle-btn').scrollIntoView(); document.querySelector('.password-toggle-btn').click()");
        $page->assertScript(
            'document.querySelector(".password-input-wrapper input").type',
            'text'
        );
        $page->script("document.querySelector('.password-toggle-btn').click()");
        $page->assertScript(
            'document.querySelector(".password-input-wrapper input").type',
            'password'
        );
    });
});
