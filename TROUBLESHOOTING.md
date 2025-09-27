# Water Purifier ERP - Troubleshooting Guide

## Login Issues

### Common Login Problems and Solutions

#### 1. "An error occurred. Please try again."

**Possible Causes:**
- Database connection issues
- Missing database tables
- Incorrect database credentials
- PHP errors in the login script

**Solutions:**

1. **Check Database Connection:**
   - Visit `setup-database.php` to test database connection
   - Ensure MySQL is running
   - Verify database credentials in `config/database.php`

2. **Check Database Tables:**
   - Run the installation script: `install.php`
   - Ensure all required tables are created
   - Check if users table has data

3. **Check PHP Error Logs:**
   - Look for PHP errors in your server's error log
   - Enable error reporting in local environment

#### 2. "Database connection failed"

**Solutions:**
- Verify MySQL is running
- Check database credentials
- Ensure database `water_purifier_erp` exists
- Grant proper permissions to database user

#### 3. "Invalid credentials"

**Solutions:**
- Check if user exists in database
- Verify password is correct
- Ensure user status is 'active'
- Try resetting password

### Environment Detection

The system automatically detects whether it's running on:
- **Local machine** (localhost, 127.0.0.1, .local, .test, .dev domains)
- **Live server** (production environment)

### Database Setup Commands

```sql
-- Create database
CREATE DATABASE water_purifier_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user (optional)
CREATE USER 'erp_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON water_purifier_erp.* TO 'erp_user'@'localhost';
FLUSH PRIVILEGES;
```

### Testing Tools

1. **Database Setup Test:** `setup-database.php`
2. **Login System Test:** `test-login.php`
3. **Installation:** `install.php`

### Common Configuration Issues

#### Local Environment
- Database: `localhost`, `root`, no password
- Debug mode: Enabled
- Error reporting: Full

#### Production Environment
- Database: Environment variables or secure config
- Debug mode: Disabled
- Error reporting: Limited

### File Permissions

Ensure these files are readable:
- `config/database.php`
- `config/environment.php`
- `config/firebase.php`
- `includes/functions.php`

### Browser Console Errors

Check browser console for:
- JavaScript errors
- Network request failures
- CORS issues
- Missing files

### Server Requirements

- PHP 7.4+ (8.0+ recommended)
- MySQL 5.7+ (8.0+ recommended)
- Apache/Nginx with mod_rewrite
- PDO MySQL extension
- cURL extension

### Quick Fixes

1. **Clear browser cache**
2. **Restart web server**
3. **Check file permissions**
4. **Verify database connection**
5. **Run installation script**

### Support

If issues persist:
1. Check error logs
2. Verify all requirements
3. Test with provided tools
4. Check browser console
5. Verify database setup