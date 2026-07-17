# CourseHub house-compound architecture

## Compound

The GitHub repository is the compound. It contains one web house, separate backend service buildings, shared packages, infrastructure and documentation.

## Web house

`apps/web-platform` is one frontend application with four floors:

- `Public` uses `/`, `/about`, `/courses`, `/login` and other visitor paths.
- `Student` uses only `/student/*` paths.
- `Instructor` uses only `/instructor/*` paths.
- `Admin` uses only `/admin/*` paths.

Each feature is a room. An implemented room owns `Controller.php`, `Middleware.php`, `Service.php` and `Page.php`. Components, assets and tests are added inside that same room when the feature needs them.

## Backend buildings

Backend code is separated by business domain, not by page. Each service contains feature rooms with its own controller, middleware, validator, handler and repository.

## Migration rule

The existing PHP application remains the compatibility source while features are moved room by room. Copying old code into hundreds of new files without parity tests is not migration; it is merely relocating the confusion. A legacy feature may be removed only after its new frontend room and backend feature pass behavior, authorization and data-integrity tests.
