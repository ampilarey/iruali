# cPanel Git Deployment Guide for iruali

This guide will help you deploy your Laravel e-commerce application to cPanel using Git version control.

## Prerequisites

- cPanel hosting with Git Version Control enabled
- SSH access (recommended) or cPanel Terminal
- GitHub repository with your Laravel project

## Step 1: Set up Git in cPanel

### 1.1 Access Git Version Control
1. Log in to your cPanel dashboard
2. Find and click on **"Git Version Control"**
3. Click **"Create"** to set up a new repository

### 1.2 Configure Repository
- **Repository URL**: `https://github.com/yourusername/iruali.git`
- **Repository Name**: `iruali`
- **Repository Path**: `/home/yourcpaneluser/repositories/iruali`
- **Branch**: `main`
- **Click "Create"**

### 1.3 Update from Remote
1. After creation, click **"Manage"** next to your repository
2. Click **"Update from Remote"** to pull the latest code
3. Wait for the update to complete

## Step 2: Set up Document Root

### 2.1 Access File Manager
1. Go to **"File Manager"** in cPanel
2. Navigate to your `public_html` folder
3. **Backup existing files** (if any) by creating a backup folder

### 2.2 Copy Public Files
1. Navigate to `/home/yourcpaneluser/repositories/iruali/public/`
2. Select all files and folders
3. Copy them to `public_html/`
4. Overwrite existing files if prompted

### 2.3 Update index.php
Edit `public_html/index.php` to point to your Laravel application:

```php
<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Check If The Application Is Under Maintenance
|--------------------------------------------------------------------------
*/

if (file_exists($maintenance = __DIR__.'/../repositories/iruali/storage/framework/maintenance.php')) {
    require $maintenance;
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
*/

require __DIR__.'/../repositories/iruali/vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
*/

$app = require_once __DIR__.'/../repositories/iruali/bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
```

## Step 3: Install Dependencies

### 3.1 Using SSH (Recommended)
```bash
# Connect to your server via SSH
ssh yourusername@yourdomain.com

# Navigate to your repository
cd /home/yourcpaneluser/repositories/iruali

# Install Composer dependencies
composer install --no-dev --optimize-autoloader

# Install Node.js dependencies (if available)
npm install --production
```

### 3.2 Using cPanel Terminal
1. Go to **"Terminal"** in cPanel
2. Run the same commands as above

### 3.3 Manual Upload (Alternative)
If you don't have SSH access:
1. Install dependencies locally: `composer install --no-dev --optimize-autoloader`
2. Upload the `vendor/` folder to `/home/yourcpaneluser/repositories/iruali/`

## Step 4: Environment Configuration

### 4.1 Create .env File
```bash
cd /home/yourcpaneluser/repositories/iruali
cp .env.example .env
```

### 4.2 Configure .env
Edit the `.env` file with your production settings:

```env
APP_NAME=iruali
APP_ENV=production
APP_KEY=base64:your-generated-key
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

### 4.3 Generate Application Key
```bash
php artisan key:generate
```

## Step 5: Database Setup

### 5.1 Run Migrations
```bash
php artisan migrate --force
```

### 5.2 Create Storage Link
```bash
php artisan storage:link
```

### 5.3 Seed Database (Optional)
```bash
php artisan db:seed --force
```

## Step 6: Set Permissions

### 6.1 Set Directory Permissions
```bash
chmod -R 755 storage bootstrap/cache
chmod -R 644 storage/logs/*.log
```

### 6.2 Set File Permissions
```bash
chmod 644 .env
chmod 755 artisan
```

## Step 7: Cache Configuration

### 7.1 Cache Application Files
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Step 8: Test Your Application

1. Visit your domain in a web browser
2. Check for any errors in `storage/logs/laravel.log`
3. Verify that all assets are loading correctly

## Step 9: Future Deployments

### 9.1 Local Development Workflow
1. Make changes to your local code
2. Run the deployment script: `./deploy-git.sh "Your commit message"`
3. The script will:
   - Build production assets
   - Commit changes
   - Push to GitHub

### 9.2 cPanel Deployment
1. Go to **Git Version Control** in cPanel
2. Click **"Manage"** next to your repository
3. Click **"Update from Remote"**
4. Copy updated `public/` files to `public_html/`
5. Clear caches: `php artisan cache:clear`

## Troubleshooting

### Common Issues

#### 500 Internal Server Error
- Check `storage/logs/laravel.log` for error details
- Verify file permissions
- Ensure `.env` file exists and is configured

#### Missing Assets
- Verify `public/build/` folder exists
- Check that `public_html/index.php` points to the correct Laravel app
- Run `npm run build` locally and upload the build files

#### Database Connection Error
- Verify database credentials in `.env`
- Check if database exists
- Ensure database user has proper permissions

#### Permission Denied
```bash
chmod -R 755 storage bootstrap/cache
chmod -R 644 storage/logs/*.log
```

### Useful Commands

```bash
# Check Laravel version
php artisan --version

# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Check application status
php artisan about

# List all routes
php artisan route:list
```

## Security Notes

1. **Never commit `.env` file** to Git
2. **Set `APP_DEBUG=false`** in production
3. **Use strong database passwords**
4. **Keep dependencies updated**
5. **Regular backups** of your database and files

## Support

If you encounter issues:
1. Check the Laravel logs: `storage/logs/laravel.log`
2. Verify your hosting environment meets Laravel requirements
3. Contact your hosting provider for server-specific issues 