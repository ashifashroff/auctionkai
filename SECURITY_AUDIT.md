# 🔒 AuctionKai Security Audit — v3.8

**Date:** 2026-05-12  
**Auditor:** Mirai Agent  
**Scope:** Full codebase (PHP, JS, DB, server config)

---

## Summary

| Severity | Count |
|----------|-------|
| 🔴 Critical | 4 |
| 🟠 High | 5 |
| 🟡 Medium | 6 |
| 🟢 Low / Hardening | 5 |

---

## 🔴 Critical

### 1. `addslashes()` in db_backup.php — SQL Injection Risk

- **File:** `api/db_backup.php:79`
- `addslashes()` is used for SQL value escaping instead of `$db->quote()`
- `addslashes()` is not charset-aware and can be bypassed with multibyte encodings (GBK, Big5)
- A malicious admin (or CSRF-forced request) could craft data that breaks out of the addslashes escaping

**Fix:**
```php
// Before
return "'" . addslashes($val) . "'";

// After
return $db->quote((string)$val);
```

---

### 2. `db_backup.php` Bypasses CSRF Protection

- **File:** `api/db_backup.php`
- Uses `admin_check.php` instead of `api_bootstrap.php`
- No CSRF token check — triggers on a simple GET request
- An attacker can trick an admin into visiting a crafted URL to trigger a backup download
- The backup contains the full database including password hashes, email credentials, and all user data

**Fix:** Add CSRF token verification, or switch to POST-only with token

---

### 3. `download_pdf_zip.php` Bypasses CSRF Protection

- **File:** `api/download_pdf_zip.php`
- Uses `auth_check.php` + GET parameter — no CSRF protection
- Any logged-in user can be tricked into downloading statement ZIPs via a crafted link

**Fix:** Add CSRF token validation, require POST or add token to GET

---

### 4. Password Reset Token Exposed on Screen

- **File:** `auth/forgot_password.php`
- When email sending fails, the full reset link with plaintext token is displayed on-page
- Token is exposed in: browser history, referral headers, screen shoulder-surfing, server access logs
- The token grants full password reset access

**Fix:** Never display reset links on-screen. Show a generic message like "If your email is registered, you will receive a reset link." Fix the email configuration instead of exposing the token.

---

## 🟠 High

### 5. `session.cookie_secure` Not Set

- **Files:** `includes/api_bootstrap.php`, `auth/login.php`, `statement.php`
- Only `httponly` and `samesite` are configured
- On HTTPS-enabled sites, session cookies can still be sent over HTTP, enabling session hijacking via protocol downgrade
- **Impact:** Session theft on mixed-content setups

**Fix:**
```php
if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443) {
    ini_set('session.cookie_secure', 1);
}
```
Add to `api_bootstrap.php` before `session_start()`

---

### 6. Missing Security Headers on Most Pages

- **Only** `statement.php` and `auth/login.php` set CSP/X-Frame-Options
- All other pages (index.php, admin/, profile.php, etc.) are missing:
  - `X-Frame-Options: DENY`
  - `X-Content-Type-Options: nosniff`
  - `Content-Security-Policy`
- **Impact:** Clickjacking, MIME sniffing, XSS amplification

**Fix:** Add global security headers in `api_bootstrap.php` and `auth_check.php`

---

### 7. `generate_link.php` Reads `php://input` Directly

- **File:** `api/generate_link.php:12`
- Uses `json_decode(file_get_contents('php://input'), true)` instead of `$GLOBALS['_json_input']`
- The CSRF check in `api_bootstrap.php` already consumed the `php://input` stream
- Second read returns empty string — the endpoint silently fails to read input
- **Impact:** Statement link generation may be broken for JSON payloads

**Fix:**
```php
// Before
$data = json_decode(file_get_contents('php://input'), true);

// After
$data = $GLOBALS['_json_input'] ?? json_decode(file_get_contents('php://input'), true);
```

---

### 8. Brute Force Protection Is Session-Based

- **File:** `auth/login.php`
- Login attempt tracking stored in `$_SESSION` (e.g., `login_attempts_$username`)
- Attacker can simply clear cookies / start new session to retry infinitely
- **Impact:** Brute force attacks are not effectively mitigated

**Fix:** Track failed attempts in the database or use IP-based rate limiting (like `forgot_password.php` does with `checkRateLimit()`)

---

### 9. Statement PIN Only 4 Digits (10,000 Combinations)

- **File:** `statement.php`
- PIN is the last 4 digits of phone number
- Rate limiting is session-based only
- Attacker without a session can brute-force the PIN
- 10,000 combinations is trivially brute-forceable without server-side rate limiting
- **Impact:** Unauthorized access to settlement statements

**Fix:** Add server-side IP-based rate limiting for PIN attempts (e.g., 5 attempts per IP per 15 minutes, then lock out)

---

## 🟡 Medium

### 10. Inconsistent Password Policy — Admin vs Registration

- **File:** `api/admin_actions.php` (create_user case)
- Admin can create users with 6-character passwords
- Self-registration requires 8 characters + uppercase/lowercase/numbers
- **Impact:** Weak admin-created accounts

**Fix:** Enforce the same 8-char + complexity rules in admin user creation

---

### 11. reCAPTCHA Verification Uses `file_get_contents()`

- **File:** `auth/login.php`
- No timeout, no SSL verification override, no error handling
- If Google is slow/down, the entire registration flow hangs
- **Fix:** Use cURL with timeout and proper SSL verification

---

### 12. SMTP Password Stored as Plaintext in `settings` Table

- **File:** `api/admin_actions.php:save_email_settings`
- `mail_password` saved as-is to the database
- Anyone with DB access (phpMyAdmin, backup files, SQL injection elsewhere) can read SMTP credentials
- **Fix:** Encrypt with `openssl_encrypt()` using a key from `.env`, decrypt on read

---

### 13. `delete_account.php` Double CSRF Check + php://input Bug

- **File:** `api/delete_account.php`
- Includes `api_bootstrap.php` (which does CSRF check + consumes php://input)
- Then manually checks CSRF again and re-reads `php://input`
- Same `php://input` bug as #7 — second read may return empty
- Not a vulnerability per se, but the redundant logic is fragile

**Fix:** Remove duplicate CSRF check, use `$GLOBALS['_json_input']`

---

### 14. Error Messages Leak Configuration Info

- **File:** `auth/forgot_password.php`
- Shows "Could not send email: [exact PHPMailer error]" revealing mail server config, ports, auth methods
- **Fix:** Log the real error server-side, show generic message to user

---

### 15. No `session.cookie_secure` in `statement.php`

- **File:** `statement.php`
- Starts its own session without setting the secure flag
- Same issue as #5 but on the public statement page

---

## 🟢 Low / Hardening

### 16. No Security Headers on API Responses

- API endpoints return JSON but don't set `X-Content-Type-Options: nosniff`
- Browsers may MIME-sniff JSON as HTML under certain conditions
- **Fix:** Add `header('X-Content-Type-Options: nosniff')` in `api_bootstrap.php`

---

### 17. Naive `.env` Parsing

- **File:** `config.php`
- Manually splits on `=` — doesn't handle values with `=` signs, quoted values, or escaped characters
- Could misread complex passwords or URLs
- **Fix:** Use `vlucas/phpdotenv` via Composer, or handle edge cases

---

### 18. No Rate Limiting on Most API Endpoints

- Only `send_email.php` and `delete_account.php` have rate limits
- Vehicle CRUD, member CRUD, PDF generation, link generation — all unlimited
- **Impact:** Abuse potential (mass data creation, PDF DoS, link spam)

**Fix:** Add general API rate limiting middleware in `api_bootstrap.php`

---

### 19. `showToast` with `addslashes()` — Potential XSS

- **File:** `auth/login.php:335`
- `addslashes()` is not XSS-safe for JavaScript contexts
- Currently wrapped in JS string literal so risk is low, but `json_encode()` is the correct approach
- **Fix:** Replace `addslashes($error)` with `json_encode($error)`

---

### 20. Inconsistent `SameSite` Session Cookie Configuration

- Some files start sessions without setting `samesite`
- `api_bootstrap.php` sets it, but `statement.php`, `forgot_password.php`, `reset_password.php` start sessions independently
- **Fix:** Centralize session configuration in a single helper

---

## Recommended Fix Priority

1. **Immediate (🔴):** Issues #1–4 — SQL injection, CSRF bypasses, token exposure
2. **This week (🟠):** Issues #5–9 — Session security, headers, brute force
3. **Next sprint (🟡):** Issues #10–15 — Password policy, encryption, info leaks
4. **Backlog (🟢):** Issues #16–20 — Hardening, rate limiting, consistency

---

*Audit generated by Mirai Agent — Mirai Global Solutions*
