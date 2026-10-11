<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\CloudStorageServiceInterface;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final readonly class CloudStorageService implements CloudStorageServiceInterface
{
    private string $disk;
    private string $folder;

    public function __construct()
    {
        $this->disk = (string) config('filesystems.cloud', 's3');
        $this->folder = (string) config('settings.report_folder', 'reports');
    }

    public function upload(string $fileName, string $content): string
    {
        $path = $this->folder ? "{$this->folder}/{$fileName}" : $fileName;

        if (!Storage::disk($this->disk)->put($path, $content)) {
            throw new RuntimeException("Failed to upload file to cloud storage: {$path}");
        }

        try {
            return Storage::disk($this->disk)->temporaryUrl($path, now()->addHour());
        } catch (\Throwable) {
            // Fallback for disks that don't support signed URLs (e.g., local)
            return Storage::disk($this->disk)->url($path);
        }
    }
}
