#!/bin/bash
set -e

# ============================================================
# Setup a global Nginx reverse proxy on the Hetzner server
# Routes by domain:
#   backlinks.life-expat.com → bl-nginx (backlink-engine)
#   telegram.life-expat.com  → tg-nginx (engine-telegram)
# ============================================================

echo "=== 1. Installing Nginx + Certbot ==="
apt-get update
apt-get install -y nginx certbot python3-certbot-nginx

echo "=== 2. Stopping bl-nginx from binding port 80 ==="
# The backlink-engine nginx currently binds to port 80
# We need to change it to a local-only port
cd /root/backlink-engine

# Backup original docker-compose
cp docker-compose.yml docker-compose.yml.bak

# Change bl-nginx from port 80:80 to 127.0.0.1:3080:80
sed -i 's/"80:80"/"127.0.0.1:3080:80"/' docker-compose.yml

# Restart backlink-engine with new port
docker compose up -d nginx

echo "=== 3. Configuring global Nginx ==="

# Backlink engine
cat > /etc/nginx/sites-available/backlinks.life-expat.com <<'NGINX'
server {
    listen 80;
    server_name backlinks.life-expat.com;

    client_max_body_size 10M;

    location / {
        proxy_pass http://127.0.0.1:3080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 60s;
    }
}
NGINX

# Telegram engine
cat > /etc/nginx/sites-available/telegram.life-expat.com <<'NGINX'
server {
    listen 80;
    server_name telegram.life-expat.com;

    client_max_body_size 10M;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 60s;
    }
}
NGINX

# Enable sites
ln -sf /etc/nginx/sites-available/backlinks.life-expat.com /etc/nginx/sites-enabled/
ln -sf /etc/nginx/sites-available/telegram.life-expat.com /etc/nginx/sites-enabled/

# Remove default site
rm -f /etc/nginx/sites-enabled/default

echo "=== 4. Testing & starting Nginx ==="
nginx -t
systemctl enable nginx
systemctl restart nginx

echo "=== 5. SSL with Let's Encrypt ==="
certbot --nginx -d backlinks.life-expat.com -d telegram.life-expat.com --non-interactive --agree-tos --email admin@life-expat.com --redirect || echo "SSL setup failed - make sure DNS points to this server first"

echo ""
echo "=== DONE ==="
echo "Global Nginx is now routing:"
echo "  backlinks.life-expat.com → 127.0.0.1:3080 (bl-nginx)"
echo "  telegram.life-expat.com  → 127.0.0.1:8080 (tg-nginx)"
