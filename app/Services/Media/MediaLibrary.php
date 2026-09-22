<?php

namespace App\Services\Media;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MediaLibrary
{
    public function store(User $client, UploadedFile $file): array
    {
        $mimeType = $file->getMimeType();
        $size = $file->getSize();
        $path = $file->store($this->directoryFor($client), ['disk' => $this->diskName()]);

        return [
            'path' => $path,
            'url' => $this->disk()->url($path),
            'mime_type' => $mimeType,
            'size' => $size,
        ];
    }

    public function delete(User $client, string $path): void
    {
        if (! str_starts_with($path, $this->directoryFor($client).'/') || ! $this->disk()->exists($path)) {
            throw new ApiException(ErrorCode::ResourceNotFound);
        }

        $this->disk()->delete($path);
    }

    private function directoryFor(User $client): string
    {
        return trim((string) config('meta.media.directory'), '/').'/'.$client->id;
    }

    private function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    private function diskName(): string
    {
        return (string) config('meta.media.disk', 'public');
    }
}
