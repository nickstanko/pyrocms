# PyroCMS

This repository is a PyroCMS / Streams Platform application updated to support Laravel 12 and Passport-based headless API authentication.

## What Is Different

This version changes a few important pieces:

- The underlying package set has been moved forward to the Laravel 12-compatible Streams Platform and addon versions.
- API authentication now uses Laravel Passport instead of the older simple token guard.
- Headless auth routes are included at `/api/register`, `/api/login`, `/api/logout`, and `/api/user`.
- `/api/login` issues bearer tokens by making an internal request to `/oauth/token`.
- Passport password grant is explicitly enabled in `AppServiceProvider`.
- Existing Passport client secrets may need to be re-hashed for Passport 13+ validation.
- Legacy Anomaly code paths still need compatibility patching after Composer install/update. That is handled by `scripts/patch_anomaly_dispatch.php` through Composer scripts.
- In `local` and `testing`, Redis sessions fall back to file sessions if the PHP Redis extension is not installed.

## Requirements

- PHP 8.4+
- Composer
- MySQL
- Redis recommended for normal runtime usage

## Install

1. Install PHP dependencies:

```bash
composer install
```

2. Create and configure your `.env` file.

Important values include:

- `APP_NAME`
- `APP_ENV`
- `APP_KEY`
- `APP_URL`
- `DB_*`
- `REACT_CLIENT_ID`
- `REACT_CLIENT_SECRET`

3. Generate the application key if needed:

```bash
php artisan key:generate
```

4. Generate Passport keys:

```bash
php artisan passport:keys
chmod 600 storage/oauth-private.key storage/oauth-public.key
```

5. Create a Passport password grant client if you do not already have one:

```bash
php artisan passport:client --password
```

Set the resulting client id and secret as:

- `REACT_CLIENT_ID`
- `REACT_CLIENT_SECRET`

6. Run migrations:

```bash
php artisan migrate
```

7. Clear caches if this is an upgraded install:

```bash
php artisan optimize:clear
```

## Upgrade Notes

If you are updating an existing older install instead of starting fresh:

1. Deploy the updated code.
2. Run Composer with scripts enabled:

```bash
composer install --no-dev --optimize-autoloader
```

3. Ensure Passport keys exist and have correct permissions:

```bash
php artisan passport:keys
chmod 600 storage/oauth-private.key storage/oauth-public.key
```

4. Run migrations:

```bash
php artisan migrate --force
```

5. Clear caches:

```bash
php artisan optimize:clear
```

Important:

- The migration `2026_03_13_130000_hash_existing_passport_client_secrets` re-saves existing OAuth client secrets so Passport 13+ can validate them correctly.
- Do not change `REACT_CLIENT_SECRET` unless you also intentionally update the matching Passport password client.
- Do not skip Composer scripts. The Anomaly compatibility patch is part of the install/update flow.

## Passport API Routes

This app includes generic headless auth routes:

- `POST /api/register`
- `POST /api/login`
- `POST /api/logout`
- `GET /api/user`

### Register

Request:

```json
{
  "email": "user@example.com",
  "password": "secret1234",
  "first_name": "Jane",
  "last_name": "Doe",
  "auto_login": true
}
```

Optional fields:

- `username`
- `display_name`
- `auto_login`

If `auto_login` is `true` or omitted, the response will try to include a Passport access token after registration.

### Login

You can log in with any one of:

- `login`
- `email`
- `username`

Example:

```json
{
  "email": "user@example.com",
  "password": "secret1234"
}
```

or:

```json
{
  "login": "user@example.com",
  "password": "secret1234"
}
```

Successful login returns the Passport token payload from `/oauth/token` plus the resolved user record.

### Authenticated Requests

Use the returned bearer token in the `Authorization` header:

```http
Authorization: Bearer YOUR_ACCESS_TOKEN
```

Example:

```bash
curl -X GET http://localhost/api/user \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

### Logout

Logout revokes the current access token:

```bash
curl -X POST http://localhost/api/logout \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

## Notes

- The API guard now uses Passport in `config/auth.php`.
- The user provider now points at `App\UserModel`, which extends the Pyro users model and adds `HasApiTokens`.
- The login endpoint uses the configured Pyro user login mode to resolve whether Passport should authenticate by username or email.

## Docker Volumes

The Docker setup supports persistent or shared mounts for the parts of the app that usually need to live outside the image:

- `addons/` for shared or custom PyroCMS addons
- `storage/` for runtime app data
- `bootstrap/cache/` for generated cache files
- `public/app/` for user-managed public assets
- `config/` and `resources/streams/config/` if you want host-managed config overlays

The default production compose file already persists:

- `/var/www/html/addons`
- `/var/www/html/storage`
- `/var/www/html/bootstrap/cache`

If you want host bind mounts instead of Docker-managed named volumes, uncomment the example mount lines in [docker-compose.yml](/Users/nicks/Projects/pyrocms/docker-compose.yml) and point each source path at the host directory you want to share.

Important:

- Mounting `config/` or `resources/streams/config/` replaces the image copy for those directories, so only do that when your host copy is complete.
- The development compose file already bind mounts the whole repository, so local addons, config, and app data can all be edited directly there.

## Security

If you discover any security related issues, please email admin@formable.app instead of using the issue tracker.
