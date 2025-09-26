# Water Purifier ERP System

A comprehensive Enterprise Resource Planning (ERP) system designed specifically for water purifier sales and service management. Built with PHP 8.4.1, MySQL 8.0, and Firebase for real-time features.

## 🚀 Features

### System Architecture
- **Backend**: PHP 8.4.1 with PDO for database operations
- **Database**: MySQL 8.0 + Firebase Realtime Database
- **Frontend**: AJAX-powered responsive interface with Bootstrap 5
- **AI Integration**: Fully integrated AI assistant for all user roles
- **Communication**: SMTP email + SMS gateway integration
- **Real-time**: Firebase for live updates and notifications

### 🔴 Admin Panel Features
- **Dashboard & Analytics**: Real-time statistics, revenue charts, service performance metrics
- **User Management**: Complete customer and technician management with auto-credential generation
- **Sales Management**: Product catalog, sales orders, quotations, invoice generation
- **Service Management**: Service request processing, technician assignment, ticket tracking
- **Inventory Management**: Stock management, alerts, transfers, barcode system
- **Financial Management**: Daily cashbook, ledger management, payroll processing
- **Reports & Analytics**: Comprehensive reporting system with export capabilities
- **AI Integration**: Smart recommendations, predictive analytics, fraud detection

### 🟢 Customer Panel Features
- **Dashboard**: Service summary, upcoming services, payment status
- **Service Management**: Request services, track status, provide feedback
- **Communication**: Real-time chat with technicians and admin
- **Account Management**: Profile updates, service preferences, payment methods
- **Reports & History**: Complete service and payment history

### 🔵 Technician Panel Features
- **Dashboard**: Task overview, route optimization, performance metrics
- **Task Management**: Service assignments, customer information, GPS navigation
- **Inventory Management**: Parts requisition, stock checking, returns
- **Billing & Payments**: Service billing, labor charges, customer payments
- **Daily Cashbook**: Expense tracking, collection management
- **Communication**: Customer chat, admin messages, status updates

## 📋 Installation

### Prerequisites
- PHP 8.4.1 or higher
- MySQL 8.0 or higher
- Web server (Apache/Nginx)
- Firebase project for real-time features
- SMTP server for email functionality
- SMS gateway API for notifications

### Setup Instructions

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd water-purifier-erp
   ```

2. **Database Setup**
   ```bash
   # Create database
   mysql -u root -p
   CREATE DATABASE water_purifier_erp;
   
   # Import schema
   mysql -u root -p water_purifier_erp < database/schema.sql
   ```

3. **Configuration**
   ```bash
   # Update database configuration
   nano config/database.php
   
   # Update Firebase configuration
   nano config/firebase.php
   
   # Update email settings in includes/functions.php
   ```

4. **File Permissions**
   ```bash
   chmod 755 uploads/
   chmod 755 uploads/service_attachments/
   chmod 755 uploads/receipts/
   ```

5. **Web Server Configuration**
   
   **Apache (.htaccess)**
   ```apache
   RewriteEngine On
   RewriteCond %{REQUEST_FILENAME} !-f
   RewriteCond %{REQUEST_FILENAME} !-d
   RewriteRule ^(.*)$ index.php [QSA,L]
   ```

   **Nginx**
   ```nginx
   location / {
       try_files $uri $uri/ /index.php?$query_string;
   }
   ```

## 🔧 Configuration

### Database Configuration
Update `config/database.php` with your database credentials:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'water_purifier_erp');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### Firebase Configuration
Update `config/firebase.php` with your Firebase project details:
```php
define('FIREBASE_PROJECT_ID', 'your-project-id');
define('FIREBASE_API_KEY', 'your-api-key');
define('FIREBASE_AUTH_DOMAIN', 'your-project.firebaseapp.com');
define('FIREBASE_DATABASE_URL', 'https://your-project-default-rtdb.firebaseio.com/');
```

### Email Configuration
Update SMTP settings in `includes/functions.php`:
```php
function sendEmail($to, $subject, $message, $isHTML = true) {
    // Configure your SMTP settings here
}
```

### SMS Configuration
Update SMS gateway settings in `includes/functions.php`:
```php
function sendSMS($phone, $message) {
    // Configure your SMS gateway API here
}
```

## 🎯 Default Login Credentials

### Admin Account
- **Username**: admin
- **Password**: password
- **Email**: admin@waterpurifiererp.com

> **Important**: Change the default password immediately after installation!

## 📱 Mobile Responsiveness

The system is built with a mobile-first approach:
- Responsive Bootstrap 5 design
- Touch-friendly interfaces
- Optimized for smartphones and tablets
- Offline capability for technicians
- GPS integration for location services

## 🤖 AI Integration

### AI Assistant Features
- **Natural Language Processing**: Chat with AI for system help
- **Smart Recommendations**: Parts ordering, pricing suggestions
- **Predictive Analytics**: Customer service schedules, demand forecasting
- **Fraud Detection**: Unusual transaction pattern alerts
- **Performance Insights**: Automated analysis and recommendations

### AI Context Awareness
- **Admin**: Dashboard insights, customer analytics, revenue reports
- **Customer**: Service status, account information, general help
- **Technician**: Task information, performance metrics, guidelines

## 🔄 Real-time Features

### Firebase Integration
- **Live Notifications**: Real-time updates for all users
- **Chat System**: Instant messaging between users
- **Status Updates**: Live service request status changes
- **Location Tracking**: Real-time technician GPS coordinates
- **Inventory Updates**: Live stock level changes

### WebSocket Support
- Real-time dashboard updates
- Live chat functionality
- Instant notifications
- Collaborative features

## 📊 Reporting System

### Available Reports
- **Sales Reports**: Daily, monthly, yearly sales analysis
- **Service Reports**: Completion rates, customer satisfaction
- **Financial Reports**: P&L, balance sheet, cash flow
- **Customer Reports**: Service history, payment status
- **Technician Reports**: Performance, earnings, efficiency
- **Inventory Reports**: Stock levels, movement, valuation

### Export Formats
- PDF reports with company branding
- Excel/CSV for data analysis
- Email reports with scheduling
- Print-friendly formats

## 🔐 Security Features

### Authentication & Authorization
- **Role-based Access Control**: Admin, Customer, Technician roles
- **Session Management**: Secure login sessions with timeout
- **Password Security**: Bcrypt hashing, password policies
- **Two-Factor Authentication**: SMS verification support
- **Remember Me**: Secure token-based remember functionality

### Data Protection
- **Input Sanitization**: All user inputs are sanitized
- **SQL Injection Prevention**: Prepared statements throughout
- **XSS Protection**: Output escaping and validation
- **CSRF Protection**: Token-based request validation
- **File Upload Security**: Type and size validation

### Audit Trail
- **Activity Logging**: All user actions are logged
- **IP Tracking**: User IP addresses recorded
- **Change History**: Track all data modifications
- **Access Logs**: Login/logout tracking

## 🚀 Performance Optimization

### Database Optimization
- **Indexed Queries**: Optimized database indexes
- **Query Caching**: Frequently used queries cached
- **Connection Pooling**: Efficient database connections
- **Data Pagination**: Large datasets paginated

### Frontend Optimization
- **AJAX Loading**: Dynamic content loading
- **Image Optimization**: Compressed images and lazy loading
- **CSS/JS Minification**: Minified assets for faster loading
- **CDN Support**: Content delivery network ready

## 📈 Scalability

### Horizontal Scaling
- **Load Balancer Ready**: Multiple server support
- **Database Clustering**: MySQL cluster support
- **Session Storage**: Redis/Memcached support
- **File Storage**: Cloud storage integration ready

### Vertical Scaling
- **Resource Optimization**: Efficient memory usage
- **Caching Strategy**: Multi-level caching
- **Database Optimization**: Query optimization
- **Code Optimization**: Efficient algorithms

## 🔧 Maintenance

### Regular Maintenance Tasks
1. **Database Backup**: Automated daily backups
2. **Log Rotation**: Automatic log file rotation
3. **Cache Clearing**: Regular cache cleanup
4. **Security Updates**: Regular security patches
5. **Performance Monitoring**: System performance tracking

### Monitoring
- **Error Logging**: Comprehensive error tracking
- **Performance Metrics**: Response time monitoring
- **User Activity**: Usage analytics
- **System Health**: Server health monitoring

## 📞 Support

### Documentation
- **API Documentation**: Complete API reference
- **User Manuals**: Role-specific user guides
- **Video Tutorials**: Step-by-step video guides
- **FAQ Section**: Frequently asked questions

### Technical Support
- **Email Support**: support@waterpurifiererp.com
- **Phone Support**: +91-9876543210
- **Live Chat**: In-system chat support
- **Ticket System**: Issue tracking system

## 🔄 Updates & Versioning

### Version Control
- **Git Repository**: Full version control
- **Release Notes**: Detailed update logs
- **Backward Compatibility**: Version compatibility
- **Migration Scripts**: Database migration tools

### Update Process
1. **Backup**: Full system backup before updates
2. **Testing**: Staging environment testing
3. **Deployment**: Production deployment
4. **Verification**: Post-deployment verification
5. **Rollback**: Quick rollback capability

## 📄 License

This project is proprietary software. All rights reserved.

## 🤝 Contributing

This is a proprietary system. For feature requests or bug reports, please contact the development team.

## 📧 Contact

- **Email**: info@waterpurifiererp.com
- **Website**: https://waterpurifiererp.com
- **Phone**: +91-9876543210

---

**Water Purifier ERP System** - Complete Sales & Service Management Solution