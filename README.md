# ⚡ AuctionKai — Japanese Auto Auction Settlement System

A premium dark-themed settlement management system for Japanese auto auctions. Built with vanilla PHP, MySQL, and zero frameworks.

---

## Requirements

- **PHP 8.0+** with PDO MySQL extension
- **MySQL 5.7+** or **MariaDB 10.3+**
- **XAMPP** (or any PHP/MySQL stack)
- phpMyAdmin (for DB management)

---

## Quick Start

### Step 1 — Create the Database

1. Open phpMyAdmin → **SQL** tab
2. Paste the entire contents of **`schema.sql`**
3. Click **Go**

> ⚠️ Always drop the entire `auctionkai` database and re-run `schema.sql` after major updates.

### Step 2 — Configure the Connection

Edit **`config.php`**:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'auctionkai');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### Step 3 — Open the App

Navigate to `http://localhost/auctionkai/` — you'll be redirected to the login page.

### Default Accounts

| Username | Password | Role |
|----------|----------|------|
| `admin` | `password` | Admin |
| `aliashroff` | `123@intel` | User |

You can also register new accounts via the **Register** link.

---

## Features

### 🔐 Authentication & User Management
- Login / Register system with bcrypt passwords
- User data isolation — each user only sees their own data
- Session-based access control
- **Profile page** — edit name, email, change password (click username in top right)
- Username cannot be changed

### 🏷 Multi-Auction Support
- Multiple auctions displayed as clickable chips in the navbar
- Create new auctions with name and date
- Edit auction details and commission fee from the top bar
- Each auction has its own vehicles
- **2-week auto-expiry** — expired auctions delete sold vehicles + auction data, members are preserved
- Expiry badges on auction chips: 🟢 fresh / 🟡 expiring / 🔴 expired

### 👥 Members (Global Per User)
- Members are shared across all auctions for the same user
- Add, edit, and remove members
- **Autocomplete search** when assigning vehicles (type name or phone)
- Members are never deleted by auction expiry

### 🚗 Vehicles (Per Auction)
- Add vehicles to specific auctions
- Assign to any member via search
- **Modal-based edit** — click Edit to open a popup modal (AJAX-powered, no page reload)
- Toggle sold/unsold status with one click
- Per-vehicle fields: Lot #, Make, Model, Sold Price, Recycle Fee, Listing Fee, Sold Fee, Nagare Fee, Other Fee

### 💰 Smart Fee Logic
- **Commission** — flat fee per member, set per auction (default ¥3,300)
- **10% Consumption Tax** — auto-calculated on sold price, shown separately
- **Recycle Fee** — per vehicle
- **Nagare Fee** — only applies to **unsold** vehicles (hidden for sold)
- **Listing Fee / Sold Fee** — only apply to sold vehicles (disabled for unsold)
- **Other Fee** — always available

When a vehicle is marked **unsold**: Sold Price, Recycle, Listing Fee, Sold Fee are disabled and cleared; Nagare Fee appears. When **sold**: Nagare Fee is hidden.

### 📄 Settlement Statements
- Only members with at least one sold vehicle are shown
- Full breakdown: Gross Sales → + Tax → + Recycle → Total Received → − Fees → − Commission → NET PAYOUT
- **Net payout shows ¥0** when member has no sold vehicles
- "No sales history available for this auction." when nobody has sales
- Email draft generation (mailto: link)
- **PDF printing** — single member or all members, A4 format with full breakdown

### 🖨 PDF Settlement Statements
- Print-ready A4 layout with Japanese headers (精算書 / お支払い額)
- **Sold vehicles table** — Lot, Vehicle, Sold Price, Tax, Recycle, Listing, Sold Fee, Other, Net
- **Unsold vehicles table** — Lot, Vehicle, Nagare Fee, Other Fee, Total
- Fee breakdown section with all deductions
- Net payout in bold
- Auction details and expiry date in footer
- Browser print or save as PDF

---

## Calculation Formula

```
For each member with sold vehicles:

  Total Received = Sold Price + 10% Tax + Recycle Fee
  Total Deductions = Listing Fee + Sold Fee + Nagare Fee (unsold only) + Other Fee + Commission (flat/member)
  NET PAYOUT = Total Received − Total Deductions

  If no sold vehicles: NET PAYOUT = ¥0
```

---

## File Structure

```
auctionkai/
├── css/
│   ├── style.css          ← Main dark premium theme
│   └── pdf.css            ← PDF print styles (light theme, A4)
├── js/
│   └── app.js             ← All client-side JS (modal, autocomplete, AJAX)
├── config.php             ← DB credentials + PDO connection
├── schema.sql             ← Full DB schema + seed data
├── login.php              ← Login & registration page
├── logout.php             ← Session destroy & redirect
├── index.php              ← Main app (members, vehicles, statements tabs)
├── profile.php            ← User profile & password change
├── get_vehicle.php        ← AJAX endpoint: fetch vehicle data by ID
├── update_vehicle.php     ← AJAX endpoint: update vehicle via JSON
├── pdf.php                ← Print-ready A4 settlement statements
└── README.md
```

---

## Database Schema

```
users (id, username, password, name, email, role, created_at)
  ↓
auction (id, user_id, name, date, commission_fee, expires_at, created_at, updated_at)
  ↓
vehicles (id, auction_id, member_id, make, model, lot,
          sold_price, recycle_fee, listing_fee, sold_fee,
          nagare_fee, other_fee, sold)
  ↑
members (id, user_id, name, phone, email, created_at)
```

### Key Design Decisions
- **Members are global** — belong to `user_id`, not `auction_id`
- **Vehicles are per-auction** — connect members to auctions via `auction_id`
- **Commission is per-auction, per-member** — stored as `commission_fee` on auction table
- **No `fee_items` table** — all fees are per-vehicle columns on the `vehicles` table
- **Expiry system** — `expires_at` = auction date + 14 days; auto-cleanup runs on page load

---

## Changelog

### v1.0 — Initial Release
- Multi-auction support with navbar chips
- Login/Register with session-based auth
- User data isolation
- Global members per user
- Vehicle management with inline forms
- Member autocomplete search
- PDF settlement statements

### v1.1 — Fee System Overhaul
- Removed `fee_items` table entirely
- Added per-vehicle fee fields (listing, sold, nagare, other)
- Commission rate moved to auction table
- 10% consumption tax auto-calculated
- Recycle fee per vehicle
- No location field for auctions

### v1.2 — Commission as Fixed Fee
- Changed commission from percentage to fixed fee per member
- Default: ¥3,300/member
- Removed % label, shows ¥/member
- Net payout = ¥0 when no sold vehicles

### v1.3 — Nagare Fee for Unsold Only
- Nagare Fee only appears for unsold vehicles
- Sold vehicles: Sold Price, Recycle, Listing, Sold Fee enabled; Nagare hidden
- Unsold vehicles: Nagare Fee appears; sold fields disabled & cleared
- Smart toggle on Add and Edit forms

### v1.4 — Modal-Based Vehicle Edit
- AJAX-powered edit modal (get_vehicle.php + update_vehicle.php)
- No page reload on save
- Frontend + backend validation
- Click outside, Escape, or × to close
- Body scroll lock when modal is open
- Full-screen dark overlay with blur

### v1.5 — Project Reorganization
- CSS extracted to `css/style.css` and `css/pdf.css`
- JavaScript extracted to `js/app.js`
- All file paths updated
- Profile page for user settings (name, email, password)

### v1.6 — PDF Improvements
- Unsold vehicles now appear in PDF
- Separate unsold table showing Nagare and Other fees
- Commission label corrected to ¥/member

---

## phpMyAdmin Tips

- **View all data**: Click `auctionkai` database → click any table → Browse tab
- **Run queries**: SQL tab at the top
- **Export backup**: Export tab → Format: SQL → Go
- **After schema changes**: Drop entire `auctionkai` database, re-run `schema.sql`
- **Import schema**: Use the Import tab (file upload) instead of copy-paste to avoid encoding issues

---

## Common Issues

| Issue | Solution |
|-------|----------|
| Parse error after update | `git pull` and refresh — make sure no old cached files |
| Schema import fails | Drop entire DB first, then import `schema.sql` as a file (not copy-paste) |
| Commission shows as 3% | Drop & recreate DB — old `commission_rate` column needs `commission_fee` |
| Edit button not visible | Fixed in v1.4+ — overflow changed from hidden to scroll |
| PDF blank or error | Ensure `commission_fee` column exists (not `commission_rate`) |

---

## License

Private project — all rights reserved.
