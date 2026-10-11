<?php

declare(strict_types=1);

namespace App\Contracts;

interface CloudStorageServiceInterface
{
    /**
     * Upload a file to the cloud storage and return its public URL.
     *
     * @param string $fileName The name of the file to store (e.g., 'reports/2026-02-02.csv')
     * @param string $content The file content
     * @return string The public URL of the uploaded file
     */
    public function upload(string $fileName, string $content): string;
}
