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
C:\xampp\htdocs\ArchiveSystemFilipiniana
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
on root folder named 
backend
 -core
 -config.php

Then edit `config.php` and update:
- **`APP_URL`** — Must match your local path, e.g.:
  - `http://localhost/ArchiveSystemFilipiniana`
  - `http://localhost/ArchiveSystemFilipiniana`
- **`SMTP_USERNAME`** / **`SMTP_PASSWORD`** — Gmail credentials for password reset emails (if the provided email isn't working maybe it expires)
to use the forgot password reset link maybe because of the internet firewall blocking the port (This is Normal on QCPL Wifi for security policy)



### 4. Calibre Setup (Required to read .mobi file on this project  — for MOBI file reading)
1. Download **Calibre Portable** from: https://calibre-ebook.com/download_portable
2. Extract it to your `htdocs` folder, e.g.: `C:\xampp\htdocs\CalibrePortable`
3. Set the path in `config.php`:
```php
define('CALIBRE_CONVERT_PATH', 'define('CALIBRE_CONVERT_PATH', 'C:\\xampp\\htdocs\\CalibrePortable\\Calibre\\ebook-convert.exe'); or config your own 
```
> If Calibre is not installed, MOBI files will offer a download button instead of in-browser reading.

### 5. Access the System
- **Public page:** `http://localhost/ArchiveSystemFilipiniana/`
- **Admin login:** `http://localhost/ArchiveSystemFilipiniana/auth/login.php`

---

## Default Admin Login
| Field    | Value      |
|----------|------------|
| Username | `admin`    |
| Password | `password` |

> Change the password after first login!


## Tech Stack
- **Backend:** PHP 8.x on XAMPP
- **Database:** MySQL (via phpMyAdmin)
- **Frontend:** Bootstrap 5, epub.js, pdf.js
- **Optional:** Calibre (for MOBI → EPUB conversion)


