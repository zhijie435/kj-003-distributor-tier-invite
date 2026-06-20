<?php

namespace App\Policies;

use App\Models\InvitationCode;
use App\Models\User;

class InvitationCodePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        if (method_exists($user, 'hasPermission') && $user->hasPermission('invitation-codes.*')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'invitation-codes.view-any')
            || $this->checkPermission($user, 'invitation-codes.view');
    }

    public function view(User $user, InvitationCode $invitationCode): bool
    {
        return $this->checkPermission($user, 'invitation-codes.view')
            || $this->checkPermission($user, 'invitation-codes.view-any');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'invitation-codes.create');
    }

    public function update(User $user, InvitationCode $invitationCode): bool
    {
        return $this->checkPermission($user, 'invitation-codes.update');
    }

    public function delete(User $user, InvitationCode $invitationCode): bool
    {
        return $this->checkPermission($user, 'invitation-codes.delete');
    }

    public function toggleActive(User $user, InvitationCode $invitationCode): bool
    {
        return $this->checkPermission($user, 'invitation-codes.update')
            || $this->checkPermission($user, 'invitation-codes.toggle-active');
    }

    public function batchGenerate(User $user): bool
    {
        return $this->checkPermission($user, 'invitation-codes.create')
            || $this->checkPermission($user, 'invitation-codes.batch-generate');
    }

    public function redeem(User $user): bool
    {
        return true;
    }

    public function viewAnyByCustomerGroup(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function restore(User $user): bool
    {
        return $this->checkPermission($user, 'invitation-codes.delete');
    }

    protected function checkPermission(User $user, string $permission): bool
    {
        if (method_exists($user, 'hasPermission')) {
            return $user->hasPermission($permission);
        }

        return true;
    }
}
