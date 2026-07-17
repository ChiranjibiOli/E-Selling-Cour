# Full architecture replacement

This document records the requested final state:

- One repository and one `main` branch.
- One frontend house at `apps/web-platform`.
- Public, Student, Instructor, and Admin floors with unique route prefixes.
- Every page is a room that owns its route, controller, middleware, request, validator, service, API client, view-model, page, components, assets, and tests.
- Backend code is separated into domain microservices.
- Every backend feature owns its route, controller, middleware, request, validator, policy, handler, repository, response, event, and tests.
- Top-level legacy application folders (`app`, `public`, `routes`) are removed after the replacement tree is committed.
- `database`, `storage`, infrastructure, documentation, and tooling remain because they are platform infrastructure, not legacy page code.
