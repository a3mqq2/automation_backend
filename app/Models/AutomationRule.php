<?php

namespace App\Models;

use App\Enums\MatchType;
use App\Enums\TriggerType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['facebook_page_id', 'name', 'trigger_type', 'match_type', 'keywords', 'response_text', 'private_reply_text', 'is_active'])]
class AutomationRule extends Model
{
    use HasFactory;

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'trigger_type' => TriggerType::class,
            'match_type' => MatchType::class,
            'keywords' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function facebookPage(): BelongsTo
    {
        return $this->belongsTo(FacebookPage::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function forTrigger(Builder $query, TriggerType $triggerType): void
    {
        $query->where('trigger_type', $triggerType->value);
    }
}
