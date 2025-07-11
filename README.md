# Iru E-commerce Application

A modern, full-featured e-commerce application built with Laravel and Tailwind CSS.

## Features

### 🛍️ Product Management
- Product catalog with categories
- Product images and variants
- Sale pricing and discounts
- Stock management
- Product reviews and ratings

### 🛒 Shopping Experience
- Shopping cart functionality
- Wishlist management
- Product search and filtering
- Category browsing
- Responsive product listings

### 👤 User Management
- User registration and authentication
- User profiles and order history
- Secure login/logout system

### 💳 Order Processing
- Checkout process
- Order management
- Order tracking and status
- Shipping information

### 🎨 Modern UI/UX
- Responsive design with Tailwind CSS
- Modern, clean interface
- Mobile-friendly navigation
- Beautiful product displays

## Technology Stack

- **Backend**: Laravel 11 (PHP)
- **Frontend**: Blade templates with Tailwind CSS
- **Database**: MySQL/PostgreSQL
- **Authentication**: Laravel's built-in auth system
- **Styling**: Tailwind CSS with custom design system

## Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd iru-ecommerce
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

5. **Database setup**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

6. **Build frontend assets**
   ```bash
   npm run build
   ```

7. **Start the development server**
   ```bash
   php artisan serve
   ```

## Database Structure

### Core Models
- **User**: Customer accounts and authentication
- **Product**: Product catalog with variants
- **Category**: Product categorization
- **Cart/CartItem**: Shopping cart functionality
- **Order/OrderItem**: Order management
- **Wishlist**: User wishlists
- **Banner**: Promotional content
- **ProductImage**: Product media
- **ProductReview**: Customer reviews

### Key Relationships
- Users have carts, orders, and wishlists
- Products belong to categories and have images
- Orders contain multiple order items
- Carts contain multiple cart items

## Routes

### Public Routes
- `/` - Home page
- `/shop` - Product catalog
- `/products` - All products
- `/products/{product}` - Product details
- `/categories` - All categories
- `/categories/{category}` - Category products
- `/search` - Product search
- `/login` - User login
- `/register` - User registration

### Authenticated Routes
- `/cart` - Shopping cart
- `/wishlist` - User wishlist
- `/checkout` - Checkout process
- `/orders` - Order history
- `/orders/{order}` - Order details

## Features in Detail

### Product Catalog
- Browse products by category
- Search functionality with filters
- Product images and descriptions
- Sale pricing display
- Stock availability

### Shopping Cart
- Add/remove items
- Update quantities
- Calculate totals
- Persistent cart data

### User Authentication
- Secure registration and login
- Password protection
- Session management
- User profile management

### Order Management
- Complete checkout process
- Order confirmation
- Order history tracking
- Shipping information

### Wishlist
- Save favorite products
- Add to cart from wishlist
- Manage wishlist items

## Customization

### Styling
The application uses Tailwind CSS with a custom design system. Colors and styling can be modified in:
- `tailwind.config.js` - Design system configuration
- `resources/css/app.css` - Custom styles
- Blade templates in `resources/views/`

### Database
Database structure can be modified through Laravel migrations in `database/migrations/`.

### Features
New features can be added by:
1. Creating new models and migrations
2. Adding controllers and routes
3. Creating Blade views
4. Updating the navigation

## Development

### Running Tests
```bash
php artisan test
```

### Database Seeding
```bash
php artisan db:seed
```

### Asset Compilation
```bash
npm run dev    # Development
npm run build  # Production
```

## Deployment

1. Set up your production environment
2. Configure your web server (Apache/Nginx)
3. Set up your database
4. Run migrations and seeders
5. Configure environment variables
6. Build assets for production

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Support

For support and questions, please open an issue in the repository or contact the development team.

---

**Iru E-commerce** - Your trusted online shopping destination for quality products.
