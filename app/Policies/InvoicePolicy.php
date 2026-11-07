<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'vendor';
    }

    public function create(User $user): bool
    {
        return $user->role === 'vendor';
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->role === 'vendor' && (int) $invoice->vendor_id === (int) $user->id;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice);
    }
}
