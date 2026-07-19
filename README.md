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

The first complete business vertical slice is implemented: role-aware authentication, student and instructor registration, instructor approval, instructor-owned course creation/editing, Draft → Pending → Published/Rejected review, and published-only public catalog access. Learning, checkout, payments, enrollments, media, reviews, notifications, and reporting still have defined service boundaries but remain later milestones. See `docs/architecture/IMPLEMENTATION_STATUS.md`.

## Validate

```powershell
& "C:\xampp\php\php.exe" tools\check_microservices.php
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
/student/login
/instructor/login
/admin/login
```

## Docker

```powershell
docker compose up --build
```

Docker exposes the frontend at `http://localhost:9001` and the API gateway at `http://localhost:9000`.

## Create the first administrator

After the database is running, create an administrator without committing a password:

```powershell
$env:COURSEHUB_ADMIN_EMAIL="admin@example.com"
$env:COURSEHUB_ADMIN_PASSWORD="replace-with-a-long-private-password"
$env:COURSEHUB_ADMIN_NAME="Platform Administrator"
& "C:\xampp\php\php.exe" tools\create_admin.php
```

Use the private admin path and entry code configured in `.env` to open the control room. Do not use the example secrets in production.
