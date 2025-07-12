# 🚀 Iruali Production Deployment Guide

## 🔒 Security Configuration

### Production .env Settings
Copy this configuration to your production server's `.env` file:

```env
APP_NAME=iruali
APP_ENV=production
APP_KEY=base64:VqK06hVe0jsHpVn8eE/rPbXfS7un3lXsR5L75S+2KDc=
APP_DEBUG=false
APP_URL=https://iruali.mv

# Locale settings
APP_LOCALE=en
APP_FALLBACK_LOCALE=en

# Security logging
LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=iruali_iruali
DB_USERNAME=iruali_iruali
DB_PASSWORD=iruali@123

# Cache and Session (File-based for simplicity)
BROADCAST_DRIVER=log
CACHE_STORE=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=true

# Redis Configuration
MEMCACHED_HOST=127.0.0.1
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

# AWS Configuration
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

# Pusher Configuration
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1

# Vite Configuration
VITE_APP_NAME="${APP_NAME}"
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="${PUSHER_HOST}"
VITE_PUSHER_PORT="${PUSHER_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
```

## 🛡️ Security Features Implemented

✅ **Debug Mode Disabled** - `APP_DEBUG=false`  
✅ **Production Environment** - `APP_ENV=production`  
✅ **Error Logging Only** - `LOG_LEVEL=error` (Reduces log noise in production)  
✅ **Session Encryption** - `SESSION_ENCRYPT=true`  
✅ **Secure URL** - `APP_URL=https://iruali.mv`  

## 📋 Deployment Steps

### 1. Update Production .env File
- Copy the configuration above to your server's `.env` file
- Ensure all database credentials are correct
- Update the APP_URL to your actual domain

### 2. Run Deployment Script
```bash
./deploy-live.sh
```

### 3. Clear Caches
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan config:cache
```

### 4. Set Permissions
```bash
chmod -R 755 storage bootstrap/cache
chmod -R 644 storage/logs/*.log
```

## 🔍 Security Verification

After deployment, test these security features:

1. **Visit a non-existent page** (e.g., `https://iruali.mv/nonexistent`)
   - Should show generic error page, not detailed Laravel errors

2. **Check error logs**
   - Should only show error-level logs, not debug information

3. **Verify HTTPS**
   - All pages should load over HTTPS

## 🚨 Important Notes

- Keep your `.env` file secure and never commit it to Git
- Regularly update your dependencies
- Monitor your error logs for any issues
- Backup your database before major updates

## 📝 Production Logging Optimization

### Why `LOG_LEVEL=error` is Important:
- **Reduces log file size** - Only critical errors are logged
- **Improves performance** - Less I/O operations for logging
- **Easier monitoring** - Focus on actual issues, not debug noise
- **Better security** - Sensitive information isn't logged unnecessarily

### Log Levels Available:
- `debug` - All messages (development only)
- `info` - Informational messages
- `notice` - Normal but significant events
- `warning` - Warning conditions
- `error` - Error conditions (recommended for production)
- `critical` - Critical conditions
- `alert` - Action must be taken immediately
- `emergency` - System is unusable

## 📞 Support

If you encounter any issues during deployment, check:
1. Laravel logs: `storage/logs/laravel.log`
2. Server error logs
3. Database connectivity
4. File permissions 