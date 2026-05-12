# Security Fixes — v3.8.1

**Release Date:** 2026-05-12  
**Type:** Security Patch  
**Based on:** v3.8

---

## 🔴 Critical Fixes

- **Fixed SQL injection risk** in `api/db_backup.php` — replaced `addslashes()` with `PDO::quote()` (also fixed in `scripts/backup.php`)
- **Added CSRF protection** to `api/db_backup.php` download endpoint — now requires `_tok` parameter
- **Added CSRF protection** to `api/download_pdf_zip.php` — now requires `_tok` parameter
- **Fixed password reset token exposure** in `auth/forgot_password.php` — reset links are never shown on-screen; email errors are logged server-side only

## 🟠 High Fixes

- **Added `session.cookie_secure`** for HTTPS — session cookies now marked secure when site is served over HTTPS
- **Added security headers** to all pages — `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `X-XSS-Protection`, `Referrer-Policy`
- **Fixed `php://input` double-read** in `api/generate_link.php` and `api/delete_account.php` — now uses `$GLOBALS['_json_input']` consistently
- **Moved brute force protection to database** — login attempt tracking now uses `login_history` table instead of session-based counting (immune to cookie clearing)
- **Added IP-based rate limiting** for statement PIN attempts — uses file-based tracking that survives session resets (5 attempts / 15 min window)

## 🟡 Medium Fixes

- **Enforced consistent 8-char password policy** in admin user creation (was 6 chars)
- **Replaced `file_get_contents` with cURL** for reCAPTCHA verification — proper timeout, SSL verification, and error handling
- **Encrypted SMTP password** in database — uses AES-256-CBC with key from `APP_SECRET_KEY`
- **Removed duplicate CSRF check** in `api/delete_account.php` — already handled by `api_bootstrap.php`
- **Removed PHPMailer error exposure** in `auth/forgot_password.php` — generic message shown to users, details logged server-side

## 🟢 Hardening

- **Added `X-Content-Type-Options` and `X-Frame-Options`** to all API responses
- **Improved `.env` file parser** — handles values with `=` signs, quoted strings, and BOM
- **Added general API rate limiting** — 120 requests per minute per IP
- **Fixed `addslashes` → proper escaping** in all JavaScript contexts — replaced with `json_encode()` or removed redundant usage
- **Centralized session configuration** in `includes/session_config.php` — consistent `httponly`, `samesite`, and `secure` flags

## New Configuration

Add to your `.env` file:
```
APP_SECRET_KEY=your-random-64-char-secret-key-here
```

Generate with: `openssl rand -hex 32`

## ⚠️ Breaking Changes

- **Admin backup download** links now require CSRF token (`_tok` parameter) — admin panel updated automatically
- **PDF ZIP download** links now require CSRF token — statements view updated automatically
- **SMTP passwords** are now encrypted in the database — existing plaintext passwords will be read as-is on first load, then re-encrypted on next save

## Files Changed

- `api/db_backup.php` — SQL injection fix, CSRF protection
- `api/download_pdf_zip.php` — CSRF protection
- `api/generate_link.php` — php://input fix
- `api/delete_account.php` — php://input fix, removed duplicate CSRF
- `api/admin_actions.php` — password policy, SMTP encryption
- `auth/login.php` — DB-based brute force, cURL reCAPTCHA, addslashes fix, secure session
- `auth/forgot_password.php` — Token exposure fix, secure session
- `auth/reset_password.php` — Secure session
- `includes/api_bootstrap.php` — Security headers, secure session, API rate limiting
- `includes/auth_check.php` — Security headers, secure session
- `includes/helpers.php` — encryptSetting/decryptSetting functions
- `includes/mailer.php` — Decrypt SMTP password
- `includes/session_config.php` — New centralized session config
- `includes/constants.php` — Version bump to v3.8.1
- `config.php` — Improved .env parser, APP_SECRET_KEY
- `.env.example` — APP_SECRET_KEY entry
- `statement.php` — IP-based PIN rate limiting, secure session
- `views/statements.php` — CSRF token on ZIP download
- `admin/index.php` — CSRF token on backup download, addslashes fixes
- `profile.php` — addslashes fix
- `scripts/backup.php` — addslashes → PDO::quote
