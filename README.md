# CourseHub

CourseHub is a PHP/MySQL course marketplace with student, instructor, and admin
workflows. Students can buy courses using manually verified payment proof,
instructors can publish learning content and request payouts, and admins can
review instructors, courses, orders, payments, and withdrawals.

## Requirements

- PHP 8.2 or newer with `mysqli`, `fileinfo`, and sessions
- MariaDB 10.4+ or MySQL 8.0+
- MySQL/MariaDB must listen on port **3307** for this project
- Apache, Nginx, or PHP's built-in development server
- The web document root must point to `public/`

## XAMPP setup on port 3307

1. Start Apache and MySQL from XAMPP.
2. Confirm MySQL is configured for port `3307`.
3. Confirm the existing database is named `coursehub`.
4. Copy `.env.example` to `.env`.
5. Keep these database values:

```env
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=coursehub
DB_USERNAME=root
DB_PASSWORD=
DB_ALLOW_LOCAL_FALLBACK=true
```

6. Back up `coursehub`.
7. Import this compatibility migration into `coursehub` when the health check
   reports missing workflow or finance structures:

```text
database/migrations/20260712_coursehub_compatibility.sql
```

8. Verify the real connection and schema from the project root:

```bash
php tools/database_health.php
```

A healthy result must show:

```text
Port: 3307 [OK]
Database: coursehub [OK]
RESULT: PASS - coursehub on port 3307 is ready.
```

The application never falls back to port 3306 and never changes the database
name away from `coursehub`.

## New database setup

For a completely new installation:

1. Copy `.env.example` to `.env`.
2. Create `coursehub` with `database/schema.sql`.
3. Load initial categories and settings with `database/seed.sql`.
4. Apply the migrations documented in `SECURITY_AUDIT.md`.
5. Create an admin account:

```bash
php scripts/create_admin.php admin@example.com "Use-A-Strong-Password1!" "Admin Name"
```

6. Start the development server:

```bash
php -S localhost:8080 -t public
```

Open `http://localhost:8080`.

The complete Student, Instructor, and Admin behavior is documented in
[`WORKFLOWS.md`](WORKFLOWS.md).

Security controls and the remaining production checklist are documented in
[`SECURITY_REVIEW.md`](SECURITY_REVIEW.md) and [`SECURITY_AUDIT.md`](SECURITY_AUDIT.md).

## Tailwind build

The instructor course builder uses a production Tailwind CSS build rather than
the development-only browser CDN:

```bash
npm install
npm run css:build
```

Use `npm run css:dev` while changing the course-builder interface.

## Frontend structure

- `public/assets/css/main.css` contains shared design tokens, typography,
  buttons, forms, navigation, cards, alerts, and layout utilities.
- `public/assets/css/responsive.css` contains shared mobile breakpoints.
- Role and page-specific styles stay in their existing focused CSS files, such
  as `student.css`, `instructor.css`, and `admin_orders.css`.
- `public/assets/js/main.js` handles responsive navigation, logout dialogs,
  confirmations, and shared interactions without a framework dependency.
- Keep new shared behavior in these core files and add a page-specific file
  only when the styles or behavior are not reusable.

## Docker setup

Docker also uses MySQL on port `3307` for this project.

```bash
docker compose up --build
docker compose exec app php scripts/create_admin.php admin@example.com "Use-A-Strong-Password1!" "Admin Name"
```

The app is then available at `http://localhost:8080`. Reset the local Docker
database with `docker compose down -v` before re-running the schema from scratch.

## Current project status

CourseHub Rebuilt v2 is under active development.

The following workflows are currently available:

- Student, instructor, and administrator authentication
- Instructor course creation with sections and lessons
- Draft, pending, published, rejected, and archived course states
- Administrator instructor and course review
- Student cart, order, and manual payment-proof workflow
- Lifetime enrollment after successful payment verification
- Instructor earnings and withdrawal requests
- Production Tailwind CSS build for the instructor course builder

The following features still require completion or production testing:

- Real Khalti and eSewa gateway integration
- Course-specific student-instructor messaging
- Detailed lesson progress tracking
- Email verification and password-reset delivery
- Administrator MFA
- Comprehensive audit logging
- Automated integration and browser testing

## Production checklist

- Set `APP_ENV=production`, `APP_DEBUG=false`, and use HTTPS.
- Set explicit production `DB_*` values and disable local fallback.
- Use a dedicated database user with a unique password.
- Keep the web root on `public/`; never expose `app/`, `database/`, or `storage/`.
- Store `.env` and uploaded files outside Git and include them in backups.
- Configure mail delivery for registration, payment, and payout notifications.
- Replace manual proof payments with verified gateway APIs when transaction
  volume grows.
- Add scheduled backups, audit logs, monitoring, and an error-reporting service.
- Add terms, privacy, refund, instructor agreement, and tax/invoice workflows
  before accepting real customer money.

## Security notice

Older repository revisions included uploaded identity documents, payment proofs,
profile photos, and course resources. Removing files in a new commit does not
remove them from Git history. Before continuing to use a public repository:

1. Take the repository private.
2. Contact affected users and replace any exposed documents where appropriate.
3. Purge uploaded paths from history with `git filter-repo` or BFG.
4. Force-push the cleaned history and rotate any credentials that were exposed.
