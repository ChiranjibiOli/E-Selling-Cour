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

The `migrate` service now applies identity sessions, learning progress, instructor application fields, password-reset storage, and complete course-authoring fields automatically, including on an existing database volume.

Expected migration output includes:

```text
APPLIED 002_learning_progress
APPLIED 003_instructor_application_and_password_reset
APPLIED 004_course_authoring_details
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
2. Open `/pricing`, `/instructors`, `/faq`, `/privacy`, and `/terms`.
3. Open `/forgot-password` and submit a test account email.
4. Sign in as a student and confirm `Log out` appears at the bottom of the sidebar.
5. While signed in, open `/`; it should redirect to `/student/dashboard`.
6. Open `/student/progress`; the migration error should be gone.
7. Open `/register/instructor`; confirm photo, identity, expertise, experience, headline, profile link, subjects, and agreement fields exist.
8. Create or edit a course and confirm subtitle, outcomes, requirements, audience, tags, introduction video, and discount-price fields persist.
9. Confirm the course cannot be submitted until it has the required learning details and at least one curriculum lesson.
10. Open `/student/unsubscribe`; eligible enrollments should show a twelve-hour request window.
11. Create an order for a discounted course and confirm checkout uses the displayed effective price.

## Validate PHP and architecture

```powershell
& "C:\xampp\php\php.exe" tools\check_microservices.php
& "C:\xampp\php\php.exe" tools\check_course_lifecycle.php
```

To lint every PHP file locally:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object {
    & "C:\xampp\php\php.exe" -l $_.FullName
    if ($LASTEXITCODE -ne 0) { throw "PHP lint failed: $($_.FullName)" }
}
```
