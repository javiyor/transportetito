<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_their_username(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'login' => $user->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_authenticate_using_a_unique_email(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'login' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_duplicate_emails_do_not_select_an_arbitrary_user_for_login(): void
    {
        $email = 'shared@example.com';
        User::factory()->create(['email' => $email, 'username' => 'shared-one']);
        User::factory()->create(['email' => $email, 'username' => 'shared-two']);

        $this->post('/login', [
            'login' => $email,
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_duplicate_emails_cannot_be_used_for_password_recovery(): void
    {
        $email = 'shared@example.com';
        User::factory()->create(['email' => $email]);
        User::factory()->create(['email' => $email]);

        $this->assertSame(Password::INVALID_USER, Password::sendResetLink(['email' => $email]));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'login' => $user->username,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }
}
