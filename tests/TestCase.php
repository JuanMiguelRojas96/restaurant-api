<?php

namespace Tests;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /**
     * Authenticate the following requests as a fresh user with the given role.
     */
    protected function actingAsRole(Role $role): User
    {
        $user = User::factory()->create(['role' => $role]);

        Sanctum::actingAs($user);

        return $user;
    }
}
