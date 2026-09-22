<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ClientProfileService
{
    public function profile(User $client): User
    {
        return $client
            ->load('activeLicenseKey')
            ->loadCount([
                'facebookPages as connected_pages_count' => fn (Builder $query) => $query->where('is_connected', true),
            ]);
    }
}
