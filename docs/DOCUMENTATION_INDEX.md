# 📚 Iruali - Multi-Vendor E-commerce Platform Documentation

Welcome to the comprehensive documentation for the Iruali multi-vendor e-commerce platform. This documentation provides detailed information about the project architecture, features, deployment, and maintenance.

## 📋 Documentation Structure

### 🎯 Core Documentation
- **[Project Overview](PROJECT_OVERVIEW.md)** - Vision, goals, target audience, and key differentiators
- **[Tech Stack](TECH_STACK.md)** - The frameworks and packages actually installed
- **[Architecture & Audit Guide](ARCHITECTURE_AND_AUDIT_GUIDE.md)** - Code structure, the original audit findings and their status, test environment, production checklist

### 🛒 How the marketplace works
- **[Marketplace](MARKETPLACE.md)** - Per-shop order parts, commission, seller earnings and payouts, returns
- **[Card payments with BML Connect](PAYMENTS_BML.md)** - Setup, payment flow, webhook and reconciliation (BML card is the only payment method)
- **[BML website requirements](BML_COMPLIANCE.md)** - Where each of BML's website requirements is met

### 🚀 Deployment
- **[Production deploy](PRODUCTION_DEPLOY.md)** - Releasing `main` to iruali.mv by hand with `scripts/deploy-production.sh`
- **[TEST auto-deploy](TEST_AUTO_DEPLOY.md)** - How test.iruali.mv pulls `main` automatically
- The repository `README.md` covers local setup and tests; `AGENTS.md` covers running the app and the test database.

### 🎨 Brand
- `brand/` - Colour and logo references used by the Tailwind theme

The REST API has no separate specification document: `routes/api.php` and the `App\Http\Controllers\Api` controllers are the reference, and the README has the Sanctum authentication examples.

## 🏗️ Project Overview

**Iruali** is a comprehensive multi-vendor e-commerce platform built with Laravel, featuring:

### ✨ Key Features
- **Multi-Vendor Marketplace** - Complete seller onboarding and management system
- **Multilingual Support** - English and Dhivehi with full RTL support
- **Advanced Shopping Features** - Cart, wishlist, flash sales, and loyalty points
- **Order Management** - Complete order processing with tracking and notifications
- **Payment Integration** - Card payments through BML Connect (Bank of Maldives)
- **Admin Panel** - Comprehensive dashboard for platform management

### 🎨 Business Features
- **Seller Management** - Vendor onboarding, approval, and performance tracking
- **Product Management** - Advanced catalog with variants, reviews, and SEO
- **Order Processing** - Multi-status order management with notifications
- **Analytics Dashboard** - Sales, revenue, and performance insights
- **Marketing Tools** - Vouchers, loyalty points and referrals

### 🌐 Technology Stack
- **Backend**: Laravel 12, PHP 8.4 (8.2+ required), MySQL/MariaDB
- **Frontend**: Blade, TailwindCSS 4, Vite 6, SweetAlert2
- **Authentication**: Laravel Sanctum with 2FA support
- **Multilingual**: Spatie Laravel Translatable
- **Image Processing**: Intervention Image with optimization

## 📁 Quick Navigation

### For Developers
1. Start with [Tech Stack](TECH_STACK.md) to understand what is installed
2. Read the [Architecture & Audit Guide](ARCHITECTURE_AND_AUDIT_GUIDE.md) for code structure and the test environment
3. `database/migrations/` is the reference for the schema; `routes/web.php` and `routes/api.php` for endpoints
4. Follow [Production deploy](PRODUCTION_DEPLOY.md) and [TEST auto-deploy](TEST_AUTO_DEPLOY.md) for releases

### For Operations
1. Read [Marketplace](MARKETPLACE.md) for seller orders, commission and payouts
2. Check [Card payments with BML Connect](PAYMENTS_BML.md) for payment setup and reconciliation
3. Use [BML website requirements](BML_COMPLIANCE.md) when applying to BML

### For Stakeholders
1. Start with [Project Overview](PROJECT_OVERVIEW.md) for business context
2. Review [Marketplace](MARKETPLACE.md) for how shops, orders and payouts work

## 🚀 Getting Started

### Local Development
```bash
# Clone repository
git clone <repository-url>
cd iruali

# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Database setup
php artisan migrate --seed
php artisan storage:link

# Build assets and start server
npm run build
php artisan serve
```

### Admin Access
- **URL**: `/admin/dashboard`
- **Default Email**: admin@example.com (seeded outside production; override with `ADMIN_EMAIL`)
- **Default Password**: password (override with `ADMIN_PASSWORD`; `php artisan db:seed --class=UserSeeder` creates the production admin)

**⚠️ Important**: Change default credentials immediately after deployment.

## 🔗 External Resources

- **Laravel Documentation**: https://laravel.com/docs/12.x
- **TailwindCSS Documentation**: https://tailwindcss.com/docs
- **Spatie Packages**: https://spatie.be/open-source
- **BML Connect**: https://www.bankofmaldives.com.mv/ (merchant portal; see PAYMENTS_BML.md)

## 📞 Support & Contact

- **Technical Support**: tech@iruali.mv
- **Business Inquiries**: business@iruali.mv
- **Project Repository**: [Internal Repository]

## 📄 License

This project is proprietary software developed for the Iruali marketplace platform.

---

**Last Updated**: December 2024  
**Version**: 1.0.0  
**Laravel Version**: 12.x
