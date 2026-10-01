<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

/**
 * Serves uploaded files (product photos) from storage/app/public at /storage/...
 *
 * cPanel/LiteSpeed refuses to follow a symlink from the docroot into the app folder, so the usual
 * `storage:link` approach serves 403s there. Going through the app works on any host; the browser
 * caches each file for a week and the filenames are random, so it costs little.
 */
class StorageController extends Controller
{
    public function show(string $path)
    {
        $disk = Storage::disk('public');

        abort_if(str_contains($path, '..') || ! $disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'public, max-age=604800, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
