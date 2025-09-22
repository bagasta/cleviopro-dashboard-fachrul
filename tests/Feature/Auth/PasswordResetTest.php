<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const RESET_URL = 'https://wa.me/6283890930647?text=Want%20to%20change%20password.';

    public function test_reset_password_link_screen_redirects_to_whatsapp(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertRedirect(self::RESET_URL);
    }

    public function test_reset_password_link_request_redirects_to_whatsapp(): void
    {
        Notification::fake();

        $response = $this->post('/forgot-password', []);

        $response->assertRedirect(self::RESET_URL);
        Notification::assertNothingSent();
    }
}
