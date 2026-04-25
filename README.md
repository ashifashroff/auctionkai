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

After major updates, drop the entire `auctionkai` database and re-import `schema.sql`. Using the file import option in phpMyAdmin is more reliable than copy-pasting SQL.

**Default logins:**

| Username  | Password   | Role  |
|-----------|------------|-------|
| admin     | password   | admin |

Or register a new account.

---

## What It Does

**Dashboard** — see your auction at a glance. Total members, vehicles, sold count, gross sales, and net payout. Members ranked by net payout so you know who your top sellers are.

**Auctions** — create multiple auctions, switch between them using the navbar chips. Each auction has its own commission fee (default ¥3,300 per member) and auto-expires after 2 weeks. Expired auctions clean up sold vehicles but keep your members. A red badge warns you when expiry is close.

**Members** — shared across all your auctions. Click a member's name to see their sold and unsold vehicles in a modal, with a button to download their PDF statement. Edit members through a popup — no page reload. Duplicate names are blocked with a clear error message.

**Vehicles** — add, edit, and delete without page reload (everything's AJAX). Toggle sold/unsold with one click. Search the table in real-time by lot number, member name, or make/model. Nagare fee only appears for unsold vehicles — sold vehicles get sold price, tax, recycle, listing fee, and sold fee instead.

**Statements** — only members with sold vehicles appear. Full breakdown from gross sales down to net payout. Download individual or all-statement PDFs. Email drafts via mailto link.

**PDF** — print-ready A4 settlement statements with Japanese headers. Sold and unsold vehicles shown in separate tables. Fee breakdown with all deductions. Net payout in bold.

**🛡 Admin Panel** — view all registered users with status badges (active/suspended/restricted). Create new users and admins. Edit any user's name, email, username, role. Suspend users for a specific number of days with reason. Delete users and all their data. Login As any user to view their dashboard. Return to Admin Panel button shown in topbar when impersonating.

---

## Fee Logic

Commission is a flat fee per member (not per vehicle, not a percentage). Default is ¥3,300. You can change it per auction from the top bar.

10% consumption tax is auto-calculated on every sold price.

Nagare fee only applies to unsold vehicles. When you mark a vehicle as sold, the nagare field disables and the sold-price fields enable. When unsold, it flips — nagare enables, sold fields disable.

---

## The Math

```
Total Received  = Sold Price + 10% Tax + Recycle Fee
Total Deductions = Listing Fee + Sold Fee + Nagare Fee (unsold only) + Other Fee + Commission (flat/member)
NET PAYOUT      = Total Received − Total Deductions
```

No sold vehicles means ¥0 net payout.

---

## File Structure

```
auctionkai/
├── api/                        ← AJAX handlers
│   ├── add_vehicle.php
│   ├── delete_vehicle.php
│   ├── get_vehicle.php
│   ├── update_vehicle.php
│   ├── get_member_detail.php
│   └── update_member.php
│
├── auth/                       ← Authentication pages
│   ├── login.php
│   ├── logout.php
│   ├── forgot_password.php
│   └── reset_password.php
│
├── css/
│   ├── style.css               ← Custom styles (forms, tables, statements)
│   ├── pdf.css                 ← PDF print layout
│   └── tailwind-config.php     ← Tailwind CDN + theme colors
│
├── includes/                   ← Shared PHP components
│   ├── auth_check.php          ← Session guard for protected pages
│   ├── db.php                  ← PDO connection
│   ├── helpers.php             ← fmt(), h(), calcStatement()
│   └── footer.php              ← Shared footer component
│
├── js/
│   └── app.js                  ← All client-side JS
│
├── .htaccess                   ← Protect config.php and schema.sql
├── .gitignore                  ← Exclude config.php, logs, OS files
├── config.php                  ← Database credentials
├── schema.sql                  ← Full schema + seed data
├── index.php                   ← Main app (dashboard, members, vehicles, statements)
├── profile.php                 ← Edit name, email, password
├── pdf.php                     ← A4 PDF settlement statements
├── help.php                    ← Help & guide (accordion FAQ)
├── about.php                   ← About AuctionKai + tech stack + version history
├── privacy.php                 ← Privacy policy
└── README.md
```

---

## Database

```
users ──< auction ──< vehicles >── members
```

- Members belong to users (shared across auctions)
- Vehicles belong to auctions (connected to members via member_id)
- Commission fee lives on the auction table
- Nagare fee lives on the vehicles table (only used for unsold)
- No fee_items, vehicle_fees, or custom_deductions tables — all removed

---

## Security

Everything uses PDO prepared statements — no raw SQL interpolation anywhere. All vehicle write queries (delete, toggle sold, update) verify ownership through `auction.user_id`. CSRF tokens protect every form. Passwords are bcrypt. Login regenerates the session ID to prevent fixation attacks. After 5 failed login attempts for the same username, there's a 30-second cooldown. No real personal data in the seed file.
- schema.sql seed data uses placeholder credentials only — never commit real usernames or passwords to public repos
- Admin role required to access admin.php
- User impersonation tracked via session original_admin_id
- Suspended users blocked at login with expiry date shown

---

## Design

Deep navy background (#0A1420), dark blue cards (#111E2D), gold accent (#D4A84B). Noto Sans JP for text, Space Mono for prices. Buttons lift on hover. Cards fade in with staggered timing. The active auction chip pulses gold.

---

## Changelog

**v2.4** — Full admin panel with user management. Login As user impersonation. Suspend / unsuspend users. Create new users and admins. Session regeneration after login. Brute force login protection.

**v2.3** — AJAX for everything (no page reloads on any form), delete auction page with stats and confirmation, duplicate member name check, auction toggle fix, date field disabled after creation

**v2.2** — Brute force login protection, CSRF tokens on login/register, session regeneration, removed personal data from schema

**v2.1** — Dashboard tab, vehicle search, PDO prepared statements everywhere, vehicle ownership verification

**v2.0** — Tailwind CSS migration, modal-based editing, AJAX add/edit/delete, profile page

**v1.0–1.6** — Initial build, fee system overhaul, commission as flat fee, nagare for unsold, PDF improvements

---

## Troubleshooting

If CSS looks broken or modals don't open, hard refresh (Ctrl+Shift+R) — the Tailwind CDN and JS files cache aggressively. If you're locked out of login, wait 30 seconds. If a form says "Invalid request", refresh the page (CSRF token expired). After schema changes, always drop the entire database and re-import `schema.sql` rather than trying to alter tables.

---

---

## Credits

Designed & Developed by Mirai Global Solutions
© 2025–2026 AuctionKai. All rights reserved.

---

## Credits

Designed & Developed by Mirai Global Solutions
© 2025–<?= date('Y') ?> All rights reserved.
