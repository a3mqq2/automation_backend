<?php

namespace App\Models;

use App\Enums\DataDeletionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['fb_user_id', 'confirmation_code', 'status', 'completed_at'])]
class DataDeletionRequest extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => DataDeletionStatus::class,
            'completed_at' => 'datetime',
        ];
    }
}
