# TOOLS.md — Services, APIs & Infrastructure

## Server Infrastructure

### Production Server (Plesk)
- **Host:** 89.117.58.8
- **SSH User:** miraiglobaltrading
- **SSH Key:** `~/.ssh/id_ed25519` (on OpenClaw host)
- **Panel:** Plesk Obsidian
- **PHP:** 8.3 (`/opt/plesk/php/8.3/bin/php`)
- **MySQL:** MariaDB 10.x (via Plesk)
- **Web Server:** Apache + Nginx reverse proxy
- **Doc Root:** `/var/www/vhosts/miraiglobaltrading.com/auctionkai.miraiglobaltrading.com/`
- **SSL:** Let's Encrypt (auto-renew via Plesk)
- **OPcache:** Enabled — must clear after deploys (`opcache_reset()`)

### SSH Access
```bash
ssh -i ~/.ssh/id_ed25519 -o StrictHostKeyChecking=no miraiglobaltrading@89.117.58.8
```

### OPcache Clear
```bash
ssh -i ~/.ssh/id_ed25519 miraiglobaltrading@89.117.58.8 \
  "/opt/plesk/php/8.3/bin/php -r 'opcache_reset();'"
```

---

## GitHub
- **Repo:** https://github.com/ashifashroff/auctionkai
- **Owner:** ashifashroff
- **Token:** Stored in git-credentials (`~/.git-credentials`) on OpenClaw host
- **Default Branch:** main
- **Releases:** Used by auto-update system — tag version (e.g. `v3.8`) and publish release with changelog

### Creating a Release
```bash
# Via GitHub API (token from git-credentials)
curl -X POST \
  -H "Authorization: token <TOKEN>" \
  -H "Accept: application/vnd.github.v3+json" \
  https://api.github.com/repos/ashifashroff/auctionkai/releases \
  -d '{"tag_name":"v3.9","name":"v3.9 — Title","body":"## Changes\n- Item 1","target_commitish":"main"}'
```

---

## Database
- **Engine:** MariaDB 10.x
- **Name:** See `config.php` / `DB_NAME` env var
- **Access:** Via Plesk phpMyAdmin or SSH + mysql CLI
- **Backup:** Admin panel → Backups tab, or `api/db_backup.php`
- **Schema:** `schema.sql` (full), `migrations.sql` (incremental)
- **Backups stored in:** `/backups/` directory on server

---

## PHP Dependencies (Composer)

| Package | Version | Purpose |
|---------|---------|---------|
| tecnickcom/tcpdf | 6.x | PDF generation for settlement statements |

Install: `composer install`  
Lock file: `composer.lock`

---

## Frontend Dependencies (CDN)

| Library | Version | Purpose |
|---------|---------|---------|
| Tailwind CSS | 3.x (CDN) | Utility-first CSS framework |
| Font Awesome | 6.x (CDN) | Icons |
| Inter font | Google Fonts | Primary typeface |
| Parsley.js | 2.x (CDN) | Form validation |

No npm/yarn — all loaded via CDN in `includes/head.php` / `css/tailwind-config.php`.

---

## Email Providers

Configured in Admin Panel → Email Settings. Supported:

| Provider | Config |
|----------|--------|
| SMTP | host, port, username, password, encryption (TLS/SSL) |
| Mailgun | API key, domain |
| SendGrid | API key |
| Amazon SES | API key, secret, region |

Credentials stored in `settings` table. Never in code.

---

## Google reCAPTCHA
- **Version:** v2 (checkbox)
- **Config:** Admin Panel → reCAPTCHA tab
- **Keys stored in:** `settings` table (`recaptcha_site_key`, `recaptcha_secret_key`)
- **Get keys:** https://www.google.com/recaptcha/admin

---

## Deployment Pipeline

```
Local (OpenClaw workspace) → git push → GitHub
                           → rsync → Plesk server
                           → OPcache clear
```

### Deploy Command (standard)
```bash
cd ~/.openclaw/workspace/auctionkai
git add -A && git commit -m "message" && git push
rsync -az --delete \
  --exclude='config.php' --exclude='vendor/' \
  --exclude='.env' --exclude='.git/' \
  -e "ssh -i ~/.ssh/id_ed25519 -o StrictHostKeyChecking=no" \
  ./ miraiglobaltrading@89.117.58.8:/var/www/vhosts/miraiglobaltrading.com/auctionkai.miraiglobaltrading.com/
ssh -i ~/.ssh/id_ed25519 miraiglobaltrading@89.117.58.8 \
  "/opt/plesk/php/8.3/bin/php -r 'opcache_reset();'"
```

### Database Migrations
After schema changes, run new SQL on server via:
```bash
ssh ... "cd /var/www/.../auctionkai.miraiglobaltrading.com && /opt/plesk/php/8.3/bin/php -r '
require \"config.php\";
\$db = new PDO(\"mysql:host=\".DB_HOST.\";dbname=\".DB_NAME, DB_USER, DB_PASS);
\$db->exec(\"YOUR SQL HERE\");
echo \"OK\";
'"
```

---

## OpenClaw Integration
- **Main Agent:** `main` (this agent), workspace `~/.openclaw/workspace/`
- **Second Agent:** `site2` (Mirive_bot), workspace `~/.openclaw/workspaces/site2`
- **Telegram Bot (main):** `8701067195` (Mirai Agent)
- **Telegram Bot (site2):** `8756715440` (@Baxin_Store_Bot)

---

## Backup & Recovery

### Application Backup
- Admin Panel → Backups tab creates full SQL dump
- Stored in `backups/` directory on server
- Download via browser or `admin/download_backup.php`

### Emergency Recovery
```bash
# Restore database from backup
ssh ... "mysql -u USER -p DBNAME < /path/to/backup.sql"

# Restore code from GitHub
ssh ... "cd /var/www/.../auctionkai.miraiglobaltrading.com && git pull origin main"
```

### Critical: config.php
`config.php` is NOT in git (excluded). If lost, recreate from `.env.example` with production values.
