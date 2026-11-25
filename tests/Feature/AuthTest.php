<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function should_register_a_customer_ignoring_the_requested_role(): void
    {
        $payload = [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'admin',
        ];

        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'ana@example.com')
            ->assertJsonPath('data.role', Role::Customer->value)
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'role'], 'token', 'token_type'])
            ->assertJsonMissingPath('data.password');
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: string}>
     */
    public static function invalidRegistrations(): array
    {
        return [
            'duplicate email' => [['email' => 'taken@example.com'], 'email'],
            'password without numbers' => [['password' => 'onlyletters', 'password_confirmation' => 'onlyletters'], 'password'],
            'password not confirmed' => [['password_confirmation' => 'different1'], 'password'],
            'missing name' => [['name' => ''], 'name'],
        ];
    }

    /**
     * @param  array<string, string>  $override
     */
    #[Test]
    #[DataProvider('invalidRegistrations')]
    public function should_reject_invalid_registrations(array $override, string $invalidField): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $payload = array_merge([
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ], $override);

        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertUnprocessable()->assertJsonValidationErrors($invalidField);
    }

    #[Test]
    public function should_return_a_token_when_credentials_are_valid(): void
    {
        User::factory()->create(['email' => 'ana@example.com', 'password' => 'secret123']);

        $response = $this->postJson('/api/auth/login', [
            'email' => '  ANA@example.com ',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.email', 'ana@example.com')
            ->assertJsonPath('token_type', 'Bearer');
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    #[Test]
    public function should_reject_wrong_credentials_with_the_same_message_for_unknown_emails(): void
    {
        User::factory()->create(['email' => 'ana@example.com', 'password' => 'secret123']);

        $wrongPassword = $this->postJson('/api/auth/login', ['email' => 'ana@example.com', 'password' => 'nope']);
        $unknownEmail = $this->postJson('/api/auth/login', ['email' => 'ghost@example.com', 'password' => 'nope']);

        $wrongPassword->assertUnauthorized();
        $unknownEmail->assertUnauthorized();
        $this->assertSame($wrongPassword->json('message'), $unknownEmail->json('message'));
    }

    #[Test]
    public function should_return_the_authenticated_user_profile(): void
    {
        $waiter = $this->actingAsRole(Role::Waiter);

        $response = $this->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.id', $waiter->id)
            ->assertJsonPath('data.role', Role::Waiter->value);
    }

    #[Test]
    public function should_revoke_only_the_token_used_to_log_out(): void
    {
        $user = User::factory()->create();
        $phoneToken = $user->createToken('phone')->plainTextToken;
        $laptopToken = $user->createToken('laptop')->plainTextToken;

        $logout = $this->withToken($phoneToken)->postJson('/api/auth/logout');
        $this->app['auth']->forgetGuards();
        $phoneAfterLogout = $this->withToken($phoneToken)->getJson('/api/auth/me');
        $this->app['auth']->forgetGuards();
        $laptopAfterLogout = $this->withToken($laptopToken)->getJson('/api/auth/me');

        $logout->assertNoContent();
        $phoneAfterLogout->assertUnauthorized();
        $laptopAfterLogout->assertOk();
    }

    #[Test]
    public function should_answer_json_401_even_when_the_client_does_not_ask_for_json(): void
    {
        $response = $this->get('/api/auth/me');

        $response->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
    }
}
