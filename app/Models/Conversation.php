<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['facebook_page_id', 'psid', 'bot_flow_id', 'bot_flow_version_id', 'current_node_id', 'awaiting', 'variables', 'last_message_at'])]
class Conversation extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'awaiting' => 'array',
            'variables' => 'array',
            'last_message_at' => 'datetime',
            'automation_paused_until' => 'datetime',
        ];
    }

    public function facebookPage(): BelongsTo
    {
        return $this->belongsTo(FacebookPage::class);
    }

    public function botFlow(): BelongsTo
    {
        return $this->belongsTo(BotFlow::class);
    }

    public function botFlowVersion(): BelongsTo
    {
        return $this->belongsTo(BotFlowVersion::class);
    }

    public function isInFlow(): bool
    {
        return $this->bot_flow_id !== null && $this->awaiting !== null;
    }

    public function isAutomationPaused(): bool
    {
        return $this->automation_paused_until !== null && $this->automation_paused_until->isFuture();
    }

    public function awaitingKind(): ?string
    {
        return $this->awaiting['kind'] ?? null;
    }
}
