# Implementation status

## Working vertical slices

### Identity and portal access

- Student, instructor, and admin portal-aware login
- Password verification, role checks, login throttling, opaque bearer sessions, verification, and logout
- Student self-registration with an immediately active student account
- Instructor application with a pending account and durable review record
- Administrator instructor approval and rejection queue

### Course catalog and approval lifecycle

- Public category, search, course-list, and published-course detail APIs
- Instructor-owned course creation, update, listing, and state filtering
- Draft courses remain private and absent from the admin queue
- Draft or rejected courses can move to pending approval
- Pending courses are locked from instructor editing
- Admin approval publishes a course; rejection records a review note
- Editing a published course returns it to pending review
- Working instructor create/edit/list pages and admin approval pages

### Commerce, payment, and lifetime access

- Student cart add, remove, list, ownership checks, and published-course checks
- Server-side checkout pricing, coupon validation, order creation, and historical order-item pricing
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

### Support, reporting, earnings, and payouts

- Public contact form stored in the administrator support queue
- Student in-app notification inbox with read controls
- Live administrator dashboard metrics and recent orders
- Live instructor course, student, revenue, commission, and earnings metrics
- Private instructor bank/eSewa/Khalti payout details
- Withdrawal requests reserve available earning records to prevent double withdrawal
- Administrator approval, rejection, and paid payout workflow with transaction reference

## Implemented with an external dependency still required

- Manual payment flow stores the validated proof filename, but binary proof upload requires the media service and private storage configuration.
- Lesson video/file URLs work, but secure media upload, signed delivery URLs, streaming/CDN protection, and watermarking require storage infrastructure.
- eSewa and Khalti buttons remain disabled until merchant credentials, callback URLs, signature checks, and webhook verification are configured.
- Email and OTP delivery require an SMTP or transactional email provider.

## Structurally present but not yet complete

- Google OAuth, admin MFA, and full password-recovery delivery
- Media upload service and protected download/streaming service
- eSewa and Khalti automatic payment gateways and refund webhooks
- Refund processing and enrollment revocation
- Student access-removal request decisions
- Instructor announcements, course Q&A, and direct messaging
- Coupon creation/editing panels and advanced category-scoped discount allocation
- Full admin user actions, audit logs, security settings persistence, and exportable reports
- Course-scoped OpenAI assistant and retrieval index

The repository does not claim that a rendered room or placeholder button is completed business logic. Production readiness still requires the deployment, secrets, storage, gateway, email, legal-policy, and security steps in `docs/operations/PRODUCTION_HANDOFF.md`.
