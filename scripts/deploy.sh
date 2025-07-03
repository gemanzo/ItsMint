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
docker-compose -f compose.yml exec -T node npm install
docker-compose -f compose.yml exec -T node npm run build

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