# ⚡ AuctionKai

Japanese auto auction settlement system. Dark premium theme, vanilla PHP + Tailwind CSS, MySQL, zero frameworks.

---

## What You Need

- PHP 8.0+ with PDO MySQL
- MySQL 5.7+ or MariaDB 10.3+
- XAMPP (or similar stack)
- Internet connection (Tailwind CDN + Google Fonts load from the web)

---

## Setup

**1. Create the database** — open phpMyAdmin, go to the SQL tab, paste the entire contents of `schema.sql`, and hit Go.

**2. Configure the connection** — edit `config.php` with your MySQL host, database name, user, and password.

**3. Open the app** — navigate to `http://localhost/auctionkai/` in your browser.

**Fresh install** — paste the entire contents of `schema.sql` into phpMyAdmin → SQL tab. This creates all tables and seed data.

**Updating an existing database** — run only the new migrations from `migrations.sql`. Never run `schema.sql` on production — it drops and recreates tables, destroying all data. Each migration in `migrations.sql` is idempotent (safe to run multiple times) and only adds new tables/columns/indexes.

**Default logins:**

| Username  | Password   | Role  |
|-----------|------------|-------|
| admin     | password   | admin |

Or register a new account. Usernames and emails must be unique.

---

## What It Does

**Dashboard** — see your auction at a glance. Total members, vehicles, sold count, gross sales, and net payout. Members ranked by net payout so you know who your top sellers are.

**Auctions** — create multiple auctions, switch between them using the navbar chips. Each auction has its own commission fee (default ¥3,300 per member) and auto-expires after 2 weeks. Expired auctions delete all vehicles (sold and unsold) and the auction itself. Member records are preserved. A red badge warns you when expiry is close.

**Members** — shared across all your auctions. Click a member's name to see their sold and unsold vehicles in a modal, with a button to download their PDF statement. Edit members through a popup — no page reload. Search members by name, phone, or email with instant filtering. Duplicate names are blocked with a clear error message. Bulk CSV import — upload CSV file to add multiple members at once. Auto-detects header row. Supports name/phone/email columns in any order. Skips duplicates automatically. Shows per-row error details. Download CSV template with example data.

**Vehicles** — add, edit, and delete without page reload (everything's AJAX). Toggle sold/unsold with one click. Paginated vehicles table (10/25/50/100 per page). Real-time search filter by lot, make, model, member name. AJAX pagination — no full page reload. Skeleton loading animation while fetching. Stays on same page after add/edit/delete. Nagare fee only appears for unsold vehicles — sold vehicles get sold price, tax, recycle, listing fee, and sold fee instead. Duplicate lot numbers are caught in real-time before submission.

**Statements** — only members with sold vehicles appear. Full breakdown from gross sales down to net payout. Download individual or all-statement PDFs. Email drafts via mailto link.

**PDF** — print-ready A4 settlement statements with Japanese headers. Sold and unsold vehicles shown in separate tables. Fee breakdown with all deductions. Net payout in bold. White background, print-friendly layout.

**🛡 Admin Panel** — view all registered users with status badges (active/suspended/restricted). Create new users and admins. Edit any user's name, email, username, role. Suspend users for a specific number of days with reason. Delete users and all their data. Login As any user to view their dashboard. Return to Admin Panel button shown in topbar when impersonating.

**📋 Activity Log** — tracks all important actions automatically. Actions logged: login/logout, auction create/update/delete, member add/update/remove, vehicle add/update/delete/toggle, PDF generate, email send, backup download, admin actions, password changes. Admin panel shows full log for all users with pagination and filtering. Profile page shows user's own last 20 actions. Old logs can be cleared by admin (min 30 days). Never crashes the app — errors caught silently.

**📖 Help & Guide** — built-in accordion-style help page covering getting started, managing members, vehicles, statements, and fee settings.

**🔒 Forgot Password** — request a password reset link by email. Reset with a new password (minimum 8 characters). Password strength indicator shows Weak/Fair/Good/Strong in real-time.

**⌨ Keyboard Shortcuts** — press `?` to see all shortcuts. Navigate tabs with `G` then `M/V/S/D`. Add vehicle with `N`, add member with `Shift+N`. Focus lot field with `L`. Search with `/`. Close modals with `Esc`.

---

## Fee Logic

Commission is a flat fee per member (not per vehicle, not a percentage). Default is ¥3,300. You can change it per auction from the top bar.

10% consumption tax is auto-calculated on every sold price.

Nagare fee only applies to unsold vehicles. When you mark a vehicle as sold, the nagare field disables and the sold-price fields enable. When unsold, it flips — nagare enables, sold fields disable.

---

## The Math

```
Total Received  = Sold Price + 10% Tax + Recycle Fee
Total Deductions = Listing Fee + Sold Fee + Nagare Fee (unsold only) + Commission (flat/member)
NET PAYOUT      = Total Received − Total Deductions
```

No sold vehicles means ¥0 net payout.

---

## File Structure

```
auctionkai/
├── admin/
│   ├── index.php               ← User management + Email Settings
│   ├── actions.php             ← Handle admin POST actions
│   ├── health.php              ← System health dashboard
│   ├── download_backup.php     ← Secure backup download
│   └── .htaccess
├── api/
│   ├── add_vehicle.php
│   ├── delete_vehicle.php
│   ├── get_vehicle.php
│   ├── update_vehicle.php
│   ├── get_member_detail.php
│   ├── update_member.php
│   ├── check_lot.php
│   ├── get_vehicles_page.php
│   ├── send_email.php
│   ├── import_members_csv.php  ← CSV bulk member import
│   ├── csv_template.php         ← Download CSV template
│   └── delete_auction.php
│   └── delete_account.php    ← GDPR account deletion
│   └── update_payment.php    ← Payment status AJAX
│   └── log_statement.php     ← Statement event logger
│   └── download_pdf_zip.php  ← Bulk ZIP download
│   └── member_fees.php       ← Special fees CRUD
├── backups/                  ← Auto-created backup files
│   └── .htaccess             ← Block direct access
├── scripts/
│   └── backup.php            ← Cron backup script
├── auth/
│   ├── login.php
│   ├── logout.php
│   ├── forgot_password.php
│   └── reset_password.php
├── css/
│   ├── style.css
│   ├── pdf.css
│   ├── summary.css
│   └── tailwind-config.php
├── includes/
│   ├── auth_check.php
│   ├── admin_check.php
│   ├── db.php
│   ├── helpers.php
│   ├── mailer.php
│   ├── settings.php
│   ├── maintenance_check.php ← Maintenance gate
│   ├── branding.php          ← Dynamic branding loader
│   └── footer.php
├── js/
│   └── app.js
├── vendor/                     ← PHPMailer (gitignored)
├── .htaccess
├── .gitignore
├── config.php
├── schema.sql
├── index.php
├── profile.php
├── pdf.php
├── auction_summary.php
├── help.php
├── about.php
├── privacy.php
└── README.md
```
│   ├── add_vehicle.php
│   ├── delete_vehicle.php
│   ├── get_vehicle.php
│   ├── update_vehicle.php
│   ├── get_member_detail.php
│   ├── update_member.php
│   └── check_lot.php           ← Duplicate lot number check
│   └── get_vehicles_page.php    ← Paginated vehicle fetch
│   └── send_email.php          ← AJAX email endpoint
│
├── admin/                       ← Admin panel
│   ├── index.php               ← User management + Email Settings
│   ├── actions.php             ← Handle admin POST actions (users + email)
│   └── .htaccess               ← Protect admin directory
│
│   └── db_backup.php             ← Admin-only SQL backup generator
├── auth/                       ← Authentication pages
│   ├── login.php
│   ├── logout.php
│   ├── forgot_password.php
│   └── reset_password.php
│
├── css/
│   ├── style.css               ← Custom styles (forms, tables, statements, toasts, shortcuts)
│   ├── pdf.css                 ← PDF print layout
│   └── tailwind-config.php     ← Tailwind CDN + theme colors
│
├── includes/                   ← Shared PHP components
│   ├── auth_check.php          ← Session guard for protected pages
│   ├── db.php                  ← PDO connection
│   ├── helpers.php             ← fmt(), h(), calcStatement()
│   ├── mailer.php              ← PHPMailer wrapper, multi-provider
│   ├── settings.php             ← DB settings helper
│   ├── activity.php             ← Activity logging helper
│   └── footer.php              ← Shared footer component
│
├── js/
│   └── app.js                  ← All client-side JS (toasts, shortcuts, AJAX)
│
├── .htaccess                   ← Protect config.php and schema.sql
├── .gitignore                  ← Exclude config.php, logs, OS files
├── config.php                  ← Database credentials
├── schema.sql                  ← Full schema + seed data + indexes
├── index.php                   ← Main app (dashboard, members, vehicles, statements)
├── profile.php                 ← Edit name, email, password
├── pdf.php                     ← A4 PDF settlement statements
├── vendor/                     ← PHPMailer (gitignored)
├── help.php                    ← Help & guide (accordion FAQ)
├── about.php                   ← About AuctionKai + tech stack + version history
├── privacy.php                 ← Privacy policy
├── terms.php                   ← Terms of Use page
└── README.md
```

---

## Database

```
users ──< auction ──< vehicles >── members
password_resets (token-based password reset)
```

- Members belong to users (shared across auctions)
- Vehicles belong to auctions (connected to members via member_id)
- Commission fee lives on the auction table
- Nagare fee lives on the vehicles table (only used for unsold)
- Users have status (active/suspended/restricted) with suspend tracking
- Performance indexes on auction_id, user_id, member_id, and sold columns

---

## Security

Everything uses PDO prepared statements — no raw SQL interpolation anywhere. All vehicle write queries (delete, toggle sold, update) verify ownership through `auction.user_id`. CSRF tokens protect every form. Passwords are bcrypt with `password_hash()`. Login regenerates the session ID to prevent fixation attacks. After 5 failed login attempts for the same username, there's a 30-second cooldown. No real personal data in the seed file.

- schema.sql seed data uses placeholder credentials only — never commit real usernames or passwords to public repos
- Admin role required to access admin/ panel
- User impersonation tracked via session `original_admin_id`
- Suspended users blocked at login with expiry date shown
- Duplicate email and username checks on registration
- Duplicate lot number check via real-time AJAX before vehicle save
- Login history — tracks last 50 login attempts per user (success and failed)
- Shows browser, OS, IP address, timestamp on profile page
- Admin panel shows last login per user
- Failed attempts shown with red highlight
- Session timeout — auto logout after configurable inactivity period (default 30 min)
- Warning toast appears X minutes before expiry with "Stay Logged In" button
- Configurable from Admin Panel → Session Settings
- Timeout duration: 5–480 minutes
- Warning time: 1–10 minutes before expiry
- Auto-logout activity logged
- Account deletion (GDPR) — users can permanently delete their account and all associated data
- Password confirmation required before deletion
- Prevents deletion of last admin account
- Deletes all data: auctions, members, vehicles, login history, activity log
- Session destroyed immediately after deletion
- Payment status tracking per member (Unpaid / Partial / Paid)
- One-click status update from Statements tab
- Payment summary dashboard (total paid/unpaid)
- Paid timestamp recorded automatically
- PAID/PARTIAL stamp on PDF statements
- Payment status logged to activity log
- System Health Check page showing PHP version, MySQL stats, disk space, PHP extensions, server info, app statistics, warning alerts, and quick actions
- Maintenance mode — put system in maintenance with one toggle
- Custom maintenance page title and message
- Optional ETA display on maintenance page
- Admins bypass maintenance and can still work normally
- Animated gear icon with auto-refresh every 60 seconds
- Yellow warning banner visible to admins when maintenance is active
- Maintenance events logged to activity log
- Custom branding — set system name, tagline, company name, contact details
- Custom accent color with live color picker and live preview
- Branding applied to: app header, PDF statements, email templates, footer
- Contact info (email, phone, address) appears on PDF statements
- Changes take effect immediately — no code editing required
- Scheduled backups (daily/weekly/monthly) with cron support
- Configurable retention period
- Gzip compression support
- Manual trigger from admin panel
- Backup file browser with download/delete
- Protected backups/ folder
- Cron setup instructions shown in panel
- Statement history — tracks every PDF generated and email sent
- Per-member collapsible history on Statements tab
- Shows action type, net payout, timestamp, IP address
- Admin panel shows full history across all users
- Bulk PDF ZIP download — all member statements in one ZIP file
- Each member gets their own HTML statement file (open in browser → print to PDF)
- README.txt included in ZIP with instructions
- Auction name and date in ZIP filename
- Requires PHP ZipArchive extension (check System Health page)
- ZIP download logged to activity log
- Special Fees tab — add custom per-member fees for each auction
- Supports deductions (car wash, bank charges, storage, repairs) and additions (bonus payments)
- Quick preset buttons for common fees
- Fees appear in settlement statements and PDF documents
- Real-time UI update without page reload
- Delete fees individually
- All fee changes logged to activity log
- All form validation via Parsley.js (no HTML5 native validation)
- Password minimum 8 characters with strength indicator

---

## Design

Deep navy background (#0A1420), dark blue cards (#111E2D), gold accent (#D4A84B). Noto Sans JP for text, Space Mono for prices. Buttons lift on hover. Cards fade in with staggered timing. The active auction chip pulses gold. Toast notifications for all user actions (success/error/warning/info). Mobile responsive with card view for vehicles on small screens.

---

## Changelog

**v3.4** — Login history tracking, session timeout with admin controls, GDPR account deletion, payment status tracking with PDF stamp, system health check page, maintenance mode, custom branding with color picker, scheduled backups with cron support, statement history tracking, bulk PDF ZIP download, special fees tab per member per auction with presets

**v3.3** — Login history tracking: records success and failed login attempts per user (browser, OS, IP, timestamp). Profile page shows last 10 attempts. Admin panel shows last login per user. Failed attempts highlighted in red. Auto-cleanup keeps last 50 records per user.

**v3.2** — Bulk member import via CSV file upload. CSV template download with example data. Auto duplicate detection on import. Per-row error reporting.

**v3.1** — Activity log system across all actions. Admin panel shows full log with pagination and filtering. Profile page shows user's own last 20 actions. Old logs can be cleared by admin (min 30 days). Never crashes the app — errors caught silently.

**v3.0** — Security hardening: CSRF on all API endpoints, secure session cookies (httponly+samesite), rate limiting on password reset, input length limits, removed duplicate admin.php. Multi-provider email settings via admin panel (Server Mail/Gmail/Xserver/Sakura/Custom SMTP). Email credentials stored in DB (not config files). AJAX admin forms (no page refresh). Paginated vehicles + members tables with search and AJAX loading. Skeleton loading states. 2-column statement cards with member search. API bootstrap for consistent auth/CSRF.

**v2.6** — Admin panel with user management (separate admin/ folder). Disable/enable user accounts. Role management (admin/user). User stats dashboard. Disabled users blocked at login.

**v2.5** — Removed Other Fee from all UI/forms/tables/statements/PDF. Real-time duplicate lot number check. Password strength indicator (Weak/Fair/Good/Strong). Member search filter. New members appear at top without page reload. Keyboard shortcuts with help modal. Toast notifications across all pages. Parsley.js form validation. Mobile responsive vehicles table (card view). Help, About, Privacy pages. Shared footer component. Forgot password / reset password flow. Duplicate email check on registration. Cache-busting for CSS/JS. Fixed vehicle add/edit bugs (placeholder count, variable names).

**v2.4** — Real email sending via PHPMailer + Gmail SMTP. HTML settlement email with full fee breakdown. Email config status in admin panel. Paginated vehicles table with AJAX loading. Real-time search across all vehicle fields. 10/25/50/100 per page selector. Skeleton loading state.

**v2.4** — Full admin panel with user management. Login As user impersonation. Suspend / unsuspend users. Create new users and admins. Session regeneration after login. Brute force login protection. Auth/ and includes/ folder restructure.

**v2.3** — AJAX for everything (no page reloads on any form), delete auction page with stats and confirmation, duplicate member name check, auction toggle fix, date field disabled after creation

**v2.2** — Brute force login protection, CSRF tokens on login/register, session regeneration, removed personal data from schema

**v2.1** — Dashboard tab, vehicle search, PDO prepared statements everywhere, vehicle ownership verification, DB indexes

**v2.0** — Tailwind CSS migration, modal-based editing, AJAX add/edit/delete, profile page

**v1.0–1.6** — Initial build, fee system overhaul, commission as flat fee, nagare for unsold, PDF improvements

---

## Troubleshooting

If CSS looks broken or modals don't open, hard refresh (Ctrl+Shift+R) — the Tailwind CDN and JS files cache aggressively. CSS and JS files include `?v=2.5` cache-busting to help. If you're locked out of login, wait 30 seconds. If a form says "Invalid request", refresh the page (CSRF token expired). After schema changes, always drop the entire database and re-import `schema.sql` rather than trying to alter tables. If toast notifications don't appear, clear browser cache and reload.

---

## 📧 Email Setup

Email is configured through the Admin Panel.
No credentials in code files — safe for GitHub.

### First-Time Setup: Install PHPMailer

The `vendor/` folder is gitignored and won't appear after `git pull`. Set it up once:

**Option A — Composer (recommended):**
```bash
composer require phpmailer/phpmailer
```

**Option B — Manual:**
1. Create folder: `vendor/phpmailer/phpmailer/src/`
2. Download from [PHPMailer GitHub](https://github.com/PHPMailer/PHPMailer/tree/master/src):
   - `PHPMailer.php`
   - `SMTP.php`
   - `Exception.php`
3. Create `vendor/autoload.php`:
```php
<?php
require_once __DIR__ . '/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/phpmailer/src/SMTP.php';
```

> **Note:** Future `git pull` will NOT delete your local `vendor/` folder — Git ignores it completely.

### Supported Providers
| Provider | Notes |
|---|---|
| Server Mail | PHP mail(), no credentials needed |
| Custom SMTP | Any SMTP server |
| Gmail SMTP | Requires Gmail App Password |
| Xserver | Works with Xserver hosting SMTP |
| Sakura Internet | Works with Sakura hosting SMTP |

### Setup Steps
1. Log in as admin
2. Go to Admin Panel → Email Settings
3. Select your mail provider
4. Enter credentials
5. Click Test Email to verify
6. Enable and Save

---

### Database Backup
- One-click full SQL backup from Admin Panel
- Downloads timestamped .sql file
- Compatible with phpMyAdmin import
- Includes all tables: users, auctions, members, vehicles, settings, activity logs
- Admin only — protected by session check

---

## ⚡ Performance

Database indexes are included in schema.sql for all frequently queried columns:

- vehicles — indexed on auction_id, member_id, sold, lot, and composite (auction_id, sold)
- auction — indexed on user_id, expires_at, composite (user_id, date)
- members — indexed on user_id
- users — indexed on username, email, role

For existing installs, run the index creation queries at the bottom of schema.sql in phpMyAdmin → SQL tab.

---

## Credits

Designed & Developed by Mirai Global Solutions
© 2025–2026 AuctionKai. All rights reserved.
