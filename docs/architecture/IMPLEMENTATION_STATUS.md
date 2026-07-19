# Implementation status

## Working vertical slices

### Identity and portal access

- Student, instructor, and admin portal-aware login
- Password verification, role checks, login throttling, opaque bearer sessions, verification, and server-side logout
- Visible CSRF-protected logout control in every protected portal
- Authenticated users are redirected from the public landing page to their own dashboard
- Student self-registration with an immediately active student account
- Complete instructor application fields: personal photo, identity document, headline, expertise, experience, biography, course subjects, professional profile, and rules agreement
- Instructor uploads are MIME-validated, randomly named, size-limited, and stored outside the public web root
- Instructor application with a pending account and durable review record
- Administrator instructor approval and rejection queue with complete application details
- Single-use, expiring password-reset tokens with existing-session revocation after a successful reset

### Public website

- Full landing page, live published-course catalog, complete course-detail page, About page, Contact page, Pricing page, and published-instructor directory
- Functional FAQ, Privacy, Terms, Forgot password, and Reset password screens
- Public search includes course title, subtitle, tags, description, and instructor name
- Published-only public course and instructor visibility

### Course catalog and approval lifecycle

- Public category, search, course-list, and published-course detail APIs
- Instructor-owned course creation, update, listing, and state filtering
- Complete course authoring fields: title, subtitle, descriptions, outcomes, requirements, audience, tags, standard price, discount price, language, level, duration, thumbnail reference, and introduction video
- Drafts may remain incomplete and private; submission requires a subtitle, learning promise, and at least one curriculum lesson
- Draft courses remain private and absent from the admin queue
- Draft or rejected courses can move to pending approval
- Pending courses are locked from instructor editing
- Admin review shows the complete commercial and learning promise
- Admin approval publishes a course; rejection records a review note
- Editing a published course returns it to pending review
- Working instructor create/edit/list pages and admin approval pages

### Commerce, payment, and lifetime access

- Student cart add, remove, list, ownership checks, and published-course checks
- Checkout uses the same effective standard/discount price displayed publicly
- Server-side coupon validation, course-scope enforcement, eligible-item discount allocation, order creation, and historical order-item pricing
- Zero-total orders create a recorded free payment and lifetime enrollment without fake payment proof
- Manual payment submission with transaction reference and proof metadata
- Admin payment verification and rejection queue
- Approved payment atomically marks the order paid, grants lifetime enrollments, records instructor earnings, and sends notifications
- Student payment history, course library, and admin enrollment records

### Learning and verified reviews

- Instructor-owned curriculum sections and lessons with pending-course edit locks
- Enrolled-only course player for text, video, link, PDF, and document lessons
- Lesson completion records and per-course progress reporting
- Student progress dashboard
- Verified reviews restricted to students with active enrollment
- Student review create/update/delete and administrator moderation API

### Support, access policy, reporting, earnings, and payouts

- Public contact form stored in the administrator support queue
- Student in-app notification inbox with read controls
- Twelve-hour student access-removal request window
- Administrator approval or rejection of access-removal requests with historical enrollment retention
- Live administrator dashboard metrics and recent orders
- Live instructor course, student, revenue, commission, and earnings metrics
- Private instructor bank/eSewa/Khalti payout details
- Withdrawal requests reserve available earning records to prevent double withdrawal
- Administrator approval, rejection, and paid payout workflow with transaction reference
- Repeatable migration runner executes required schema changes automatically during Docker startup

## Implemented with an external dependency still required

- Instructor application binaries are stored privately, but a protected administrator document viewer still needs to be completed before production identity verification.
- Course thumbnail and lesson binary upload still require the media service; current course authoring stores validated text fields and media references.
- Manual payment flow stores the validated proof filename, but binary proof upload requires the media service and private storage configuration.
- Lesson video/file URLs work, but secure media upload, signed delivery URLs, streaming/CDN protection, and watermarking require storage infrastructure.
- eSewa and Khalti buttons remain disabled until merchant credentials, callback URLs, signature checks, and webhook verification are configured.
- Password reset records and pages work; email delivery requires SMTP or a transactional email provider. A local development reset link can be enabled explicitly.
- Email and OTP delivery require an SMTP or transactional email provider.
- Privacy and Terms pages are operational drafts and require qualified legal review before commercial launch.

## Structurally present but not yet complete

- Google OAuth and admin MFA
- Media upload service, protected admin document viewer, and protected download/streaming service
- eSewa and Khalti automatic payment gateways and refund webhooks
- Refund processing and financial reversal workflow
- Instructor announcements, course Q&A, and direct messaging
- Coupon creation/editing panels, despite secure coupon validation already existing in checkout
- Full admin user actions, audit logs, security settings persistence, and exportable reports
- Wishlist and certificates
- Superadmin separation from the standard admin role
- Course-scoped OpenAI assistant and retrieval index

The repository does not claim that a rendered room or placeholder button is completed business logic. Production readiness still requires the deployment, secrets, storage, gateway, email, legal-policy, and security steps in `docs/operations/PRODUCTION_HANDOFF.md`.
