<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Demo password shared by all demo users (documented in the README).
     */
    public const DEMO_PASSWORD = 'password';

    /**
     * One demo user per role, so every permission can be tried from Swagger.
     *
     * @var array<int, array{name: string, email: string, role: Role}>
     */
    private const DEMO_USERS = [
        ['name' => 'Admin Demo', 'email' => 'admin@restaurante.test', 'role' => Role::Admin],
        ['name' => 'Mesero Demo', 'email' => 'mesero@restaurante.test', 'role' => Role::Waiter],
        ['name' => 'Cocina Demo', 'email' => 'cocina@restaurante.test', 'role' => Role::Kitchen],
        ['name' => 'Cliente Demo', 'email' => 'cliente@restaurante.test', 'role' => Role::Customer],
    ];

    public function run(): void
    {
        foreach (self::DEMO_USERS as $demoUser) {
            $user = User::firstOrNew(['email' => $demoUser['email']]);
            $user->name = $demoUser['name'];
            $user->password = self::DEMO_PASSWORD;
            $user->role = $demoUser['role'];
            $user->save();
        }
    }
}
