<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'vendor';
    }

    public function create(User $user): bool
    {
        return $user->role === 'vendor';
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->role === 'vendor' && (int) $payment->vendor_id === $user->id;
    }

    public function reverse(User $user, Payment $payment): bool
    {
        return $this->view($user, $payment);
    }
}
