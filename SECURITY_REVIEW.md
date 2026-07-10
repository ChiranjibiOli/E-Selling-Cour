# Security review and controls

This rebuild applies defensive controls to the highest-risk workflows. No application can honestly be guaranteed to contain zero vulnerabilities; production release should still include manual penetration testing, dependency review, server hardening, and monitoring.

## Controls implemented

| Risk | Control |
| --- | --- |
| SQL injection | Prepared MySQLi statements for user-controlled values; filters use allowlists. |
| Horizontal/vertical access control | Role middleware plus ownership checks for instructor courses, student enrollments, resources, orders, profiles, and payouts. |
| Draft exposure | Admin/public queries exclude instructor drafts; unpublished details require course ownership or a non-draft admin review state. |
| CSRF | Session-bound random token on every POST request, constant-time comparison, and POST method enforcement. |
| Session fixation/theft | Strict cookie sessions, HTTP-only, SameSite Lax, secure under HTTPS, and session ID regeneration after login. |
| XSS | Contextual HTML escaping, allowlist sanitization for rich lesson text, and a restrictive Content Security Policy. |
| Clickjacking/MIME attacks | CSP frame ancestors, X-Frame-Options, and X-Content-Type-Options. |
| Sensitive caching | Authenticated pages send no-store/private cache headers. |
| File upload abuse | MIME, extension, size, randomized name, basename, and protected-directory validation. |
| IDOR on private files | Identity, payment, payout, and lesson files are returned only by endpoints that re-check role or enrollment. |
| Open redirect/header injection | Redirect paths are locally constrained and CR/LF characters are removed. |
| Course-change bypass | Published content changes create before/after snapshots, move to pending review, and notify admins. |
| Double payment/enrollment | Pending-state checks, affected-row checks, unique keys, and transactions. |
| Withdrawal race/double payout | Earnings are locked with `FOR UPDATE`; states move atomically from available to requested to paid/rejected. |
| Direct payout conflict | Direct payouts select only available earnings and create no active withdrawal request. |

## Required production work

1. Serve only `public/` as the web document root.
2. Set `APP_ENV=production`, `APP_DEBUG=false`, and enable HTTPS.
3. Use a dedicated least-privilege database user and a unique secret password.
4. Move all private uploads outside any public web directory.
5. Configure malware scanning for uploaded documents and proofs.
6. Add email verification, password reset, and optional MFA before public launch.
7. Add centralized audit logging, error reporting, rate limiting, backups, and alerts.
8. Replace or supplement manual proof payments with verified eSewa/Khalti gateway callbacks when handling real money.
9. Perform a new authenticated test with separate Student A, Student B, Instructor A, Instructor B, and Admin accounts.
10. Keep the GitHub repository private and purge historic identity/payment uploads from Git history before using it with real users.
