# ⚡ AuctionKai — Japanese Auto Auction Settlement System

A premium dark-themed settlement management system for Japanese auto auctions. Built with vanilla PHP, Tailwind CSS, MySQL, and zero frameworks.

---

## 🖼 Preview

| Login | Dashboard | Statements |
|-------|-----------|-------------|
| Dark themed auth | Multi-auction management | Full breakdown with PDF |

---

## 📋 Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.0+ with PDO MySQL |
| MySQL / MariaDB | 5.7+ / 10.3+ |
| XAMPP | Any recent version |
| Internet | Required (Tailwind CDN + Google Fonts) |

---

## 🚀 Quick Start

### 1. Create the Database

1. Open **phpMyAdmin** → **SQL** tab
2. Paste the entire contents of `schema.sql`
3. Click **Go**

> ⚠️ After major updates, drop the entire `auctionkai` database and re-import `schema.sql`.

### 2. Configure Connection

Edit `config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'auctionkai');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 3. Open the App

Navigate to `http://localhost/auctionkai/`

### Default Accounts

| Username | Password | Role |
|----------|----------|------|
| `admin` | `password` | Admin |
| `aliashroff` | `123@intel` | User |

Or register a new account via the **Register** link.

---

## ✨ Features

### 🔐 Authentication
- Login / Register with bcrypt passwords
- Session-based access control
- User data isolation — each user sees only their own data
- **Profile page** — edit name, email, change password (click username top-right)

### 🏷 Multi-Auction Support
- Clickable auction chips in the navbar
- Create auctions with name and date
- Edit auction details + commission from the top bar
- **2-week auto-expiry** — expired auctions delete sold vehicles + auction, members preserved
- Expiry badges: 🟢 fresh / 🟡 expiring / 🔴 expired

### 👥 Members (Global Per User)
- Shared across all auctions for the same user
- **Click member name** → modal with sold/unsold vehicles + PDF link
- **Edit member** → modal popup (AJAX, no page reload)
- Add, remove members
- Autocomplete search when assigning vehicles
- Members are never deleted by auction expiry

### 🚗 Vehicles (Per Auction)
- Add via AJAX with loading preloader
- **Modal-based edit** — AJAX fetch + save, no page reload
- **AJAX delete** — fade-out animation
- Toggle sold/unsold with one click
- Per-vehicle fields: Lot #, Make, Model, Sold Price, Recycle Fee, Listing Fee, Sold Fee, Nagare Fee, Other Fee

### 💰 Smart Fee Logic

| Fee | When It Applies |
|-----|----------------|
| **Commission** | Flat fee per member (default ¥3,300) |
| **10% Consumption Tax** | Auto-calculated on sold price |
| **Recycle Fee** | Per sold vehicle |
| **Listing Fee** | Per sold vehicle |
| **Sold Fee** | Per sold vehicle |
| **Nagare Fee** | Per **unsold** vehicle only |
| **Other Fee** | Per vehicle (any status) |

**Sold/Unsold toggle:**
- **Sold ✓** → Sold Price, Recycle, Listing, Sold Fee enabled; Nagare disabled
- **Unsold ✗** → Nagare enabled; sold fields disabled & cleared

### 📄 Settlement Statements
- Only members with sold vehicles are shown
- Full breakdown: Gross Sales → + Tax → + Recycle → Total Received → − Fees → − Commission → NET PAYOUT
- **¥0 payout** when member has no sales
- Email draft generation (mailto: link)
- **PDF printing** — single member or all, A4 format

### 🖨 PDF Settlement Statements
- Print-ready A4 with Japanese headers (精算書 / お支払い額)
- **Sold vehicles table** — Price, Tax, Recycle, Listing, Sold Fee, Other, Net
- **Unsold vehicles table** — Nagare, Other fees
- Fee breakdown with all deductions
- Net payout in bold
- Browser print or save as PDF

---

## 🧮 Calculation Formula

```
For each member with sold vehicles:

  Total Received  = Sold Price + 10% Tax + Recycle Fee
  Total Deductions = Listing Fee + Sold Fee + Nagare Fee (unsold only) + Other Fee + Commission (flat/member)
  NET PAYOUT      = Total Received − Total Deductions

  No sold vehicles → NET PAYOUT = ¥0
```

---

## 📁 File Structure

```
auctionkai/
├── css/
│   ├── style.css              ← Custom styles (form elements, tables, statements)
│   ├── pdf.css                ← PDF print styles (light theme, A4)
│   └── tailwind-config.php    ← Tailwind CDN + custom theme config
├── js/
│   └── app.js                 ← All client-side JS (modals, AJAX, autocomplete)
├── config.php                 ← DB credentials + PDO connection
├── schema.sql                 ← Full DB schema + seed data
├── login.php                  ← Login & registration
├── logout.php                 ← Session destroy & redirect
├── index.php                  ← Main app (members, vehicles, statements)
├── profile.php                ← User profile & password change
├── pdf.php                    ← Print-ready A4 settlement statements
├── get_vehicle.php            ← AJAX: fetch vehicle data
├── update_vehicle.php         ← AJAX: update vehicle via JSON
├── delete_vehicle.php         ← AJAX: delete vehicle
├── add_vehicle.php            ← AJAX: add new vehicle
├── get_member_detail.php      ← AJAX: fetch member detail (vehicles list)
├── update_member.php          ← AJAX: fetch/update member
└── README.md
```

---

## 🗃 Database Schema

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
- **Commission is flat per member** — stored as `commission_fee` on auction table
- **Nagare fee for unsold only** — stored per vehicle, calculated from unsold vehicles
- **No `fee_items` table** — all fees are per-vehicle columns
- **Expiry** — `expires_at` = auction date + 14 days; auto-cleanup on page load

---

## 🎨 Theme & Design

| Element | Color | Code |
|---------|-------|------|
| Background | Deep Navy | `#0A1420` |
| Cards | Dark Blue | `#111E2D` |
| Gold Accent | Premium Gold | `#D4A84B` |
| Success | Soft Green | `#4CAF82` |
| Danger | Muted Red | `#CC7777` |
| Text | Warm White | `#E8DCC8` |
| Muted | Slate Blue | `#6A88A0` |

**Fonts:** Noto Sans JP (UI) + Space Mono (numbers/prices)

**Animations:** Fade-in, slide-down, pulse-gold on active auction, button hover lift

---

## 📝 Changelog

### v2.0 — Tailwind CSS + AJAX Overhaul
- Migrated to Tailwind CSS (CDN, no build step)
- Same dark premium theme with custom `ak-*` color palette
- All CRUD operations via AJAX (add/edit/delete vehicles, edit members)
- Modal-based editing for vehicles and members
- Member detail modal (click name → sold/unsold list + PDF)
- Loading preloaders on form submission
- Smooth animations (fade-in, slide-down, pulse-gold)
- Organized file structure (css/, js/ directories)
- Profile page for user settings

### v1.6 — PDF Improvements
- Unsold vehicles appear in PDF
- Commission label corrected to ¥/member
- Fixed column alignment in PDF tables

### v1.5 — Project Reorganization
- CSS extracted to css/ directory
- JavaScript extracted to js/app.js
- Profile page added

### v1.4 — Modal-Based Vehicle Edit
- AJAX-powered edit modal
- Frontend + backend validation
- Body scroll lock when modal is open

### v1.3 — Nagare Fee for Unsold Only
- Nagare Fee only appears for unsold vehicles
- Smart field toggle on Add and Edit forms

### v1.2 — Commission as Fixed Fee
- Changed from percentage to flat fee per member
- Default: ¥3,300/member

### v1.1 — Fee System Overhaul
- Removed fee_items table
- Per-vehicle fee fields
- Commission on auction table
- 10% consumption tax
- Recycle fee per vehicle

### v1.0 — Initial Release
- Multi-auction support
- Login/Register with auth
- User data isolation
- Global members
- PDF settlement statements

---

## 🔧 Troubleshooting

| Issue | Solution |
|-------|----------|
| CSS looks broken | Ctrl+Shift+R to clear cache (Tailwind CDN) |
| Parse error after update | `git pull` + hard refresh browser |
| Schema import fails | Drop entire DB first, import `schema.sql` as file |
| Modal doesn't open | Ctrl+Shift+R (JS cache) |
| Commission shows wrong | Drop & recreate DB — need `commission_fee` column |
| PDF blank/error | Ensure `commission_fee` column exists |
| Fields look invisible | Fixed — inputs use `--card` background now |
| Nagare field missing | It's always visible, disabled when Sold is checked |

---

## 🔒 Security Notes

- Passwords stored with `password_hash()` (bcrypt)
- SQL injection prevented via PDO prepared statements
- XSS prevented via `htmlspecialchars()` output encoding
- CSRF tokens on all form submissions
- User data isolation — each user only accesses their own records
- **Rotate the GitHub token** if it was ever exposed in chat

---

## 📜 License

Private project — all rights reserved.
