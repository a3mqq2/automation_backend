<?php

namespace App\Models;

use App\Enums\TriggerType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'page_id', 'name', 'category', 'picture_url', 'page_access_token', 'is_connected', 'connected_at'])]
#[Hidden(['page_access_token'])]
class FacebookPage extends Model
{
    use HasFactory;

    protected $attributes = [
        'is_connected' => false,
    ];

    protected function casts(): array
    {
        return [
            'page_access_token' => 'encrypted',
            'is_connected' => 'boolean',
            'connected_at' => 'datetime',
            'comments_polled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function automationRules(): HasMany
    {
        return $this->hasMany(AutomationRule::class);
    }

    public function botFlows(): HasMany
    {
        return $this->hasMany(BotFlow::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    #[Scope]
    protected function connected(Builder $query): void
    {
        $query->where('is_connected', true);
    }

    #[Scope]
    protected function withActiveCommentRules(Builder $query): void
    {
        $query->whereHas('automationRules', fn (Builder $rules) => $rules
            ->where('is_active', true)
            ->where('trigger_type', TriggerType::Comment->value));
    }
}
