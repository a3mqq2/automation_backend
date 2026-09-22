<?php

namespace App\Services\Pages;

final readonly class FacebookAccountPage
{
    private const MANAGE_TASK = 'MANAGE';

    private const REQUIRED_TASKS = ['MODERATE', 'MESSAGING'];

    public function __construct(
        public string $pageId,
        public string $name,
        public ?string $category,
        public ?string $pictureUrl,
        public ?array $tasks,
        public ?string $accessToken,
    ) {
    }

    public static function fromGraph(array $data): self
    {
        return new self(
            pageId: (string) ($data['id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            category: isset($data['category']) ? (string) $data['category'] : null,
            pictureUrl: data_get($data, 'picture.data.url'),
            tasks: isset($data['tasks']) && is_array($data['tasks']) ? array_values($data['tasks']) : null,
            accessToken: isset($data['access_token']) ? (string) $data['access_token'] : null,
        );
    }

    public function canConnect(): bool
    {
        if ($this->accessToken === null || $this->accessToken === '') {
            return false;
        }

        if ($this->tasks === null || in_array(self::MANAGE_TASK, $this->tasks, true)) {
            return true;
        }

        return array_diff(self::REQUIRED_TASKS, $this->tasks) === [];
    }
}
