# iruali cPanel Deployment Checklist

## Pre-Deployment (Local)

- [x] ✅ Project is ready for deployment
- [x] ✅ `.env.example` file created
- [x] ✅ `.gitignore` configured properly
- [x] ✅ Production assets built (`npm run build`)
- [x] ✅ `public/index.php` updated for cPanel paths
- [x] ✅ `README.md` with deployment instructions
- [x] ✅ `deploy-cpanel.sh` script created

## cPanel Deployment Steps

### 1. Database Setup
- [ ] Create MySQL database in cPanel
- [ ] Create database user with full privileges
- [ ] Note down database credentials

### 2. File Upload
- [ ] Upload project files to `/home/yourcpaneluser/repositories/iruali/`
- [ ] Copy contents of `public/` folder to `public_html/`
- [ ] Ensure `public_html/index.php` points to correct Laravel paths

### 3. Environment Configuration
- [ ] Copy `.env.example` to `.env` in project root
- [ ] Update database credentials in `.env`
- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Set `APP_URL=https://yourdomain.com`

### 4. Dependencies & Setup
- [ ] Run `composer install --no-dev --optimize-autoloader`
- [ ] Run `php artisan key:generate`
- [ ] Set file permissions: `chmod -R 775 storage bootstrap/cache`
- [ ] Run `php artisan storage:link`
- [ ] Run `php artisan migrate --force`

### 5. Assets (if Node.js not available on server)
- [ ] Upload `public/build/` folder to `public_html/`
- [ ] Ensure all CSS/JS files are accessible

### 6. Testing
- [ ] Visit your domain
- [ ] Check if homepage loads
- [ ] Test user registration/login
- [ ] Test product browsing
- [ ] Check for any error messages

## File Structure After Deployment

```
/home/yourcpaneluser/
├── public_html/                    # Document root
│   ├── index.php                  # Updated for cPanel
│   ├── .htaccess
│   ├── favicon.ico
│   └── build/                     # Production assets
│       ├── assets/
│       └── manifest.json
└── repositories/
    └── iruali/                    # Main Laravel app
        ├── app/
        ├── bootstrap/
        ├── config/
        ├── database/
        ├── resources/
        ├── routes/
        ├── storage/
        ├── vendor/
        ├── .env                   # Production config
        └── artisan
```

## Troubleshooting

### Common Issues & Solutions

1. **500 Internal Server Error**
   - Check `storage/logs/laravel.log`
   - Verify file permissions
   - Ensure `.env` exists and is configured

2. **Database Connection Error**
   - Verify database credentials in `.env`
   - Check if database exists
   - Ensure user has proper permissions

3. **Missing Assets (CSS/JS not loading)**
   - Ensure `public/build/` is in `public_html/`
   - Check file permissions
   - Verify paths in `manifest.json`

4. **Permission Errors**
   ```bash
   chmod -R 755 storage bootstrap/cache
   chmod -R 644 storage/logs/*.log
   ```

5. **App Key Error**
   ```bash
   php artisan key:generate
   ```

## Security Checklist

- [ ] `APP_DEBUG=false` in production
- [ ] Strong database passwords
- [ ] Proper file permissions
- [ ] `.env` file not accessible via web
- [ ] SSL certificate installed (recommended)

## Performance Optimization

- [ ] Enable Laravel caching
- [ ] Optimize database queries
- [ ] Use CDN for assets (optional)
- [ ] Enable Gzip compression
- [ ] Set up proper caching headers

## Backup Strategy

- [ ] Regular database backups
- [ ] File system backups
- [ ] Version control (Git)
- [ ] Document configuration changes

## Monitoring

- [ ] Set up error logging
- [ ] Monitor server resources
- [ ] Track application performance
- [ ] Set up uptime monitoring

---

**Deployment completed successfully! 🎉**

Your iruali e-commerce platform is now live on cPanel. 