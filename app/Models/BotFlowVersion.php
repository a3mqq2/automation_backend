<?php

namespace App\Models;

use App\Support\BotFlows\FlowDefinition;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['bot_flow_id', 'version', 'definition', 'published_at'])]
class BotFlowVersion extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function botFlow(): BelongsTo
    {
        return $this->belongsTo(BotFlow::class);
    }

    public function definition(): FlowDefinition
    {
        return FlowDefinition::fromArray($this->definition);
    }
}
