# CourseHub

CourseHub is a main-only monorepo organized as a house-compound frontend with domain microservices.

## Active application code

- Frontend house: `apps/web-platform`
- Public floor: `apps/web-platform/src/Public`
- Student floor: `apps/web-platform/src/Student`
- Instructor floor: `apps/web-platform/src/Instructor`
- Admin floor: `apps/web-platform/src/Admin`
- Backend service buildings: `services/*`
- Mobile application boundary: `apps/mobile-app`

The old top-level `app/`, `public/`, and `routes/` application trees are not part of this repository state.

Every frontend room owns its route, controller, middleware, request, validator, service, API client, view-model, page, components, assets, tests, and room documentation. Every backend feature owns its route, controller, middleware, request, validator, policy, handler, repository, response, event, tests, and feature documentation.

## Honest implementation status

Working vertical slices now include:

- role-aware student, instructor, and admin authentication
- student and instructor registration plus instructor approval
- instructor-owned course creation and Draft → Pending → Published/Rejected review
- published-only public catalog, search, and course details
- student cart, server-priced checkout, order history, and zero-total enrollment
- manual payment submission and administrator verification
- atomic lifetime enrollment, instructor earning, and notification creation
- instructor curriculum sections and lessons
- enrolled-only course player and lesson progress
- verified student course reviews
- public contact support queue and student notifications
- live admin and instructor business dashboards
- instructor payout details, earning reservation, withdrawals, and administrator payout recording

Media upload/storage, eSewa/Khalti gateways, refunds, access-removal decisions, messaging/Q&A, OAuth/MFA delivery, audit operations, and the course-scoped AI assistant still require further implementation or external credentials. See:

- `docs/architecture/IMPLEMENTATION_STATUS.md`
- `docs/operations/PRODUCTION_HANDOFF.md`

## Validate

```powershell
& "C:\xampp\php\php.exe" tools\check_microservices.php
& "C:\xampp\php\php.exe" tools\check_course_lifecycle.php
```

## Run the frontend house

Use the router script so every unique portal path reaches the web front controller:

```powershell
& "C:\xampp\php\php.exe" -S localhost:9001 `
  -t apps\web-platform\public `
  apps\web-platform\public\router.php
```

Open `http://localhost:9001`.

Useful paths:

```text
/
/learn/sign-in
/teach/studio-access
/private admin path from .env
```

## Docker

Docker Desktop must be running first.

```powershell
docker compose up --build -d
docker compose ps
```

Docker exposes the frontend at `http://localhost:9001` and the API gateway at `http://localhost:9000`.

For an existing database volume, apply the learning-progress migration as described in `docs/operations/PRODUCTION_HANDOFF.md`.

## Create the first administrator

After the database is running, create an administrator without committing a password:

```powershell
$env:COURSEHUB_ADMIN_EMAIL="admin@example.com"
$env:COURSEHUB_ADMIN_PASSWORD="replace-with-a-long-private-password"
$env:COURSEHUB_ADMIN_NAME="Platform Administrator"
& "C:\xampp\php\php.exe" tools\create_admin.php
```

Use the private admin path and entry code configured in `.env` to open the control room. Do not use the example secrets in production.
