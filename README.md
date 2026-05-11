# AuctionKai — Settlement Management System

> Japanese auto auction settlement management. Track members, vehicles, fees, and generate PDF statements.  
> **v3.8** • Built by [Mirai Global Solutions](https://miraiglobaltrading.com)

---

## Overview

AuctionKai is a PHP/MySQL web application for managing auto auction settlements. Users create auctions, add members (sellers) and vehicles, calculate fees, and generate PDF settlement statements. Multi-user with admin panel.

**Live:** https://auctionkai.miraiglobaltrading.com/  
**Repo:** https://github.com/ashifashroff/auctionkai

---

## Features

### Core
- **Multi-Auction** — create and switch between multiple auctions with expiry tracking (14-day default)
- **Members** — global member list shared across auctions, CSV import, inline edit/delete
- **Vehicles** — add vehicles with make/model/lot/fees, toggle sold/unsold, bulk actions
- **Fees** — recycle, listing, sold, nagare, other fees per vehicle; special fees per member
- **Statements** — auto-calculated settlement PDFs with itemized breakdown, PIN-protected share links
- **Auction Summary** — consolidated view of all members and totals for an auction

### Auth & Security
- Registration with password strength indicator (Weak/Fair/Good/Strong)
- Login rate limiting (5 attempts → 30s lockout)
- CSRF protection on all forms and API endpoints
- Google reCAPTCHA v2 on registration (configurable from admin)
- Forgot/reset password via email
- Session timeout with configurable duration + 2-minute warning

### Admin Panel
- View/edit/suspend/delete users, create new users and admins
- Login As any user (impersonation)
- Activity log with pagination and filtering
- Error log viewer with severity levels and resolve actions
- Email provider configuration (SMTP, Mailgun, SendGrid, Amazon SES)
- Session timeout settings
- Maintenance mode with custom message and ETA
- Branding (name, tagline, logo, accent color, footer text)
- Database backup with download and cleanup
- reCAPTCHA configuration
- **Auto-Update Notifications** — checks GitHub releases hourly, shows banner + changelog, red dot badge on Updates tab, dismiss per-version, manual refresh

### Other
- Help & Guide page with accordion sections
- Profile page with password change and activity history
- Privacy Policy and Terms of Service pages
- Custom 403/404/500/503 error pages
- Responsive mobile-first design (320px–768px optimized)
- Japanese Yen (¥) currency formatting throughout

---

## Architecture

```
Browser → index.php (router) → views/ (UI) → api.php / api/*.php (JSON) → models/ (DB) → MySQL
                                  ↑
                            includes/ (shared logic)
```

- **Frontend:** Tailwind CSS (CDN), vanilla JS (no framework), Font Awesome icons
- **Backend:** PHP 8.3, PDO/MySQL, no framework
- **PDF:** TCPDF via composer, generated server-side
- **Auth:** PHP sessions, bcrypt passwords, CSRF tokens
- **API:** JSON endpoints under `api/`, shared bootstrap in `includes/api_bootstrap.php`

### Key Patterns
- All API endpoints use `api_bootstrap.php` for auth, CSRF, JSON input parsing
- JSON input is cached in `$GLOBALS['_json_input']` (fixes php://input stream consumption)
- Settings stored in `settings` table (key/value), loaded once per request
- CSRF token in `<meta name="csrf-token">`, sent as `_tok` in all API calls
- Admin pages require `includes/admin_check.php`
- Activity logging via `includes/activity.php` (fire-and-forget)
- Error logging via custom handler in `includes/error_handler.php`

---

## Installation

### Requirements
- PHP 8.0+ (8.3 recommended)
- MySQL 5.7+ / MariaDB 10.3+
- Apache with mod_rewrite
- Composer (for TCPDF)

### Steps

```bash
# 1. Clone
git clone https://github.com/ashifashroff/auctionkai.git
cd auctionkai

# 2. Install dependencies
composer install

# 3. Create database
# Import schema.sql in phpMyAdmin or:
mysql -u root -p < schema.sql

# 4. Configure
cp .env.example .env
# Edit .env with your DB credentials

# 5. Set permissions
chmod 755 backups/
# Ensure Apache can write to backups/

# 6. Access
# Navigate to your URL, register or use default admin/admin
```

### Default Accounts
| Username | Password | Role |
|----------|----------|------|
| admin | password | admin |
| demo | password | user |

⚠️ **Change these immediately after install.**

---

## Deployment

### Server (Plesk on 89.117.58.8)
- **Host:** miraiglobaltrading@89.117.58.8
- **SSH Key:** `~/.ssh/id_ed25519`
- **Doc Root:** `/var/www/vhosts/miraiglobaltrading.com/auctionkai.miraiglobaltrading.com/`
- **PHP:** 8.3 via Plesk (`/opt/plesk/php/8.3/bin/php`)
- **OPcache:** Enabled — run `opcache_reset()` after deploys

### Deploy Process
```bash
# From the OpenClaw workspace:
cd ~/.openclaw/workspace/auctionkai

# 1. Git commit & push
git add -A && git commit -m "message" && git push

# 2. Rsync to server (excludes config, vendor, .env, .git)
rsync -az --delete \
  --exclude='config.php' \
  --exclude='vendor/' \
  --exclude='.env' \
  --exclude='.git/' \
  -e "ssh -i ~/.ssh/id_ed25519 -o StrictHostKeyChecking=no" \
  ./ miraiglobaltrading@89.117.58.8:/var/www/vhosts/miraiglobaltrading.com/auctionkai.miraiglobaltrading.com/

# 3. Clear OPcache
ssh -i ~/.ssh/id_ed25519 miraiglobaltrading@89.117.58.8 \
  "/opt/plesk/php/8.3/bin/php -r 'opcache_reset();'"
```

### Important: Files NOT to deploy
- `config.php` — contains live DB credentials (set via Plesk environment)
- `.env` — same reason
- `vendor/` — install via `composer install` on server if needed
- `.git/` — not needed in production

---

## Database Structure

### Tables
| Table | Purpose |
|-------|---------|
| `users` | Login accounts (username, password, name, email, role, status) |
| `auction` | Auctions (user_id, name, date, commission_fee, expires_at) |
| `members` | Sellers (user_id, name, phone, email) — global, shared across auctions |
| `vehicles` | Vehicles (auction_id, member_id, make, model, lot, fees, sold) |
| `password_resets` | Password reset tokens (email, token, expires_at) |
| `settings` | Key-value config (email, maintenance, branding, update cache) |
| `activity_log` | Audit trail (user_id, action, details, created_at) |
| `error_logs` | PHP errors (severity, message, file, line, stack_trace, resolved) |
| `special_fees` | Per-member special fees (member_id, label, amount) |
| `statement_links` | Shareable PIN-protected statement links |

### Key Relationships
- `users` → `auction` (1:N)
- `users` → `members` (1:N)
- `auction` → `vehicles` (1:N, CASCADE DELETE)
- `members` → `vehicles` (1:N, CASCADE DELETE)
- `members` → `special_fees` (1:N)

---

## API Endpoints

All API endpoints require authentication (session). JSON endpoints require CSRF token as `_tok`.

### Core (`api.php`)
| Action | Method | Description |
|--------|--------|-------------|
| `add_auction` | POST | Create auction with name, date, commission_fee |
| `add_member` | POST | Add member (name, email) |
| `save_auction` | POST | Update auction name + commission fee |

### Separate Files (`api/`)
| File | Method | Description |
|------|--------|-------------|
| `add_vehicle.php` | POST | Add vehicle to auction |
| `update_vehicle.php` | POST | Edit vehicle fields |
| `delete_vehicle.php` | POST | Delete vehicle by ID |
| `get_vehicles_page.php` | GET | Paginated vehicle list |
| `get_members_page.php` | GET | Paginated member list |
| `get_member_fees_page.php` | GET | Member fees + special fees |
| `update_member.php` | POST | Edit member details |
| `update_payment.php` | POST | Update payment status |
| `generate_link.php` | POST | Create statement share link |
| `send_email.php` | POST | Email statement with PDF |
| `admin_actions.php` | POST | All admin operations (switch-based) |
| `activity_log.php` | GET | Activity log entries |
| `error_logs.php` | GET | Error log entries |
| `db_backup.php` | POST | Create database backup |
| `delete_auction.php` | GET | Delete auction |
| `import_members_csv.php` | POST | CSV member import |
| `member_fees.php` | GET | Member fees data |
| `download_pdf_zip.php` | GET | Download all PDFs as ZIP |

---

## File Structure

```
auctionkai/
├── admin/
│   ├── actions.php           ← Admin action handlers (legacy redirect-based)
│   ├── download_backup.php   ← Backup file download
│   ├── health.php            ← Health check + error log viewer
│   ├── index.php             ← Main admin panel (all tabs)
│   └── .htaccess             ← Deny direct access to non-PHP
├── api/
│   ├── add_vehicle.php       ← Add vehicle endpoint
│   ├── admin_actions.php     ← Admin AJAX actions (switch/case)
│   ├── activity_log.php      ← Activity log API
│   ├── check_lot.php         ← Check if lot number exists
│   ├── csv_template.php      ← Download CSV import template
│   ├── db_backup.php         ← Trigger DB backup
│   ├── delete_auction.php    ← Delete auction
│   ├── delete_vehicle.php    ← Delete vehicle
│   ├── download_pdf_zip.php  ← Download all PDFs as ZIP
│   ├── error_logs.php        ← Error log API
│   ├── generate_link.php     ← Generate share link
│   ├── get_member_detail.php ← Member detail for modals
│   ├── get_member_fees_page.php ← Paginated fees
│   ├── get_members_page.php  ← Paginated members
│   ├── get_vehicle.php       ← Single vehicle data
│   ├── get_vehicles_page.php ← Paginated vehicles
│   ├── import_members_csv.php ← CSV import
│   ├── log_statement.php     ← Log statement generation
│   ├── member_fees.php       ← Member fees data
│   ├── send_email.php        ← Send statement email
│   ├── update_member.php     ← Edit member
│   ├── update_payment.php    ← Update payment status
│   ├── update_profile.php    ← User profile update
│   └── update_vehicle.php    ← Edit vehicle
├── auth/
│   ├── forgot_password.php   ← Password reset request
│   ├── login.php             ← Login page
│   ├── logout.php            ← Logout handler
│   └── reset_password.php    ← Reset password form
├── backups/                  ← Backup storage (gitkeep + .htaccess deny)
├── css/
│   ├── pdf.css               ← PDF-specific styles
│   ├── style.css             ← Custom styles (dark theme, mobile)
│   ├── summary.css           ← Auction summary styles
│   └── tailwind-config.php   ← Tailwind CDN with custom colors
├── includes/
│   ├── activity.php          ← Activity logging + icons/colors
│   ├── admin_check.php       ← Admin role verification
│   ├── api_bootstrap.php     ← Shared API init (auth, CSRF, JSON input)
│   ├── auth_check.php        ← Session auth for pages
│   ├── branding.php          ← Branding variable loader
│   ├── constants.php         ← App constants (version, limits, etc.)
│   ├── db.php                ← PDO connection + error handler init
│   ├── error_handler.php     ← Custom error handler + DB logging
│   ├── footer.php            ← Common footer
│   ├── helpers.php           ← Formatting, calcStatement, WhatsApp, PDF HTML
│   ├── mailer.php            ← PHPMailer with PDF attachment
│   ├── maintenance_check.php ← Maintenance mode interceptor
│   ├── models.php            ← Data query functions
│   ├── post_handlers.php     ← POST request handlers
│   ├── rate_limiter.php      ← Login rate limiting
│   ├── settings.php          ← User settings CRUD
│   └── updater.php           ← GitHub release checker & update notifications
├── js/
│   ├── app.js                ← Main app logic (tab switching, search, etc.)
│   ├── common.js             ← Shared utilities (toast, confirm modal, formatting)
│   ├── fees.js               ← Special fees management
│   ├── members.js            ← Member CRUD + auction management
│   ├── statements.js         ← Statement generation + sharing
│   └── vehicles.js           ← Vehicle CRUD + inline editing
├── models/
│   ├── AuctionModel.php      ← Auction DB operations
│   ├── MemberFeesModel.php   ← Member fees + special fees
│   ├── MemberModel.php       ← Member DB operations
│   ├── PaymentModel.php      ← Payment status operations
│   ├── SettingsModel.php     ← Settings CRUD
│   └── VehicleModel.php      ← Vehicle DB operations
├── views/
│   ├── dashboard.php         ← Dashboard tab content
│   ├── members.php           ← Members tab content
│   ├── special_fees.php      ← Special fees tab content
│   ├── statements.php        ← Statements tab content
│   ├── vehicles.php          ← Vehicles tab content
│   └── partials/
│       ├── auction_bar.php   ← Auction selector chips + add form
│       ├── head.php          ← <head> with Tailwind + meta
│       ├── modals.php        ← Shared modal templates
│       ├── tabs.php          ← Tab navigation
│       └── topbar.php        ← Top navigation bar
├── about.php                 ← About page
├── api.php                   ← Core JSON API router
├── auction_summary.php       ← Auction summary view
├── config.php                ← DB + app config (from env vars) ⚠️ NOT in git
├── help.php                  ← Help & Guide page
├── index.php                 ← Main app entry point (router)
├── migrations.sql            ← Migration queries for existing installs
├── pdf.php                   ← PDF generation endpoint
├── privacy.php               ← Privacy policy
├── profile.php               ← User profile page
├── schema.sql                ← Full database schema + seed data
├── scripts/
│   └── backup.php            ← CLI backup script (cron-ready)
├── statement.php             ← Public statement view (PIN-protected)
├── terms.php                 ← Terms of service
├── 403.php / 404.php / 500.php / 503.php  ← Error pages
├── .env.example              ← Environment variable template
├── .gitignore                ← Git ignore rules
├── composer.json             ← PHP dependencies (TCPDF)
└── README.md                 ← This file
```

---

## The Math

Settlement calculation per member in an auction:

```
Total Sold Price    = SUM(sold_price) for sold vehicles
Total Recycle Fee   = SUM(recycle_fee) for sold vehicles
Total Listing Fee   = SUM(listing_fee) for sold vehicles
Total Sold Fee      = SUM(sold_fee) for sold vehicles
Total Nagare Fee    = SUM(nagare_fee) for sold vehicles
Total Other Fee     = SUM(other_fee) for sold vehicles

Subtotal Fees       = Recycle + Listing + Sold + Nagare + Other
Commission Fee      = auction.commission_fee (default ¥3,300 per member)

Total Fees          = Subtotal Fees + Commission Fee + Special Fees
Net Amount          = Total Sold Price - Total Fees
```

---

## 🔔 Update Notifications

AuctionKai checks your GitHub repository for new releases automatically. To trigger an update notification:

1. Create a new Release on GitHub
2. Tag it as `v3.9` (or next version)
3. Write release notes in the description
4. Publish the release
5. Admin panel will show the notification within 1 hour (or click Refresh)

---

## Security

- Passwords: bcrypt via `password_hash()` / `password_verify()`
- CSRF: random 32-char token, validated on all state-changing requests
- SQL: PDO prepared statements throughout (no string interpolation)
- XSS: all output escaped via `h()` helper (`htmlspecialchars`)
- Rate limiting: 5 login attempts, 30-second lockout
- Sessions: HTTP-only cookies, SameSite=Lax, secure flag on HTTPS
- Input: validated on both client (Parsley.js) and server side
- Admin: role-based access, separate auth check
- API: shared bootstrap enforces auth + CSRF before any action

---

## Design

- **Dark theme** with gold (#D4A84B) accents
- Tailwind CSS via CDN with custom config (`tailwind-config.php`)
- Custom CSS variables: `--ak-bg`, `--ak-card`, `--ak-border`, `--ak-text`, `--ak-gold`, etc.
- Mobile-first responsive: card views replace tables on mobile
- Font: Inter (via Google Fonts)
- Icons: Font Awesome 6
- PDFs styled with custom `pdf.css` matching the dark theme

---

## Changelog

- **v3.8** — Auto-update notifications, custom auction fees, mobile admin overhaul, API stream fix, settings column fix, vehicle delete fix
- **v3.7** — Mobile UI fixes, admin panel responsive redesign
- **v3.5** — Special fees, statement links, PDF improvements
- **v3.0** — Multi-auction support, admin panel
- **v2.0** — Members, vehicles, statements
- **v1.0** — Initial release

---

## Credits

Designed & Developed by **Mirai Global Solutions**  
[GitHub](https://github.com/ashifashroff/auctionkai)
