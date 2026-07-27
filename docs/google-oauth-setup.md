# Google Student sign-in setup

CourseHub now supports free Google Identity Services sign-in for Student accounts. Instructor and Admin authentication remains separate and password-based.

## 1. Create the Google web client

1. Open Google Cloud Console and create or select a project.
2. Configure Google Auth Platform branding and choose an external audience when the application is intended for normal public users.
3. Create an OAuth client with application type **Web application**.
4. Add this local Authorized JavaScript origin:

   ```text
   http://localhost:9001
   ```

5. Add the production origin later, for example:

   ```text
   https://courses.example.com
   ```

This implementation uses the Google Identity Services JavaScript callback, so it needs an Authorized JavaScript origin. It does not need a client secret or a redirect URI.

## 2. Configure CourseHub

Copy `.env.example` to `.env` when needed, then replace:

```env
GOOGLE_CLIENT_ID=replace-with-your-google-web-client-id.apps.googleusercontent.com
```

with the Web client ID from Google Cloud.

Never commit a real credential value to GitHub. The client ID is displayed in the browser by design, but environment-specific configuration still belongs in `.env` rather than source files.

## 3. Start or restart the project

```powershell
cd C:\xampp\htdocs\E-Selling-Cour
docker compose up -d --build
```

The migration service automatically creates the `oauth_accounts` table. Open:

```text
http://localhost:9001/learn/sign-in
```

The **Continue with Google** button appears only when `GOOGLE_CLIENT_ID` is configured in `.env` and the containers have been restarted.

## Account behaviour

- Google sign-in is available only for verified `@gmail.com` Student accounts.
- A new Gmail user receives an active Student account automatically.
- An existing active Student account with the same Gmail address is linked to Google.
- An unverified Student account is activated after successful Google verification.
- Instructor, Admin, blocked, and manually disabled accounts cannot use this Student OAuth route.
- CourseHub stores Google's stable account identifier, not the Google ID token.

## Production note

The current backend validates the returned Google ID token through Google's HTTPS `tokeninfo` endpoint and then independently checks the audience, authorised party, issuer, expiry, Gmail verification, role, and local account status. For a high-traffic production deployment, replace the remote `tokeninfo` call with Google's official PHP client library so public signing keys can be cached locally.
