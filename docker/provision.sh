#!/bin/bash
# provision.sh — spin up a new VoltexaHub forum instance
# Usage: ./provision.sh <subdomain> [admin_email]
set -e

SUBDOMAIN="${1}"
ADMIN_EMAIL="${2:-}"
BASE_DIR="/opt/voltexahub"
FORUMS_DIR="${BASE_DIR}/forums"
IMAGE="voltexahub:latest"
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-rootsecret}"
MYSQL_HOST="${MYSQL_HOST:-127.0.0.1}"

if [ -z "$SUBDOMAIN" ]; then
  echo "Usage: $0 <subdomain> [admin_email]"
  exit 1
fi

INSTANCE_ID="${SUBDOMAIN}"
INSTANCE_DIR="${FORUMS_DIR}/${INSTANCE_ID}"
ADMIN_PASSWORD="$(openssl rand -base64 12 | tr -d '/+=' | head -c 16)"

if [ -d "$INSTANCE_DIR" ]; then
  echo "Instance '${INSTANCE_ID}' already exists."
  exit 1
fi

echo "==> Provisioning forum: ${SUBDOMAIN}.voltexahub.com"

# Generate credentials
APP_KEY="base64:$(openssl rand -base64 32)"
DB_PASSWORD="$(openssl rand -hex 16)"
PUSHER_KEY="$(openssl rand -hex 16)"
PUSHER_SECRET="$(openssl rand -hex 16)"

# Create MySQL database and user (via docker exec)
echo "==> Creating database..."
docker exec voltexahub-mysql mysql -u root -p"${MYSQL_ROOT_PASSWORD}" -e "
CREATE DATABASE IF NOT EXISTS \`forum_${INSTANCE_ID}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'forum_${INSTANCE_ID}'@'%' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`forum_${INSTANCE_ID}\`.* TO 'forum_${INSTANCE_ID}'@'%';
FLUSH PRIVILEGES;
"

# Create instance directory
mkdir -p "${INSTANCE_DIR}"

# Write docker-compose from template
sed \
  -e "s|INSTANCE_ID|${INSTANCE_ID}|g" \
  -e "s|INSTANCE_SUBDOMAIN|${SUBDOMAIN}|g" \
  -e "s|INSTANCE_APP_KEY|${APP_KEY}|g" \
  -e "s|INSTANCE_DB_PASSWORD|${DB_PASSWORD}|g" \
  -e "s|INSTANCE_PUSHER_KEY|${PUSHER_KEY}|g" \
  -e "s|INSTANCE_PUSHER_SECRET|${PUSHER_SECRET}|g" \
  -e "s|MYSQL_HOST|${MYSQL_HOST}|g" \
  "${BASE_DIR}/app/voltexahub/docker/instance.yml" > "${INSTANCE_DIR}/docker-compose.yml"

# Start instance
echo "==> Starting container..."
cd "${INSTANCE_DIR}"
docker compose up -d

# Wait briefly for container to get an IP
sleep 3
CONTAINER_IP=$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' "forum_${INSTANCE_ID}" 2>/dev/null)

# Register route in Traefik dynamic config
if [ -n "$CONTAINER_IP" ]; then
  echo "==> Registering Traefik route (${CONTAINER_IP})..."
  python3 - <<PYEOF
import yaml, os

path = "/opt/voltexahub/traefik/dynamic/forums.yml"
d = yaml.safe_load(open(path)) if os.path.exists(path) else {"http": {"routers": {}, "services": {}}}
d["http"]["routers"]["forum-${INSTANCE_ID}"] = {
    "rule": "Host(\`${SUBDOMAIN}.voltexahub.com\`)",
    "entryPoints": ["websecure"],
    "tls": {"certResolver": "letsencrypt"},
    "service": "forum-${INSTANCE_ID}"
}
d["http"]["services"]["forum-${INSTANCE_ID}"] = {
    "loadBalancer": {"servers": [{"url": "http://${CONTAINER_IP}:80"}]}
}
yaml.dump(d, open(path, "w"), default_flow_style=False)
print("Route registered.")
PYEOF
fi

echo "==> Waiting for instance to initialize..."
sleep 10

# Create default admin user
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@${SUBDOMAIN}.voltexahub.com}"
echo "==> Creating admin user..."
docker exec "forum_${INSTANCE_ID}" php artisan tinker --execute="
  \$role = Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
  \$user = App\Models\User::create([
    'name' => 'Admin',
    'username' => 'admin',
    'email' => '${ADMIN_EMAIL}',
    'password' => bcrypt('${ADMIN_PASSWORD}'),
    'email_verified_at' => now(),
  ]);
  \$user->assignRole(\$role);
  echo 'Admin created.';
" 2>/dev/null || true

echo ""
echo "✅ Forum provisioned!"
echo "   URL:      https://${SUBDOMAIN}.voltexahub.com"
echo "   Username: admin"
echo "   Email:    ${ADMIN_EMAIL}"
echo "   Password: ${ADMIN_PASSWORD}"
echo "   Container: forum_${INSTANCE_ID}"
