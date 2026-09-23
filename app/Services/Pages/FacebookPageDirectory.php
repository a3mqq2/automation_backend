<?php

namespace App\Services\Pages;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\Meta\MetaGraphClient;

class FacebookPageDirectory
{
    private const PAGE_FIELDS = 'id,name,category,picture{url},tasks,access_token';

    public function __construct(private readonly MetaGraphClient $graph)
    {
    }

    public function pagesOf(User $user): array
    {
        if (! $user->hasLinkedFacebook()) {
            throw new ApiException(ErrorCode::FacebookNotLinked);
        }

        if (! $user->hasValidFacebookToken()) {
            throw new ApiException(ErrorCode::FacebookTokenExpired);
        }

        $pages = array_map(
            fn (array $page) => FacebookAccountPage::fromGraph($page),
            $this->graph->collect('me/accounts', $user->fb_access_token, [
                'fields' => self::PAGE_FIELDS,
                'limit' => 100,
            ]),
        );

        return array_values(array_filter($pages, fn (FacebookAccountPage $page) => $page->pageId !== ''));
    }

    public function findPage(User $user, string $pageId): FacebookAccountPage
    {
        foreach ($this->pagesOf($user) as $page) {
            if ($page->pageId === $pageId) {
                return $page;
            }
        }

        throw new ApiException(ErrorCode::PageNotAvailable);
    }
}
