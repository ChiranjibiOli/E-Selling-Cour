# CourseHub microservices architecture

This repository uses one `main` branch and a monorepo layout.

## Applications

- `apps/public-web` — landing, about, contact, course discovery, login entry, privacy and terms.
- `apps/student-web` — student login and the future student portal.
- `apps/instructor-web` — instructor login, registration and the future instructor portal.
- `apps/admin-web` — private administrator login and the future admin portal.
- `apps/mobile-app` — mobile client consuming the same API gateway.

## Services

- `services/api-gateway` — public API entry point, routing, request IDs and response normalization.
- `services/identity-service` — role-aware login, sessions, OAuth adapters, password reset and MFA.
- `services/catalog-service` — categories, courses, drafts and approvals.
- `services/learning-service` — sections, lessons and progress.
- `services/commerce-service` — cart, coupons and orders.
- `services/payment-service` — manual payment, Khalti, eSewa and webhook verification.
- `services/enrollment-service` — lifetime access and revocation.
- `services/media-service` — secure uploads and protected media.
- `services/notification-service` — email, OTP and in-app notifications.
- `services/review-service` — ratings and reviews.
- `services/reporting-service` — dashboards and reports.

## Vertical slices

A page or business feature owns its route, controller, middleware, validator, handler, components, assets and tests. Code should not be grouped only by technical type across the whole repository.

Example:

```text
apps/instructor-web/src/Pages/CreateCourse/
  CreateCourseRoute.php
  CreateCourseController.php
  Middleware/
  Components/
  assets/
  Tests/

services/catalog-service/src/Features/CreateCourse/
  CreateCourseController.php
  CreateCourseRequest.php
  CreateCourseValidator.php
  CreateCourseHandler.php
  CreateCoursePolicy.php
  CourseRepository.php
  Tests/
```

## Migration rule

The existing application remains operational under the current root `public/` document root until each feature has been moved and verified. New applications and services are introduced beside it. A feature is removed from the legacy application only after its replacement passes integration and security tests.

## Data ownership

During migration, services may connect to the existing `coursehub` database using separate credentials. The target state is one database or schema per service. A service must not write directly to another service's tables.

## Security boundaries

- All public APIs enter through the gateway.
- Admin registration is never public.
- Portal role and authenticated account role must match.
- Passwords are verified only by the identity service.
- Raw session tokens are returned once and stored only as hashes server-side.
- Payment providers are verified server-to-server before enrollment is created.
- Every service exposes `/health` and must emit a request ID in responses.
