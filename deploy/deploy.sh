#!/bin/bash
set -e

# ============================================================
# Deploy engine_telegram_sos_expat to Hetzner server
# Usage: ./deploy/deploy.sh
# ============================================================

SERVER="root@89.167.26.169"
REMOTE_DIR="/opt/engine-telegram"
REPO_URL="https://github.com/will383842/engine_telegram_sos-expat.git"

echo "=== 1. Preparing server directory ==="
ssh $SERVER "mkdir -p $REMOTE_DIR"

echo "=== 2. Cloning/pulling latest code ==="
ssh $SERVER "
  if [ -d $REMOTE_DIR/.git ]; then
    cd $REMOTE_DIR && git pull origin main
  else
    git clone $REPO_URL $REMOTE_DIR
    cd $REMOTE_DIR
  fi
"

echo "=== 3. Copying production files ==="
scp .env.production $SERVER:$REMOTE_DIR/.env.production
scp storage/app/firebase-credentials.json $SERVER:$REMOTE_DIR/storage/app/firebase-credentials.json

echo "=== 4. Building and starting containers ==="
ssh $SERVER "
  cd $REMOTE_DIR

  # Build the app image
  docker compose build --no-cache

  # Start all services
  docker compose up -d

  # Wait for DB
  echo 'Waiting for PostgreSQL...'
  sleep 5

  # Run migrations
  docker compose exec -T app php artisan migrate --force

  # Seed templates
  docker compose exec -T app php artisan telegram:seed-templates

  # Cache config
  docker compose exec -T app php artisan config:cache
  docker compose exec -T app php artisan route:cache
"

echo "=== 5. Setting up Nginx reverse proxy ==="
ssh $SERVER "
  # Add telegram.life-expat.com server block to the backlink-engine Nginx
  # Our tg-nginx listens on 127.0.0.1:8080, we need the main Nginx to proxy to it

  # Check if a global nginx exists, otherwise we'll update the backlink nginx
  if ! command -v nginx &> /dev/null; then
    echo 'No global nginx, using Docker proxy on port 8080'
    echo 'You need to point telegram.life-expat.com DNS to this server'
    echo 'And either:'
    echo '  a) Change tg-nginx port from 127.0.0.1:8080 to 0.0.0.0:8080'
    echo '  b) Or add a server block to bl-nginx'
  fi
"

echo "=== 6. Setting up Telegram webhook ==="
ssh $SERVER "
  cd $REMOTE_DIR
  docker compose exec -T app php artisan telegram:setup-webhook
"

echo "=== 7. Health check ==="
ssh $SERVER "
  echo 'Container status:'
  docker ps --filter 'name=tg-' --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}'
  echo ''
  echo 'Testing health endpoint...'
  curl -s http://127.0.0.1:8080/up | head -5 || echo 'Health check pending...'
"

echo ""
echo "=== DEPLOYMENT COMPLETE ==="
echo "Next steps:"
echo "  1. Point telegram.life-expat.com DNS A record to 89.167.26.169"
echo "  2. Configure SSL (certbot or Cloudflare proxy)"
echo "  3. Expose port 8080 externally or add reverse proxy"
