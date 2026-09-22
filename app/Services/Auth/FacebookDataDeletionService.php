<?php

namespace App\Services\Auth;

use App\Enums\DataDeletionStatus;
use App\Models\DataDeletionRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FacebookDataDeletionService
{
    public function delete(string $facebookUserId): DataDeletionRequest
    {
        return DB::transaction(function () use ($facebookUserId): DataDeletionRequest {
            $client = User::query()->where('fb_user_id', $facebookUserId)->first();

            if ($client !== null) {
                $client->tokens()->delete();
                $client->delete();
            }

            return DataDeletionRequest::query()->create([
                'fb_user_id' => $facebookUserId,
                'confirmation_code' => Str::lower(Str::random(32)),
                'status' => DataDeletionStatus::Completed,
                'completed_at' => now(),
            ]);
        });
    }

    public function findByConfirmationCode(string $confirmationCode): ?DataDeletionRequest
    {
        return DataDeletionRequest::query()->where('confirmation_code', $confirmationCode)->first();
    }
}
