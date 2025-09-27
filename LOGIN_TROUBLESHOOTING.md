# Login Troubleshooting Guide

## Quick Fix Steps

### Step 1: Reset Admin Password
1. Visit: `reset-admin.php`
2. This will create/reset the admin user with default credentials
3. Default credentials: **admin** / **admin123**

### Step 2: Test Login System
1. Visit: `debug-login.php` - Full system test
2. Visit: `test-api-login.php` - Test API directly
3. Visit: `test-login.php` - Simple login test

### Step 3: Check Database
1. Visit: `setup-database.php` - Test database connection
2. Ensure MySQL is running
3. Check if database `water_purifier_erp` exists

## Common Issues and Solutions

### Issue 1: "An error occurred. Please try again."

**Causes:**
- Database connection failed
- Missing database tables
- PHP errors in login script
- Missing admin user

**Solutions:**
1. **Check Database:**
   ```sql
   CREATE DATABASE water_purifier_erp;
   ```

2. **Run Installation:**
   - Visit `install.php`
   - Fill in the form
   - Create admin user

3. **Reset Admin User:**
   - Visit `reset-admin.php`
   - This creates admin user with password `admin123`

### Issue 2: "Invalid credentials"

**Causes:**
- Wrong username/password
- User doesn't exist
- User is inactive
- Password hash mismatch

**Solutions:**
1. **Check User Exists:**
   ```sql
   SELECT * FROM users WHERE username = 'admin';
   ```

2. **Reset Password:**
   - Visit `reset-admin.php`
   - This resets password to `admin123`

3. **Check User Status:**
   ```sql
   UPDATE users SET status = 'active' WHERE username = 'admin';
   ```

### Issue 3: Database Connection Failed

**Causes:**
- MySQL not running
- Wrong credentials
- Database doesn't exist
- Permission issues

**Solutions:**
1. **Start MySQL:**
   ```bash
   # Windows (XAMPP)
   Start XAMPP Control Panel
   
   # Linux/Mac
   sudo service mysql start
   ```

2. **Create Database:**
   ```sql
   CREATE DATABASE water_purifier_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. **Check Credentials:**
   - Local: `localhost`, `root`, no password
   - Production: Check environment variables

### Issue 4: Session Issues

**Causes:**
- Session not started
- Session directory not writable
- Cookie issues

**Solutions:**
1. **Check Session Directory:**
   ```php
   echo session_save_path();
   ```

2. **Fix Permissions:**
   ```bash
   chmod 755 /tmp
   ```

## Testing Tools

### 1. Database Setup Test
- **URL:** `setup-database.php`
- **Purpose:** Test database connection and tables
- **What it shows:** Database status, table count, user count

### 2. Login Debug Test
- **URL:** `debug-login.php`
- **Purpose:** Comprehensive login system test
- **What it shows:** All login components status

### 3. API Test
- **URL:** `test-api-login.php`
- **Purpose:** Test login API directly
- **What it shows:** API response and errors

### 4. Simple Login Test
- **URL:** `test-login.php`
- **Purpose:** Basic login functionality test
- **What it shows:** User verification

### 5. Reset Admin
- **URL:** `reset-admin.php`
- **Purpose:** Create/reset admin user
- **What it does:** Ensures admin user exists with correct password

## Default Credentials

After running installation or reset:
- **Username:** `admin`
- **Password:** `admin123`
- **Email:** `admin@example.com`
- **Role:** `admin`

## Environment Detection

The system automatically detects:
- **Local:** localhost, 127.0.0.1, .local, .test, .dev domains
- **Production:** Everything else

Local environment:
- Database: localhost, root, no password
- Debug mode: ON
- Error reporting: Full

Production environment:
- Database: Environment variables
- Debug mode: OFF
- Error reporting: Limited

## Manual Database Setup

If automatic setup fails:

```sql
-- Create database
CREATE DATABASE water_purifier_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create admin user
INSERT INTO users (username, password, email, role, status, email_verified, created_at) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com', 'admin', 'active', 1, NOW());

-- Note: The password hash above is for 'admin123'
```

## Browser Console Errors

Check browser console for:
- JavaScript errors
- Network request failures
- CORS issues
- Missing files

Common console errors:
- `404 Not Found` - API endpoint not found
- `500 Internal Server Error` - PHP error
- `Network Error` - Connection issue

## File Permissions

Ensure these files are readable:
- `config/database.php`
- `config/environment.php`
- `includes/functions.php`
- `api/auth/login.php`

## Server Requirements

- PHP 7.4+ (8.0+ recommended)
- MySQL 5.7+ (8.0+ recommended)
- Apache/Nginx with mod_rewrite
- PDO MySQL extension
- cURL extension

## Quick Commands

```bash
# Check PHP version
php -v

# Check MySQL
mysql --version

# Check if MySQL is running
sudo service mysql status

# Start MySQL
sudo service mysql start

# Check PHP extensions
php -m | grep -E "(pdo|curl|json)"
```

## Still Having Issues?

1. **Check error logs:**
   - PHP error log
   - Web server error log
   - Browser console

2. **Run all test tools:**
   - `setup-database.php`
   - `debug-login.php`
   - `test-api-login.php`
   - `reset-admin.php`

3. **Verify requirements:**
   - PHP version
   - MySQL running
   - File permissions
   - Database exists

4. **Try manual setup:**
   - Create database manually
   - Create admin user manually
   - Test with SQL directly