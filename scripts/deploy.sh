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
# Try reproducible install first; on rollup optional deps failure, clean and reinstall then retry build
docker-compose -f compose.yml exec -T node sh -lc '
  set -e
  export CI=1
  # First attempt: reproducible install
  if ! npm ci --no-audit --no-fund; then
    echo "npm ci failed; cleaning lockfile and node_modules due to rollup optional deps bug"
    rm -rf node_modules package-lock.json
    npm i --no-audit --no-fund
  fi

  echo "Running frontend build (attempt 1)"
  if npm run build; then
    echo "Build succeeded on first attempt"
  else
    echo "Build failed, likely due to rollup native optional deps. Applying clean reinstall and retrying..."
    rm -rf node_modules package-lock.json
    npm cache clean --force >/dev/null 2>&1 || true
    npm i --no-audit --no-fund
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