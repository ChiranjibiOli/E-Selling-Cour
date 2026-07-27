# Gmail verification setup

CourseHub now requires Gmail verification for new Student accounts and sends six-digit Gmail codes for Student password recovery.

## 1. Prepare a sender Gmail account

Use a dedicated Gmail account for CourseHub system emails.

1. Turn on Google 2-Step Verification for the sender account.
2. Create a Google App Password for CourseHub.
3. Copy the generated App Password. Do not use the ordinary Gmail password.
4. Do not commit the App Password to GitHub.

Some managed, security-key-only, or Advanced Protection accounts may not offer App Passwords. Use a suitable sender account or another SMTP provider in that case.

## 2. Configure `.env`

Add these values to the local `.env` file:

```dotenv
ALLOW_LOCAL_EMAIL_CODE=false
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USERNAME=your-coursehub-gmail@gmail.com
SMTP_PASSWORD=replace-with-the-google-app-password
SMTP_FROM_ADDRESS=your-coursehub-gmail@gmail.com
SMTP_FROM_NAME=CourseHub
```

Use the sender Gmail address for both `SMTP_USERNAME` and `SMTP_FROM_ADDRESS`. Store the App Password without surrounding spaces. Quote the value if the local environment parser requires it.

## 3. Restart and migrate

```powershell
git pull origin main
docker compose down
docker compose up --build -d
docker compose logs migrate --tail=100
```

The migration output must include:

```text
APPLIED 007_student_email_verification
MIGRATIONS COMPLETE
```

## 4. Test Student registration

1. Open `http://localhost:9001/register/student`.
2. Register using an address ending in `@gmail.com`.
3. Confirm that the six-digit code arrives in Gmail.
4. Enter the code on `/verify-otp`.
5. Confirm that Student sign-in succeeds only after verification.

## 5. Test Student password recovery

1. Open `http://localhost:9001/forgot-password`.
2. Enter an active verified Student Gmail address.
3. Confirm that the reset code arrives in Gmail.
4. Enter the code and a new password.
5. Confirm that old sessions are revoked and the new password works.

## Local-only fallback

When SMTP credentials are temporarily unavailable, local development can display the code on the verification page:

```dotenv
APP_ENV=local
ALLOW_LOCAL_EMAIL_CODE=true
```

This fallback does not send email and must remain disabled for a real deployment.
