<?php

namespace App\Services\Auth;

use App\Models\FacebookPage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FacebookAccountRevocationService
{
    public function revoke(string $facebookUserId): ?User
    {
        $client = User::query()->where('fb_user_id', $facebookUserId)->first();

        if ($client === null) {
            return null;
        }

        return DB::transaction(function () use ($client): User {
            $client->facebookPages()->each(fn (FacebookPage $page) => $page->forceFill([
                'is_connected' => false,
                'page_access_token' => null,
            ])->save());

            $client->tokens()->delete();

            $client->forceFill([
                'fb_access_token' => null,
                'token_expires_at' => null,
            ])->save();

            return $client;
        });
    }
}
