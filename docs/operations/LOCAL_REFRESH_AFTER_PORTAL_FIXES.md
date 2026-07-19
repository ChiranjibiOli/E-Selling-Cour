# Refresh the local CourseHub stack after portal fixes

Run these commands in PowerShell:

```powershell
cd C:\xampp\htdocs\E-Selling-Cour
git switch main
git pull origin main

# Docker Desktop must be running.
docker compose down
docker compose up --build -d
docker compose ps
docker compose logs migrate
```

The `migrate` service now applies identity sessions, learning progress, instructor application fields, and password-reset storage automatically, including on an existing database volume.

Expected migration output includes:

```text
APPLIED 002_learning_progress
APPLIED 003_instructor_application_and_password_reset
MIGRATIONS COMPLETE
```

Already-applied migrations appear as `SKIP` instead.

## Local password-reset testing

Real password-reset delivery needs email infrastructure. To show the reset link only during local development, add this to the private `.env` file:

```dotenv
APP_ENV=local
ALLOW_LOCAL_RESET_TOKEN=true
```

Then rebuild the identity service:

```powershell
docker compose up --build -d identity-service api-gateway web-platform
```

Never enable `ALLOW_LOCAL_RESET_TOKEN` in staging or production.

## Verify the visible fixes

1. Open `/about` and confirm the real About page appears.
2. Open `/forgot-password` and submit a test account email.
3. Sign in as a student and confirm `Log out` appears at the bottom of the sidebar.
4. While signed in, open `/`; it should redirect to `/student/dashboard`.
5. Open `/student/progress`; the migration error should be gone.
6. Open `/register/instructor`; confirm photo, identity, expertise, experience, headline, profile link, subjects, and agreement fields exist.
7. Open `/student/unsubscribe`; eligible enrollments should show a twelve-hour request window.
