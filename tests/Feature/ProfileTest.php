<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create([
            'nama' => 'initialuser',
            'phone_number' => '628555555555',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'nama' => 'updateduser',
                'phone_number' => '628111111111',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('updateduser', $user->nama);
        $this->assertSame('628111111111', $user->phone_number);
    }

    public function test_profile_information_allows_reusing_same_phone_number(): void
    {
        $user = User::factory()->create([
            'nama' => 'stableuser',
            'phone_number' => '628222222222',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'nama' => 'stableuser',
                'phone_number' => '628222222222',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertSame('628222222222', $user->fresh()->phone_number);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
