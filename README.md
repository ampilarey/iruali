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
- Card payments through BML Connect (Bank of Maldives) — the only payment method; see `docs/PAYMENTS_BML.md`
- SEO optimized

## Local Development Setup

### Prerequisites
- PHP 8.4 (the app requires 8.2 or higher; production and the test suite run on 8.4)
- Composer
- Node.js & npm
- MySQL/MariaDB for the test suite (`phpunit.xml` uses the `iruali_test` MySQL database); local development can use SQLite, which is what `.env.example` is set up for

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

   `.env.example` points at SQLite (`database/database.sqlite`; create it with `touch database/database.sqlite`). To use MySQL/MariaDB instead:
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

### Tests

```bash
php artisan test
```

`phpunit.xml` runs the suite against a MySQL/MariaDB database named `iruali_test` (user `root`, empty password), not the SQLite development database, so that database must exist. Built assets live in `public/build/` and are committed: after frontend changes run `npm run build` and commit the result.

## Deployment

The site runs on cPanel. Deployment is scripted (`scripts/`) and documented in:

- **[docs/PRODUCTION_DEPLOY.md](docs/PRODUCTION_DEPLOY.md)** — releasing `main` to `iruali.mv` by hand with `scripts/deploy-production.sh` (production is never deployed automatically).
- **[docs/TEST_AUTO_DEPLOY.md](docs/TEST_AUTO_DEPLOY.md)** — how `test.iruali.mv` pulls `main` automatically on every push.

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

# Card payments (see docs/PAYMENTS_BML.md). Leave BML_API_KEY empty to hide card payment.
BML_API_KEY=
BML_ENVIRONMENT=sandbox
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
- Tokens expire after 30 days (`SANCTUM_EXPIRATION`, in minutes); the scheduled `sanctum:prune-expired` command (`routes/console.php`) deletes expired tokens daily, which needs the Laravel scheduler cron on the server.

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
- Tokens expire 30 days after they are issued. Expired tokens are removed by the daily `sanctum:prune-expired --hours=24` schedule once the scheduler cron (`* * * * * php artisan schedule:run`) is set up on the server.

---

For more endpoints and usage, see the API documentation or contact the backend team.
