<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Carbon\Carbon;

class OneDriveBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:onedrive 
                            {--force : Force backup even if recent backup exists}
                            {--test : Test OneDrive connection without uploading}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create backup and upload to OneDrive using Microsoft Graph API';

    /**
     * Guzzle HTTP client
     */
    protected $client;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting OneDrive backup process...');

        // Check if OneDrive credentials are configured
        if (!$this->checkCredentials()) {
            $this->error('❌ OneDrive credentials not configured. Please set up your .env file.');
            return 1;
        }

        // Initialize HTTP client
        $this->client = new Client([
            'timeout' => 300, // 5 minutes timeout for large uploads
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'Content-Type' => 'application/json',
            ]
        ]);

        // Test connection if requested
        if ($this->option('test')) {
            return $this->testConnection();
        }

        // Run local backup first
        if (!$this->runLocalBackup()) {
            $this->error('❌ Local backup failed. Aborting OneDrive upload.');
            return 1;
        }

        // Get the latest backup file
        $backupFile = $this->getLatestBackupFile();
        if (!$backupFile) {
            $this->error('❌ No backup file found to upload.');
            return 1;
        }

        // Upload to OneDrive
        if ($this->uploadToOneDrive($backupFile)) {
            $this->info('✅ Backup successfully uploaded to OneDrive!');
            return 0;
        } else {
            $this->error('❌ Failed to upload backup to OneDrive.');
            return 1;
        }
    }

    /**
     * Check if OneDrive credentials are configured
     */
    protected function checkCredentials()
    {
        $required = [
            'ONEDRIVE_CLIENT_ID',
            'ONEDRIVE_CLIENT_SECRET',
            'ONEDRIVE_REFRESH_TOKEN',
            'ONEDRIVE_TENANT_ID'
        ];

        foreach ($required as $key) {
            if (empty(config("services.onedrive.{$key}") ?? env($key))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Test OneDrive connection
     */
    protected function testConnection()
    {
        $this->info('🔍 Testing OneDrive connection...');

        try {
            $response = $this->client->get('https://graph.microsoft.com/v1.0/me/drive');
            $data = json_decode($response->getBody(), true);

            if (isset($data['id'])) {
                $this->info('✅ OneDrive connection successful!');
                $this->info("📁 Drive ID: {$data['id']}");
                $this->info("👤 User: {$data['owner']['user']['displayName']}");
                return 0;
            }
        } catch (RequestException $e) {
            $this->error('❌ OneDrive connection failed: ' . $e->getMessage());
            if ($e->hasResponse()) {
                $error = json_decode($e->getResponse()->getBody(), true);
                $this->error('Error details: ' . json_encode($error, JSON_PRETTY_PRINT));
            }
        }

        return 1;
    }

    /**
     * Run local backup using Spatie Laravel Backup
     */
    protected function runLocalBackup()
    {
        $this->info('💾 Creating local backup...');

        try {
            $exitCode = $this->call('backup:run');
            return $exitCode === 0;
        } catch (\Exception $e) {
            $this->error('Local backup error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the latest backup file from local storage
     */
    protected function getLatestBackupFile()
    {
        $backupPath = storage_path('app/laravel-backup');
        
        if (!is_dir($backupPath)) {
            return null;
        }

        $files = glob($backupPath . '/*.zip');
        
        if (empty($files)) {
            return null;
        }

        // Get the most recent file
        $latestFile = array_reduce($files, function($a, $b) {
            return filemtime($a) > filemtime($b) ? $a : $b;
        });

        return $latestFile;
    }

    /**
     * Upload backup file to OneDrive
     */
    protected function uploadToOneDrive($filePath)
    {
        $fileName = basename($filePath);
        $fileSize = filesize($filePath);
        
        $this->info("📤 Uploading {$fileName} ({$this->formatBytes($fileSize)}) to OneDrive...");

        try {
            // Create upload session for large files (>4MB)
            if ($fileSize > 4 * 1024 * 1024) {
                return $this->uploadLargeFile($filePath, $fileName);
            } else {
                return $this->uploadSmallFile($filePath, $fileName);
            }
        } catch (RequestException $e) {
            $this->error('Upload failed: ' . $e->getMessage());
            Log::error('OneDrive upload failed', [
                'file' => $fileName,
                'error' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody() : null
            ]);
            return false;
        }
    }

    /**
     * Upload small file (<4MB)
     */
    protected function uploadSmallFile($filePath, $fileName)
    {
        $fileContent = file_get_contents($filePath);
        
        $response = $this->client->put(
            "https://graph.microsoft.com/v1.0/me/drive/root:/Backups/{$fileName}:/content",
            ['body' => $fileContent]
        );

        $data = json_decode($response->getBody(), true);
        $this->info("✅ File uploaded successfully! File ID: {$data['id']}");
        
        return true;
    }

    /**
     * Upload large file (>4MB) using upload session
     */
    protected function uploadLargeFile($filePath, $fileName)
    {
        $fileSize = filesize($filePath);
        $chunkSize = 320 * 1024; // 320KB chunks (Microsoft recommended)
        
        // Create upload session
        $sessionResponse = $this->client->post(
            "https://graph.microsoft.com/v1.0/me/drive/root:/Backups/{$fileName}:/createUploadSession",
            ['json' => [
                'item' => [
                    '@microsoft.graph.conflictBehavior' => 'replace',
                    'name' => $fileName
                ]
            ]]
        );

        $sessionData = json_decode($sessionResponse->getBody(), true);
        $uploadUrl = $sessionData['uploadUrl'];

        // Upload file in chunks
        $handle = fopen($filePath, 'rb');
        $uploadedBytes = 0;

        while (!feof($handle)) {
            $chunk = fread($handle, $chunkSize);
            $chunkLength = strlen($chunk);
            
            $range = "bytes {$uploadedBytes}-" . ($uploadedBytes + $chunkLength - 1) . "/{$fileSize}";
            
            $response = $this->client->put($uploadUrl, [
                'headers' => [
                    'Content-Length' => $chunkLength,
                    'Content-Range' => $range
                ],
                'body' => $chunk
            ]);

            $uploadedBytes += $chunkLength;
            
            // Show progress
            $progress = round(($uploadedBytes / $fileSize) * 100, 1);
            $this->output->write("\r📤 Uploading... {$progress}%");
        }

        fclose($handle);
        $this->output->writeln(''); // New line after progress

        $data = json_decode($response->getBody(), true);
        $this->info("✅ Large file uploaded successfully! File ID: {$data['id']}");
        
        return true;
    }

    /**
     * Get access token using refresh token
     */
    protected function getAccessToken()
    {
        $clientId = config('services.onedrive.client_id') ?? env('ONEDRIVE_CLIENT_ID');
        $clientSecret = config('services.onedrive.client_secret') ?? env('ONEDRIVE_CLIENT_SECRET');
        $refreshToken = config('services.onedrive.refresh_token') ?? env('ONEDRIVE_REFRESH_TOKEN');
        $tenantId = config('services.onedrive.tenant_id') ?? env('ONEDRIVE_TENANT_ID');

        $tokenClient = new Client();
        
        try {
            $response = $tokenClient->post("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token", [
                'form_params' => [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' => $refreshToken,
                    'grant_type' => 'refresh_token',
                    'scope' => 'https://graph.microsoft.com/.default'
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['access_token'];
        } catch (RequestException $e) {
            $this->error('Failed to get access token: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Format bytes to human readable format
     */
    protected function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
