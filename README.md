# iruali - Multi-Vendor E-commerce Platform

A modern, multi-vendor e-commerce platform built with Laravel for the Maldives market.

## Features

- Multi-vendor marketplace
- Multilingual support (English & Dhivehi)
- Flash sales with countdown timers
- User authentication and authorization
- Shopping cart and wishlist
- Order management and tracking
- Loyalty points system
- Referral system
- SEO optimized

## Local Development Setup

### Prerequisites
- PHP 8.1 or higher
- Composer
- Node.js & npm
- MySQL/MariaDB

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/iruali.git
   cd iruali
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install Node.js dependencies**
   ```bash
   npm install
   ```

4. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Configure database in `.env`**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=iruali
   DB_USERNAME=root
   DB_PASSWORD=
   ```

6. **Run migrations and seeders**
   ```bash
   php artisan migrate --seed
   ```

7. **Create storage link**
   ```bash
   php artisan storage:link
   ```

8. **Build assets**
   ```bash
   npm run dev
   ```

9. **Start the development server**
   ```bash
   php artisan serve
   ```

10. **Visit** `http://127.0.0.1:8000`

## cPanel Deployment

### Method 1: Git Version Control (Recommended)

1. **Push your code to GitHub**
   ```bash
   git add .
   git commit -m "Ready for deployment"
   git push origin main
   ```

2. **In cPanel:**
   - Go to **Git Version Control**
   - Click **Create**
   - Enter your GitHub repository URL
   - Set clone directory: `/home/yourcpaneluser/repositories/iruali`
   - Click **Create**

3. **Set up document root:**
   - Copy contents of `public/` folder to `public_html/`
   - Edit `public_html/index.php` to point to your Laravel app

4. **Install dependencies:**
   ```bash
   cd /home/yourcpaneluser/repositories/iruali
   composer install --no-dev --optimize-autoloader
   ```

5. **Configure environment:**
   ```bash
   cp .env.example .env
   # Edit .env with production settings
   php artisan key:generate
   ```

6. **Set permissions:**
   ```bash
   chmod -R 775 storage bootstrap/cache
   ```

7. **Run migrations:**
   ```bash
   php artisan migrate --force
   php artisan storage:link
   ```

8. **Build assets (if server supports Node.js):**
   ```bash
   npm install
   npm run build
   ```
   Or build locally and upload `public/build/` to `public_html/`

### Method 2: Manual Upload

1. **Prepare files locally:**
   - Run `composer install --no-dev --optimize-autoloader`
   - Run `npm run build`
   - Create a zip file excluding `vendor/`, `node_modules/`, `.env`

2. **Upload to cPanel:**
   - Extract in `/home/yourcpaneluser/repositories/iruali`
   - Copy `public/` contents to `public_html/`
   - Follow steps 4-8 from Method 1

## File Structure for cPanel

```
/home/yourcpaneluser/
├── public_html/           # Document root (public files only)
│   ├── index.php         # Updated to point to Laravel app
│   ├── .htaccess
│   ├── favicon.ico
│   └── build/            # Compiled assets
└── repositories/
    └── iruali/           # Main Laravel application
        ├── app/
        ├── bootstrap/
        ├── config/
        ├── database/
        ├── resources/
        ├── routes/
        ├── storage/
        ├── vendor/
        └── .env
```

## Environment Variables

### Required for Production
```env
APP_NAME=iruali
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_db_name
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

## Troubleshooting

### Common Issues

1. **500 Error:**
   - Check `storage/logs/laravel.log`
   - Verify file permissions
   - Ensure `.env` exists and is configured

2. **Database Connection Error:**
   - Verify database credentials in `.env`
   - Check if database exists
   - Ensure user has proper permissions

3. **Missing Assets:**
   - Run `npm run build`
   - Upload `public/build/` to `public_html/`

4. **Permission Errors:**
   ```bash
   chmod -R 755 storage bootstrap/cache
   chmod -R 644 storage/logs/*.log
   ```

## Support

For issues and questions, please check the Laravel documentation or create an issue in the repository.

## License

This project is proprietary software.

# API Authentication & Usage

## Authentication (Sanctum)

- Login via `/api/v1/login` to receive a Bearer token.
- Use the token in the `Authorization` header for all protected endpoints.
- Logout via `/api/v1/logout` (requires token).
- Tokens are issued with abilities (scopes) for fine-grained access control.
- Tokens older than 30 days are automatically deleted for security.

### Example: Login

```
curl -X POST https://yourdomain.com/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com", "password": "password"}'
```
**Response:**
```json
{
  "success": true,
  "data": {
    "user": { "id": 1, "name": "User", ... },
    "token": "1|longsanctumtokenstring",
    "token_type": "Bearer",
    "abilities": ["order:read", "cart:write", "wishlist:write", "profile:read", "profile:write"]
  },
  "message": "Login successful"
}
```

### Example: Authenticated Request

```
curl -X GET https://yourdomain.com/api/v1/user \
  -H "Authorization: Bearer 1|longsanctumtokenstring"
```

### Example: Logout

```
curl -X POST https://yourdomain.com/api/v1/logout \
  -H "Authorization: Bearer 1|longsanctumtokenstring"
```

## Token Abilities (Scopes)
- Each token is issued with specific abilities.
- Example: `order:read`, `cart:write`, `wishlist:write`, `profile:read`, `profile:write`
- You can check abilities in your controllers using `$request->user()->tokenCan('order:read')`.

## Token Expiration
- Tokens older than 30 days are deleted automatically by a scheduled job.

---

For more endpoints and usage, see the API documentation or contact the backend team.
