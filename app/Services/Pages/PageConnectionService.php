<?php

namespace App\Services\Pages;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\FacebookPage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PageConnectionService
{
    public function __construct(
        private readonly FacebookPageDirectory $directory,
        private readonly PageWebhookSubscriber $webhookSubscriber,
        private readonly ConnectedPageQueryService $connectedPages,
    ) {
    }

    public function available(User $user): array
    {
        $accountPages = $this->directory->pagesOf($user);
        $storedPages = FacebookPage::query()
            ->whereIn('page_id', array_map(fn (FacebookAccountPage $page) => $page->pageId, $accountPages))
            ->get()
            ->groupBy('page_id');

        $availablePages = array_map(
            fn (FacebookAccountPage $page) => $this->describe($user, $page, $storedPages->get($page->pageId, new Collection())),
            $accountPages,
        );

        usort($availablePages, fn (AvailablePage $first, AvailablePage $second) => strcasecmp($first->page->name, $second->page->name));

        return $availablePages;
    }

    public function connect(User $user, string $pageId): FacebookPage
    {
        $accountPage = $this->directory->findPage($user, $pageId);

        if (! $accountPage->canConnect()) {
            throw new ApiException(ErrorCode::PageNotAvailable);
        }

        $this->ensureNotConnectedByAnotherAccount($user, $pageId);
        $this->webhookSubscriber->subscribe($pageId, $accountPage->accessToken);

        $page = DB::transaction(function () use ($user, $accountPage): FacebookPage {
            $this->ensureNotConnectedByAnotherAccount($user, $accountPage->pageId, lock: true);

            return FacebookPage::query()->updateOrCreate(
                ['user_id' => $user->id, 'page_id' => $accountPage->pageId],
                [
                    'name' => $accountPage->name,
                    'category' => $accountPage->category,
                    'picture_url' => $accountPage->pictureUrl,
                    'page_access_token' => $accountPage->accessToken,
                    'is_connected' => true,
                    'connected_at' => now(),
                ],
            );
        });

        return $this->connectedPages->withCounts($page);
    }

    public function disconnect(User $user, string $pageId): void
    {
        $page = $user->facebookPages()->where('page_id', $pageId)->where('is_connected', true)->first();

        if ($page === null) {
            throw new ApiException(ErrorCode::PageNotConnected);
        }

        $this->webhookSubscriber->unsubscribe($page->page_id, $page->page_access_token);

        $page->forceFill([
            'is_connected' => false,
            'page_access_token' => null,
        ])->save();
    }

    private function describe(User $user, FacebookAccountPage $accountPage, Collection $storedRows): AvailablePage
    {
        $ownRow = $storedRows->firstWhere('user_id', $user->id);

        if ($ownRow !== null && $ownRow->is_connected) {
            $this->refresh($ownRow, $accountPage);
        }

        return new AvailablePage(
            page: $accountPage,
            facebookPageId: $ownRow?->id,
            isConnected: (bool) $ownRow?->is_connected,
            connectedByAnotherAccount: $storedRows->contains(
                fn (FacebookPage $row) => $row->user_id !== $user->id && $row->is_connected,
            ),
        );
    }

    private function refresh(FacebookPage $page, FacebookAccountPage $accountPage): void
    {
        $page->fill([
            'name' => $accountPage->name,
            'category' => $accountPage->category,
            'picture_url' => $accountPage->pictureUrl,
        ]);

        if ($accountPage->accessToken !== null && $accountPage->accessToken !== '') {
            $page->page_access_token = $accountPage->accessToken;
        }

        if ($page->isDirty()) {
            $page->save();
        }
    }

    private function ensureNotConnectedByAnotherAccount(User $user, string $pageId, bool $lock = false): void
    {
        $query = FacebookPage::query()
            ->where('page_id', $pageId)
            ->where('user_id', '!=', $user->id)
            ->where('is_connected', true);

        if ($lock) {
            $query->lockForUpdate();
        }

        if ($query->exists()) {
            throw new ApiException(ErrorCode::PageConnectedByAnotherAccount);
        }
    }
}
