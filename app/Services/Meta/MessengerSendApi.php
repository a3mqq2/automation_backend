<?php

namespace App\Services\Meta;

use App\Models\FacebookPage;

class MessengerSendApi
{
    public function __construct(private readonly MetaGraphClient $graph)
    {
    }

    public function sendText(FacebookPage $page, string $psid, string $text, array $quickReplies = []): array
    {
        $message = ['text' => $text];

        if ($quickReplies !== []) {
            $message['quick_replies'] = $quickReplies;
        }

        return $this->graph->post("{$page->page_id}/messages", (string) $page->page_access_token, [
            'recipient' => ['id' => $psid],
            'messaging_type' => 'RESPONSE',
            'message' => $message,
        ]);
    }

    public function sendImage(FacebookPage $page, string $psid, string $imageUrl, array $quickReplies = []): array
    {
        $message = ['attachment' => ['type' => 'image', 'payload' => ['url' => $imageUrl, 'is_reusable' => false]]];

        if ($quickReplies !== []) {
            $message['quick_replies'] = $quickReplies;
        }

        return $this->graph->post("{$page->page_id}/messages", (string) $page->page_access_token, [
            'recipient' => ['id' => $psid],
            'messaging_type' => 'RESPONSE',
            'message' => $message,
        ]);
    }

    public function sendButtonTemplate(FacebookPage $page, string $psid, string $text, array $buttons): array
    {
        return $this->graph->post("{$page->page_id}/messages", (string) $page->page_access_token, [
            'recipient' => ['id' => $psid],
            'messaging_type' => 'RESPONSE',
            'message' => [
                'attachment' => [
                    'type' => 'template',
                    'payload' => ['template_type' => 'button', 'text' => $text, 'buttons' => $buttons],
                ],
            ],
        ]);
    }

    public function sendCards(FacebookPage $page, string $psid, array $elements, array $quickReplies = []): array
    {
        $message = [
            'attachment' => [
                'type' => 'template',
                'payload' => [
                    'template_type' => 'generic',
                    'image_aspect_ratio' => 'square',
                    'elements' => $elements,
                ],
            ],
        ];

        if ($quickReplies !== []) {
            $message['quick_replies'] = $quickReplies;
        }

        return $this->graph->post("{$page->page_id}/messages", (string) $page->page_access_token, [
            'recipient' => ['id' => $psid],
            'messaging_type' => 'RESPONSE',
            'message' => $message,
        ]);
    }

    public function sendPrivateReply(FacebookPage $page, string $commentId, string $text): array
    {
        return $this->graph->post("{$page->page_id}/messages", (string) $page->page_access_token, [
            'recipient' => ['comment_id' => $commentId],
            'message' => ['text' => $text],
        ]);
    }
}
