<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TenantResourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'vendor';
    }

    public function create(User $user): bool
    {
        return $user->role === 'vendor';
    }

    public function view(User $user, Model $resource): bool
    {
        return $user->role === 'vendor' && (int) $resource->vendor_id === $user->id;
    }

    public function update(User $user, Model $resource): bool
    {
        return $this->view($user, $resource);
    }
}
