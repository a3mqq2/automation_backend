<?php

namespace App\Console\Commands;

use App\Models\FacebookPage;
use App\Models\User;
use App\Services\Demo\DemoSeedResult;
use App\Services\Demo\ElectronicsDemoSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('demo:electronics
    {client : The client (users.id) that owns the page}
    {--page= : Facebook page id to use instead of the first connected page}
    {--fresh : Delete the page rules, bot flows, catalog and conversations before seeding}
    {--skip-posts : Do not link the latest page posts to the offer products}')]
#[Description('Seed a complete electronics store automation (catalog, comment and message rules, published bot flows) on a client page')]
class SeedElectronicsDemoCommand extends Command
{
    public function handle(ElectronicsDemoSeeder $seeder): int
    {
        $client = User::query()->find($this->argument('client'));

        if ($client === null) {
            $this->error("Client {$this->argument('client')} does not exist.");

            return self::FAILURE;
        }

        $page = $this->targetPage($client);

        if ($page === null) {
            $this->error("Client {$client->id} has no connected Facebook page. Connect one from the Facebook pages screen first.");

            return self::FAILURE;
        }

        if ($this->option('fresh') && ! $this->confirmFreshSeed($page)) {
            return self::FAILURE;
        }

        if (! $client->hasActiveSubscription()) {
            $this->warn('This client has no active subscription, so the dashboard will ask for a license key first.');
        }

        $this->report($seeder->seed($page, (bool) $this->option('fresh'), ! $this->option('skip-posts')));

        return self::SUCCESS;
    }

    private function targetPage(User $client): ?FacebookPage
    {
        return $client->facebookPages()
            ->where('is_connected', true)
            ->when($this->option('page'), fn ($query, $pageId) => $query->where('page_id', $pageId))
            ->orderBy('id')
            ->with('user')
            ->first();
    }

    private function confirmFreshSeed(FacebookPage $page): bool
    {
        if (! $this->input->isInteractive()) {
            return true;
        }

        return $this->confirm("This deletes every rule, bot flow, catalog item and conversation on \"{$page->name}\". Continue?", true);
    }

    private function report(DemoSeedResult $result): void
    {
        $this->info("Electronics automation is ready on \"{$result->page->name}\" ({$result->page->page_id}).");
        $this->newLine();

        $this->table(['Item', 'Count'], [
            ['Categories', $result->categories],
            ['Brands', $result->brands],
            ['Products', $result->products],
            ['Comment rules', $result->commentRules],
            ['Message rules', $result->messageRules],
            ['Published bot flows', count($result->flows)],
        ]);

        $this->table(['Bot flow', 'Version', 'Nodes', 'Triggers'], array_map(fn (array $flow) => [
            $flow['name'],
            $flow['version'],
            $flow['nodes'],
            implode('، ', $flow['triggers']),
        ], $result->flows));

        if ($result->linkedPosts !== []) {
            $this->info('Comment on these posts to see replies that include the product name and price:');
            $this->table(['Post', 'Product', 'Link'], array_map(fn (array $post) => [
                Str::limit(str_replace("\n", ' ', $post['message']) ?: $post['post_id'], 40),
                $post['product'],
                $post['permalink_url'] ?? $post['post_id'],
            ], $result->linkedPosts));
        } elseif ($result->postLinkingError !== null) {
            $this->warn("Could not read the page posts ({$result->postLinkingError}). The price comment rule uses a general reply instead.");
        } elseif (! $this->option('skip-posts')) {
            $this->warn('The page has no posts to link, so the price comment rule uses a general reply.');
        }
    }
}
