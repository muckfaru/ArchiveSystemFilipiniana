# Quick Start Guide

## 🚀 First Time Setup (5 minutes)

### Step 1: Clone & Setup Config
```bash
# Clone the repo
git clone <repository-url>
cd ArchiveSystemFilipiniana

# Create your local config
copy backend\core\config.example.php backend\core\config.php
```

### Step 2: Edit config.php
Open `backend/core/config.php` and change these 3 things:

```php
// 1. Your database password (if you have one)
define('DB_PASS', '');  // ← Add your MySQL password here

// 2. Your XAMPP path
define('APP_URL', 'http://localhost/qcpl/ArchiveSystemFilipiniana');
// ↑ Change this to match YOUR XAMPP htdocs path

// 3. Calibre path (optional - leave empty if you don't have it)
define('CALIBRE_CONVERT_PATH', '');
```

### Step 3: Import Database
```bash
# Using command line
mysql -u root -p archive_system < database.sql

# OR use phpMyAdmin
# 1. Go to http://localhost/phpmyadmin
# 2. Create database "archive_system"
# 3. Import database.sql
```

### Step 4: Done! 🎉
Open: `http://localhost/qcpl/ArchiveSystemFilipiniana`

---

## 🔄 Daily Workflow

### Before Starting Work
```bash
git pull
# Check if config.example.php changed
# If yes, update your config.php with new settings
```

### After Making Changes
```bash
git add .
git commit -m "Your message"
git push
```

**⚠️ IMPORTANT**: Your `config.php` will NEVER be committed (it's in .gitignore)

---

## ❓ Common Issues

### "Database connection failed"
→ Check your database password in `config.php`

### "Page not found" or broken CSS
→ Check your `APP_URL` in `config.php` matches your XAMPP path

### "config.php not found"
→ Copy from `config.example.php` and edit it

---

## 📚 Need More Help?
Read the full [SETUP.md](SETUP.md) guide
