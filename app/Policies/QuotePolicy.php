<?php

namespace App\Policies;

use App\Models\Quote;
use App\Models\User;

class QuotePolicy
{
    public function view(User $user, Quote $quote): bool
    {
        return $user->isAdministrator() || ($user->hasRole('Vendedor') && $quote->user_id === $user->id);
    }

    public function update(User $user, Quote $quote): bool
    {
        return $this->view($user, $quote);
    }

    public function convert(User $user, Quote $quote): bool
    {
        return $this->view($user, $quote);
    }
}
