# 🚀 PureFit Bangladesh - Production Deployment Guide

## 🎯 Complete Setup Instructions for Live Business

### 📋 Pre-Deployment Checklist

#### 🔧 Server Requirements
- **PHP 8.0+** with extensions: PDO, MySQL, cURL, GD, OpenSSL, JSON
- **MySQL 8.0+** or MariaDB 10.5+
- **Apache/Nginx** with mod_rewrite enabled
- **SSL Certificate** (Let's Encrypt recommended)
- **Minimum 2GB RAM** and 20GB storage

#### 🔐 Required Credentials
- **Database credentials** (MySQL)
- **Firebase project credentials** ✅ (Already configured)
- **bKash merchant account** (for payments)
- **Nagad merchant account** (for payments)
- **Email SMTP credentials** (Gmail/SendGrid)
- **SMS gateway API** (SSL Wireless/Robi)

---

## 🗄️ Database Setup

### 1. Create Database
```sql
CREATE DATABASE purefit_business CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Import Schema
```bash
mysql -u username -p purefit_business < database_schema.sql
```

### 3. Verify Tables
```sql
USE purefit_business;
SHOW TABLES;
-- Should show 25+ tables including users, products, orders, etc.
```

---

## ⚙️ Configuration Setup

### 1. Database Configuration
Edit `config/database.php`:
```php
// Production Database Settings
define('DB_HOST', 'your-production-host');
define('DB_NAME', 'purefit_business');
define('DB_USER', 'your-db-username');
define('DB_PASS', 'your-secure-password');

// Application Settings
define('APP_ENV', 'production');
define('APP_DEBUG', false);
define('APP_URL', 'https://purefitbd.com');
```

### 2. Firebase Configuration ✅
Firebase is already configured with your credentials:
- **Project ID:** purefit-8eff0
- **Google OAuth:** 632626262725-21duevi4qhbnol2d28fj3i49lqt06p2k.apps.googleusercontent.com
- **Facebook OAuth:** 1767235537207404

### 3. Payment Gateway Setup

#### bKash Configuration
```php
define('BKASH_APP_KEY', 'your-production-app-key');
define('BKASH_APP_SECRET', 'your-production-app-secret');
define('BKASH_USERNAME', 'your-merchant-username');
define('BKASH_PASSWORD', 'your-merchant-password');
define('BKASH_BASE_URL', 'https://tokenized.pay.bka.sh/v1.2.0-beta');
```

#### Nagad Configuration
```php
define('NAGAD_MERCHANT_ID', 'your-nagad-merchant-id');
define('NAGAD_MERCHANT_PRIVATE_KEY', 'your-private-key');
define('NAGAD_PGP_PUBLIC_KEY', 'nagad-pgp-public-key');
```

### 4. Email Configuration
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'purefitbd@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('FROM_EMAIL', 'noreply@purefitbd.com');
```

### 5. SMS Gateway Setup
```php
define('SMS_API_URL', 'https://smsplus.sslwireless.com/api/v3/send-sms');
define('SMS_API_TOKEN', 'your-sms-api-token');
define('SMS_SENDER_ID', 'PureFit');
```

---

## 🔒 Security Configuration

### 1. Generate Security Keys
```php
// Generate random 32-character keys
define('JWT_SECRET', 'your-32-char-jwt-secret-key-here');
define('ENCRYPTION_KEY', 'your-32-char-encryption-key-here');
```

### 2. File Permissions
```bash
# Set proper permissions
chmod 755 /var/www/html
chmod 755 admin/ customer/ api/ includes/ config/
chmod 777 uploads/ cache/ logs/
chmod 644 *.php
chmod 600 config/database.php
```

### 3. Apache Security (.htaccess)
```apache
# Root .htaccess
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Strict-Transport-Security "max-age=63072000"
```

---

## 🚀 Production Deployment Steps

### Step 1: Upload Files
```bash
# Upload all files to your web server
rsync -avz --exclude='.git' ./ user@server:/var/www/html/
```

### Step 2: Run Setup Script
1. Visit `https://yourdomain.com/setup.php`
2. Complete all setup steps:
   - ✅ Database connection test
   - ✅ Firebase configuration test  
   - ✅ File permissions check
   - ✅ Sample data loading
   - ✅ Admin account creation

### Step 3: Security Hardening
```bash
# Remove setup files
rm setup.php
rm -rf api/setup/

# Set production environment
echo "APP_ENV=production" >> .env
```

### Step 4: SSL Configuration
```bash
# Install Let's Encrypt SSL
certbot --apache -d purefitbd.com -d www.purefitbd.com
```

---

## 🔧 Post-Deployment Configuration

### 1. Admin Account Setup
- Login to `/admin/real-time-dashboard.php`
- Complete admin profile setup
- Configure system settings
- Set up email templates
- Configure business information

### 2. Product Catalog Setup
- Add product categories
- Upload product images
- Set up product variants
- Configure pricing and inventory
- Enable featured products

### 3. Payment Gateway Testing
- Test bKash sandbox payments
- Verify webhook endpoints
- Test refund functionality
- Configure payment notifications

### 4. Email System Setup
- Test email delivery
- Configure email templates
- Set up automated notifications
- Test password reset emails

---

## 📊 System Monitoring

### 1. Error Logging
```php
// Enable error logging in production
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/purefit/errors.log');
```

### 2. Performance Monitoring
- Set up Google Analytics
- Configure Firebase Analytics
- Monitor page load times
- Track conversion rates

### 3. Database Monitoring
- Monitor query performance
- Set up automated backups
- Configure replication (if needed)
- Monitor disk space usage

---

## 🔄 Maintenance Tasks

### Daily Tasks
- Monitor order processing
- Check payment gateway status
- Review error logs
- Backup database

### Weekly Tasks  
- Analyze sales reports
- Review customer feedback
- Update inventory levels
- Check system performance

### Monthly Tasks
- Security updates
- Performance optimization
- Feature updates
- Business analytics review

---

## 📱 Mobile App Integration

### API Endpoints Ready
All RESTful APIs are ready for mobile app integration:
- Authentication APIs
- Product catalog APIs
- Shopping cart APIs
- Order management APIs
- Payment processing APIs

### Firebase Integration
- Real-time data synchronization
- Push notifications
- User authentication
- Analytics tracking

---

## 🎯 Business Operations

### Order Processing Workflow
1. **Order Placement** - Customer places order online
2. **Payment Processing** - Automatic payment verification
3. **Order Confirmation** - Email/SMS notifications sent
4. **Inventory Update** - Stock levels automatically updated
5. **Fulfillment** - Order picked and packed
6. **Shipping** - Delivery tracking activated
7. **Installation** - Technician scheduled (for applicable products)
8. **Follow-up** - Customer satisfaction survey

### Service Management Workflow
1. **Service Request** - Customer books service online
2. **Technician Assignment** - Automatic assignment based on area
3. **Scheduling** - Customer chooses preferred time slot
4. **Service Execution** - Technician completes service
5. **Quality Check** - Customer feedback and rating
6. **Follow-up** - Maintenance reminders and offers

---

## 🆘 Troubleshooting

### Common Issues

#### Database Connection Errors
```bash
# Check MySQL service
systemctl status mysql

# Check credentials
mysql -u username -p -e "SELECT 1"
```

#### File Upload Issues
```bash
# Check directory permissions
ls -la uploads/
chmod 777 uploads/ -R
```

#### Firebase Authentication Issues
- Verify OAuth redirect URLs
- Check Firebase project settings
- Validate API keys

#### Payment Gateway Issues
- Test with sandbox credentials first
- Check webhook URLs
- Verify merchant account status

---

## 📞 Support Contacts

### Technical Support
- **System Admin:** admin@purefitbd.com
- **Developer:** Available through GitHub issues
- **Emergency:** +880-1700-123456

### Business Support
- **Sales:** sales@purefitbd.com
- **Service:** service@purefitbd.com
- **General:** info@purefitbd.com

---

## 🎉 Go Live Checklist

### ✅ Pre-Launch
- [ ] Database imported and tested
- [ ] All configurations updated
- [ ] Payment gateways tested
- [ ] Email system working
- [ ] SSL certificate installed
- [ ] Admin account created
- [ ] Sample products added
- [ ] Mobile responsiveness verified

### ✅ Launch Day
- [ ] DNS pointed to new server
- [ ] Monitoring systems active
- [ ] Backup systems running
- [ ] Support team notified
- [ ] Social media updated
- [ ] Google Analytics tracking

### ✅ Post-Launch
- [ ] Monitor system performance
- [ ] Track user registrations
- [ ] Monitor payment processing
- [ ] Review error logs
- [ ] Customer feedback collection

---

## 🚀 Success Metrics

### Business KPIs to Track
- **Daily Orders** - Target: 10+ orders/day
- **Conversion Rate** - Target: 2-5%
- **Average Order Value** - Target: ৳15,000+
- **Customer Retention** - Target: 60%+
- **Service Satisfaction** - Target: 4.5+ stars

### Technical KPIs
- **Page Load Time** - Target: <3 seconds
- **Uptime** - Target: 99.9%
- **API Response Time** - Target: <500ms
- **Error Rate** - Target: <0.1%

---

**🎯 Your PureFit Bangladesh water purifier business management system is now ready for production use!**

**📧 Need Help?** Contact admin@purefitbd.com for technical support.