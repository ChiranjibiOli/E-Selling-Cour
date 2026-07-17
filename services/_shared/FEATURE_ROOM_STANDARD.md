# Backend feature-room standard

Every implemented backend feature owns these files inside its domain service:

- `Controller.php`: translates HTTP input/output only.
- `Middleware.php`: authentication, role and request-boundary checks.
- `Validator.php`: validates syntax and required values.
- `Policy.php`: authorization and ownership decisions.
- `Handler.php`: business transaction orchestration.
- `Repository.php`: data access owned by that service.
- `Tests/`: behavior, authorization and failure tests.

A service may not write another service's tables. Cross-domain work uses a trusted API or an event with idempotency.
