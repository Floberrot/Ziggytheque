# Security Report — Symfony 7 (Existing Dockerfile Audit)

## Findings

**[CRITICAL] COPY .env /app/.env — secrets baked into image**
- The original Dockerfile copies `.env` (which contains `APP_SECRET` and `DATABASE_URL`)
  directly into the Docker image. Anyone who can pull the image can extract these secrets.
- **Remediation**: Removed from Dockerfile. Set all secrets as Railway environment variables
  via the Railway dashboard. Add `.env` to `.gitignore` and `.dockerignore` immediately.
  **Rotate `APP_SECRET` and `DATABASE_URL` credentials** — they were already baked into
  a potentially pushed image.

**[CRITICAL] php:latest — unpinned base image**
- Using `php:latest` means your build is not reproducible. A future `latest` could be a
  major version bump (e.g. PHP 9) that breaks your app silently.
- **Remediation**: Pinned to `php:8.3-fpm-alpine3.20` in the updated Dockerfile.

**[WARNING] Running php-fpm as root**
- The original Dockerfile had no user directive, so php-fpm ran as root. A container
  escape would give an attacker root on the host.
- **Remediation**: Added `addgroup -S www && adduser -S www -G www` and `USER www`.

**[WARNING] apt-get without pinned versions / no cache cleanup**
- The original used `apt-get install -y nginx` on a Debian image without cleaning up
  the apt cache, bloating the image and making builds non-reproducible.
- **Remediation**: Switched to `php:8.3-fpm-alpine` with `apk add --no-cache` (no cleanup
  step needed).

**[INFO] No multi-stage build in original**
- Composer dev dependencies and source files were all in one layer.
- **Remediation**: Added Composer and Webpack Encore build stages; only production
  artifacts reach the runtime image.

## Summary
2 critical findings, 2 warnings, 1 informational note.
**Action required**: rotate all credentials from the original `.env` before deploying.
