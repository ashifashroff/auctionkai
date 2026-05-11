# SKILLS.md — Project Workflows, Standards & Practices

## Coding Standards

### PHP
- **Style:** PSR-12 adjacent (not strict, but consistent)
- **Tags:** `<?php` only, no short tags
- **Encoding:** UTF-8, all files
- **Naming:** snake_case for functions/variables, PascalCase for classes
- **DB queries:** Always use PDO prepared statements, never interpolate
- **Output escaping:** Use `h()` helper (wraps `htmlspecialchars` with ENT_QUOTES)
- **Error handling:** Try/catch around DB operations, custom error handler logs to DB
- **Includes:** Use `require_once` for critical dependencies, `include` for templates

### JavaScript
- **Style:** ES6+, async/await for fetch calls
- **Naming:** camelCase for functions/variables
- **API calls:** Always include CSRF token (`_tok`) and handle errors with `showToast()`
- **No frameworks:** Vanilla JS only, jQuery is NOT used
- **DOM:** Use `document.getElementById`, `querySelector`, event delegation preferred

### CSS
- **Framework:** Tailwind CSS (CDN) — prefer utility classes over custom CSS
- **Custom CSS:** Only in `css/style.css` for dark theme variables and mobile overrides
- **Colors:** Use CSS custom properties (`--ak-*`) not hex values directly
- **Responsive:** Mobile-first, use `md:` prefix for desktop breakpoints
- **Mobile breakpoint:** 768px (`md:` in Tailwind)

---

## UI/UX Guidelines

### AuctionKai Design System
- **Theme:** Dark mode with gold (#D4A84B) accents
- **Background:** `bg-ak-bg` (#0f1117)
- **Cards:** `bg-ak-card` (#1a1d27) with `border-ak-border`
- **Text:** `text-ak-text` (primary), `text-ak-text2` (secondary), `text-ak-muted` (tertiary)
- **Inputs:** `inp` class → dark bg, gold focus border
- **Buttons:** `btn btn-gold` (primary), `btn btn-dark` (secondary), `btn btn-sm` (compact)
- **Labels:** `lbl` class → uppercase tracking, muted color
- **Toasts:** Use `showToast(message, type, duration)` — types: success, error, info, warning
- **Confirm dialogs:** Use `showConfirmModal(title, message, callback)`

### Mobile Rules
- Tables → card views on mobile (hide table, show cards)
- Tab bar → icon-only on mobile
- Use `grid-cols-1 sm:grid-cols-2 md:grid-cols-3` pattern
- Font sizes: smaller on mobile (`text-[10px]`, `text-xs`)
- No horizontal scroll — always `overflow-x-auto` or stack

### Layout Pattern
```
Topbar (sticky, z-50)
Auction Bar (chips + add form)
Stats Cards (grid 2-col mobile, 4-col desktop)
Tab Navigation
Tab Content
Toast Container (fixed, z-9999)
```

---

## Debugging Methods

### Server-Side (PHP)
1. Check `error_logs` table via Admin → Health or `api/error_logs.php`
2. Custom error handler catches E_ERROR, E_WARNING, E_NOTICE, exceptions
3. Add temporary logging: `error_log()` or insert into debug table
4. Check PHP error log via Plesk: `var/log/php-fpm/` or Plesk Logs viewer

### Client-Side (JS)
1. Browser DevTools → Console for JS errors
2. Network tab for failed API calls (check response body)
3. `showToast()` for user-visible feedback
4. Check CSRF token: `document.querySelector('meta[name="csrf-token"]')?.content`

### Common Issues
| Symptom | Cause | Fix |
|---------|-------|-----|
| "Invalid request" on form submit | CSRF token expired | Refresh page |
| API returns empty/NULL | `php://input` consumed twice | Use `$GLOBALS['_json_input']` |
| Settings page blank | Column name mismatch (key vs setting_key) | Use `key` column |
| JS error: function not defined | Missing script include | Check `common.js` loaded |
| CSS broken after deploy | OPcache serving old files | `opcache_reset()` + Ctrl+Shift+R |
| Toast not appearing | Missing `#toast-container` div | Add to page template |

---

## Deployment Workflow

1. **Develop locally** (OpenClaw workspace)
2. **Test** — check PHP syntax: `php -l file.php`
3. **Commit** — descriptive message, reference version
4. **Push** — `git push origin main`
5. **Rsync** — exclude config, vendor, .env, .git
6. **Clear OPcache** — run `opcache_reset()` on server
7. **Verify** — hit the live URL, check admin panel
8. **Release** — tag version on GitHub with changelog for auto-update system

### Version Bump Checklist
1. Update `APP_VERSION` in `includes/constants.php`
2. Commit + push
3. Create GitHub release with tag matching version
4. Deploy to server

---

## Security Practices

- Never commit `config.php`, `.env`, or credentials to git
- All API endpoints enforce auth + CSRF check via `api_bootstrap.php`
- Passwords always hashed with `password_hash()` (bcrypt)
- Input validated client-side (Parsley) AND server-side
- Settings table uses parameterized queries — no raw SQL
- Backup files protected by `.htaccess` deny
- Admin routes protected by `admin_check.php`
- Rate limiting on login (5 attempts / 30s lockout)
- Session timeout configurable, default 30 minutes

---

## Performance Optimization

- **OPcache** enabled on production
- **Indexes** on frequently queried columns (see schema.sql)
- **Pagination** on all list endpoints (25 default, 100 max)
- **Settings** loaded once per request, not per-query
- **Update check** cached 1 hour in DB (no GitHub API hit every page load)
- **CSS/JS** loaded from CDN (Tailwind, Font Awesome)
- **No ORM** — raw PDO queries for performance

---

## Testing Procedures

Manual testing is the primary method (no automated test suite):

1. **Auth flow:** Register → Login → Logout → Forgot Password → Reset
2. **Auction CRUD:** Create → Edit → Delete, verify commission_fee saves
3. **Member CRUD:** Add → Edit → Delete, CSV import
4. **Vehicle CRUD:** Add → Edit → Toggle Sold → Delete
5. **Fees:** Add special fee → Verify statement calculation
6. **Statements:** Generate PDF → Email → Share link → Verify PIN access
7. **Admin:** All tabs load, forms save, toast confirms
8. **Mobile:** Check all views at 320px, 375px, 768px widths
9. **Updates tab:** Verify version display, release notes, dismiss, refresh

---

## Common Troubleshooting

### CSS/JS Not Updating
```bash
# Clear server cache
ssh ... "/opt/plesk/php/8.3/bin/php -r 'opcache_reset();'"
# Then hard refresh in browser (Ctrl+Shift+R)
```

### Database Migration Failed
- Check column names: `settings` table uses `key`/`value` (not `setting_key`/`setting_value`)
- Use `ON DUPLICATE KEY UPDATE` for safe re-runs
- Always backup before schema changes

### API Returns 500
- Check `error_logs` table
- Verify `api_bootstrap.php` is included
- Check `$GLOBALS['_json_input']` for consumed input stream issues
