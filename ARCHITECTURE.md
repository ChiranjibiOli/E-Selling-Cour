# CourseHub architecture

CourseHub uses one GitHub repository and one `main` branch.

## Frontend compound

`apps/web-platform` is one web house with four floors:

- Public: `/`, `/about`, `/courses`, `/login`
- Student: `/student/*`
- Instructor: `/instructor/*`
- Admin: `/admin/*`

Implemented feature rooms contain separate controller, middleware, service and page files. Planned rooms contain an honest README placeholder.

## Backend compound

`services` contains domain microservices. Backend features are grouped by business responsibility, with feature-room files for HTTP translation, middleware, validation, authorization, business handling and data access.

## Current migration state

The architecture and room map are implemented. Identity login/session/logout has working service code. Other existing features are mapped as `compatibility-backed` while their business logic is extracted and parity-tested. The original application is not deleted until each mapped feature is truly replaced; deleting it earlier would deliberately break the site.
