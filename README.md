# AuctionKai — MySQL Version

## Requirements
- PHP 8.0+ with PDO MySQL extension
- MySQL 5.7+ or MariaDB 10.3+
- phpMyAdmin (for DB management)

---

## Step 1 — Create the Database in phpMyAdmin

1. Open phpMyAdmin in your browser
2. Click the **SQL** tab at the top
3. Paste the entire contents of **`schema.sql`** into the box
4. Click **Go**

This will create the `auctionkai` database with all tables and sample data.

---

## Step 2 — Configure the Connection

Open **`config.php`** and update:

```php
define('DB_HOST', 'localhost');   // usually localhost
define('DB_NAME', 'auctionkai'); // database name from schema.sql
define('DB_USER', 'root');        // your MySQL username
define('DB_PASS', '');            // your MySQL password
```

> On shared hosting (Xserver, Sakura, ConoHa) the host is usually `localhost`
> and you create the DB user through the hosting control panel.

---

## Step 3 — Upload Files

Upload to your server:
```
auctionkai/
├── config.php   ← edit credentials first
├── index.php
├── pdf.php
├── schema.sql   ← only needed once for setup
└── README.md
```

Then open `index.php` in your browser.

---

## Quick Start (Local)

```bash
cd auctionkai_mysql/
php -S localhost:8080
# open: http://localhost:8080
```

Make sure MySQL is running and you've run schema.sql first.

---

## File Summary

| File | Purpose |
|------|---------|
| `config.php` | DB credentials + PDO connection |
| `schema.sql` | Run once in phpMyAdmin to create tables |
| `index.php`  | Full app — all tabs, all CRUD |
| `pdf.php`    | Print-ready A4 settlement statements |

---

## phpMyAdmin Tips

- **View all data**: Click `auctionkai` database → click any table → Browse tab
- **Run queries**: SQL tab at the top
- **Export backup**: Export tab → Format: SQL → Go
- **Check records**: `members`, `vehicles`, `fees`, `custom_deductions`, `auction` tables
