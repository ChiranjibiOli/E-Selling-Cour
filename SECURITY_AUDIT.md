# Security and Business-Logic Audit

Audit date: 2026-07-12  
Repository: `ChiranjibiOli/E-Selling-Cour`  
Target branch: `main`

## Scope

This pass reviewed the PHP/MySQL course marketplace with emphasis on:

- authentication, sessions and authorization;
- CSRF and cross-origin state changes;
- SQL construction and input validation;
- IDOR and private-file access;
- upload validation and executable-upload prevention;
- cart, checkout, payment, enrollment, earnings and payout state transitions;
- instructor and course approval workflows;
- external lesson URLs and server-side redirects;
- duplicate processing, stale state and concurrent requests;
- security headers, environment configuration and web-server exposure.

This is a code and architecture hardening pass. It is not a guarantee that no vulnerability exists. A complete production assessment also requires runtime testing against the deployed web server, database, PHP extensions, TLS configuration and operating-system permissions.

## High-impact findings patched

### Authentication and sessions

- Added persistent login throttling by client IP and normalized email.
- Added constant-work password verification for unknown accounts.
- Added password-hash upgrade after successful login.
- Added idle and absolute session expiry.
- Added periodic session-ID regeneration.
- Rotate CSRF token after login.
- Reject invalid authentication state and unsupported roles.
- Restrict post-login redirects to an explicit internal allowlist.
- Require POST and CSRF for logout and all state-changing requests.
- Added same-origin checks for unsafe HTTP methods.

### Registration and profiles

- Validate real image content, MIME, dimensions and file size.
- Generate random server-side filenames.
- Store instructor identity/profile files outside the public web root.
- Delete orphan files when registration or profile database work fails.
- Serve private profile images only through authenticated/authorized endpoints.
- Added safe MIME fallback when `fileinfo` behaves differently between PHP installations.

### Cart and checkout

- Re-read and lock cart rows inside the transaction.
- Recalculate prices from the locked database state.
- Reject unpublished courses, inactive instructors, inactive categories and existing enrollments.
- Reconcile order-item totals to the order total.
- Reject duplicate transaction references.
- Delete only the cart rows that were actually checked out.
- Delete orphan payment-proof files after rollback.
- Validate real JPG, PNG or PDF proof content and dimensions.
- Store payment proofs outside the public web root.

### Payment approval and enrollment

- Moved admin order POST actions into an isolated controller.
- Lock order, payment, items and related records before transitions.
- Require a valid stored payment proof.
- Require payment amount to match the locked order total.
- Require item instructor to own the course and remain active.
- Require course to remain published before approval.
- Prevent duplicate earnings and active duplicate enrollments.
- Reconcile item totals before order approval.
- Require exact affected-row counts for payment and order state changes.
- Replace raw database errors with safe user-facing messages and server logs.

### Earnings, direct payouts and withdrawals

- Direct payout now uses the immutable recorded `instructor_amount` from the sale.
- Removed recalculation of an old sale using the current commission rate.
- Lock and verify every earning before payout.
- Require a complete receiving method matching the selected payout method.
- Prevent duplicate direct-order and withdrawal payouts.
- Verify withdrawal amount against the exact linked locked earnings.
- Require all linked earnings to transition together.
- Restore all locked earnings when a withdrawal is rejected.
- Store payout proof privately and clean failed uploads.
- Limit transaction references and administrative notes.
- Added database triggers to recompute commission math and reject invalid rates.

### Course and instructor approval

- Instructor approval now locks the account and verifies identity/profile files.
- Account transitions require an eligible previous state and exact affected rows.
- Course publication now verifies active instructor, active category, metadata, price, thumbnail, sections, lessons, URLs and uploaded resources.
- Every curriculum section must contain at least one valid lesson.
- Review notes are bounded and rejection requires a reason.
- Course status changes remain conditional on `pending` while locked.

### Private resources and external URLs

- Hardened payment-proof, payout-proof, identity-document, profile-photo and course-resource endpoints.
- Resolve stored files by basename inside approved directories only.
- Normalize download filenames and reject header injection.
- Apply MIME allowlists per resource type.
- Route external lesson links through enrollment/role checks.
- Reject localhost, private/reserved IPs, local/internal hostnames, credentials and unexpected ports.
- Add `noopener`, no-referrer behavior and no-store caching where applicable.

### Deployment and server hardening

- Database credentials are loaded from environment variables.
- Added secure session settings to `.env.example`.
- Added CSP, HSTS-on-HTTPS, frame, MIME, referrer and permissions headers.
- Added `.htaccess` denial for `app`, `database`, `storage`, `tests` and `tools`.
- Disabled directory listing and executable script extensions in public uploads.
- Production document root must still point to `public/`.

## Automated checks added

Workflow: `.github/workflows/php-security-checks.yml`

The workflow runs on pushes to `main` and pull requests using PHP 8.2 and 8.3:

```bash
find app public tests tools -type f -name '*.php' -print0 \
  | sort -z \
  | xargs -0 -n1 php -l

php tests/security_smoke.php
php tools/security_audit.php
```

The smoke test covers:

- safe internal redirects;
- external URL restrictions;
- rich-text sanitization;
- stored-file path resolution;
- safe download filenames;
- header-injection and traversal inputs.

The static audit rejects high-confidence dangerous patterns such as:

- `eval`, shell execution and dynamic command functions;
- `unserialize`;
- direct request-controlled includes;
- direct request-controlled redirect headers;
- committed private keys and common secret-key forms.

### Verification limitation

The connected GitHub status endpoint did not expose a completed push-triggered workflow run during this audit. The execution environment also could not clone the repository because outbound DNS/network access was unavailable. Therefore the workflow and tests are committed, but a green run must be confirmed in the repository Actions page or by running the commands locally.

## Database migration order

Back up the database first. Then run the files in this order:

1. `database/migrations/20260710_course_workflow.sql` if not already applied.
2. `database/migrations/20260712_security_preflight.sql`.
3. Resolve every row returned by the preflight.
4. `database/migrations/20260712_security_constraints.sql`.
5. `database/migrations/20260712_financial_invariants.sql`.
6. `database/migrations/20260712_order_invariants.sql`.

The preflight is read-only. The other migrations add unique keys and triggers. They can fail when historical duplicate or inconsistent data still exists, which is preferable to silently blessing corrupted financial records.

## Required local integration tests

Run these against a copied local database before production deployment:

1. Submit five incorrect logins, verify throttling, then verify successful login after expiry.
2. Register an instructor with valid and invalid images; verify failed registrations leave no private files.
3. Attempt instructor approval without documents, with invalid documents and with valid documents.
4. Submit an incomplete course and verify admin publication is blocked with exact reasons.
5. Submit a complete course and verify only admin approval makes it publicly visible.
6. Attempt to purchase a blocked instructor's course or a course in an inactive category.
7. Open two checkout tabs, change a course price/cart row, and verify only the locked current state is ordered.
8. Submit the same transaction reference twice and verify the second order is rejected.
9. Approve the same payment from two sessions and verify only one enrollment/earning set is created.
10. Change the commission setting after a sale and verify direct payout still uses the original recorded instructor amount.
11. Double-submit direct payout and withdrawal payout forms; verify only one payout exists.
12. Reject a withdrawal and verify every linked earning returns to `available` exactly once.
13. Try another user's order, profile photo, payment proof, payout proof and course-resource IDs.
14. Upload renamed PHP/script files to every upload field and verify rejection/non-execution.
15. Test lesson URLs pointing to `localhost`, `127.0.0.1`, private IP ranges, credentials and port `8443`.
16. Verify all authenticated pages send no-store headers and production HTTPS sends HSTS.
17. Verify Apache document root is `public/` and `.htaccess` rules are enabled.

## Remaining production responsibilities

- Use HTTPS in production.
- Set `APP_DEBUG=false`.
- Use a dedicated database account with only required privileges.
- Keep database and private storage backups encrypted and access-controlled.
- Configure PHP upload/body limits at the server level.
- Monitor failed logins, payment rejections and payout events.
- Use a real payment provider/webhook verification flow before treating automated online payments as settled.
- Schedule dependency, server and penetration testing after deployment changes.
