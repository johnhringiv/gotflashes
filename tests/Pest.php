<?php

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(Tests\TestCase::class)->in('Feature', 'Unit');
uses(Tests\TestCase::class, LazilyRefreshDatabase::class)->in('Browser');
