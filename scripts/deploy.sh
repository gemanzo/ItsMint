#!/bin/bash
set -e # Exit immediately if a command exits with a non-zero status.

echo "🚀 Deployment to production started..."

# 1. Enable maintenance mode
echo ">>> Enabling maintenance mode..."
docker-compose -f compose.yml exec -T php php artisan down || true

# 2. Install/update Composer dependencies
echo ">>> Installing Composer dependencies..."
docker-compose -f compose.yml exec -T php composer install --no-interaction --no-dev --prefer-dist --optimize-autoloader

# 3. Install/update NPM dependencies and build frontend assets
echo ">>> Building frontend assets..."
# Run build in a one-off container to avoid file locks and "resource busy" during installs
docker-compose -f compose.yml run --rm node sh -lc '
  set -e
  export CI=1
  # Reproducible install first
  if ! npm ci --no-audit --no-fund; then
    echo "npm ci failed; retrying with npm i (workaround for npm optional deps bug)"
    npm i --no-audit --no-fund
  fi

  # Attempt build, skipping native rollup to avoid platform optional deps
  echo "Running frontend build (attempt 1) with ROLLUP_SKIP_NATIVE=1"
  export ROLLUP_SKIP_NATIVE=1
  if npm run build; then
    echo "Build succeeded on first attempt"
  else
    echo "Build failed; installing rollup MUSL binary explicitly and retrying..."
    npm i --no-audit --no-fund --no-save @rollup/rollup-linux-x64-musl || true
    echo "Running frontend build (attempt 2)"
    npm run build
  fi
'

# 4. Run database migrations
echo ">>> Running database migrations..."
docker-compose -f compose.yml exec -T php php artisan migrate --force

# 5. Clear and optimize caches
echo ">>> Clearing and optimizing caches..."
docker-compose -f compose.yml exec -T php php artisan cache:clear
docker-compose -f compose.yml exec -T php php artisan config:clear
docker-compose -f compose.yml exec -T php php artisan optimize:clear
docker-compose -f compose.yml exec -T php php artisan optimize

# 6. Restart Horizon workers
echo ">>> Restarting Horizon..."
docker-compose -f compose.yml exec -T php php artisan horizon:terminate

# 7. Disable maintenance mode
echo ">>> Disabling maintenance mode..."
docker-compose -f compose.yml exec -T php php artisan up

echo "✅ Deployment to production finished successfully!" 