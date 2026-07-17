# House-compound local development

## Pull main

```powershell
git switch main
git fetch origin
git reset --hard origin/main
```

Back up local changes before `reset --hard`.

## Validate structure

```powershell
& "C:\xampp\php\php.exe" tools\check_microservices.php
```

## Environment

```powershell
Copy-Item .env.microservices.example .env.microservices
```

The default database host is `host.docker.internal`, port `3307`, database `coursehub`.

## Identity migration

```powershell
Get-Content services\identity-service\database\migrations\001_identity_sessions.sql |
  & "C:\xampp\mysql\bin\mysql.exe" -h 127.0.0.1 -P 3307 -u root coursehub
```

## Start the new architecture

```powershell
docker compose --env-file .env.microservices -f docker-compose.microservices.yml up --build
```

- Gateway: `http://localhost:9000`
- Web house: `http://localhost:9001`
- Public entrance: `http://localhost:9001/`
- Student entrance: `http://localhost:9001/student/login`
- Instructor entrance: `http://localhost:9001/instructor/login`
- Admin entrance: `http://localhost:9001/admin/login`
- Identity health: `http://localhost:9101/health`

The new web house currently exposes architecture-aware rooms. A room marked `compatibility-backed` maps an existing feature to its migration target but does not yet duplicate the full legacy page. Existing production behavior stays in the original application until parity tests allow deletion.
