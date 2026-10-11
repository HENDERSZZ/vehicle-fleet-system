# Vehicle Fleet Management System

A web-based vehicle fleet management system originally developed for a government client. This public repository is intended as a cleaned reference version and should use synthetic demo data only.

> **Project status:** Legacy PHP application published for reference and learning. A modernized version using a TypeScript/React frontend and a Go/Python API is planned; details will be added when available.

**Live demo:** https://andy.page.gd/  
**Demo credentials:** `User` / `1234!` — use these only on the public demo, not for any other system.

## Features

- **Vehicle inventory:** Registration, chassis and engine numbers, vehicle categories, and status tracking.
- **Driver management:** Driver records and vehicle assignment/release history.
- **Maintenance and refuelling:** Records with receipt uploads.
- **Requisitions:** Vehicle request workflow.
- **Hierarchical access control:** Data access scoped by region, county, subcounty, or location.
- **Role-based access:** Super admin, admin, HQ manager, manager, and staff roles.
- **Reporting:** Dashboard statistics and CSV/PDF exports.
- **Audit logging:** Records of user actions.
- **Session controls:** Database-backed single-session enforcement and a 15-minute inactivity timeout.

> This README describes the public version. Do not document or rely on commercial licensing features unless they are intentionally retained in the published codebase.

## Technology stack

- PHP 8.0+
- MySQL 5.7+ or MariaDB
- PDO
- Bootstrap and JavaScript
- FPDF for PDF generation
- Composer, including `vlucas/phpdotenv`

## Requirements

Before installation, make sure you have:

- PHP 8.0 or later with the extensions required by your PHP configuration and database driver (at minimum, PDO and the MySQL PDO driver).
- MySQL 5.7+ or MariaDB.
- Composer.
- Apache with `mod_rewrite` and `.htaccess` support enabled, if using the supplied Apache configuration.

The application currently expects to be served from a directory named `vehicle`. The `/vehicle` base path is hardcoded in `admin.php` and some page links, so a different deployment path may require code changes.

## Installation

### 1. Get the code

```bash
git clone https://github.com/<your-username>/<repo>.git
cd <repo>
composer install
```

Replace `<your-username>/<repo>` with the actual GitHub repository path.

### 2. Configure environment variables

Copy the example environment file and edit it:

```bash
cp .env.example .env
```

Set your own database details in `.env`:

```dotenv
DB_HOST=localhost
DB_DATABASE=vehicles
DB_USERNAME=your_database_user
DB_PASSWORD=replace_with_a_strong_password
```

These are example values. Use the database host, database name, username, and password supplied by your own hosting environment. Do not copy production credentials into this file or commit `.env` to Git.

The provided environment file may contain additional application-specific settings. Check the configuration code before adding or removing variables. The public version should not require commercial license keys; remove obsolete licensing variables from `.env.example` only after confirming that no remaining code uses them.

### 3. Create the database

Create a database with UTF-8 support, then import the schema and optional demo seed data:

```sql
CREATE DATABASE vehicles CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then, from the project root, run:

```bash
mysql -u your_database_user -p vehicles < database/schema.sql
mysql -u your_database_user -p vehicles < database/seed.sql
```

Replace `your_database_user` with your database account. The seed file should contain demo data only; inspect it before importing and never publish real user, vehicle, or client records.

### 4. Configure the web server

Configure Apache to serve the project from a directory named `vehicle`, for example:

```text
http://localhost/vehicle/
```

Ensure `.htaccess` rules are enabled and PHP can read the application files. Grant write access only to the directories that the application needs for uploaded receipts, profile images, or logs. Do not make the entire project directory writable by the web server.

### 5. Sign in and verify

Open the application in your browser and test the main workflows with a demo account. Verify that the account has only the permissions appropriate for a demo environment before exposing the application publicly.

## Project structure

```text
auth/        Login, logout, and session handling
includes/    Configuration, shared functions, and layout partials
pages/       Application modules (vehicles, drivers, maintenance, refuel, etc.)
library/     FPDF and related PDF-generation code
uploads/     Receipt and profile-image uploads (should not contain real data)
database/    Database schema and seed data
```

The exact contents may vary by repository version.

## Security and deployment notes

The codebase includes safeguards such as PDO prepared statements, password hashing, session ID regeneration, CSRF tokens for state-changing requests, output escaping, upload type/size checks, role/location-based authorization, and audit logging. These controls should still be reviewed and tested before production use.

- **Never commit `.env` or real credentials.** Keep `.env` out of version control and commit only a sanitized `.env.example`.
- **Rotate exposed credentials.** If database passwords, API keys, cryptographic secrets, or other credentials have ever been committed or shared, replace them and remove them from repository history where appropriate.
- **Use synthetic data only.** Remove real names, registration numbers, chassis/engine numbers, receipts, profile images, logs, and database dumps before publishing.
- **Protect uploads.** Prefer storing uploaded files outside the web root and allowlist permitted file extensions and content types.
- **Use HTTPS** for any hosted deployment.
- **Review demo accounts.** Do not reuse public demo credentials in production; disable or restrict privileged demo accounts.
- **Check dependencies.** Review third-party licenses and keep dependencies updated.
- **Test authorization.** Confirm that each role can access only the data and actions intended for it.

This is a legacy application and has not been represented as production-hardened. Deploy it only after an independent security review and environment-specific configuration.

## Known limitations and possible improvements

- Introduce a router and separate data access, business logic, and views (MVC or a framework).
- Replace the legacy page flow with a JSON API and a React + TypeScript frontend.
- Use a single configurable base URL instead of the hardcoded `/vehicle` path.
- Allowlist upload extensions and store uploaded files outside the web root.
- Add automated tests and continuous integration.
- Containerize the application for repeatable local setup.
- Review encryption and secret-management practices if any encryption features remain in the codebase.

## Contributing and bug reports

Before opening a pull request:

1. Do not include real client or personal data.
2. Keep credentials and local configuration out of commits.
3. Test the affected workflow manually and add automated tests where practical.
4. Describe the change and any database migration needed.

When reporting a bug, include the PHP version, database type/version, steps to reproduce, and relevant error messages. Remove passwords, session tokens, personal information, and other secrets from logs before sharing them.

## License

This repository states that it is distributed under the MIT License. Confirm that you have the right to release all included code and that third-party components retain their required copyright and license notices. See the repository's `LICENSE` file for the full terms.
