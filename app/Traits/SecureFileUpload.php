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
        $extension = $file->getClientOriginalExtension();
        $uuid = Uuid::uuid4()->toString();
        return $uuid . '.' . $extension;
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
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            return null;
        }

        // Generate secure filename
        $filename = $this->generateSecureFilename($file);
        
        // Store file
        return $file->storeAs($directory, $filename, 'public');
    }

    /**
     * Delete file from storage
     */
    protected function deleteFile(?string $filePath): bool
    {
        if (!$filePath) {
            return false;
        }

        return Storage::disk('public')->delete($filePath);
    }
} 