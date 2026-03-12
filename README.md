# Archive System Filipiniana
**Quezon City Public Library - Digital Archive System**

---

## Setup Instructions (New User)

### 1. Clone the Repository
```bash
git clone https://github.com/muckfaru/AchiveSystemFilipiniana.git
```
Place the project folder inside your XAMPP `htdocs` directory, e.g.:
```
C:\xampp\htdocs\qcpl\ArchiveSystemFilipiniana
```

### 2. Create the Database
1. Start **XAMPP** (Apache + MySQL)
2. Open **phpMyAdmin** (`http://localhost/phpmyadmin`)
3. Create a new database named: `archive_system`
4. Import `database.sql` into the database
   - This creates all tables AND inserts the default form template with required fields

### 3. Configure `config.php`
```bash
# Navigate to the config folder
cd backend/core

# Copy the example config
copy config.example.php config.php
```
Then edit `config.php` and update:
- **`APP_URL`** — Must match your local path, e.g.:
  - `http://localhost/qcpl/ArchiveSystemFilipiniana`
  - `http://localhost/ArchiveSystemFilipiniana`
- **`CALIBRE_CONVERT_PATH`** — Path to Calibre's `ebook-convert.exe` (optional, see below)
- **`SMTP_USERNAME`** / **`SMTP_PASSWORD`** — Gmail credentials for password reset emails (optional)

> **⚠️ IMPORTANT:** `config.php` is in `.gitignore` — it will NOT be pushed to GitHub.
> Each user must create their own copy from `config.example.php`.

### 4. Calibre Setup (Optional — for MOBI file reading)
1. Download **Calibre Portable** from: https://calibre-ebook.com/download_portable
2. Extract it to your `htdocs` folder, e.g.: `C:\xampp\htdocs\CalibrePortable`
3. Set the path in `config.php`:
```php
define('CALIBRE_CONVERT_PATH', 'C:\\xampp\\htdocs\\CalibrePortable\\Calibre\\ebook-convert.exe');
```
> If Calibre is not installed, MOBI files will offer a download button instead of in-browser reading.

### 5. Access the System
- **Public page:** `http://localhost/qcpl/ArchiveSystemFilipiniana/`
- **Admin login:** `http://localhost/qcpl/ArchiveSystemFilipiniana/auth/login.php`

---

## Default Admin Login
| Field    | Value      |
|----------|------------|
| Username | `admin`    |
| Password | `password` |

> Change the password after first login!

---

## For Existing Developers (Pulling Updates)

If you already have the system running and pull new changes:
1. Your `config.php` won't be affected (it's gitignored)
2. If `database.sql` has changed, check the `backend/migrations/` folder for new migration scripts
3. Run any new migrations in order: `php backend/migrations/XXX_migration_name.php`

---

## Tech Stack
- **Backend:** PHP 8.x on XAMPP
- **Database:** MySQL (via phpMyAdmin)
- **Frontend:** Bootstrap 5, epub.js, pdf.js
- **Optional:** Calibre (for MOBI → EPUB conversion)


