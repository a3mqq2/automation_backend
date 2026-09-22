<?php

namespace App\Services\Meta;

use App\Exceptions\MetaGraphException;
use App\Models\FacebookPage;

class MessengerProfileApi
{
    private const PROFILE_FIELDS = 'first_name,last_name,profile_pic';

    public function __construct(private readonly MetaGraphClient $graph)
    {
    }

    public function variablesFor(FacebookPage $page, string $psid): array
    {
        try {
            $profile = $this->graph->get($psid, (string) $page->page_access_token, ['fields' => self::PROFILE_FIELDS]);
        } catch (MetaGraphException $exception) {
            $exception->report();

            return [];
        }

        return array_filter([
            'first_name' => $this->text($profile, 'first_name'),
            'last_name' => $this->text($profile, 'last_name'),
            'profile_pic' => $this->text($profile, 'profile_pic'),
        ], fn (?string $value) => $value !== null);
    }

    private function text(array $profile, string $field): ?string
    {
        $value = $profile[$field] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
