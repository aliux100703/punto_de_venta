<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function view(User $user, Sale $sale): bool
    {
        return $user->isAdministrator() || ($user->hasRole('Vendedor') && $sale->seller_id === $user->id);
    }

    public function adjust(User $user, Sale $sale): bool
    {
        return $user->isAdministrator();
    }
}
