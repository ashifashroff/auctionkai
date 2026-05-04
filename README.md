# ⚡ AuctionKai

Japanese auto auction settlement system. Dark premium theme, vanilla PHP + Tailwind CSS, MySQL, zero frameworks.

---

## What You Need

- PHP 8.0+ with PDO MySQL
- MySQL 5.7+ or MariaDB 10.3+
- XAMPP or similar stack (or any Plesk/cPanel hosting)
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

**Special Fees** — add custom per-member fees for each auction. Supports deductions (car wash, bank charges, storage, repairs, inspection, key duplicate) and additions (bonus payments). Quick preset chips for common fees. Fees appear in settlement statements and PDF documents. Server-rendered table with member name, fee name, type badge, amount, and date. Summary row shows total deductions and additions. Delete fees individually with animated row removal. All fee changes logged to activity log.

**Statements** — only members with sold vehicles appear. Full breakdown from gross sales down to net payout. Special fees included in deductions/additions. Download individual or all-statement PDFs. Email drafts via mailto link. Payment status tracking (Unpaid / Partial / Paid) with one-click update. Paid timestamp recorded automatically. PAID/PARTIAL stamp on PDF statements.

**PDF** — print-ready A4 settlement statements with Japanese headers. Sold and unsold vehicles shown in separate tables. Fee breakdown with all deductions including special fees (bold). Net payout in bold. PAID/PARTIAL watermark stamp. White background, print-friendly layout. Bulk ZIP download — all member statements in one ZIP file.

**🛡 Admin Panel** — view all registered users with status badges (active/suspended/restricted). Create new users and admins. Edit any user's name, email, username, role. Suspend users for a specific number of days with reason. Delete users and all their data. Login As any user to view their dashboard. Return to Admin Panel button shown in topbar when impersonating.

**📋 Activity Log** — tracks all important actions automatically. Actions logged: login/logout, auction create/update/delete, member add/update/remove, vehicle add/update/delete/toggle, PDF generate, email send, backup download, admin actions, password changes, special fee add/edit/delete, payment status changes. Admin panel shows full log for all users with pagination and filtering. Profile page shows user's own last 20 actions. Old logs can be cleared by admin (min 30 days). Never crashes the app — errors caught silently.

**📖 Help & Guide** — built-in accordion-style help page covering getting started, managing members, vehicles, special fees, statements, and fee settings.

**🔒 Forgot Password** — request a password reset link by email. Reset with a new password (minimum 8 characters). Password strength indicator shows Weak/Fair/Good/Strong in real-time.

**⌨ Keyboard Shortcuts** — press `?` to see all shortcuts. Navigate tabs with `G` then `M/V/S/D`. Add vehicle with `N`, add member with `Shift+N`. Focus lot field with `L`. Search with `/`. Close modals with `Esc`.

---

## Fee Logic

Commission is a flat fee per member (not per vehicle, not a percentage). Default is ¥3,300. You can change it per auction from the top bar.

10% consumption tax is auto-calculated on every sold price.

Nagare fee only applies to unsold vehicles. When you mark a vehicle as sold, the nagare field disables and the sold-price fields enable. When unsold, it flips — nagare enables, sold fields disable.

Special fees are per-member, per-auction. They can be deductions (subtract from payout) or additions (add to payout). They appear in the settlement statement fee breakdown and PDF.

---

## The Math

```
Total Received  = Sold Price + 10% Tax + Recycle Fee
Total Deductions = Listing Fee + Sold Fee + Nagare Fee (unsold only)
                  + Commission (flat/member)
                  + Special Fee Deductions − Special Fee Additions
NET PAYOUT      = Total Received − Total Deductions
```

No sold vehicles means ¥0 net payout.

---

## File Structure

```
auctionkai/
├── admin/
│   ├── index.php               ← User management + Email Settings + Backups
│   ├── actions.php             ← Handle admin POST actions
│   ├── health.php              ← System health dashboard
│   └── download_backup.php     ← Secure backup download
├── models/
│   ├── AuctionModel.php
│   ├── MemberModel.php
│   ├── VehicleModel.php
│   ├── SettingsModel.php
│   ├── PaymentModel.php
│   └── MemberFeesModel.php
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
│   ├── import_members_csv.php
│   ├── csv_template.php
│   ├── delete_auction.php
│   ├── delete_account.php
│   ├── update_payment.php
│   ├── log_statement.php
│   ├── download_pdf_zip.php
│   ├── get_member_fees_page.php
│   └── member_fees.php         ← Special fees CRUD
├── auth/
│   ├── login.php
│   ├── logout.php
│   ├── forgot_password.php
│   └── reset_password.php
├── backups/
│   └── .htaccess
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
│   ├── activity.php
│   ├── maintenance_check.php
│   ├── models.php
│   ├── branding.php
│   └── footer.php
├── js/
│   └── app.js
├── scripts/
│   └── backup.php              ← Cron backup script
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
├── terms.php
└── README.md
```

---

## Database

```
users ──< auction ──< vehicles >── members
                   └─< member_fees >── members
                   └─< payment_status >── members
password_resets (token-based password reset)
activity_log (tracks all user actions)
statement_history (tracks PDF/email events)
settings (key-value store for branding, email, maintenance, session)
```

- Members belong to users (shared across auctions)
- Vehicles belong to auctions (connected to members via member_id)
- Member fees belong to auctions + members (per-member, per-auction)
- Payment status tracked per member per auction (Unpaid/Partial/Paid)
- Commission fee lives on the auction table
- Nagare fee lives on the vehicles table (only used for unsold)
- Users have status (active/suspended/restricted) with suspend tracking
- Performance indexes on auction_id, user_id, member_id, and sold columns

---

## Security

Everything uses PDO prepared statements — no raw SQL interpolation anywhere. All vehicle write queries (delete, toggle sold, update) verify ownership through `auction.user_id`. CSRF tokens protect every form. Passwords are bcrypt with `password_hash()`. Login regenerates the session ID to prevent fixation attacks. After 5 failed login attempts for the same username, there's a 30-second cooldown. No real personal data in the seed file.

- Admin role required to access admin/ panel
- User impersonation tracked via session `original_admin_id`
- Suspended users blocked at login with expiry date shown
- Duplicate email and username checks on registration
- Duplicate lot number check via real-time AJAX before vehicle save
- Login history — tracks last 50 login attempts per user (success and failed)
- Session timeout — auto logout after configurable inactivity period (default 30 min)
- Account deletion (GDPR) — users can permanently delete their account and all data
- Payment status tracking per member (Unpaid / Partial / Paid)
- PAID/PARTIAL stamp on PDF statements
- Maintenance mode — put system in maintenance with one toggle, admins bypass
- Custom branding — system name, tagline, company, contact details, accent color
- All form validation via Parsley.js (no HTML5 native validation)
- Password minimum 8 characters with strength indicator

---

## Design

Deep navy background (#0A1420), dark blue cards (#111E2D), gold accent (#D4A84B). Noto Sans JP for text, Space Mono for prices. Buttons lift on hover. Cards fade in with staggered timing. The active auction chip pulses gold. Toast notifications for all user actions (success/error/warning/info). Mobile responsive with card view for vehicles on small screens.

---

## Changelog

**v3.5** — Special fees tab redesign matching vehicle tab style (grid layout, member search dropdown, quick preset chips, server-rendered table with summary row). PDF fixes: branding variable scope, header duplication, PAID stamp positioning, special fees bold. Delete auction fix (unclosed braces + cleanup of member_fees/payment_status). Member dropdown styling consistency.

**v3.4** — Login history tracking, session timeout with admin controls, GDPR account deletion, payment status tracking with PDF stamp, system health check page, maintenance mode, custom branding with color picker, scheduled backups with cron support, statement history tracking, bulk PDF ZIP download, special fees tab per member per auction with presets.

**v3.3** — Login history tracking: records success and failed login attempts per user (browser, OS, IP, timestamp). Profile page shows last 10 attempts. Admin panel shows last login per user. Failed attempts highlighted in red. Auto-cleanup keeps last 50 records per user.

**v3.2** — Bulk member CSV import, activity log system across all actions, admin log viewer with AJAX pagination.

**v3.1** — Activity logging, profile activity history, admin log viewer.

**v3.0** — Multi-provider email support, CSRF on all APIs, paginated vehicles + members, AJAX admin, security hardening.

**v2.6** — Admin panel with user management. Disable/enable user accounts. Role management. User stats dashboard.

**v2.5** — Real-time duplicate lot check. Password strength indicator. Member search. Keyboard shortcuts. Toast notifications. Parsley.js validation. Mobile responsive. Help, About, Privacy pages. Forgot password flow.

**v2.4** — Full admin panel with user impersonation, suspend/unsuspend, brute force protection. Real email via PHPMailer + Gmail SMTP.

**v2.3** — AJAX for everything, delete auction page, duplicate member check.

**v2.2** — Brute force login protection, CSRF tokens, session regeneration.

**v2.1** — Dashboard tab, vehicle search, PDO prepared statements, DB indexes.

**v2.0** — Tailwind CSS migration, modal-based editing, AJAX add/edit/delete, profile page.

**v1.0–1.6** — Initial build, fee system overhaul, commission as flat fee, nagare for unsold, PDF improvements.

---

## Troubleshooting

If CSS looks broken or modals don't open, hard refresh (Ctrl+Shift+R) — the Tailwind CDN and JS files cache aggressively. CSS and JS files include `?v=3.5` cache-busting to help. If you're locked out of login, wait 30 seconds. If a form says "Invalid request", refresh the page (CSRF token expired). After schema changes, always drop the entire database and re-import `schema.sql` rather than trying to alter tables. If toast notifications don't appear, clear browser cache and reload.

---

## 📧 Email Setup

Email is configured through the Admin Panel. No credentials in code files — safe for GitHub.

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
- member_fees — indexed on auction_id, member_id

For existing installs, run the index creation queries at the bottom of schema.sql in phpMyAdmin → SQL tab.

---

## Credits

Designed & Developed by Mirai Global Solutions
© 2025–2026 AuctionKai. All rights reserved.
