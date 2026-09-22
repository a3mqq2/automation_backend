<?php

namespace App\Models;

use App\Enums\ActivityEventType;
use App\Enums\ActivityStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['facebook_page_id', 'event_type', 'status', 'payload'])]
class ActivityLog extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'event_type' => ActivityEventType::class,
            'status' => ActivityStatus::class,
            'payload' => 'array',
        ];
    }

    public function facebookPage(): BelongsTo
    {
        return $this->belongsTo(FacebookPage::class);
    }

    #[Scope]
    protected function automatedReplies(Builder $query): void
    {
        $query->whereIn('event_type', array_map(
            fn (ActivityEventType $type) => $type->value,
            ActivityEventType::automatedReplies(),
        ))->where('status', ActivityStatus::Success->value);
    }
}
