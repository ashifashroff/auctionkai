# ENVIRONMENT.md — Environment Variables & Configuration

## Overview

AuctionKai uses environment variables for all sensitive configuration. Variables are loaded in `config.php` from the `$_ENV` superglobal (set by Plesk or server environment), with fallback defaults for local development.

---

## Required Variables

### Database

| Variable | Purpose | Example | Required |
|----------|---------|---------|----------|
| `DB_HOST` | MySQL server hostname | `localhost` | ✅ |
| `DB_NAME` | Database name | `auctionkai` | ✅ |
| `DB_USER` | Database username | `auctionkai_admin` | ✅ |
| `DB_PASS` | Database password | (secure password) | ✅ |
| `DB_CHARSET` | Character set | `utf8mb4` | Default: `utf8mb4` |

### Application

| Variable | Purpose | Example | Required |
|----------|---------|---------|----------|
| `APP_URL` | Base URL of the application | `https://auctionkai.example.com` | Optional (auto-detected) |

---

## Optional Variables (configured via Admin Panel)

These are stored in the `settings` database table, not environment variables:

### Email / SMTP

| Setting Key | Purpose | Default |
|-------------|---------|---------|
| `mail_enabled` | Enable email sending | `0` |
| `mail_provider` | Provider (smtp/mailgun/sendgrid/ses) | `smtp` |
| `mail_host` | SMTP hostname | — |
| `mail_port` | SMTP port | `587` |
| `mail_username` | SMTP username | — |
| `mail_password` | SMTP password | — |
| `mail_from_email` | Sender email address | — |
| `mail_from_name` | Sender display name | `AuctionKai Settlement System` |
| `mail_encryption` | Encryption (tls/ssl) | `tls` |

### reCAPTCHA

| Setting Key | Purpose | Default |
|-------------|---------|---------|
| `recaptcha_enabled` | Enable reCAPTCHA | `0` |
| `recaptcha_site_key` | Google site key | — |
| `recaptcha_secret_key` | Google secret key | — |

### Session

| Setting Key | Purpose | Default |
|-------------|---------|---------|
| `session_timeout_enabled` | Enable auto-logout | `1` |
| `session_timeout_minutes` | Minutes until logout | `30` |
| `session_timeout_warn_minutes` | Warning before logout | `2` |

### Maintenance

| Setting Key | Purpose | Default |
|-------------|---------|---------|
| `maintenance_mode` | Show maintenance page | `0` |
| `maintenance_title` | Maintenance page heading | `System Maintenance` |
| `maintenance_message` | Maintenance page body | — |
| `maintenance_eta` | Expected return time | — |

### Branding

| Setting Key | Purpose | Default |
|-------------|---------|---------|
| `brand_name` | App display name | `AuctionKai` |
| `brand_tagline` | Subtitle text | `Settlement Management System` |
| `brand_owner` | Company name | `Mirai Global Solutions` |
| `brand_email` | Contact email | — |
| `brand_phone` | Contact phone | — |
| `brand_address` | Contact address | — |
| `brand_logo_url` | Logo image URL | — |
| `brand_accent_color` | Primary color hex | `#D4A84B` |
| `brand_footer_text` | Footer text | `Designed & Developed by Mirai Global Solutions` |

### Updates

| Setting Key | Purpose | Default |
|-------------|---------|---------|
| `update_check_cache` | Cached GitHub API response (JSON) | — |
| `update_dismissed_version` | Dismissed update version | — |

---

## Where Secrets Are Stored

| Secret | Storage Location |
|--------|-----------------|
| Database credentials | Plesk environment variables → `config.php` → `$_ENV` |
| SMTP credentials | `settings` table in database (admin panel) |
| reCAPTCHA keys | `settings` table in database (admin panel) |
| GitHub API token | `~/.git-credentials` on OpenClaw host (for releases only) |
| SSH key | `~/.ssh/id_ed25519` on OpenClaw host |

⚠️ **Never commit `config.php` or `.env` to git.** Both are in `.gitignore`.

---

## Local Development Setup

```bash
cp .env.example .env
# Edit .env with your local MySQL credentials
# Import schema.sql into your local MySQL
# Start PHP built-in server: php -S localhost:8000
```

The `.env.example` file contains all required variables with placeholder values.
