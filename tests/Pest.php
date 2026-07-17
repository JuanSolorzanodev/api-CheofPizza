<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Feature tests
|--------------------------------------------------------------------------
*/

pest()
    ->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Unit tests
|--------------------------------------------------------------------------
*/

pest()
    ->extend(TestCase::class)
    ->in('Unit');
