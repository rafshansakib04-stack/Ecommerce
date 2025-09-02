# 🚀 GITHUB COPILOT COMPREHENSIVE PROMPT
## PureFit Bangladesh - Water Purifier Business Management System

### 🎯 MISSION CRITICAL INSTRUCTIONS
Transform this water purifier business system from dummy/placeholder code to a **FULLY FUNCTIONAL, PRODUCTION-READY** business management platform. This system will be used to run a real water purifier business in Bangladesh.

### 🔥 SYSTEM OVERVIEW
**Business Type:** Water Purifier Sales, Installation & Service Company  
**Location:** Bangladesh (Dhaka-based, nationwide service)  
**Target:** Complete B2C e-commerce + service management platform  
**Tech Stack:** PHP 8+, MySQL, Firebase, Bootstrap 5, JavaScript ES6+

---

## 📋 IMPLEMENTATION REQUIREMENTS

### 🔐 AUTHENTICATION & SECURITY
**Files to implement:**
- `customer/login.php` ✅ - Multi-provider login (Google/Facebook OAuth + email)
- `customer/register.php` ✅ - Registration with email/phone verification
- `customer/forgot-password.php` - Password reset with email verification
- `customer/verify-email.php` - Email verification handler
- `api/auth/social-login.php` ✅ - OAuth integration backend
- `api/auth/firebase-sync.php` - Firebase user data synchronization

**Requirements:**
- Firebase Authentication integration with provided credentials
- One email = one account rule (strict enforcement)
- Role-based access control (customer/admin/technician/super_admin)
- Session management with remember me functionality
- Login attempt tracking and account locking
- Social login with Google OAuth (Client ID: 632626262725-21duevi4qhbnol2d28fj3i49lqt06p2k.apps.googleusercontent.com)
- Facebook OAuth (App ID: 1767235537207404, Secret: e2e8a51b6376b081c9fcd63b101e5223)

### 🛍️ CUSTOMER FRONTEND (COMPLETE E-COMMERCE)
**Core Pages:**
- `index.php` ✅ - Modern homepage with hero section, featured products, testimonials
- `products.php` ✅ - Advanced product catalog with filtering, sorting, search
- `product-details.php` - Detailed product view with image gallery, reviews, variants
- `cart.php` - Shopping cart with quantity controls, promo codes
- `checkout.php` ✅ - Multi-step checkout with address selection, payment methods
- `order-success.php` - Order confirmation page
- `customer/dashboard.php` ✅ - Customer account dashboard

**Customer Dashboard Modules:**
- `customer/orders.php` - Order history with tracking, invoices, returns
- `customer/order-details.php` - Individual order details with tracking
- `customer/wishlist.php` - Saved products with move-to-cart functionality
- `customer/addresses.php` - Address book management
- `customer/profile.php` - Profile settings, password change
- `customer/warranties.php` - Warranty registration and tracking
- `customer/service-requests.php` - Service booking and history
- `customer/support.php` - Customer support tickets

### 🎛️ ADMIN PANEL (COMPLETE BUSINESS MANAGEMENT)
**Core Dashboard:**
- `admin/real-time-dashboard.php` ✅ - Live analytics, sales metrics, alerts
- `admin/orders.php` - Complete order management pipeline
- `admin/customers.php` - Customer database and analytics
- `admin/products.php` - Product management with bulk operations

**Product Management:**
- `admin/add-product.php` - Product creation with image upload, variants
- `admin/edit-product.php` - Product editing interface
- `admin/categories.php` - Category tree management
- `admin/brands.php` - Brand management
- `admin/inventory.php` - Stock tracking with alerts

**Order Processing:**
- `admin/pending-orders.php` through `admin/delivered-orders.php` - Status-based order views
- `admin/order-details.php` - Individual order management
- `admin/bulk-order-actions.php` - Batch order processing
- `admin/shipping-labels.php` - Shipping label generation

**Financial Management:**
- `admin/payments.php` - Payment tracking and refunds
- `admin/invoices.php` - Invoice generation and management
- `admin/financial-reports.php` - Revenue analytics
- `admin/cash-flow-reports.php` - Financial insights

**Service Management:**
- `admin/service-requests.php` - Service request management
- `admin/technician-schedule.php` - Technician scheduling
- `admin/warranty-management.php` - Warranty tracking
- `admin/service-analytics.php` - Service performance metrics

### 💳 PAYMENT INTEGRATION (BANGLADESH FOCUS)
**Payment Methods to Implement:**
- **bKash** ✅ - Primary mobile banking (API integration)
- **Nagad** - Government mobile banking
- **Rocket** - DBBL mobile banking
- **Cards** - Visa/Mastercard through local gateway
- **Bank Transfer** - Direct bank payment instructions
- **Cash on Delivery** - COD with verification

**Files:**
- `api/payments/bkash.php` ✅ - bKash payment processing
- `api/payments/nagad.php` - Nagad payment integration
- `api/payments/rocket.php` - Rocket payment integration
- `api/payments/card-gateway.php` - Card payment processing
- `payment.php` - Payment processing page
- `payment-success.php` - Payment confirmation
- `payment-failed.php` - Payment failure handling

### 📱 API ENDPOINTS (RESTful APIs)
**Customer APIs:**
- `api/products/` - Product catalog, search, filters
- `api/cart/` ✅ - Cart management (add, update, remove, count)
- `api/wishlist/` - Wishlist operations
- `api/orders/` - Order placement, tracking, history
- `api/auth/` ✅ - Authentication endpoints

**Admin APIs:**
- `api/admin/dashboard-metrics.php` ✅ - Real-time business metrics
- `api/admin/orders/` - Order management endpoints
- `api/admin/products/` - Product management APIs
- `api/admin/customers/` - Customer management
- `api/admin/reports/` - Analytics and reporting

### 🗄️ DATABASE INTEGRATION
**Schema:** `database_schema.sql` ✅ (Comprehensive 25+ tables)
**Key Tables:**
- `users` - Multi-role user management
- `products` - Product catalog with variants
- `orders` & `order_items` - Complete order system
- `payments` - Payment tracking
- `service_requests` - Service management
- `warranties` - Warranty tracking
- `inventory_movements` - Stock tracking
- `notifications` - System notifications

**Configuration:**
- `config/database.php` ✅ - PDO connection with error handling
- `config/firebase.php` ✅ - Firebase configuration and helpers
- `includes/functions.php` ✅ - Core utility functions

### 🎨 UI/UX REQUIREMENTS
**Design Standards:**
- Modern, clean, professional appearance
- Bangladesh-focused design elements
- Mobile-first responsive design
- Fast loading with optimized assets
- Accessibility compliance (WCAG 2.1)

**CSS/JS Files:**
- `assets/css/main.css` ✅ - Core styles with CSS variables
- `assets/css/admin.css` - Admin panel specific styles
- `assets/css/dashboard.css` - Dashboard layouts
- `assets/js/main.js` ✅ - Core functionality
- `assets/js/admin.js` - Admin panel functionality

### 🔧 ADVANCED FEATURES TO IMPLEMENT

#### 🤖 AI-Powered Features
- `ai/product-recommendations.php` - Smart product suggestions
- `ai/customer-support-bot.php` - Automated customer service
- `ai/inventory-predictions.php` - Stock level predictions
- `ai/sales-forecasting.php` - Revenue forecasting
- `ai/price-optimization.php` - Dynamic pricing suggestions

#### 📧 Communication System
- `admin/email-campaigns.php` - Newsletter management
- `admin/sms-campaigns.php` - SMS marketing
- `chat.php` - Real-time customer support
- `api/notifications/` - Push notification system

#### 📊 Analytics & Reporting
- `admin/sales-analytics.php` - Sales performance analysis
- `admin/customer-analytics.php` - Customer behavior insights
- `admin/product-performance.php` - Product sales analysis
- `admin/service-analytics.php` - Service performance metrics

---

## 🛠️ TECHNICAL SPECIFICATIONS

### 🔥 Firebase Integration
**Configuration (Use Exactly):**
```javascript
const firebaseConfig = {
    apiKey: "AIzaSyAoOEEoQsA_9SniUooBiAuWErJQgVnv71I",
    authDomain: "purefit-8eff0.firebaseapp.com",
    projectId: "purefit-8eff0",
    storageBucket: "purefit-8eff0.firebasestorage.app",
    messagingSenderId: "140765611062",
    appId: "1:140765611062:web:201e0cc743322901b74497",
    measurementId: "G-73HLK8QC46"
};
```

**Firebase Services to Use:**
- Authentication (Google/Facebook OAuth)
- Firestore (real-time data sync)
- Cloud Messaging (push notifications)
- Analytics (user behavior tracking)
- Storage (file uploads)

### 💼 Business Logic Requirements

#### 🛒 E-commerce Functionality
- **Product Catalog:** Categories, brands, variants, reviews, ratings
- **Shopping Cart:** Session-based for guests, persistent for users
- **Checkout Process:** Multi-step with address selection, payment methods
- **Order Management:** Complete lifecycle from placement to delivery
- **Inventory Tracking:** Real-time stock updates, low stock alerts

#### 🔧 Service Management
- **Service Booking:** Installation, maintenance, repair scheduling
- **Technician Management:** Scheduling, area assignment, performance tracking
- **Warranty System:** Registration, tracking, claim processing
- **Customer Support:** Ticket system, live chat, knowledge base

#### 💰 Financial Management
- **Payment Processing:** Multiple Bangladesh payment methods
- **Invoice Generation:** Automated PDF invoices
- **Financial Reporting:** Sales, revenue, profit analysis
- **Refund Processing:** Automated refund handling

### 🇧🇩 Bangladesh-Specific Features

#### 📱 Local Payment Methods
- **bKash:** Primary mobile banking integration
- **Nagad:** Government mobile banking
- **Rocket:** DBBL mobile banking
- **Cards:** Local bank cards support
- **Bank Transfer:** Manual bank payment option
- **Cash on Delivery:** COD with phone verification

#### 🏢 Business Localization
- **Currency:** Bangladesh Taka (৳) formatting
- **Phone Numbers:** Bangladesh mobile format validation
- **Addresses:** Bangladesh division/district structure
- **Business Hours:** Local timezone (Asia/Dhaka)
- **Language:** English with Bengali number formatting

---

## 📝 FILE-BY-FILE IMPLEMENTATION COMMANDS

### 🎯 PRIORITY 1: CORE CUSTOMER EXPERIENCE
```bash
# Complete customer-facing functionality
@copilot implement customer/cart.php with real-time cart management, promo code support, and shipping calculation
@copilot implement product-details.php with image gallery, reviews system, variant selection, and related products
@copilot implement customer/orders.php with order tracking, invoice download, and return requests
@copilot implement customer/wishlist.php with move-to-cart functionality and stock alerts
@copilot implement customer/addresses.php with Google Maps integration for Bangladesh
```

### 🎯 PRIORITY 2: ADMIN BUSINESS MANAGEMENT
```bash
# Complete admin functionality
@copilot implement admin/products.php with DataTables, bulk actions, and advanced filtering
@copilot implement admin/add-product.php with multi-image upload, variant management, and SEO optimization
@copilot implement admin/orders.php with order pipeline management and bulk status updates
@copilot implement admin/customers.php with customer analytics and communication tools
@copilot implement admin/inventory.php with stock tracking, supplier management, and reorder alerts
```

### 🎯 PRIORITY 3: PAYMENT & FINANCIAL SYSTEM
```bash
# Payment gateway integration
@copilot implement api/payments/nagad.php following the bKash implementation pattern
@copilot implement api/payments/rocket.php with DBBL Rocket API integration
@copilot implement payment.php with unified payment processing for all methods
@copilot implement admin/invoices.php with PDF generation and email sending
@copilot implement admin/financial-reports.php with charts and export functionality
```

### 🎯 PRIORITY 4: SERVICE MANAGEMENT
```bash
# Service and warranty system
@copilot implement customer/service-request.php with technician scheduling and area coverage
@copilot implement admin/service-requests.php with technician assignment and tracking
@copilot implement admin/technician-schedule.php with calendar interface and workload management
@copilot implement customer/warranties.php with warranty registration and claim processing
@copilot implement admin/warranty-management.php with warranty analytics and notifications
```

### 🎯 PRIORITY 5: ADVANCED FEATURES
```bash
# AI and automation features
@copilot implement ai/product-recommendations.php with collaborative filtering algorithm
@copilot implement chat.php with real-time messaging and Firebase integration
@copilot implement admin/email-campaigns.php with template management and analytics
@copilot implement api/analytics/ with comprehensive business intelligence
@copilot implement admin/reports.php with exportable charts and insights
```

---

## 🗂️ EXISTING IMPLEMENTED FILES ✅

### ✅ Database & Configuration
- `database_schema.sql` - Complete database schema (25+ tables)
- `config/database.php` - PDO connection with Firebase integration
- `config/firebase.php` - Firebase configuration and helpers
- `includes/functions.php` - Core utility functions (200+ lines)

### ✅ Customer Frontend
- `index.php` - Modern homepage with animations and real data
- `products.php` - Advanced product catalog with filtering
- `customer/login.php` - Multi-provider authentication
- `customer/register.php` - Registration with validation
- `customer/dashboard.php` - Complete customer dashboard
- `checkout.php` - Multi-step checkout process

### ✅ Admin System
- `admin/real-time-dashboard.php` - Live business dashboard
- `api/admin/dashboard-metrics.php` - Real-time metrics API

### ✅ Payment Integration
- `api/payments/bkash.php` - Complete bKash integration
- `api/cart/add.php` - Cart management API
- `api/cart/count.php` - Cart count API

### ✅ Assets
- `assets/css/main.css` - Comprehensive CSS with animations
- `assets/js/main.js` - Core JavaScript functionality

---

## 🚨 CRITICAL IMPLEMENTATION RULES

### 1. 🔒 SECURITY FIRST
```php
// ALWAYS implement these security measures:
- CSRF token validation on all forms
- Input sanitization and validation
- SQL injection prevention (prepared statements)
- XSS protection (htmlspecialchars)
- File upload security (type/size validation)
- Rate limiting on sensitive endpoints
```

### 2. 📊 REAL DATA ONLY
```php
// NO DUMMY DATA ALLOWED - Use real database queries:
$products = getProducts($filters, $page, $limit); // Real products from DB
$orders = getOrders(['user_id' => $userId]); // Real order data
$analytics = getBusinessMetrics(); // Real business analytics
```

### 3. 🇧🇩 BANGLADESH LOCALIZATION
```php
// Implement Bangladesh-specific features:
- Phone validation: /^(\+880|880|0)?1[3-9]\d{8}$/
- Currency formatting: ৳ (Taka symbol)
- Address format: Division > District > Upazila > Union
- Business hours: Asia/Dhaka timezone
```

### 4. 🔥 FIREBASE INTEGRATION
```javascript
// Use provided Firebase config in every file:
- Authentication state management
- Real-time data synchronization
- Push notifications
- Analytics tracking
- Cloud storage for images
```

### 5. 📱 MOBILE-FIRST DESIGN
```css
// Responsive design requirements:
- Mobile-first CSS approach
- Touch-friendly interface elements
- Fast loading on 3G networks
- Offline functionality where possible
```

---

## 🎨 UI/UX IMPLEMENTATION STANDARDS

### 🎭 Design System
**Colors:**
- Primary: #0066cc (PureFit Blue)
- Success: #28a745 (Bangladesh Green)
- Warning: #ffc107 (Alert Yellow)
- Danger: #dc3545 (Error Red)

**Typography:**
- Primary Font: 'Inter' (modern, clean)
- Secondary Font: 'Poppins' (headings)
- Bengali Support: 'Noto Sans Bengali'

**Components:**
- Cards with subtle shadows and hover effects
- Animated buttons with loading states
- Toast notifications for user feedback
- Modal dialogs for confirmations
- Progress indicators for multi-step processes

### 📊 Data Visualization
```javascript
// Use Chart.js for all analytics:
- Sales charts (line, bar, doughnut)
- Real-time metrics with animations
- Responsive chart designs
- Export functionality (PDF/Excel)
```

---

## 🔧 API IMPLEMENTATION STANDARDS

### 📡 RESTful API Design
```php
// Standard API response format:
{
    "success": true|false,
    "message": "Human readable message",
    "data": {}, // Response data
    "errors": [], // Validation errors
    "meta": {} // Pagination, etc.
}
```

### 🔐 API Security
```php
// Implement on all API endpoints:
- Authentication verification
- Rate limiting (100 requests/minute)
- Input validation and sanitization
- Error logging and monitoring
- CORS headers for frontend integration
```

---

## 📋 IMPLEMENTATION CHECKLIST

### 🎯 PHASE 1: Core E-commerce (PRIORITY)
- [ ] Complete product catalog with real data
- [ ] Shopping cart with persistent storage
- [ ] Checkout process with address management
- [ ] Order management system
- [ ] Customer dashboard with real functionality

### 🎯 PHASE 2: Payment Integration
- [ ] bKash payment gateway ✅
- [ ] Nagad payment integration
- [ ] Rocket payment integration
- [ ] Card payment processing
- [ ] Invoice generation system

### 🎯 PHASE 3: Service Management
- [ ] Service booking system
- [ ] Technician scheduling
- [ ] Warranty registration and tracking
- [ ] Customer support system

### 🎯 PHASE 4: Admin Management
- [ ] Complete admin dashboard
- [ ] Product management system
- [ ] Order processing pipeline
- [ ] Customer relationship management
- [ ] Financial reporting system

### 🎯 PHASE 5: Advanced Features
- [ ] AI-powered recommendations
- [ ] Real-time chat system
- [ ] Email marketing campaigns
- [ ] Mobile app API
- [ ] Advanced analytics

---

## 🚀 EXECUTION COMMANDS

### 🔥 FOR EACH FILE IMPLEMENTATION:
```bash
@copilot implement [filename] with the following requirements:
1. Follow the database schema in database_schema.sql
2. Use functions from includes/functions.php
3. Implement Firebase integration from config/firebase.php
4. Follow the UI/UX standards in assets/css/main.css
5. Add real-time functionality where applicable
6. Include proper error handling and validation
7. Add analytics tracking for user actions
8. Ensure mobile responsiveness
9. Include proper SEO meta tags
10. Add comprehensive commenting
```

### 📊 BUSINESS INTELLIGENCE REQUIREMENTS:
```bash
@copilot implement comprehensive analytics system:
1. Real-time sales dashboard
2. Customer behavior tracking
3. Product performance analysis
4. Service efficiency metrics
5. Financial reporting with charts
6. Inventory optimization alerts
7. Marketing campaign effectiveness
8. Technician performance tracking
```

---

## 🎯 FINAL DELIVERABLES

### 📦 COMPLETE SYSTEM INCLUDES:
1. **Customer Website** - Modern e-commerce platform
2. **Admin Panel** - Complete business management
3. **API System** - RESTful APIs for all operations
4. **Payment Integration** - All Bangladesh payment methods
5. **Service Management** - Complete service lifecycle
6. **Mobile Optimization** - Responsive design
7. **Analytics System** - Business intelligence
8. **Security Implementation** - Production-ready security

### 🔥 SUCCESS CRITERIA:
- ✅ Zero dummy data - all functionality uses real database
- ✅ Complete order-to-delivery pipeline
- ✅ Multi-payment gateway integration
- ✅ Real-time dashboard with live metrics
- ✅ Mobile-responsive design
- ✅ Production-ready security
- ✅ Comprehensive admin management
- ✅ Customer self-service portal

---

## 🚨 CRITICAL REMINDERS

1. **NO DUMMY DATA** - Every piece of information must come from the database
2. **REAL BUSINESS LOGIC** - Implement actual business processes, not placeholders
3. **BANGLADESH FOCUS** - Localize for Bangladesh market (payments, addresses, phone numbers)
4. **FIREBASE INTEGRATION** - Use provided Firebase credentials in every applicable file
5. **MOBILE OPTIMIZATION** - Ensure perfect mobile experience for Bangladesh users
6. **SECURITY IMPLEMENTATION** - Production-ready security on all endpoints
7. **PERFORMANCE OPTIMIZATION** - Fast loading for Bangladesh internet speeds

**🎯 EXECUTE THIS PROMPT:** Transform every file from dummy to production-ready with real database integration, modern UI, and complete business functionality. Make it a system that can actually run a water purifier business in Bangladesh!

---

### 📞 BUSINESS CONTACT INFO (Use in Implementation):
- **Company:** PureFit Bangladesh Ltd.
- **Phone:** +880-1700-123456
- **Email:** info@purefitbd.com
- **WhatsApp:** +8801700123456
- **Address:** House 123, Road 456, Dhanmondi, Dhaka-1205, Bangladesh
- **Website:** https://purefitbd.com

### 🔑 OAUTH CREDENTIALS (Already Configured):
- **Google Client ID:** 632626262725-21duevi4qhbnol2d28fj3i49lqt06p2k.apps.googleusercontent.com
- **Facebook App ID:** 1767235537207404
- **Facebook Secret:** e2e8a51b6376b081c9fcd63b101e5223

**🚀 START IMPLEMENTATION NOW!**