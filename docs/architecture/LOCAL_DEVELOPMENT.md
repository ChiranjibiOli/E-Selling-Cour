# Microservices local development

The legacy application remains available through the existing `docker-compose.yml` or XAMPP setup. The new architecture runs beside it with `docker-compose.microservices.yml`.

## 1. Pull main

```powershell
git switch main
git fetch origin
git reset --hard origin/main
```

Back up local uncommitted changes before using `reset --hard`.

## 2. Create environment file

```powershell
Copy-Item .env.microservices.example .env.microservices
```

Edit the database credentials when they differ from the defaults.

## 3. Apply identity migration

From a shell with the MySQL client available:

```powershell
Get-Content services/identity-service/database/migrations/001_identity_sessions.sql |
  mysql -h 127.0.0.1 -P 3307 -u root coursehub
```

When the database user has a password, add `-p` and enter it interactively. Do not put production passwords in command history.

## 4. Start applications and services

Docker Compose does not automatically load arbitrary env-file names, so pass it explicitly:

```powershell
docker compose --env-file .env.microservices -f docker-compose.microservices.yml up --build
```

## Local endpoints

| Component | URL |
|---|---|
| API gateway | http://localhost:9000 |
| Public web | http://localhost:9001 |
| Student portal | http://localhost:9002 |
| Instructor portal | http://localhost:9003 |
| Admin portal | http://localhost:9004 |
| Identity service direct health | http://localhost:9101/health |

The other services are internal-only Docker services at this stage. Their `/health` endpoints are reachable by the API gateway and other containers.

## Health checks

```powershell
Invoke-RestMethod http://localhost:9000/health
Invoke-RestMethod http://localhost:9001/health
Invoke-RestMethod http://localhost:9002/health
Invoke-RestMethod http://localhost:9003/health
Invoke-RestMethod http://localhost:9004/health
Invoke-RestMethod http://localhost:9101/health
```

## Login API test

```powershell
$body = @{
  portal = 'student'
  email = 'student@example.com'
  password = 'your-password'
} | ConvertTo-Json

$response = Invoke-RestMethod `
  -Method Post `
  -Uri http://localhost:9000/api/v1/auth/login `
  -ContentType 'application/json' `
  -Body $body

$response
```

Use only an account created in your local database. The service returns an opaque bearer token once and stores only its SHA-256 hash.

## Verify a session

```powershell
Invoke-RestMethod `
  -Method Get `
  -Uri http://localhost:9000/api/v1/auth/session `
  -Headers @{ Authorization = "Bearer $($response.access_token)" }
```

## Logout

```powershell
Invoke-RestMethod `
  -Method Post `
  -Uri http://localhost:9000/api/v1/auth/logout `
  -Headers @{ Authorization = "Bearer $($response.access_token)" }
```

## Stop

```powershell
docker compose --env-file .env.microservices -f docker-compose.microservices.yml down
```

## Migration policy

Do not delete a working legacy feature merely because a service folder exists. Migrate one vertical slice, test it, route traffic to it, then remove the old implementation in a separate reviewed change.
