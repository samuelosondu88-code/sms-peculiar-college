# Production Deployment Checklist

## SMS Peculiar International College v2.0.0

---

## Phase 0: Pre-Deployment (Complete Once)

### Database
- [ ] **Run database migration**: `mysql -u root -p sms_peculiar_college < database/migration_production_audit.sql`
  - Adds charset to all tables (prevents Unicode corruption)
  - Creates indexes on 25+ frequently-queried columns
  - Adds FULLTEXT index for library search
- [ ] **Create backups directory**: `mkdir -p backups/ logs/` (already done)
- [ ] **Create dedicated MySQL user** (not root):
  ```sql
  CREATE USER 'peculiar_prod'@'localhost' IDENTIFIED BY 'generate-a-strong-password';
  GRANT ALL PRIVILEGES ON sms_peculiar_college.* TO 'peculiar_prod'@'localhost';
  FLUSH PRIVILEGES;
  ```
- [ ] **Run schema files in order**:
  1. `database/schema.sql`
  2. `database/security_schema.sql`
  3. `database/results_schema.sql`
  4. `database/exam_security_schema.sql`
  5. `database/teacher_exams_schema.sql`
  6. `database/cbt_schema.sql`
  7. `database/classroom_schema.sql`
  8. `database/lesson_plans_schema.sql`
  9. `database/migration_results_enhancements.sql`
  10. `database/migration_production_audit.sql`

### Configuration
- [ ] **Create `.env` file** (copy from `.env.example`):
  ```
  cp .env.example .env
  ```
- [ ] **Generate APP_KEY**:
  ```
  php -r "echo bin2hex(random_bytes(32));"
  ```
  Set the output as `APP_KEY` in `.env`
- [ ] **Set database credentials** in `.env`: `DB_USER`, `DB_PASS`
- [ ] **Set `APP_URL`** to your actual domain
- [ ] **Set school info**: `SCHOOL_NAME`, `SCHOOL_PHONE`, `SCHOOL_EMAIL`
- [ ] **Verify `.env` is in `.gitignore`** (it already is)

### Security Hardening
- [ ] **Verify `.htaccess` blocks**: `database/`, `config/`, `includes/`, `vendor/`, `cron/`, `backups/`, `logs/`, `.env`
- [ ] **HTTPS**: Ensure SSL certificate installed. `.htaccess` already has redirect rule active.
- [ ] **Verify CSP header** in `.htaccess` matches your CDN URLs if changed
- [ ] **Remove install directory** if it exists: `rm -rf install/`
- [ ] **Set file permissions**:
  ```
  find . -type d -exec chmod 755 {} \;
  find . -type f -exec chmod 644 {} \;
  chmod 640 .env
  chmod 640 config/database.php
  chmod 750 backups/ logs/
  ```
- [ ] **Ensure `documents/`, `uploads/` directories are writable** by web server user

---

## Phase 1: First Deployment

### Code
- [ ] **Clone repo** to production server
- [ ] **Run composer** (if vendor dependencies exist)
- [ ] **Configure Apache virtual host** pointing to project root
- [ ] **Enable `mod_rewrite`**, `mod_headers`, `mod_deflate`, `mod_expires`:
  ```
  sudo a2enmod rewrite headers deflate expires
  sudo systemctl restart apache2
  ```
- [ ] **Set `BASE_URL` in `.env`** to match deployment path (e.g., `/sms-peculiar-college` or empty for root)

### PHP Configuration
- [ ] **Verify PHP 8.0+** is installed
- [ ] **Required extensions**: `pdo_mysql`, `openssl`, `mbstring`, `gd`, `curl`, `json`, `fileinfo`, `zip`
- [ ] **PHP `php.ini` settings**:
  ```
  display_errors = Off
  log_errors = On
  error_log = /path/to/project/logs/php_errors.log
  session.cookie_httponly = 1
  session.cookie_secure = 1
  session.use_only_cookies = 1
  session.cookie_samesite = "Strict"
  expose_php = Off
  upload_max_filesize = 10M
  post_max_size = 12M
  max_execution_time = 120
  ```

### SSL/HTTPS
- [ ] **Install SSL certificate** (Let's Encrypt, Cloudflare, or commercial)
- [ ] **Verify HTTPS redirect** works (`.htaccess` enforces it)
- [ ] **Verify `Strict-Transport-Security`** header is sent (max-age=31536000)

---

## Phase 2: Verification

### Authentication
- [ ] Test email login - working
- [ ] Test PIN login - working
- [ ] Test password reset flow - working
- [ ] Test session timeout (1 hour idle)
- [ ] Test session hijack detection (different IP/UA forces re-login)
- [ ] Test login throttling (5 failed attempts = 5 min lockout)
- [ ] Test CSRF protection (submit form without token = rejected)
- [ ] Test role-based access (student cannot access /admin/)

### Examination Module
- [ ] Test exam creation (teacher)
- [ ] Test question addition
- [ ] Test security settings per exam
- [ ] Test full security check workflow (fullscreen + camera + fingerprint)
- [ ] Test exam taking flow - timer, auto-save, navigation
- [ ] Test tab switch detection and violation counting
- [ ] Test fullscreen exit detection
- [ ] Test auto-submit on violation threshold
- [ ] Test timer expiry auto-submit
- [ ] Test manual exam submission
- [ ] Test exam results display

### Results Module
- [ ] Test score entry with CA + exam calculation
- [ ] Test grade computation (A-F based on configurable thresholds)
- [ ] Test class position ranking
- [ ] Test promotion/demotion logic
- [ ] Test result approval workflow (subject_teacher -> class_teacher -> principal -> published)
- [ ] Test PDF report card generation
- [ ] Test result PIN verification (public result-checker)

### Data Integrity
- [ ] Test backup script:
  ```
  php cron/backup.php --verbose
  ```
- [ ] Verify backup files created in `backups/`
- [ ] Test restore from backup:
  ```
  gunzip < backups/db_*.sql.gz | mysql -u root -p sms_peculiar_college
  ```

---

## Phase 3: Go Live

### DNS & Domain
- [ ] Set DNS A record pointing to server IP
- [ ] Verify domain resolves correctly
- [ ] Verify SSL certificate covers domain

### Monitoring
- [ ] Set up `cron/backup.php` as daily cron job:
  ```
  0 2 * * * /usr/bin/php /var/www/html/cron/backup.php
  ```
- [ ] Configure server monitoring (uptime, disk, memory)
- [ ] Configure log rotation for `logs/php_errors.log`:
  ```
  /var/www/html/logs/*.log {
      daily
      rotate 30
      compress
      missingok
      notifempty
  }
  ```

### Post-Deployment
- [ ] **First login** as admin via email/password
- [ ] **Configure school settings** in admin panel
- [ ] **Create academic session** and terms
- [ ] **Create classes** (JSS1-3, SS1-3)
- [ ] **Create subjects** for each class
- [ ] **Register teachers** and assign subjects
- [ ] **Register students** with admission numbers
- [ ] **Create exam(s)** and verify student can take them
- [ ] **Run `database/seed.php`** if needed for demo data (dev only)
- [ ] **Verify error logging** works: check `logs/php_errors.log` after visiting a page
- [ ] **Remove seed scripts** from web-accessible directories in production

---

## Issues Fixed in This Audit

### Critical (5)
| # | Issue | Fix |
|---|-------|-----|
| 1 | DB password hardcoded in `config/database.php` | Moved to `.env` only; no fallback defaults |
| 2 | APP_KEY insecure default in `config/app.php` | Moved to `.env`; error logged if missing |
| 3 | Password reset lacks CSRF + rate limiting | Added `getCsrfField()`, `checkRateLimit()`, user enumeration fix |
| 4 | Schema collisions (duplicate `payments`, `audit_logs` tables) | Documented in migration; run `migration_production_audit.sql` |
| 5 | `ORDER BY RAND()` in CBT exam selection | Replaced with random-offset approach |

### High (9)
| # | Issue | Fix |
|---|-------|-----|
| 6 | `$db->query()` with variable interpolation in `teacher/exams/index.php:47-48` | Converted to prepared statements |
| 7 | Library search uses `LIKE '%...%'` (full table scan) | Added FULLTEXT index + `MATCH AGAINST` in query |
| 8 | Missing indexes on 20+ frequently-queried columns | Migration `migration_production_audit.sql` adds all indexes |
| 9 | No backup strategy implemented | Created `cron/backup.php` with rotation + integrity check |
| 10 | Result computation N+1 queries in PDF generation | Batch-fetch pattern documented for future optimization |
| 11 | `getClassPosition()` O(n) per student | Acceptable for school-scale (100s, not millions) |
| 12 | Debug code in `security-check.php` (`alert('camStart FIRED')`) | Removed |
| 13 | Leftover `camera-verify.js` reference in `$extraScripts` | Removed |
| 14 | No pagination on several admin listing pages | Acceptable with LIMIT 50; full pagination deferred |

### Medium (5)
| # | Issue | Status |
|---|-------|--------|
| 15 | Derived columns without sync triggers (`fees.balance`) | Acceptable for now; add DB triggers later |
| 16 | No connection pooling | Acceptable for school-scale app |
| 17 | Session fingerprint uses IP (breaks behind proxies) | Acceptable; documented |
| 18 | Dashboard count queries separate (6 queries) | Minor performance gain |
| 19 | Missing charset on schema tables (data corruption risk) | Migration `migration_production_audit.sql` fixes |

### Low (3)
| # | Issue | Status |
|---|-------|--------|
| 20 | `SELECT *` usage (bandwidth waste) | Minor; deferred |
| 21 | `$db->query()` pattern (low risk) | Only used for hardcoded/admin queries |
| 22 | Inconsistent `INT(11)` vs `INT` | Cosmetic only |

---

## Architecture Summary

```
sms-peculiar-college/
├── .env              [NOT COMMITTED] - All secrets
├── .env.example      [COMMITTED] - Template
├── .htaccess         [COMMITTED] - Apache rules, HTTPS, CSP
├── config/
│   ├── database.php  - PDO connection (env-only)
│   ├── app.php       - App constants (env-only for secrets)
│   ├── session.php   - Session security, CSRF auto-verify
│   ├── env.php       - .env file loader
│   └── logging.php   - Production error handler
├── includes/
│   ├── functions.php - Core utilities
│   ├── security.php  - CSRF, rate_limit, session, encryption
│   ├── exam_security.php - Exam integrity system
│   └── result_functions.php - Grading engine
├── cron/
│   └── backup.php    - Daily DB + file backup
├── backups/          [PROTECTED] - Backup output
├── logs/             [PROTECTED] - Error logs
└── database/
    └── migration_production_audit.sql - Indexes + charset
```

---

## Quick Reference: Key Configs

### Generate APP_KEY
```bash
php -r "echo bin2hex(random_bytes(32));"
```

### Create .env from template
```bash
cp .env.example .env
# Edit .env with your values
```

### Run backup manually
```bash
php cron/backup.php --verbose
```

### Set up daily cron
```bash
crontab -e
# Add:
0 2 * * * /usr/bin/php /var/www/html/cron/backup.php >> /var/www/html/logs/backup.log 2>&1
```

### Test database connection
```bash
php -r "require 'config/database.php'; \$db = getDB(); echo 'OK';"
```
