# Setup Instructions

## Initial Setup

### 1. Clone the Repository
```bash
git clone <repository-url>
cd ArchiveSystemFilipiniana
```

### 2. Create Local Configuration File
```bash
# Copy the example config file
copy backend\core\config.example.php backend\core\config.php
```

### 3. Update Configuration
Open `backend/core/config.php` and update the following values for your local environment:

#### Database Settings
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'archive_system');
define('DB_USER', 'root');          // Your MySQL username
define('DB_PASS', '');              // Your MySQL password
```

#### Application URL
Update this to match your local XAMPP path:
```php
define('APP_URL', 'http://localhost/qcpl/ArchiveSystemFilipiniana');
```

Examples:
- `http://localhost/ArchiveSystemFilipiniana`
- `http://localhost:8080/archive`
- `http://archive.local`

#### Calibre Path (Optional)
If you have Calibre installed for MOBI conversion:
```php
define('CALIBRE_CONVERT_PATH', 'C:\\Program Files\\Calibre2\\ebook-convert.exe');
```

Leave empty if not installed:
```php
define('CALIBRE_CONVERT_PATH', '');
```

#### Email Settings (Optional)
For password reset functionality, configure Gmail SMTP:
```php
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
```

### 4. Import Database
```bash
# Import the database schema
mysql -u root -p archive_system < database.sql
```

Or use phpMyAdmin:
1. Open http://localhost/phpmyadmin
2. Create database `archive_system`
3. Import `database.sql`

### 5. Set Permissions
Ensure the `uploads/` directory is writable:
```bash
# Windows (run as Administrator)
icacls uploads /grant Everyone:F /T

# Linux/Mac
chmod -R 777 uploads/
```

### 6. Access the Application
Open your browser and navigate to:
```
http://localhost/qcpl/ArchiveSystemFilipiniana
```

## Important Notes

### ⚠️ Never Commit config.php
The `backend/core/config.php` file contains sensitive information and is specific to each developer's environment. It's already in `.gitignore` and should NEVER be committed to Git.

### ✅ Always Use config.example.php as Template
When adding new configuration options, update `config.example.php` so other developers know what to configure.

### 🔄 Pulling Updates
When you pull updates from Git, check if `config.example.php` has changed. If so, update your local `config.php` accordingly.

## Troubleshooting

### "Database connection failed"
- Check your database credentials in `config.php`
- Ensure MySQL is running
- Verify the database exists

### "Page not found" or CSS/JS not loading
- Check your `APP_URL` in `config.php`
- Ensure it matches your actual XAMPP path
- Clear browser cache

### "Permission denied" on uploads
- Check folder permissions on `uploads/` directory
- Ensure web server has write access

### MOBI files not converting
- Install Calibre or leave `CALIBRE_CONVERT_PATH` empty
- MOBI files will offer download instead of conversion

## Development Workflow

1. **Pull latest changes**: `git pull`
2. **Check config.example.php**: See if new settings were added
3. **Update your config.php**: Add any new settings from example
4. **Test locally**: Ensure everything works
5. **Commit your changes**: `git add .` and `git commit`
6. **Push**: `git push`

Remember: Your `config.php` stays local and is never pushed to Git!
