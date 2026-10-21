<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * @property User|null $admin
 * @property User|null $cwdOfficer
 * @property User|null $supervisor
 * @property User|null $personnel
 */
#[\AllowDynamicProperties]
abstract class TestCase extends BaseTestCase
{
    public ?User $admin = null;

    public ?User $cwdOfficer = null;

    public ?User $supervisor = null;

    public ?User $personnel = null;
}
