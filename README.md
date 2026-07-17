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

The complete requested folder and file architecture is present. Identity login, rate limiting, bearer sessions, verification, and logout contain working backend logic. Other business-domain feature rooms currently provide defined boundaries and placeholders. Their final business logic must be implemented and tested inside those rooms. The removed legacy code is not secretly acting as a fallback.

## Validate

```powershell
& "C:\xampp\php\php.exe" tools\check_microservices.php
```

## Run the frontend house

```powershell
& "C:\xampp\php\php.exe" -S localhost:9001 -t apps\web-platform\public
```

Open `http://localhost:9001`.

## Docker

```powershell
docker compose up --build
```
