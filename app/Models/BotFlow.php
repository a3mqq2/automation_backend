<?php

namespace App\Models;

use App\Support\BotFlows\FlowDefinition;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['facebook_page_id', 'name', 'flow_json', 'is_active'])]
class BotFlow extends Model
{
    use HasFactory;

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'flow_json' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function facebookPage(): BelongsTo
    {
        return $this->belongsTo(FacebookPage::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(BotFlowVersion::class)->orderByDesc('version');
    }

    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(BotFlowVersion::class, 'published_version_id');
    }

    public function draftDefinition(): FlowDefinition
    {
        return FlowDefinition::fromArray((array) $this->flow_json);
    }

    public function isPublished(): bool
    {
        return $this->published_version_id !== null;
    }

    public function hasUnpublishedChanges(): bool
    {
        $published = $this->publishedVersion;

        if ($published === null) {
            return true;
        }

        return $this->draftDefinition()->toArray() !== FlowDefinition::fromArray($published->definition)->toArray();
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function runnable(Builder $query): void
    {
        $query->where('is_active', true)->whereNotNull('published_version_id');
    }
}
