# ☁️ Google Drive Setup Guide

This document explains how to configure Google Drive integration for automatic daily report storage.

## 1. Create Google Cloud Credentials

1. Go to [Google Cloud Console](https://console.cloud.google.com/).
2. Create a new project or select an existing one.
3. In the sidebar, go to **APIs & Services** > **Library**.
4. Search for **"Google Drive API"** and enable it.
5. Go to **APIs & Services** > **Credentials**.
6. Click **Create Credentials** > **OAuth client ID**.
7. If prompted, configure the *OAuth consent screen*:
   - User Type: **External** (or Internal if you have Google Workspace).
   - Fill in mandatory info (App name, email).
   - **IMPORTANT**: Add your email as a "Test User".
8. Go back to create the Client ID:
   - Application type: **Web application**.
   - Authorized redirect URIs: `https://developers.google.com/oauthplayground` (You'll need this to get the token).
9. Copy your **Client ID** and **Client Secret**.

## 2. Obtain Refresh Token

Since the app runs server-side in the background (without user interaction), you need a `Refresh Token` that doesn't expire.

1. Go to [Google OAuth 2.0 Playground](https://developers.google.com/oauthplayground).
2. Click the ⚙️ (gear icon) in the top right.
3. Check **"Use your own OAuth credentials"**.
4. Enter the **Client ID** and **Client Secret** obtained in step 1.
5. In the API list on the left, find **Drive API v3**.
6. Select the scope `https://www.googleapis.com/auth/drive.file` (or `drive` for full access).
7. Click **Authorize APIs** and login with the Google account where you want to save the files.
8. Once authorized, click **Exchange authorization code for tokens**.
9. Copy the generated `Refresh Token`.

## 3. Environment Configuration (.env)

Insert the obtained values into your `.env` file:

```env
# Filesystem driver (google for Drive, s3 for AWS, local for testing)
FILESYSTEM_CLOUD=google

# Google Cloud Console Credentials
GOOGLE_DRIVE_CLIENT_ID=your-client-id
GOOGLE_DRIVE_CLIENT_SECRET=your-client-secret

# Token obtained from OAuth Playground
GOOGLE_DRIVE_REFRESH_TOKEN=your-refresh-token

# (Optional) Specific Folder ID where to save files.
# Leave empty to save in the root of Drive.
# The ID is the last part of the URL when you open a folder on Drive.
GOOGLE_DRIVE_FOLDER=
```

## 4. Verification

To verify that everything is working:
1. Ensure the configuration cache is cleared:
   ```bash
   ./sail artisan config:clear
   ```
2. Manually trigger a report generation:
   ```bash
   ./sail artisan tinker --execute="app(App\Services\StandardReportService::class)->generateAndSendReport(App\Models\User::first())"
   ```
3. Check if the file appears in your Google Drive and if you receive the link on Telegram/Email.
