# Database infrastructure

`schema.sql` and `seed.sql` preserve the current CourseHub data model while services are separated by domain. The identity service also requires `services/identity-service/database/migrations/001_identity_sessions.sql`.

The current development setup uses one MySQL server. Each service must access only the tables it owns until separate service databases are introduced.
