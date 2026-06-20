<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationCodeGuestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_invitation_codes_index(): void
    {
        $this->getJson('/api/invitation-codes')->assertStatus(401);
    }

    public function test_guest_cannot_create_invitation_code(): void
    {
        $this->postJson('/api/invitation-codes', [])->assertStatus(401);
    }

    public function test_guest_cannot_redeem_invitation_code(): void
    {
        $this->postJson('/api/invitation-codes/redeem', [
            'code' => 'TEST1234',
        ])->assertStatus(401);
    }

    public function test_guest_cannot_validate_invitation_code(): void
    {
        $this->postJson('/api/invitation-codes/validate', [
            'code' => 'TEST1234',
        ])->assertStatus(401);
    }

    public function test_guest_cannot_batch_generate(): void
    {
        $this->postJson('/api/invitation-codes/batch-generate', [])->assertStatus(401);
    }

    public function test_guest_cannot_view_invitation_code_detail(): void
    {
        $this->getJson('/api/invitation-codes/1')->assertStatus(401);
    }

    public function test_guest_cannot_update_invitation_code(): void
    {
        $this->putJson('/api/invitation-codes/1', [])->assertStatus(401);
    }

    public function test_guest_cannot_delete_invitation_code(): void
    {
        $this->deleteJson('/api/invitation-codes/1')->assertStatus(401);
    }

    public function test_guest_cannot_toggle_active(): void
    {
        $this->patchJson('/api/invitation-codes/1/toggle-active')->assertStatus(401);
    }

    public function test_guest_cannot_restore_invitation_code(): void
    {
        $this->postJson('/api/invitation-codes/1/restore')->assertStatus(401);
    }

    public function test_guest_cannot_view_customer_group_invitation_codes(): void
    {
        $this->getJson('/api/customer-groups/1/invitation-codes')->assertStatus(401);
    }
}
