# Changelog

All notable changes to the SMS Peculiar College project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to Semantic Versioning.

## [Unreleased]

### Added
- **Phase 1 – Foundation**
  - `composer.json` with PSR-4 autoloading (`App\` → `app/`) and curated production
    dependencies (phpdotenv, PHPMailer, Monolog, mPDF, ramsey/uuid).
  - Root `autoload.php` that prefers Composer's autoloader and falls back to a
    lightweight PSR-4 autoloader when Composer is unavailable (free-shared-host safe).
  - `app/` namespace scaffold: `Config`, `Core`, `Helpers`, `Services`,
    `Repositories`, `Modules`.
  - `.gitignore` entries for `storage/*` (logs, backups, cache, tmp, templates).
  - `app/Helpers/helpers.php` with new non-colliding helpers: `logger()`,
    `storage_path()`, `config_path()`, `app_env()`, `app_debug()`, `uuid_v4()`.
- **Phase 2 – Central logging & error handling**
  - `app/Core/ErrorHandler.php` — single registration point for PHP error,
    exception and shutdown handlers, wired to `logger()` (Monolog-backed when
    Composer is installed, daily-file fallback otherwise). Hides internals in
    production, renders 500 page, logs trace; cleans up on exit.
  - `app/Config/bootstrap.php` — bootstraps env (phpdotenv preferred, legacy
    `config/env.php` fallback), autoloader and logger.
- **Phase 3 – Error pages & maintenance mode**
  - Added `maintenance.php` page (HTTP 503) and helpers
    `is_maintenance_mode()`, `maintenance_bypass_token()`.
  - Wired a maintenance guard into `config/session.php`: when `APP_MAINTENANCE`
    is enabled, non-admins are served the maintenance page; authenticated admins
    or `GET ?down_for_maintenance=<token>` bypass it so the site can be restored
    remotely.
  - Documented `APP_MAINTENANCE*` env vars in `.env.example`.
  - Confirmed existing `error-403.php` / `error-404.php` / `error-419.php` /
    `error-500.php` are consistent and wired to `ErrorHandler`.
- **Phase 4 – Email service & templates**
  - `app/Services/MailService.php` — PHPMailer/SMTP delivery with a graceful
    fallback to PHP `mail()` when PHPMailer is absent, HTML template rendering
    with a shared layout, plain-text extraction, and logging.
  - Templates under `storage/templates/email/`: `layout.php`, `welcome.php`,
    `password-reset.php`, `otp.php`, `fee-receipt.php`.
  - New helper `send_email_template()`; legacy `sendEmail()` now prefers
    `MailService` while keeping its `mail()` path as a fallback.
- **Phase 5 – Report card service (mPDF / FPDF)**
  - `app/Services/ReportCardService.php` — builds the report card as HTML/CSS and
    streams a PDF via mPDF when installed, else reports `mpdfAvailable() === false`.
  - `admin/results/pdf.php` and `teacher/results/pdf.php` now prefer
    `ReportCardService` (mPDF) and fall back to the existing FPDF renderer, so
    downloads keep working on hosts without Composer.
- **Phase 6 – Service layer**
  - `app/Services/AuthService.php` — bcrypt hashing/verify with a reusable
    password-strength policy; legacy `generatePasswordHash()`/`verifyPassword()`
    now delegate to it.
  - `app/Services/StudentService.php`, `app/Services/ResultService.php`,
    `app/Services/PaymentService.php` — typed domain logic (student lookup/
    creation, grade aggregation, idempotent Paystack payment recording).
  - `payments/callback.php` now routes through `PaymentService`.
- **Phase 7 – Repository layer**
  - `app/Repositories/UserRepository.php`, `StudentRepository.php`,
    `PaymentRepository.php` — PDO data-access classes; services delegate to them
    (service → repository layering).
- **Phase 8 – Upload hardening**
  - `app/Services/UploadService.php` — upload error/size/extension validation,
    real MIME (magic-byte) sniffing, unsafe-filename rejection, and storage
    under a random name.
  - Legacy `uploadFile()` and `uploadSecureFile()` now route through it while
    keeping their original paths as fallback.
- **Phase 9 – Backup / restore utility**
  - `scripts/backup.php` — CLI, pure-PHP MySQL dump (no `mysqldump` dependency):
    `dump`, `--list`, `--restore=<file>`, `--rotate=<n>`. Writes gzip'd backups to
    `storage/backups/`.
  - Added `Deny from all` to `storage/templates/` (web-protection gap).
- **Phase 10 – Security headers & password policy**
  - `sendSecurityHeaders()` (previously dead code) is now invoked from the boot
    chain in `config/session.php`; aligned COOP (`same-origin-allow-popups`) and
    CORP (`cross-origin`) in `.htaccess` to match PHP.
  - Password strength policy (`App\Services\AuthService::meetsPolicy`) enforced
    on change-password, reset-password and admin user creation; added
    `Validator::addError()`.
  - `documents/`, `uploads/`, `documents/profiles/` now disable PHP execution via
    `.htaccess`.
- **Phase 11 – Database indexes**
  - `database/migration_indexes.sql` — idempotent migration adding composite
    indexes for results, approvals, attendance, fees, payments, audit logs and
    login throttling lookups.
  - `scripts/migrate.php` — CLI runner (`--check` dry-run; DELIMITER-aware
    statement parsing; surfaces per-statement errors). Applied against the live
    MariaDB: 19/19 statements OK, re-run is a no-op (idempotent).
- **Phase 12 – Documentation & verification**
  - `README.md`: new "Modular Refactor & Operations" section documenting `app/`
    structure, no-Composer fallback, maintenance mode, CSV-driven
    `scripts/backup.php`, index migration, and security defaults.
  - Full project lint: all 186 PHP files pass `php -l`.
  - Smoke tests re-run green (boot through `config/session.php` exits 0; upload
    validation accepts a valid PNG and rejects PHP-content and double-extension
    files).
- **Composer enablement (vendor/)**
  - Installed `composer install` (monolog 3, mpdf 8, phpmailer 6, phpdotenv 5,
    ramsey/uuid 4, phpunit 10). Premium services now activate automatically;
    the no-Composer fallbacks remain for shared hosts.
  - **Fix:** `config/env.php` + `app/Config/bootstrap.php` — phpdotenv's
    immutable adapter only fills `$_ENV`/`$_SERVER`, leaving `getenv()`
    (used by `config/database.php`, `config/app.php`) empty. The legacy loader
    now always `putenv()`s and is always invoked, restoring DB/APP_KEY reads.
  - **Fix:** `ReportCardService::renderHtml()` — `getGrade()` is strict-typed
    `float` but `getResultSettings()` returns DECIMAL strings; settings are now
    cast to float.
  - **Fix:** `logger()` — `RotatingFileHandler` was constructed with a formatter
    class string in the `$filePermission` slot; formatter is now set via
    `setFormatter()`.
  - Verified against live DB: login flow (302 → admin dashboard 200), mPDF
    report card over HTTP (`%PDF-1.4`, ~289 KB), Monolog writes formatted logs.
- **Automated tests**
  - `phpunit.xml` + `tests/` — 15 unit tests (18 assertions) covering
    `AuthService` (hash/verify/policy) and `UploadService` (MIME, size,
    double-extension, upload-error validation). Green on PHPUnit 10.5.
  - Optimised PSR-4 classmap autoloader generated (`composer dump-autoload -o`).
- **Schema-drift fixes (surfaced by the live DB smoke test)**
  - `scripts/migrate.php` now accepts `--file=<name>` to run any migration in
    `database/`.
  - `database/migration_admission_forms_columns.sql` — `admin/admission-forms.php`
    expects `form_name`/`price`/`academic_session_id`/`is_active` on
    `admission_forms`; columns added (idempotent).
  - `admin/pins/index.php` — ORDER BY/list used nonexistent `created_at` on
    `student_pins`; switched to `generated_at`.
  - Applied the existing `database/exam_security_schema.sql` to create the five
    missing exam-security tables (`exam_security_settings`, `exam_activity_logs`,
    `exam_proctoring_evidence`, `exam_device_fingerprints`,
    `exam_integrity_scores`) and the `exam_attempts` security columns, fixing
    `admin/exams/monitor.php`.
  - Full admin-module smoke test now passes (every page 200).
- **Documentation**
  - `.env.example` completed with `BASE_URL`, `PAYSTACK_PUBLIC_KEY` and
    `PAYSTACK_SECRET_KEY` (the keys the app actually reads).

### Changed
- `config/session.php` now boots `bootstrap.php` and registers
  `App\Core\ErrorHandler` before session initialisation (the app's single
  error-handling entry point).
- Audit logging via `logger()` added to the security-sensitive flows:
  email login success/failure, 2FA challenge + verification (success/failure),
  student PIN login (success/failure), logout, password change, password reset,
  profile update, admin user creation, user status toggles, staff updates,
  result-approval actions, and Paystack payment verification
  (channels: `auth`, `results`, `payment`).

### Fixed
- `app/Config/bootstrap.php` legacy env fallback path pointed to a non-existent
  `app/env.php`; now resolves to `config/env.php`.
- `ErrorHandler::handleException()` type-hinted a namespaced (non-existent)
  `App\Core\Throwable`; corrected to `\Throwable` and made it `exit()` cleanly
  in both CLI and web so PHP's default fatal handler never leaks output.
- `bootstrap.php` logger call created a logger but never wrote; changed to
  `->info()` and restricted to CLI to avoid per-request log noise.

### Fixed (portal end-to-end verification round)
- **Student PIN login could never succeed.** `student_pins.pin` was
  `VARCHAR(20)` while login verifies PINs with `password_verify()` against a
  bcrypt hash (60 chars); the column truncated every stored hash, and admin
  generation stored plaintext PINs while login expected hashes. Fixes:
  - `database/migration_student_pins_pin_length.sql` widens `student_pins.pin`
    to `VARCHAR(255)`; `database/security_schema.sql` updated to match for
    fresh installs.
  - `admin/pins/index.php` now stores `password_hash()` of each generated PIN
    and shows the plain PIN only once, at generation time (bulk + single).
    The PIN list and print slip no longer expose stored hashes.
  - `auth/login.php` uppercases the submitted PIN (generated PINs are
    uppercase) before verification.
  - Verified end-to-end: admin-generated-style hashed PIN → 302 → student
    dashboard; login auto-generates a successor hashed PIN.
- **Virtual Classroom module 500s** (student/teacher/parent classroom pages):
  `virtual_classes`, `class_enrollments`, `class_materials`,
  `class_announcements`, `class_assignments`, `assignment_submissions`,
  `class_attendance`, `class_discussions`, `class_schedule` were never created
  in the live database. Applied existing `database/classroom_schema.sql`
  (idempotent) via the migration runner.
- **`submissions.status`** missing from the live table (declared in
  `database/schema.sql` but the DB was created from an older definition);
  `database/migration_books_submissions_columns.sql` adds it.
- **`books.description`** was referenced by `student/library.php` but absent
  from the base schema and live DB;
  `database/migration_books_submissions_columns.sql` adds it and
  `database/schema.sql` now declares it for fresh installs.
- `student/results.php` and `teacher/grades.php` called `redirect()` /
  `requireRole()` without requiring `includes/functions.php`, causing
  "Call to undefined function redirect()". Both now require it (all pages
  verified; no other standalone page had the gap).
- **Remote database-wipe vector closed.** `seed.php` TRUNCATEd every table
  and reseeded demo data, and executed over HTTP with no authentication —
  any anonymous visitor could wipe the live database. It is now CLI-only
  (`PHP_SAPI === 'cli'`) and additionally requires an explicit `--confirm`
  argument. `setup.php` (HTTP-reachable DDL) is now CLI-only as well. Both
  return HTTP 403 over the web.
- Removed `test_sidebar2.php`, a leftover dev artifact that enabled
  `display_errors` and forged a logged-in student session via
  `session_id('test')` for any visitor.
- Re-verified after hardening: admin (47/47), public (9/9) and all portal
  crawls green; `api/students-by-class.php` returns valid JSON;
  `index.php` 302, `result-checker.php` 200, `maintenance.php` 503.
- **2FA login flow verified end-to-end** (login → 2FA challenge → OTP →
  dashboard) against the live DB; the flow, OTP storage/expiry and
  session establishment all work correctly.
- **Verified role guard**: a logged-in student hitting an admin page gets
  `403 - Access Denied`.
- `uploads/.htaccess` (denied script execution, `Options -Indexes -ExecCGI`)
  cascades to subdirectories; `docs/` contains no secrets.
- **`error-419.php` returned HTTP 500 on every access.** 419 is a
  non-standard status that this Apache/PHP builds coerce to 500, and the page
  also used the undefined `SCHOOL_NAME` when opened standalone. It now
  `require`s `config/app.php` and emits the standard status `403`, rendering
  cleanly (previously: fatal "Undefined constant" on the logged-in builds).

## [2.0.0] - Baseline
- Existing procedural school management system snapshot (pre-refactor).

[Unreleased]: https://example.com/compare/v2.0.0...HEAD