<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;

trait SecureFileUpload
{
    /**
     * Generate a secure filename using UUID
     */
    protected function generateSecureFilename(UploadedFile $file): string
    {
        // Never trust the name the browser sent: a "photo.html" would be served as a web page.
        $extension = strtolower((string) ($file->extension() ?: $file->getClientOriginalExtension()));
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $extension = 'bin';
        }
        $uuid = Uuid::uuid4()->toString();

        return $uuid.'.'.$extension;
    }

    /**
     * Validate and store file securely
     */
    protected function storeFileSecurely(
        UploadedFile $file,
        string $directory,
        array $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'],
        int $maxSize = 2048
    ): ?string {
        // Validate file size (in KB)
        if ($file->getSize() > ($maxSize * 1024)) {
            return null;
        }

        // Validate MIME type
        if (! in_array($file->getMimeType(), $allowedMimes)) {
            return null;
        }

        // Generate secure filename
        $filename = $this->generateSecureFilename($file);

        // Store file, then the smaller WebP copies the storefront prefers
        $path = $file->storeAs($directory, $filename, 'public');
        if ($path) {
            \App\Support\ImageVariants::make($path);
        }

        return $path;
    }

    /**
     * Delete file from storage
     */
    protected function deleteFile(?string $filePath): bool
    {
        if (! $filePath) {
            return false;
        }

        \App\Support\ImageVariants::delete($filePath);

        return Storage::disk('public')->delete($filePath);
    }

    /**
     * Store an upload on a PRIVATE disk (default "local" = storage/app/private), for documents such
     * as ID cards: nothing goes under public/, no image copies are made, and the name is a random
     * UUID whose extension comes from the detected content type, never from the browser's file name.
     * Serve these only through a controller that checks who is asking.
     * Null when the file is too big or its content type is not one of $allowed.
     *
     * @param  array<string, string>  $allowed  content type => extension, e.g. ['application/pdf' => 'pdf']
     */
    protected function storePrivateFileSecurely(UploadedFile $file, string $directory, array $allowed, int $maxSize = 5120, string $disk = 'local'): ?string
    {
        if ($file->getSize() > ($maxSize * 1024)) {
            return null;
        }

        $extension = $allowed[(string) $file->getMimeType()] ?? null;
        if ($extension === null) {
            return null;
        }

        $path = $file->storeAs($directory, Uuid::uuid4()->toString().'.'.$extension, $disk);

        return $path ?: null;
    }

    /**
     * Delete a file stored with storePrivateFileSecurely().
     */
    protected function deletePrivateFile(?string $filePath, string $disk = 'local'): bool
    {
        if (! $filePath) {
            return false;
        }

        return Storage::disk($disk)->delete($filePath);
    }
}
