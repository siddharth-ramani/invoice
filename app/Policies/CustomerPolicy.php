<?php

namespace App\Policies;

use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'vendor'], true);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, User $customer): bool
    {
        return ($user->role === 'admin' && in_array($customer->role, ['vendor', 'customer'], true)) || ($user->role === 'vendor' && $customer->role === 'customer' && (int) $customer->created_by === (int) $user->id);
    }

    public function update(User $user, User $customer): bool
    {
        return $this->view($user, $customer);
    }

    public function delete(User $user, User $customer): bool
    {
        return $this->view($user, $customer);
    }
}
