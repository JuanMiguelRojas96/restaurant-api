#!/bin/sh
# Runs every time the container starts, before Apache.
set -e

# Environment variables only exist at runtime (Render injects them), so caches are built here.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Bring the database schema up to date. --force: Laravel asks for confirmation in production.
php artisan migrate --force

# Demo data. Every seeder is idempotent: it never duplicates rows nor overwrites menu changes,
# and it restores the demo users' password, so the public demo always works.
php artisan db:seed --force

# OpenAPI spec for Swagger UI (it is a build artifact, not committed to git).
php artisan l5-swagger:generate

exec "$@"
