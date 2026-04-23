# AuctionKai — Settlement Management System

A Japanese auto auction settlement system with multi-auction support, user authentication, and PDF generation.

## Requirements
- PHP 8.0+ with PDO MySQL extension
- MySQL 5.7+ or MariaDB 10.3+
- phpMyAdmin (for DB management)

---

## Step 1 — Create the Database

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

---

## Step 3 — Open the App

Navigate to `http://localhost/auctionkai/` — you'll be redirected to the login page.

### Default Accounts

| Username | Password | Role |
|----------|----------|------|
| `admin` | `password` | Admin |
| `aliashroff` | `123@intel` | User |

You can also register new accounts via the **Register** link on the login page.

---

## Features

### 🔐 Authentication
- Login / Register system
- User data isolation — each user only sees their own data
- Session-based access control

### 🏷 Multi-Auction Support
- Multiple auctions displayed as clickable chips in the navbar
- Create new auctions with name, date, and location
- Edit auction details from the top bar
- Each auction has its own vehicles and fee settings

### 👥 Members (Global)
- Members are shared across all auctions
- Add, edit, and remove members
- Autocomplete search when assigning vehicles

### 🚗 Vehicles (Per Auction)
- Add vehicles to specific auctions
- Assign to any member via search
- Toggle sold/unsold status
- Edit vehicle details inline
- Lot number, Make, Model, Sold Price

### ⚙️ Fee Settings (Per Auction)
- Fully custom fee system — no hardcoded fees
- **3 fee types:**
  - **Flat (¥/vehicle)** — fixed amount per sold vehicle
  - **Percent (%)** — percentage of gross sales
  - **Per Vehicle (¥/all)** — per vehicle regardless of status
- Add, edit, and remove fee items
- New auctions get 4 default fees (Entry, Commission, Tax, Transport)

### 📄 Settlement Statements
- Per-member settlement with full breakdown
- Deductions calculated from fee items
- Net payout display
- Email draft generation
- PDF printing (single or all members)

---

## File Structure

```
auctionkai/
├── config.php        ← DB credentials + PDO connection
├── schema.sql        ← Run once to create database & seed data
├── login.php         ← Login & registration page
├── logout.php        ← Session destroy & redirect
├── index.php         ← Main app (all tabs, all CRUD)
├── pdf.php           ← Print-ready A4 settlement statements
├── style.css         ← All styles (dark premium theme)
└── README.md
```

---

## Quick Start (Local)

```bash
# Make sure MySQL is running and you've run schema.sql first
cd auctionkai/
php -S localhost:8080
# open: http://localhost:8080/login.php
```

---

## Database Schema

```
users (id, username, password, name, email, role, created_at)
  ↓
auction (id, user_id, name, date, location, created_at, updated_at)
  ↓                     ↓
fee_items              vehicles (id, auction_id, member_id, make, model, lot, sold_price, sold)
  (id, auction_id,       ↑
   name, type,            |
   amount, sort_order)  members (id, user_id, name, phone, email, created_at)
```

---

## phpMyAdmin Tips

- **View all data**: Click `auctionkai` database → click any table → Browse tab
- **Run queries**: SQL tab at the top
- **Export backup**: Export tab → Format: SQL → Go
- **Check records**: `users`, `auction`, `members`, `vehicles`, `fee_items` tables
