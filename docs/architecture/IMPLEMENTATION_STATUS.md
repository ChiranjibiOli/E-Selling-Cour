# Implementation status

## Working vertical slices

### Identity and portal access

- Student, instructor, and admin portal-aware login
- Password verification, role checks, login throttling, opaque bearer sessions, verification, and logout
- Student self-registration with an immediately active student account
- Instructor application with a pending account and durable review record
- Administrator instructor-approval and rejection queue

### Course catalog and approval lifecycle

- Public category, search, course-list, and published-course detail APIs
- Instructor-owned course creation, update, listing, and state filtering
- Draft courses remain private and absent from the admin queue
- Draft or rejected courses can move to pending approval
- Pending courses are locked from instructor editing
- Admin approval publishes a course; rejection records a review note
- Editing a published course returns it to pending review
- Working instructor create/edit/list pages and admin approval pages

## Structurally created, business logic pending

Learning progress, commerce, payment providers, enrollment, media, notifications, reviews, and reporting retain complete feature-room boundaries but still require domain implementation and integration tests.

The repository does not claim that placeholder handlers are completed business features.
