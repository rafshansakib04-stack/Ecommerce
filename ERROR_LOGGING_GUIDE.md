# Error Logging System Guide

## Overview
The Water Purifier ERP System now includes comprehensive error logging to help debug issues and monitor system health.

## Log Files Location
All log files are stored in the `/logs/` directory:
- `error-YYYY-MM-DD.log` - General errors and warnings
- `fatal-YYYY-MM-DD.log` - Fatal errors and exceptions
- `login-YYYY-MM-DD.log` - Login attempts and authentication
- `database-YYYY-MM-DD.log` - Database operations and queries
- `api-YYYY-MM-DD.log` - API calls and responses

## Log File Structure
Each log entry contains:
- Timestamp
- Category (ERROR, LOGIN, DATABASE, etc.)
- IP Address
- Request URI
- Message
- Context (JSON data)

Example log entry:
```
[2024-01-15 10:30:45] [LOGIN] [127.0.0.1] [/api/auth/login.php] Login attempt started | {"username":"admin","success":false}
```

## Debugging Tools

### 1. View Logs Interface
Access: `http://your-domain/view-logs.php`
- View all log files in a web interface
- Filter by log type
- Search and analyze entries
- Clear logs (admin only)

### 2. Test Error Logging
Access: `http://your-domain/test-error-logging.php`
- Test all logging functions
- Verify log files are being created
- Check log file permissions

### 3. Debug with Logs
Access: `http://your-domain/debug-with-logs.php`
- Comprehensive system debugging
- Database connection testing
- Login system verification
- Recent log entries display

## Common Login Issues and Solutions

### Issue: "Error occurred try again"
**Check these logs:**
1. `login-YYYY-MM-DD.log` - Look for login attempts
2. `error-YYYY-MM-DD.log` - Look for general errors
3. `fatal-YYYY-MM-DD.log` - Look for fatal errors

**Common causes:**
- Database connection failed
- User not found
- Password verification failed
- Session issues

### Issue: Database Connection Failed
**Check:**
1. `database-YYYY-MM-DD.log` - Database operation logs
2. `fatal-YYYY-MM-DD.log` - Connection errors
3. Verify database credentials in `.env` file

### Issue: User Not Found
**Check:**
1. `login-YYYY-MM-DD.log` - Look for "User not found" entries
2. Run `reset-admin.php` to create admin user
3. Verify user exists in database

### Issue: Password Verification Failed
**Check:**
1. `login-YYYY-MM-DD.log` - Look for "Invalid password" entries
2. Run `reset-admin.php` to reset password
3. Verify password hash in database

## Log Analysis Commands

### View Recent Login Attempts
```bash
tail -f logs/login-$(date +%Y-%m-%d).log
```

### View All Errors Today
```bash
cat logs/error-$(date +%Y-%m-%d).log
```

### Search for Specific Errors
```bash
grep "LOGIN_ERROR" logs/login-$(date +%Y-%m-%d).log
```

### View Fatal Errors
```bash
cat logs/fatal-$(date +%Y-%m-%d).log
```

## Log File Permissions
Ensure the logs directory has proper permissions:
```bash
chmod 755 logs/
chmod 644 logs/*.log
```

## Log Rotation
Log files are created daily. Old logs can be safely deleted:
```bash
# Delete logs older than 30 days
find logs/ -name "*.log" -mtime +30 -delete
```

## Monitoring Logs
For production environments, consider:
1. Setting up log monitoring
2. Configuring log rotation
3. Setting up alerts for critical errors
4. Regular log analysis

## Troubleshooting Steps

### Step 1: Check Log Files
1. Go to `view-logs.php`
2. Check each log type for errors
3. Look for patterns in error messages

### Step 2: Test System Components
1. Run `test-error-logging.php`
2. Run `debug-with-logs.php`
3. Check database connection

### Step 3: Reset if Needed
1. Run `reset-admin.php` to reset admin user
2. Run `setup-database.php` to test database
3. Clear logs if needed

### Step 4: Monitor Real-time
1. Try logging in
2. Watch logs in real-time
3. Identify the exact failure point

## Log Categories Explained

- **LOGIN_ATTEMPT** - User trying to log in
- **LOGIN_SUCCESS** - Successful login
- **LOGIN_ERROR** - Login failed
- **DATABASE** - Database operations
- **DATABASE_ERROR** - Database failures
- **API** - API calls
- **FATAL_ERROR** - Critical system errors
- **EXCEPTION** - Uncaught exceptions

## Best Practices

1. **Regular Monitoring**: Check logs daily
2. **Error Analysis**: Look for patterns in errors
3. **Log Rotation**: Keep logs manageable
4. **Security**: Protect log files from public access
5. **Backup**: Include logs in system backups

## Emergency Procedures

### If System is Down:
1. Check `fatal-YYYY-MM-DD.log`
2. Check database connection
3. Restart web server if needed
4. Contact system administrator

### If Login is Broken:
1. Check `login-YYYY-MM-DD.log`
2. Run `reset-admin.php`
3. Test with `test-login.php`
4. Check database user table

### If Database is Down:
1. Check `database-YYYY-MM-DD.log`
2. Verify database server status
3. Check credentials in `.env`
4. Restart database service

## Support
For additional help:
1. Check the troubleshooting guides
2. Review log files for specific errors
3. Test individual components
4. Contact system administrator with log details