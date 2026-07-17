# House-compound architecture

The repository is the compound. `apps/web-platform` is one frontend house. Public, Student, Instructor, and Admin are separate floors with unique entrances. Each page or feature is a room that owns all files needed to render, validate, authorize, communicate with an API, and test that page.

Backend code lives in separate service buildings organized by business domain rather than by frontend page. Each domain service contains feature rooms with their own controller-to-repository chain.

## Portal entrances

- Public: `/`
- Student login: `/student/login`
- Instructor login: `/instructor/login`
- Admin login: `/admin/login`

Admin has no public registration entrance.
