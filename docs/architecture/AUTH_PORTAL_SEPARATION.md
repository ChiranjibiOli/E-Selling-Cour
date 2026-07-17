# Distinct portal authentication

CourseHub deliberately uses different authentication entry points and different implementation files for each role.

- Student: `/learn/sign-in`
- Instructor: `/teach/studio-access`
- Admin: configured by `ADMIN_LOGIN_PATH` and never linked from the public navbar

Different paths are only a small hardening layer. Authorization is enforced by the identity service using the requested portal, account role, account status, rate limiting, opaque bearer sessions, and an additional admin access code. Optional admin IP allowlisting is enforced by the web entry gate.

The three login rooms do not share controller, middleware, request, validator, or screen filenames. Shared code is limited to CSRF, HTTP responses, environment loading, and the identity-service client.
