<?php

namespace App\Services\Licensing;

use App\Enums\ErrorCode;
use App\Enums\LicenseKeyStatus;
use App\Exceptions\ApiException;
use App\Models\Admin;
use App\Models\LicenseKey;
use App\Support\ListQuery;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LicenseKeyService
{
    private const MAX_GENERATION_ATTEMPTS = 10;

    public function __construct(private readonly LicenseKeyGenerator $generator)
    {
    }

    public function paginate(ListQuery $listQuery): LengthAwarePaginator
    {
        $licenseKeys = LicenseKey::query()->with(['usedBy', 'creator']);

        if ($listQuery->hasFilter('status')) {
            match (LicenseKeyStatus::from($listQuery->filter('status'))) {
                LicenseKeyStatus::Used => $licenseKeys->used(),
                LicenseKeyStatus::Available => $licenseKeys->available(),
                LicenseKeyStatus::Expired => $licenseKeys->expiredUnused(),
            };
        }

        if ($listQuery->hasSearch()) {
            $pattern = $listQuery->searchPattern();
            $licenseKeys->where(fn (Builder $query) => $query
                ->where('key', 'like', $pattern)
                ->orWhere('note', 'like', $pattern)
                ->orWhereHas('usedBy', fn (Builder $client) => $client
                    ->where('name', 'like', $pattern)
                    ->orWhere('email', 'like', $pattern)));
        }

        return $listQuery->paginate($licenseKeys);
    }

    public function issue(Admin $admin, CarbonInterface $expiresAt, ?string $note, int $quantity): Collection
    {
        return DB::transaction(function () use ($admin, $expiresAt, $note, $quantity): Collection {
            $licenseKeys = new Collection();

            for ($index = 0; $index < $quantity; $index++) {
                $licenseKeys->push($this->createWithUniqueKey($admin, $expiresAt, $note));
            }

            return $licenseKeys->load(['usedBy', 'creator']);
        });
    }

    public function detail(LicenseKey $licenseKey): LicenseKey
    {
        return $licenseKey->load(['usedBy', 'creator']);
    }

    public function delete(LicenseKey $licenseKey): void
    {
        if ($licenseKey->is_used) {
            throw new ApiException(ErrorCode::LicenseKeyUsedCannotBeDeleted);
        }

        $licenseKey->delete();
    }

    private function createWithUniqueKey(Admin $admin, CarbonInterface $expiresAt, ?string $note): LicenseKey
    {
        for ($attempt = 0; $attempt < self::MAX_GENERATION_ATTEMPTS; $attempt++) {
            $key = $this->generator->generate();

            if (LicenseKey::query()->where('key', $key)->exists()) {
                continue;
            }

            try {
                $licenseKey = new LicenseKey(['key' => $key, 'expires_at' => $expiresAt, 'note' => $note]);
                $licenseKey->created_by = $admin->id;
                $licenseKey->save();

                return $licenseKey;
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        throw new RuntimeException('Unable to generate a unique license key.');
    }
}
