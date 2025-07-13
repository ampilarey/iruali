# Deployment Scripts Guide

## Overview
This guide covers the three deployment scripts available for the Iruali E-commerce application, each designed for different environments and use cases.

## Available Scripts

### 1. `deploy-live.sh` - Production Deployment
**Purpose:** Deploy to production environment with optimizations and safety checks.

**Key Features:**
- ✅ **Production optimizations** (no dev dependencies, cached configs)
- ✅ **Safe migrations** with `--force --no-interaction`
- ✅ **No seeding** (production data protection)
- ✅ **Asset optimization** with production build
- ✅ **Health checks** and error handling
- ✅ **Colored output** for better visibility

**Usage:**
```bash
./deploy-live.sh
```

**What it does:**
1. Pulls latest code from git
2. Installs production dependencies only
3. Builds optimized assets
4. Clears all caches
5. Runs migrations safely
6. Caches configuration for performance
7. Optimizes for production
8. Deploys assets to public_html
9. Sets proper permissions
10. Runs health check

### 2. `deploy-dev.sh` - Development Deployment
**Purpose:** Deploy to development environment with seeding and dev tools.

**Key Features:**
- ✅ **Development dependencies** included
- ✅ **Database seeding** for development data
- ✅ **Caches cleared** for development
- ✅ **Application key generation** if needed
- ✅ **Development-friendly** configuration

**Usage:**
```bash
./deploy-dev.sh
```

**What it does:**
1. Pulls latest code from git
2. Installs all dependencies (including dev)
3. Builds development assets
4. Clears all caches
5. Runs migrations safely
6. **Seeds development data**
7. Generates application key if needed
8. Clears caches for development
9. Sets development permissions
10. Runs health check

### 3. `deploy-git.sh` - Git Management
**Purpose:** Build assets, commit changes, and push to GitHub.

**Key Features:**
- ✅ **Asset building** before commit
- ✅ **Git status checking**
- ✅ **Automatic commits** with custom messages
- ✅ **Remote repository** validation
- ✅ **cPanel deployment** instructions

**Usage:**
```bash
./deploy-git.sh "Your commit message"
```

**What it does:**
1. Builds production assets
2. Checks git status
3. Adds all changes
4. Commits with custom message
5. Pushes to remote repository
6. Provides cPanel deployment instructions

## Environment-Specific Features

### Production (`deploy-live.sh`)
```bash
# Production optimizations
composer install --no-dev --optimize-autoloader --no-interaction
npm ci --production
npm run build

# Safe migrations (no seeding)
php artisan migrate --force --no-interaction

# Configuration caching
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

### Development (`deploy-dev.sh`)
```bash
# Development dependencies
composer install --optimize-autoloader
npm install

# Development build
npm run build

# Safe migrations with seeding
php artisan migrate --force --no-interaction
php artisan db:seed --force --no-interaction

# Development-friendly caching
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## Safety Features

### Migration Safety
All scripts use safe migration commands:
```bash
php artisan migrate --force --no-interaction
```
- `--force`: Runs in production without confirmation
- `--no-interaction`: Non-interactive mode for automation

### Error Handling
- `set -e`: Script stops on any error
- Health checks after deployment
- Proper error messages with colors
- Directory validation

### Data Protection
- **Production**: No seeding (protects production data)
- **Development**: Includes seeding for development data
- Safe migration rollbacks available

## Usage Examples

### Production Deployment
```bash
# Make sure you're in production environment
./deploy-live.sh
```

### Development Setup
```bash
# For development environment
./deploy-dev.sh
```

### Git Workflow
```bash
# Build and commit changes
./deploy-git.sh "Add new feature: user notifications"

# Or with default message
./deploy-git.sh
```

## Pre-Deployment Checklist

### For Production
- [ ] Verify `.env` is configured for production
- [ ] Ensure database backup is available
- [ ] Check disk space availability
- [ ] Verify server requirements
- [ ] Test in staging environment first

### For Development
- [ ] Verify `.env` is configured for development
- [ ] Ensure database is ready for seeding
- [ ] Check npm and composer are installed
- [ ] Verify git repository is configured

## Post-Deployment Verification

### Production Checks
```bash
# Check application status
php artisan --version

# Verify assets are loaded
curl -I https://yourdomain.com

# Check error logs
tail -f storage/logs/laravel.log

# Test critical functionality
php artisan route:list
```

### Development Checks
```bash
# Start development server
php artisan serve

# Watch for asset changes
npm run dev

# Run tests
php artisan test

# Check seeded data
php artisan tinker
```

## Troubleshooting

### Common Issues

#### 1. Permission Denied
```bash
chmod +x deploy-*.sh
```

#### 2. Composer Issues
```bash
composer install --no-dev --optimize-autoloader
```

#### 3. Migration Failures
```bash
php artisan migrate:status
php artisan migrate:rollback
```

#### 4. Asset Build Failures
```bash
npm install
npm run build
```

### Debug Steps
1. Check script permissions: `ls -la deploy-*.sh`
2. Verify Laravel installation: `php artisan --version`
3. Check environment: `php artisan env`
4. Review logs: `tail -f storage/logs/laravel.log`

## Best Practices

### 1. Always Test First
- Test deployment scripts in development
- Verify all functionality works
- Check for any errors or warnings

### 2. Backup Before Production
- Database backup before migration
- Code backup before deployment
- Configuration backup

### 3. Monitor After Deployment
- Check application logs
- Monitor performance
- Test critical user flows
- Verify all features work

### 4. Use Version Control
- Always commit before deployment
- Use meaningful commit messages
- Keep deployment scripts in version control

## Script Customization

### Adding Custom Steps
You can add custom steps to any script:

```bash
# Add after migrations
print_status "Running custom setup..."
php artisan custom:command

# Add before deployment
print_status "Running pre-deployment checks..."
php artisan health:check
```

### Environment Variables
Scripts respect Laravel environment variables:
- `APP_ENV`: Determines environment
- `DB_CONNECTION`: Database configuration
- `CACHE_DRIVER`: Cache configuration

## Support

For deployment issues:
1. Check this documentation
2. Review script output for errors
3. Check Laravel logs
4. Verify environment configuration
5. Test individual commands manually 