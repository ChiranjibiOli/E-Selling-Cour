# CourseHub production handoff

This document separates repository work from configuration that requires the platform owner's credentials, infrastructure, and policy decisions.

## 1. Pull the completed `main` branch

```powershell
cd C:\xampp\htdocs\E-Selling-Cour
git switch main
git pull origin main
```

## 2. Start the local stack

Docker Desktop must be open and the Linux engine must be running before these commands. The Windows named-pipe error means Docker Desktop is not running, not that PHP has suddenly developed feelings.

```powershell
docker compose down
docker compose up --build -d
docker compose ps
```

Open:

```text
Frontend: http://localhost:9001
API gateway: http://localhost:9000
```

## 3. Apply the learning-progress migration

A new Docker volume receives the migration automatically. For an existing database volume, run:

```powershell
Get-Content .\database\migrations\002_learning_progress.sql |
  docker compose exec -T db mysql `
    -ucoursehub_user `
    -plocal_coursehub_password `
    -P3307 coursehub
```

For XAMPP MySQL instead of Docker MySQL, adjust the username, password, database, and port to the local installation.

## 4. Validate PHP and architecture

```powershell
& "C:\xampp\php\php.exe" tools\check_microservices.php
& "C:\xampp\php\php.exe" tools\check_course_lifecycle.php
```

Then manually test this business sequence:

1. Register and approve an instructor.
2. Create a draft course.
3. Build sections and lessons.
4. Submit and approve the course.
5. Register a student and add the course to the cart.
6. Create an order.
7. Submit a manual payment reference and proof filename.
8. Approve the payment as admin.
9. Confirm the student receives lifetime access.
10. Complete a lesson and confirm progress changes.
11. Write a verified review.
12. Confirm instructor earnings appear.
13. Save payout details and request withdrawal.
14. Approve and record the payout as admin.

## 5. Secrets that must be configured by the owner

Create a private `.env` from `.env.example` and replace all examples.

Required production secrets include:

- database username and password
- private admin path
- long random admin entry code
- secure session configuration
- public application URL
- eSewa merchant credentials
- Khalti public and secret keys
- payment callback and webhook URLs
- SMTP or transactional-email credentials
- object-storage or private-media credentials
- OpenAI API key, only when the course assistant is implemented

Never commit real secrets to GitHub.

## 6. Manual-payment proof uploads

The repository now records proof metadata and performs the verification workflow. Production still needs a private binary upload service.

Implement or configure the media service so that it:

- accepts only authenticated uploads
- validates MIME type from file bytes, not only the extension
- permits JPG, PNG, WebP, and PDF for payment proof
- uses random server filenames
- stores proof outside the public web root or in private object storage
- applies strict file-size limits
- prevents executable file types and double extensions
- gives only the owning student and authorized admin access
- returns the stored proof filename to the payment form
- logs upload and admin-view activity

Do not make `payment_proofs` publicly browsable.

## 7. Course thumbnails, identity documents, and lesson media

Production media requires storage decisions that cannot be guessed inside source code.

Recommended separation:

```text
public-course-images/
private-instructor-identity/
private-payment-proofs/
protected-lesson-media/
```

Lesson delivery should use short-lived signed URLs or an authenticated streaming proxy. Add account/course authorization before issuing every URL. Browser download controls are deterrents, not absolute protection against screen recording.

## 8. eSewa and Khalti

The UI intentionally leaves automatic gateways disabled until real merchant credentials exist.

For each gateway, provide:

- sandbox and production merchant identifiers
- secret/signing credentials
- success, failure, and cancellation callback URLs
- webhook endpoint and signature-verification requirements
- exact amount/currency rules
- transaction-status verification endpoint
- refund endpoint and policy

The production integration must:

1. create the order server-side
2. send the server-calculated amount
3. verify callback/webhook signatures
4. query the gateway server-to-server when required
5. compare order ID, currency, amount, and transaction status
6. reject reused transaction IDs
7. use an idempotency key
8. grant enrollment in one database transaction
9. record failed verification without trusting the browser success page

## 9. Email and OTP delivery

Choose an SMTP or transactional email provider and configure:

- sender domain
- SPF, DKIM, and DMARC
- password-reset template
- OTP template
- instructor decision template
- course decision template
- payment decision template
- enrollment confirmation
- withdrawal status template

Rate-limit OTP and password-reset requests. Store hashes and expiry times rather than reusable plaintext codes.

## 10. OpenAI course assistant

The assistant is not enabled yet. Before enabling it:

- create a separate OpenAI API project and key
- place the key only in environment configuration
- build a retrieval index from published course descriptions, approved lessons, FAQ, and platform policy
- filter retrieved content by enrollment when private lessons are involved
- reject requests outside the platform/course scope
- exclude passwords, OTPs, payment secrets, identity documents, and other students' data
- add rate limits, token limits, moderation, logging, and cost controls
- cite the relevant CourseHub source inside responses

A ChatGPT Plus subscription does not include API usage for this website.

## 11. Legal and operational decisions

The owner must provide final text and approval for:

- Terms and Conditions
- Privacy Policy
- Refund Policy
- 12-hour access-removal policy
- instructor agreement
- prohibited content policy
- copyright/takedown policy
- payout schedule and minimum payout
- platform commission rate
- Nepal tax/invoice requirements
- data-retention and account-deletion policy

These are business/legal decisions, not safe values for source code to invent.

## 12. Production security checklist

Before public launch:

- force HTTPS
- set `SESSION_COOKIE_SECURE=true`
- use HttpOnly and SameSite cookies
- rotate every example password and entry code
- disable detailed PHP errors in public responses
- put the admin portal behind MFA
- configure backups and restore testing
- apply a web application firewall/rate limits where appropriate
- scan dependencies and container images
- restrict database and service ports from the public internet
- enable centralized logs and alerting
- verify CSRF on every state-changing browser action
- test horizontal and vertical authorization for every object ID
- test payment idempotency and duplicate references
- test upload bypasses and private-file access
- perform an independent security review before handling real money or identity documents

## 13. Still-planned product modules

The repository retains routes and panel designs for modules that need another implementation pass:

- full media service
- automatic eSewa/Khalti and refunds
- access-removal approval/revocation
- instructor announcements and Q&A
- complete coupon/category management UI
- profile image and instructor identity-document workflow
- Google OAuth, admin MFA, and full reset/OTP delivery
- audit-log and security-operations screens
- exportable financial reports
- course-scoped AI assistant

The current implementation status is maintained in `docs/architecture/IMPLEMENTATION_STATUS.md`.
