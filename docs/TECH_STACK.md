# 🛠️ Iruali - Tech Stack Documentation

## 📋 Overview

The Iruali multi-vendor e-commerce platform is built using modern web technologies with a focus on scalability, security, and performance. This document provides a comprehensive overview of the technology stack, dependencies, and architectural decisions.

## 🏗️ Architecture Overview

### Backend Architecture
- **Framework**: Laravel 12 (PHP 8.2+)
- **Pattern**: Model-View-Controller (MVC) with Service Layer
- **Database**: MySQL 8.0+ with optimized indexing
- **Authentication**: Laravel Sanctum with 2FA support
- **File Storage**: Local with symbolic links and cloud storage ready

### Frontend Architecture
- **CSS Framework**: TailwindCSS 4.1+
- **JavaScript**: Alpine.js 3.4+
- **Build Tool**: Vite 7.0+
- **Responsive**: Mobile-first design approach
- **Progressive**: PWA-ready architecture

## 🔧 Backend Technologies

### Core Framework
```json
{
  "laravel/framework": "^12.0",
  "php": "^8.2"
}
```

**Laravel 12 Features Used:**
- MVC Architecture with Service Layer
- Eloquent ORM with relationships
- Route Model Binding and Middleware
- Queue System for background jobs
- Event System for notifications
- Artisan Commands for maintenance

### Key Dependencies

#### Authentication & Security
```json
{
  "laravel/sanctum": "^4.1",
  "pragmarx/google2fa": "^8.0",
  "google/recaptcha": "^1.3"
}
```
- **Laravel Sanctum**: API authentication and SPA authentication
- **Google 2FA**: Two-factor authentication for enhanced security
- **reCAPTCHA**: Bot protection and form security
- **Rate Limiting**: Built-in throttling for API and authentication endpoints

#### Multilingual Support
```json
{
  "spatie/laravel-translatable": "^6.11"
}
```
- **Spatie Translatable**: Advanced multilingual content management
- **Locale Management**: Session-based and database-stored preferences
- **RTL Support**: Built-in right-to-left language support for Dhivehi

#### Image Processing & Storage
```json
{
  "intervention/image": "^3.11"
}
```
- **Intervention Image**: Advanced image manipulation and optimization
- **WebP Conversion**: Automatic modern image format conversion
- **Multiple Sizes**: Automatic thumbnail and responsive image generation
- **Storage Management**: Organized file structure with cleanup

#### Data Processing
```json
{
  "maatwebsite/excel": "^3.1"
}
```
- **Laravel Excel**: Import/export functionality for bulk operations
- **Data Migration**: Seamless data transfer and backup capabilities
- **Report Generation**: Excel-based reporting and analytics

#### Backup & Maintenance
```json
{
  "spatie/laravel-backup": "^9.3"
}
```
- **Automated Backups**: Database and file system backup automation
- **Cloud Storage**: Integration with cloud backup services
- **Disaster Recovery**: Comprehensive backup and restore procedures

### Development Dependencies
```json
{
  "laravel/breeze": "^2.3",
  "spatie/laravel-permission": "^6.20",
  "guzzlehttp/guzzle": "^7.9",
  "laravel/socialite": "^5.21"
}
```

#### Auth & Permissions
- **Laravel Breeze**: Authentication scaffolding and views
- **Spatie Permission**: Role-based access control (RBAC)
- **Social Login**: OAuth integration for third-party authentication

## 🎨 Frontend Technologies

### CSS Framework
```json
{
  "tailwindcss": "^4.1.11",
  "@tailwindcss/forms": "^0.5.10",
  "@tailwindcss/typography": "^0.5.16",
  "@tailwindcss/aspect-ratio": "^0.4.2"
}
```

**TailwindCSS Configuration:**
- Custom color palette for brand identity
- Responsive breakpoints optimized for mobile commerce
- Component utilities for rapid development
- Form styling enhancements for better UX

### JavaScript Framework
```json
{
  "alpinejs": "^3.4.2"
}
```

**Alpine.js Features:**
- Lightweight reactive framework for interactivity
- Component-based JavaScript without build complexity
- Directives for DOM manipulation and state management
- Integration with Laravel and server-rendered content

### Build Tools
```json
{
  "vite": "^7.0.4",
  "laravel-vite-plugin": "^2.0.0",
  "autoprefixer": "^10.4.21",
  "postcss": "^8.5.6"
}
```

**Vite Configuration:**
- Fast hot module replacement (HMR) for development
- Asset optimization and minification for production
- CSS and JS bundling with tree shaking
- Development server with HTTPS support

### UI Components
```json
{
  "sweetalert2": "^11.22.2"
}
```
- **SweetAlert2**: Beautiful alert and confirmation dialogs
- **User Experience**: Enhanced interaction feedback and confirmations

## 🗄️ Database Technologies

### Database System
- **Primary**: MySQL 8.0+ (Production)
- **Development**: SQLite (Local development)
- **Features**: Full-text search, JSON columns, and spatial indexes

### Database Features Used
- **Migrations**: Version-controlled database schema
- **Seeders**: Sample data and test data population
- **Eloquent ORM**: Advanced relationships and query optimization
- **Query Builder**: Performance-optimized database queries
- **Pagination**: Built-in Laravel pagination for large datasets

### Key Database Extensions
- **Foreign Key Constraints**: Data integrity and referential integrity
- **Indexes**: Strategic indexing for query optimization
- **JSON Columns**: Flexible data storage for product attributes
- **Timestamps**: Automatic created_at/updated_at tracking
- **Soft Deletes**: Data retention with logical deletion

## 🔐 Security Stack

### Laravel Security Features
- **CSRF Protection**: Built-in CSRF token validation
- **XSS Protection**: Automatic output escaping and sanitization
- **SQL Injection Prevention**: Eloquent ORM and query builder protection
- **Authentication**: Secure session management with Sanctum
- **Authorization**: Role-based access control with granular permissions

### Authentication System
```php
// Multi-layer authentication implementation
Route::middleware(['auth:sanctum', 'role:seller'])->group(function () {
    // Protected seller routes
});
```

**Features:**
- **Multi-Provider**: Email/password, OAuth, and API token authentication
- **2FA Support**: Google Authenticator integration
- **OTP Verification**: SMS and email OTP support
- **Session Management**: Secure session handling with Sanctum
- **Rate Limiting**: Protection against brute force attacks

## 📱 Progressive Web App Features

### Service Worker Implementation
- **Offline Support**: Basic offline functionality for critical pages
- **Caching Strategy**: Asset and API response caching
- **Background Sync**: Offline action queuing and synchronization
- **Push Notifications**: Customer notification system integration

### Manifest Configuration
```json
{
  "name": "Iruali Marketplace",
  "short_name": "Iruali",
  "description": "Multi-vendor e-commerce platform",
  "start_url": "/",
  "display": "standalone",
  "theme_color": "#1e40af",
  "background_color": "#ffffff"
}
```

## 🚀 Performance Optimizations

### Frontend Optimizations
- **Asset Minification**: CSS and JS compression with Vite
- **Image Optimization**: Automatic WebP conversion and lazy loading
- **Code Splitting**: Modular JavaScript loading for faster initial loads
- **Critical CSS**: Inline critical styles for above-the-fold content

### Backend Optimizations
- **Query Optimization**: Efficient database queries with proper indexing
- **Caching**: Redis/Memcached integration ready
- **Database Indexing**: Strategic indexes for common query patterns
- **Asset Versioning**: Cache-busting for updated assets

### Server Optimizations
- **Gzip Compression**: Text asset compression
- **Browser Caching**: Appropriate cache headers for static content
- **CDN Ready**: Optimized for content delivery networks
- **Database Connection Pooling**: Efficient database connections

## 🔄 Development Workflow

### Build Process
```bash
# Development
npm run dev          # Start Vite dev server with HMR
php artisan serve    # Start Laravel development server

# Production
npm run build        # Build optimized assets
composer install --no-dev --optimize-autoloader
```

### Code Quality Tools
- **Laravel Pint**: Code style enforcement (PHP CS Fixer)
- **Laravel Testing**: PHPUnit integration with Pest
- **ESLint**: JavaScript code quality (configured)
- **Type Safety**: PHP 8.2+ type declarations and strict types

## 📦 Package Management

### Composer Dependencies
```bash
# Production dependencies
composer install --no-dev --optimize-autoloader

# Development dependencies
composer install
```

### NPM Dependencies
```bash
# Development
npm install

# Production build
npm run build
```

## 🏗️ Deployment Architecture

### Production Environment
- **Web Server**: Apache/Nginx with PHP-FPM
- **Database**: MySQL 8.0+ with replication support
- **File Storage**: Local filesystem with cloud backup
- **SSL/TLS**: HTTPS enforcement with modern cipher suites
- **Monitoring**: Error logging and performance monitoring

### Development Environment
- **Local Development**: Laravel Sail (Docker) support
- **Database**: SQLite for quick setup and testing
- **Asset Compilation**: Vite dev server with HMR
- **Debugging**: Laravel Telescope and debugging tools

## 🔧 Configuration Management

### Environment Variables
```env
# Core Laravel settings
APP_NAME="Iruali"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://iruali.mv

# Database configuration
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=iruali_production

# Sanctum configuration
SANCTUM_STATEFUL_DOMAINS=iruali.mv,www.iruali.mv

# Image processing
INTERVENTION_IMAGE_DRIVER=gd

# Cache configuration
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Service Providers
- **AppServiceProvider**: Global application configuration
- **AuthServiceProvider**: Authentication and authorization setup
- **RouteServiceProvider**: Route configuration and caching

## 📊 Monitoring & Analytics

### Error Tracking
- **Laravel Logging**: Comprehensive error logging with contextual information
- **Custom Exception Handling**: Specialized exception handling for e-commerce scenarios
- **Performance Monitoring**: Request timing and database query monitoring

### Performance Monitoring
- **Query Logging**: Database query optimization and analysis
- **Asset Monitoring**: Loading time optimization and bundle analysis
- **Server Monitoring**: Resource usage tracking and alerting

---

**Document Version**: 1.0  
**Last Updated**: December 2024  
**Laravel Version**: 12.x  
**PHP Version**: 8.2+
