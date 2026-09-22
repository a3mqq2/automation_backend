<?php

namespace App\Services\Automation\Engine;

use App\Enums\ActivityEventType;
use App\Enums\TriggerType;
use App\Models\FacebookPage;
use App\Services\Catalog\ProductContext;
use App\Services\Meta\CommentReplyApi;
use App\Services\Meta\MessengerSendApi;

class CommentAutomationService
{
    public function __construct(
        private readonly EventDeduplicator $deduplicator,
        private readonly RuleMatcher $rules,
        private readonly CommentReplyApi $comments,
        private readonly MessengerSendApi $messenger,
        private readonly AutomationActionExecutor $executor,
        private readonly ProductContext $products,
        private readonly TextInterpolator $interpolator,
    ) {
    }

    public function handle(FacebookPage $page, array $comment): void
    {
        $commentId = (string) ($comment['comment_id'] ?? '');
        $senderId = (string) data_get($comment, 'from.id', '');

        if ($commentId === '' || $senderId === $page->page_id) {
            return;
        }

        if (! $this->deduplicator->isFirstOccurrence("comment:{$commentId}")) {
            return;
        }

        $text = is_string($comment['message'] ?? null) ? $comment['message'] : '';
        $rule = $this->rules->firstMatch($page, TriggerType::Comment, $text);

        if ($rule === null) {
            return;
        }

        $postId = isset($comment['post_id']) ? (string) $comment['post_id'] : null;
        $product = $this->products->productForPost($page, $postId);
        $placeholders = $product === null ? [] : $this->products->forProduct($product);

        if ($product === null && $this->products->isRequiredBy($rule->response_text, $rule->private_reply_text)) {
            return;
        }

        $responseText = $this->interpolator->render((string) $rule->response_text, $placeholders);
        $privateReplyText = $this->interpolator->render((string) $rule->private_reply_text, $placeholders);

        $context = [
            'rule_id' => $rule->id,
            'rule_name' => $rule->name,
            'post_id' => $comment['post_id'] ?? null,
            'comment_id' => $commentId,
            'sender_id' => $senderId,
            'sender_name' => data_get($comment, 'from.name'),
            'incoming_text' => $text,
            'product_id' => $product?->id,
        ];

        if (filled($responseText)) {
            $this->executor->run(
                $page,
                ActivityEventType::CommentReply,
                $context + ['response_text' => $responseText],
                fn () => $this->comments->reply($page, $commentId, $responseText),
            );
        }

        if (filled($privateReplyText)) {
            $this->executor->run(
                $page,
                ActivityEventType::PrivateReply,
                $context + ['response_text' => $privateReplyText],
                fn () => $this->messenger->sendPrivateReply($page, $commentId, $privateReplyText),
            );
        }
    }
}
