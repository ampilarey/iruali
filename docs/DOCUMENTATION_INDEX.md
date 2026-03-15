# 📚 Iruali - Multi-Vendor E-commerce Platform Documentation

Welcome to the comprehensive documentation for the Iruali multi-vendor e-commerce platform. This documentation provides detailed information about the project architecture, features, deployment, and maintenance.

## 📋 Documentation Structure

### 🎯 Core Documentation
- **[Project Overview](PROJECT_OVERVIEW.md)** - Vision, goals, target audience, and key differentiators
- **[Tech Stack](TECH_STACK.md)** - Complete technology stack and dependencies
- **[Database Schema](DATABASE_SCHEMA.md)** - Database structure, relationships, and migrations

### 🚀 Implementation Guides
- **[Feature Specifications](FEATURE_SPECIFICATIONS.md)** - Detailed feature descriptions and requirements
- **[API Specification](API_SPECIFICATION.md)** - REST API endpoints and data formats
- **[Deployment Guide](DEPLOYMENT_GUIDE.md)** - Step-by-step deployment instructions

### 🔧 Technical References
- **[Authentication Guide](AUTHENTICATION_GUIDE.md)** - User authentication and authorization systems
- **[Multi-Vendor Management](MULTI_VENDOR_GUIDE.md)** - Seller onboarding and management processes
- **[E-commerce Features](ECOMMERCE_FEATURES.md)** - Shopping cart, checkout, and order processing

## 🏗️ Project Overview

**Iruali** is a comprehensive multi-vendor e-commerce platform built with Laravel, featuring:

### ✨ Key Features
- **Multi-Vendor Marketplace** - Complete seller onboarding and management system
- **Multilingual Support** - English and Dhivehi with full RTL support
- **Advanced Shopping Features** - Cart, wishlist, flash sales, and loyalty points
- **Order Management** - Complete order processing with tracking and notifications
- **Payment Integration** - Multiple payment gateway support
- **Admin Panel** - Comprehensive dashboard for platform management

### 🎨 Business Features
- **Seller Management** - Vendor onboarding, approval, and performance tracking
- **Product Management** - Advanced catalog with variants, reviews, and SEO
- **Order Processing** - Multi-status order management with notifications
- **Analytics Dashboard** - Sales, revenue, and performance insights
- **Marketing Tools** - Coupons, vouchers, and promotional campaigns

### 🌐 Technology Stack
- **Backend**: Laravel 12, PHP 8.2+, MySQL
- **Frontend**: TailwindCSS, Alpine.js, Vite
- **Authentication**: Laravel Sanctum with 2FA support
- **Multilingual**: Spatie Laravel Translatable
- **Image Processing**: Intervention Image with optimization

## 📁 Quick Navigation

### For Developers
1. Start with [Tech Stack](TECH_STACK.md) to understand the architecture
2. Review [Database Schema](DATABASE_SCHEMA.md) for data relationships
3. Check [API Specification](API_SPECIFICATION.md) for endpoints
4. Follow [Deployment Guide](DEPLOYMENT_GUIDE.md) for setup

### For Content Managers
1. Read [Multi-Vendor Management](MULTI_VENDOR_GUIDE.md) for seller processes
2. Check [Feature Specifications](FEATURE_SPECIFICATIONS.md) for functionality
3. Review [Authentication Guide](AUTHENTICATION_GUIDE.md) for user roles

### For Stakeholders
1. Start with [Project Overview](PROJECT_OVERVIEW.md) for business context
2. Review [Feature Specifications](FEATURE_SPECIFICATIONS.md) for capabilities
3. Check [Deployment Guide](DEPLOYMENT_GUIDE.md) for technical requirements

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
- **URL**: `/admin`
- **Default Email**: admin@iruali.mv
- **Default Password**: password

**⚠️ Important**: Change default credentials immediately after deployment.

## 🔗 External Resources

- **Laravel Documentation**: https://laravel.com/docs/12.x
- **TailwindCSS Documentation**: https://tailwindcss.com/docs
- **Alpine.js Documentation**: https://alpinejs.dev/
- **Spatie Packages**: https://spatie.be/open-source

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
