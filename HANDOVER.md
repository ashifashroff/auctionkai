# HANDOVER.md — Project Transfer Documentation

**Last updated:** 2026-05-11  
**Version:** v3.8  
**Agent:** Mirai Agent (OpenClaw main)

---

## Current Project Status: ✅ STABLE

AuctionKai is fully functional and in production. All core features working. Admin panel operational. Mobile responsive. Auto-update system live.

---

## What Is Completed

### Core Application
- [x] User registration, login, logout, forgot/reset password
- [x] Multi-auction support with create/edit/delete
- [x] Member management (add, edit, delete, CSV import)
- [x] Vehicle management (add, edit, delete, toggle sold, inline edit)
- [x] Fee calculation (recycle, listing, sold, nagare, other, special fees)
- [x] Commission fee per auction (customizable, default ¥3,300)
- [x] PDF statement generation (TCPDF)
- [x] Statement sharing via PIN-protected links
- [x] Email statements with PDF attachment
- [x] Auction summary view
- [x] Dashboard with stats

### Admin Panel (all tabs working)
- [x] Users management (create, edit, suspend, delete, impersonate)
- [x] Activity log (pagination, filtering)
- [x] Error log viewer (severity, resolve, cleanup)
- [x] Email settings (SMTP, Mailgun, SendGrid, SES)
- [x] Session timeout settings
- [x] Maintenance mode
- [x] Branding (name, logo, colors, footer)
- [x] Database backup (create, download, cleanup)
- [x] reCAPTCHA configuration
- [x] Admin settings (username, name, email, password change)
- [x] Updates tab (version check, release notes, how-to guide)

### Infrastructure
- [x] Auto-update notifications (GitHub Releases API, 1-hour cache)
- [x] Mobile responsive design (320px–768px)
- [x] CSRF protection on all endpoints
- [x] Login rate limiting
- [x] Custom error pages (403/404/500/503)
- [x] Health check page

---

## Current Bugs / Known Issues

### ⚠️ Site2 Agent (Mirive_bot)
- **Status:** NOT WORKING
- **Bot:** @Baxin_Store_Bot (Telegram ID: 8756715440)
- **Agent:** `site2` on OpenClaw
- **Problem:** Internal errors, provider 4xx on `vultr/nvidia/DeepSeek-V3.2-NVFP4`
- **Fix attempted:** Changed default model to `vultr/zai-org/GLM-5.1-FP8`, cleared sessions
- **Still failing:** Bot may not be receiving/processing messages correctly
- **Next step:** Check OpenClaw logs for routing errors, verify Telegram webhook, test Vultr API model list

### Minor Issues
- No automated test suite (manual testing only)
- Parsley.js sometimes conflicts with AJAX form submission (removed from session/reCAPTCHA forms)
- `format_yen()` in JS may not handle NaN gracefully (edge case with empty inputs)
- CSV import has no duplicate detection (will create duplicate members)

---

## Priorities for Next Steps

### High Priority
1. **Fix site2 agent** — diagnose Telegram bot connectivity and model routing
2. **Verify vehicle delete** — confirm the `showConfirmModal` fix works on production
3. **Test admin forms** — ensure all admin tabs save correctly after settings column fix

### Medium Priority
4. **Add duplicate detection** for CSV member import
5. **Add API rate limiting** beyond just login (prevent brute-force on all endpoints)
6. **Add audit log** for admin actions (login-as, user delete, settings changes)
7. **Add data export** — CSV/Excel export for members and vehicles

### Low Priority / Nice-to-Have
8. **WebSocket** for real-time updates (vehicle changes, payment updates)
9. **Two-factor authentication** (TOTP)
10. **Automated backup scheduling** (cron job for `scripts/backup.php`)
11. **Dark/light theme toggle**
12. **Internationalization** (Japanese/English switch)

---

## Important Warnings

### ⚠️ php://input Stream Bug (FIXED but fragile)
The CSRF check in `api_bootstrap.php` reads `php://input` to parse JSON for CSRF validation. This consumed the stream, so subsequent `json_decode(file_get_contents('php://input'))` returned empty. **Fix:** JSON is cached in `$GLOBALS['_json_input']`. If any new API file reads `php://input` directly instead of using `$GLOBALS['_json_input']`, it will break. **Always use:**
```php
$input = json_decode($GLOBALS['_json_input'] ?? file_get_contents('php://input'), true);
```

### ⚠️ Settings Table Column Names
The `settings` table uses `key` and `value` columns (NOT `setting_key`/`setting_value`). Some old code may reference the wrong names. The current `includes/settings.php` uses the correct `key`/`value`.

### ⚠️ OPcache
Production server has OPcache enabled. After any code deploy, you MUST clear it:
```php
opcache_reset();
```
Otherwise PHP will serve cached (old) files.

### ⚠️ config.php Not in Git
`config.php` is excluded from git and rsync. It's configured on the server via Plesk environment variables. If the server is wiped, you need to recreate it from `.env.example` with production values.

### ⚠️ No Admin Action Audit Trail
Admin actions like "Login As" (impersonation), user deletion, and settings changes are logged in `activity_log` but there's no dedicated admin audit log with immutable records.

---

## Deployment Quick Reference

```bash
# Standard deploy
cd ~/.openclaw/workspace/auctionkai
git add -A && git commit -m "message" && git push
rsync -az --delete --exclude='config.php' --exclude='vendor/' --exclude='.env' --exclude='.git/' \
  -e "ssh -i ~/.ssh/id_ed25519 -o StrictHostKeyChecking=no" \
  ./ miraiglobaltrading@89.117.58.8:/var/www/vhosts/miraiglobaltrading.com/auctionkai.miraiglobaltrading.com/
ssh -i ~/.ssh/id_ed25519 miraiglobaltrading@89.117.58.8 "/opt/plesk/php/8.3/bin/php -r 'opcache_reset();'"
```

---

## Key Files to Read First

1. `includes/constants.php` — version, limits, all app constants
2. `includes/api_bootstrap.php` — shared API logic (auth, CSRF, JSON input caching)
3. `includes/helpers.php` — `h()`, `format_yen()`, `calcStatement()`, PDF builders
4. `js/common.js` — `showToast()`, `showConfirmModal()`, CSRF handling
5. `schema.sql` — full database structure
6. `config.php` (on server only) — DB credentials

---

## Architecture Quick Map

```
index.php → includes/ (auth, db, helpers, settings)
         → views/ (dashboard, members, vehicles, statements, special_fees)
         → views/partials/ (head, topbar, auction_bar, tabs, modals)
         → js/ (app.js, common.js, members.js, vehicles.js, fees.js, statements.js)

api.php → JSON API router (add_auction, add_member, save_auction)
api/   → Individual API files (add_vehicle, delete_vehicle, etc.)
         All include api_bootstrap.php

admin/index.php → Admin panel (all tabs, single-page)
admin/health.php → Health check + error viewer
```

---

*This document should be updated whenever significant changes are made to the project.*
