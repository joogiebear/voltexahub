#!/bin/bash
# ============================================================================
# VoltexaHub Production VPS Installer
# Supports: Ubuntu 20.04/22.04/24.04, Debian 11/12
# Run: chmod +x install.sh && sudo ./install.sh
# ============================================================================
# Errors handled explicitly per section

# ---------------------------------------------------------------------------
# Colors & helpers
# ---------------------------------------------------------------------------
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

info()    { echo -e "${CYAN}→${NC} $1"; }
success() { echo -e "${GREEN}✓${NC} $1"; }
warn()    { echo -e "${YELLOW}⚠${NC} $1"; }
error()   { echo -e "${RED}✗${NC} $1"; exit 1; }
header()  { echo -e "\n${BOLD}${BLUE}=== $1 ===${NC}\n"; }

# ---------------------------------------------------------------------------
# Section 1: Banner + OS check
# ---------------------------------------------------------------------------
echo -e "${BOLD}${CYAN}"
cat << 'BANNER'
 __     __    _ _                  _   _       _
 \ \   / /__ | | |_ _____  ____ _| | | |_   _| |__
  \ \ / / _ \| | __/ _ \ \/ / _` | |_| | | | | '_ \
   \ V / (_) | | ||  __/>  < (_| |  _  | |_| | |_) |
    \_/ \___/|_|\__\___/_/\_\__,_|_| |_|\__,_|_.__/

BANNER
echo -e "${NC}"
echo -e "${BOLD}  Production VPS Installer${NC}"
echo -e "  ────────────────────────────────────────"
echo ''

# Parse flags
FORCE=false
for arg in "$@"; do
  case "$arg" in
    --force) FORCE=true ;;
  esac
done

# OS detection
if [ -f /etc/os-release ]; then
  . /etc/os-release
  OS_ID="$ID"
  OS_VERSION="$VERSION_ID"
  OS_NAME="$PRETTY_NAME"
else
  OS_ID="unknown"
  OS_VERSION="unknown"
  OS_NAME="Unknown OS"
fi

SUPPORTED=false
case "$OS_ID" in
  ubuntu)
    case "$OS_VERSION" in
      20.04|22.04|24.04) SUPPORTED=true ;;
    esac
    ;;
  debian)
    case "$OS_VERSION" in
      11|12|13) SUPPORTED=true ;;
    esac
    ;;
esac

if [ "$SUPPORTED" = false ]; then
  warn "Detected OS: $OS_NAME"
  warn "Supported: Ubuntu 20.04/22.04/24.04, Debian 11/12/13"
  if [ "$FORCE" = false ]; then
    error "Unsupported OS. Use --force to continue anyway."
  else
    warn "Continuing with --force flag..."
  fi
else
  success "Detected OS: $OS_NAME"
fi

# Root check
if [ "$(id -u)" -ne 0 ]; then
  error "This installer must be run as root. Try: sudo $0"
fi

# ---------------------------------------------------------------------------
# Section 2: Collect all config upfront
# ---------------------------------------------------------------------------
header 'Configuration'

# Domain / URL
read -p 'Domain name (e.g. forum.example.com) or press Enter for IP-only: ' DOMAIN
if [ -z "$DOMAIN" ]; then
  USE_SSL=false
  SERVER_IP=$(curl -s --max-time 5 ifconfig.me 2>/dev/null || echo 'localhost')
  SITE_URL="http://$SERVER_IP"
  info "No domain set — using IP: $SITE_URL"
else
  USE_SSL=true
  read -p 'SSL email address (for Let'\''s Encrypt): ' SSL_EMAIL
  SITE_URL="https://$DOMAIN"
fi

# Forum config
read -p 'Forum name [My Forum]: ' FORUM_NAME
FORUM_NAME=${FORUM_NAME:-'My Forum'}

# Admin account
echo ''
info 'Admin account setup'
read -p 'Admin username: ' ADMIN_USERNAME
while [ -z "$ADMIN_USERNAME" ]; do
  warn 'Username cannot be empty'
  read -p 'Admin username: ' ADMIN_USERNAME
done

read -p 'Admin email: ' ADMIN_EMAIL
while [ -z "$ADMIN_EMAIL" ]; do
  warn 'Email cannot be empty'
  read -p 'Admin email: ' ADMIN_EMAIL
done

read -s -p 'Admin password: ' ADMIN_PASSWORD; echo
while [ -z "$ADMIN_PASSWORD" ]; do
  warn 'Password cannot be empty'
  read -s -p 'Admin password: ' ADMIN_PASSWORD; echo
done

read -s -p 'Confirm password: ' ADMIN_PASSWORD_CONFIRM; echo
[ "$ADMIN_PASSWORD" != "$ADMIN_PASSWORD_CONFIRM" ] && error 'Passwords do not match'

# Database
echo ''
echo 'Database type:'
echo '  1) MySQL/MariaDB (recommended for production)'
echo '  2) SQLite (simple, good for small sites)'
read -p 'Choice [1]: ' DB_CHOICE
DB_CHOICE=${DB_CHOICE:-1}

if [ "$DB_CHOICE" = '1' ]; then
  DB_TYPE='mysql'
  read -p 'MySQL database name [voltexahub]: ' DB_NAME
  DB_NAME=${DB_NAME:-voltexahub}
  read -p 'MySQL username [voltexahub]: ' DB_USER
  DB_USER=${DB_USER:-voltexahub}
  DB_PASS=''
  while [ -z "$DB_PASS" ]; do
    read -s -p 'MySQL password: ' DB_PASS; echo
    [ -z "$DB_PASS" ] && warn 'MySQL password cannot be empty'
  done
  DB_HOST='127.0.0.1'
  DB_PORT='3306'
else
  DB_TYPE='sqlite'
fi

# Install path
read -p 'Install directory [/var/www/voltexahub]: ' INSTALL_DIR
INSTALL_DIR=${INSTALL_DIR:-/var/www/voltexahub}

# Confirmation summary
echo ''
echo -e "${BOLD}Summary:${NC}"
echo "  Forum name:   $FORUM_NAME"
echo "  URL:          $SITE_URL"
echo "  Admin:        $ADMIN_USERNAME ($ADMIN_EMAIL)"
echo "  Database:     $DB_TYPE"
echo "  Install path: $INSTALL_DIR"
if [ "$USE_SSL" = 'true' ]; then
  echo "  SSL:          Yes ($SSL_EMAIL)"
fi
echo ''
read -p 'Proceed with installation? [Y/n]: ' CONFIRM
[ "${CONFIRM,,}" = 'n' ] && { echo 'Installation cancelled.'; exit 0; }

# ---------------------------------------------------------------------------
# Section 3: Install system dependencies
# ---------------------------------------------------------------------------
header 'Installing System Dependencies'

export DEBIAN_FRONTEND=noninteractive
apt-get update -qq

# Ensure essential tools are present
apt-get install -y -qq curl git rsync unzip >/dev/null

# Determine PHP FPM service name for later
PHP_FPM_VER=""

# PHP 8.2+
if ! command -v php &>/dev/null; then
  info 'Installing PHP 8.2...'

  if [ "$OS_ID" = "ubuntu" ]; then
    apt-get install -y -qq software-properties-common >/dev/null
    add-apt-repository -y ppa:ondrej/php >/dev/null 2>&1
  else
    # Debian: use sury.org repo (no software-properties-common needed)
    apt-get install -y -qq apt-transport-https lsb-release ca-certificates curl gnupg >/dev/null
    curl -sSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb
    dpkg -i /tmp/debsuryorg-archive-keyring.deb >/dev/null 2>&1
    echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" \
      > /etc/apt/sources.list.d/sury-php.list
  fi

  apt-get update -qq
  apt-get install -y -qq php8.2 php8.2-cli php8.2-fpm php8.2-mysql php8.2-sqlite3 \
    php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath php8.2-gd >/dev/null
  PHP_FPM_VER="8.2"
  success 'PHP 8.2 installed'
else
  PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
  PHP_FPM_VER="$PHP_VER"
  success "PHP $PHP_VER already installed"

  # Ensure required extensions are present
  MISSING_EXTS=""
  for ext in mbstring xml curl zip bcmath gd; do
    if ! php -m 2>/dev/null | grep -qi "$ext"; then
      MISSING_EXTS="$MISSING_EXTS php${PHP_VER}-${ext}"
    fi
  done

  if [ "$DB_TYPE" = "mysql" ] && ! php -m 2>/dev/null | grep -qi "mysqlnd\|mysqli\|pdo_mysql"; then
    MISSING_EXTS="$MISSING_EXTS php${PHP_VER}-mysql"
  fi

  if [ "$DB_TYPE" = "sqlite" ] && ! php -m 2>/dev/null | grep -qi "sqlite"; then
    MISSING_EXTS="$MISSING_EXTS php${PHP_VER}-sqlite3"
  fi

  if [ -n "$MISSING_EXTS" ]; then
    info "Installing missing PHP extensions:$MISSING_EXTS"
    apt-get install -y -qq $MISSING_EXTS >/dev/null 2>&1 || warn "Some extensions may need manual install"
  fi
fi

# Ensure PHP-FPM is installed
if ! dpkg -l "php${PHP_FPM_VER}-fpm" &>/dev/null 2>&1; then
  info "Installing PHP ${PHP_FPM_VER} FPM..."
  apt-get install -y -qq "php${PHP_FPM_VER}-fpm" >/dev/null
fi
systemctl enable "php${PHP_FPM_VER}-fpm" >/dev/null 2>&1
systemctl start "php${PHP_FPM_VER}-fpm"
success "PHP-FPM ${PHP_FPM_VER} running"

# Composer
if ! command -v composer &>/dev/null; then
  info 'Installing Composer...'
  curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer --quiet
  success 'Composer installed'
else
  success 'Composer already installed'
fi

# Node.js 20
if ! command -v node &>/dev/null; then
  info 'Installing Node.js 20...'
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash - >/dev/null 2>&1
  apt-get install -y -qq nodejs >/dev/null
  success 'Node.js installed'
else
  NODE_VER=$(node --version)
  success "Node.js $NODE_VER already installed"
fi

# MySQL (if chosen)
if [ "$DB_TYPE" = 'mysql' ]; then
  if ! command -v mysql &>/dev/null; then
    info 'Installing MySQL...'
    DEBIAN_FRONTEND=noninteractive apt-get install -y -qq mysql-server >/dev/null
    systemctl start mysql
    systemctl enable mysql >/dev/null 2>&1
    success 'MySQL installed'
  else
    success 'MySQL already installed'
  fi
fi

# Nginx
if ! command -v nginx &>/dev/null; then
  info 'Installing Nginx...'
  apt-get install -y -qq nginx >/dev/null
  systemctl enable nginx >/dev/null 2>&1
  success 'Nginx installed'
else
  success 'Nginx already installed'
fi

# Certbot (if SSL)
if [ "$USE_SSL" = 'true' ]; then
  if ! command -v certbot &>/dev/null; then
    info 'Installing Certbot...'
    apt-get install -y -qq certbot python3-certbot-nginx >/dev/null
    success 'Certbot installed'
  else
    success 'Certbot already installed'
  fi
fi

# Soketi
if ! command -v soketi &>/dev/null; then
  info 'Installing Soketi...'
  npm install -g @soketi/soketi --silent 2>/dev/null
  success 'Soketi installed'
else
  success 'Soketi already installed'
fi

# ---------------------------------------------------------------------------
# Section 4: Set up MySQL database
# ---------------------------------------------------------------------------
if [ "$DB_TYPE" = 'mysql' ]; then
  header 'Setting Up Database'
  info 'Creating MySQL database and user...'
  mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
  success "Database '$DB_NAME' created"
fi

# ---------------------------------------------------------------------------
# Section 5: Clone / copy app files
# ---------------------------------------------------------------------------
header 'Setting Up Application'

mkdir -p "$INSTALL_DIR"

# Detect source directory (installer must be run from within the repo)
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
if [ -f "$SCRIPT_DIR/artisan" ]; then
  info 'Copying application files...'
  rsync -a --exclude='.git' --exclude='node_modules' --exclude='vendor' \
    "$SCRIPT_DIR/" "$INSTALL_DIR/"
  success 'Application files copied'
else
  error 'Could not find artisan. Run install.sh from the voltexahub project directory.'
fi

cd "$INSTALL_DIR"

# Set ownership and permissions
chown -R www-data:www-data "$INSTALL_DIR"
chmod -R 755 "$INSTALL_DIR"
chmod -R 775 "$INSTALL_DIR/storage" "$INSTALL_DIR/bootstrap/cache"
success 'Permissions set'

# ---------------------------------------------------------------------------
# Section 6: Configure .env
# ---------------------------------------------------------------------------
header 'Configuring Environment'

cp .env.example .env

# Core app config
sed -i "s|APP_NAME=.*|APP_NAME=\"$FORUM_NAME\"|" .env
sed -i "s|APP_URL=.*|APP_URL=$SITE_URL|" .env
sed -i "s|APP_ENV=.*|APP_ENV=production|" .env
sed -i "s|APP_DEBUG=.*|APP_DEBUG=false|" .env

# Database config
if [ "$DB_TYPE" = 'mysql' ]; then
  sed -i "s|DB_CONNECTION=.*|DB_CONNECTION=mysql|" .env
  # Uncomment and set MySQL settings
  sed -i "s|.*DB_HOST=.*|DB_HOST=127.0.0.1|" .env
  sed -i "s|.*DB_PORT=.*|DB_PORT=3306|" .env
  sed -i "s|.*DB_DATABASE=.*|DB_DATABASE=$DB_NAME|" .env
  sed -i "s|.*DB_USERNAME=.*|DB_USERNAME=$DB_USER|" .env
  sed -i "s|.*DB_PASSWORD=.*|DB_PASSWORD=$DB_PASS|" .env
else
  sed -i "s|DB_CONNECTION=.*|DB_CONNECTION=sqlite|" .env
  touch "$INSTALL_DIR/database/database.sqlite"
  chown www-data:www-data "$INSTALL_DIR/database/database.sqlite"
fi

# Queue should use database driver in production
sed -i "s|QUEUE_CONNECTION=.*|QUEUE_CONNECTION=database|" .env

# Soketi / broadcasting config
cat >> .env << 'ENVEOF'

BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=voltexahub
PUSHER_APP_KEY=voltexahub-key
PUSHER_APP_SECRET=voltexahub-secret
PUSHER_HOST=127.0.0.1
PUSHER_PORT=6001
PUSHER_SCHEME=http
PUSHER_APP_CLUSTER=mt1
ENVEOF

success '.env configured'

# ---------------------------------------------------------------------------
# Section 7: Install PHP + JS deps, run migrations
# ---------------------------------------------------------------------------
header 'Installing Dependencies'

info 'Installing PHP dependencies...'
HOME=/root COMPOSER_HOME=/root/.composer composer install --no-dev --optimize-autoloader --working-dir="$INSTALL_DIR" --no-interaction --quiet 2>&1 || { echo 'Composer install failed. Check logs.'; exit 1; }
success 'PHP dependencies installed'

info 'Generating app key...'
php artisan key:generate --force || { echo 'key:generate failed'; exit 1; }
success 'App key generated'

info 'Running migrations...'
php artisan migrate --force || { echo 'Migrations failed — check DB credentials and .env'; exit 1; }
success 'Database migrations complete'

info 'Running default seeders...'
php artisan db:seed --class=DefaultContentSeeder --force 2>/dev/null && success 'DefaultContentSeeder done' || warn 'DefaultContentSeeder skipped'
php artisan db:seed --class=RoleSeeder --force 2>/dev/null && success 'RoleSeeder done' || warn 'RoleSeeder skipped'

info 'Linking storage...'
php artisan storage:link --force 2>/dev/null || php artisan storage:link 2>/dev/null || true
success 'Storage linked'

# Clone and build frontend
info 'Setting up frontend...'
FRONTEND_DIR="$(dirname "$INSTALL_DIR")/voltexaforum"
if [ ! -d "$FRONTEND_DIR" ]; then
  info 'Cloning voltexaforum...'
  git clone --depth=1 https://github.com/joogiebear/voltexaforum.git "$FRONTEND_DIR" >/dev/null 2>&1
fi

if [ -d "$FRONTEND_DIR" ]; then
  info 'Building frontend...'
  cd "$FRONTEND_DIR"
  npm install 2>&1 | tail -3
  # Write Vite env — use relative /api since Nginx proxies it
  cat > .env.production << VITEENV
VITE_API_URL=/api
VITE_PUSHER_APP_KEY=voltexahub-key
VITE_PUSHER_HOST=$DOMAIN
VITE_PUSHER_PORT=6001
VITE_PUSHER_SCHEME=https
VITEENV
  npm run build 2>&1 | tail -5
  if [ -d dist ]; then
    success 'Frontend built'
  else
    warn 'Frontend build may have failed — check output above'
  fi
  cd "$INSTALL_DIR"
else
  warn "Could not clone frontend — Nginx will fallback to Laravel public dir"
  warn "Run manually: git clone https://github.com/joogiebear/voltexaforum.git $FRONTEND_DIR && cd $FRONTEND_DIR && npm install && npm run build"
fi

# ---------------------------------------------------------------------------
# Section 8: Create admin account
# ---------------------------------------------------------------------------
header 'Creating Admin Account'

# Escape single quotes in password for PHP
ESCAPED_ADMIN_PASSWORD=$(echo "$ADMIN_PASSWORD" | sed "s/'/\\\\'/g")

php artisan tinker --execute="
use App\\Models\\User;
use Illuminate\\Support\\Facades\\Hash;
\$user = User::where('email', '$ADMIN_EMAIL')->first();
if (!\$user) {
    \$user = User::create([
        'username' => '$ADMIN_USERNAME',
        'name' => '$ADMIN_USERNAME',
        'email' => '$ADMIN_EMAIL',
        'password' => Hash::make('$ESCAPED_ADMIN_PASSWORD'),
        'email_verified_at' => now(),
    ]);
}
\$user->assignRole('admin');
echo 'Admin created: ' . \$user->username;
"

success 'Admin account created'

# Save forum config
php artisan tinker --execute="
App\\Models\\ForumConfig::set('forum_name', '$FORUM_NAME');
App\\Models\\ForumConfig::set('forum_url', '$SITE_URL');
"
success 'Forum config saved'

# Fix ownership after running artisan as root
chown -R www-data:www-data "$INSTALL_DIR/storage" "$INSTALL_DIR/bootstrap/cache"

# ---------------------------------------------------------------------------
# Section 9: Nginx config
# ---------------------------------------------------------------------------
header 'Configuring Nginx'

NGINX_CONF="/etc/nginx/sites-available/voltexahub"

# Determine frontend dist path
FRONTEND_DIST="$(dirname "$INSTALL_DIR")/voltexaforum/dist"
if [ ! -d "$FRONTEND_DIST" ]; then
  FRONTEND_DIST="$INSTALL_DIR/public"
  info "Frontend dist not found, using Laravel public dir as fallback"
fi

# Determine PHP-FPM socket path
PHP_FPM_SOCK="/run/php/php${PHP_FPM_VER}-fpm.sock"

cat > "$NGINX_CONF" << NGINXEOF
server {
    listen 80;
    server_name ${DOMAIN:-_};

    # Frontend (Vue SPA)
    root $FRONTEND_DIST;
    index index.html;

    # API proxy to Laravel via PHP-FPM
    location /api/ {
        proxy_pass http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }

    # Stripe webhook
    location /stripe/ {
        proxy_pass http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }

    # Broadcasting auth
    location /broadcasting/ {
        proxy_pass http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
    }

    # Soketi WebSocket
    location /app/ {
        proxy_pass http://127.0.0.1:6001;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
    }

    # SPA fallback — serve index.html for all non-file routes
    location / {
        try_files \$uri \$uri/ /index.html;
    }

    # Laravel storage (uploaded files)
    location /storage/ {
        alias $INSTALL_DIR/storage/app/public/;
    }

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    client_max_body_size 10M;

    # Gzip
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml text/javascript;
}
NGINXEOF

ln -sf "$NGINX_CONF" /etc/nginx/sites-enabled/voltexahub
rm -f /etc/nginx/sites-enabled/default

nginx -t 2>/dev/null && systemctl reload nginx
success 'Nginx configured'

# ---------------------------------------------------------------------------
# Section 10: SSL with Certbot
# ---------------------------------------------------------------------------
if [ "$USE_SSL" = 'true' ]; then
  header 'Setting Up SSL'
  info 'Requesting SSL certificate...'
  certbot --nginx -d "$DOMAIN" --email "$SSL_EMAIL" --agree-tos --non-interactive --redirect
  success 'SSL certificate installed'

  # Update .env with https URL
  sed -i "s|APP_URL=.*|APP_URL=https://$DOMAIN|" "$INSTALL_DIR/.env"
  php artisan config:clear 2>/dev/null || true
  success 'App URL updated to HTTPS'
fi

# ---------------------------------------------------------------------------
# Section 11: Systemd services
# ---------------------------------------------------------------------------
header 'Setting Up Services'

# Laravel app server (artisan serve — bound to localhost, proxied by nginx)
cat > /etc/systemd/system/voltexahub-app.service << SVCEOF
[Unit]
Description=VoltexaHub Laravel App Server
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=$INSTALL_DIR
ExecStart=/usr/bin/php $INSTALL_DIR/artisan serve --host=127.0.0.1 --port=8000
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
SVCEOF

# Queue worker
cat > /etc/systemd/system/voltexahub-queue.service << SVCEOF
[Unit]
Description=VoltexaHub Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=$INSTALL_DIR
ExecStart=/usr/bin/php $INSTALL_DIR/artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
SVCEOF

# Soketi config file
cat > "$INSTALL_DIR/soketi.json" << SOKETIEOF
{
  "debug": false,
  "port": 6001,
  "appManager.driver": "array",
  "appManager.array.apps": [
    {
      "id": "voltexahub",
      "key": "voltexahub-key",
      "secret": "voltexahub-secret",
      "enableClientMessages": true
    }
  ]
}
SOKETIEOF
chown www-data:www-data "$INSTALL_DIR/soketi.json"

# Determine soketi binary path
SOKETI_BIN=$(command -v soketi 2>/dev/null || echo "/usr/bin/soketi")

# Soketi WebSocket service
cat > /etc/systemd/system/voltexahub-soketi.service << SVCEOF
[Unit]
Description=VoltexaHub Soketi WebSocket Server
After=network.target

[Service]
User=www-data
Group=www-data
ExecStart=$SOKETI_BIN start --config=$INSTALL_DIR/soketi.json
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
SVCEOF

systemctl daemon-reload
systemctl enable voltexahub-app voltexahub-queue voltexahub-soketi >/dev/null 2>&1
systemctl start voltexahub-app
systemctl start voltexahub-queue
systemctl start voltexahub-soketi
success 'All services started'

# ---------------------------------------------------------------------------
# Section 12: Cron for Laravel scheduler
# ---------------------------------------------------------------------------
info 'Setting up Laravel scheduler cron...'
CRON_LINE="* * * * * cd $INSTALL_DIR && php artisan schedule:run >> /dev/null 2>&1"
(crontab -u www-data -l 2>/dev/null | grep -v 'artisan schedule:run'; echo "$CRON_LINE") | crontab -u www-data -
success 'Cron job installed'

# ---------------------------------------------------------------------------
# Section 13: Final message
# ---------------------------------------------------------------------------
header 'Installation Complete!'

echo -e "${GREEN}${BOLD}VoltexaHub has been installed successfully!${NC}"
echo ''
echo -e "  ${BOLD}URL:${NC}        $SITE_URL"
echo -e "  ${BOLD}Admin:${NC}      $ADMIN_EMAIL"
echo -e "  ${BOLD}Admin CP:${NC}   $SITE_URL/admin"
echo ''
echo -e "  ${BOLD}Services:${NC}"
echo '    voltexahub-app      Laravel API server'
echo '    voltexahub-queue    Email/notification worker'
echo '    voltexahub-soketi   Real-time WebSocket server'
echo ''
echo -e "  ${BOLD}Manage:${NC}"
echo '    sudo systemctl status voltexahub-app'
echo '    sudo systemctl restart voltexahub-queue'
echo '    sudo journalctl -u voltexahub-app -f'
echo ''
echo -e "  ${BOLD}Logs:${NC}"
echo "    tail -f $INSTALL_DIR/storage/logs/laravel.log"
echo ''
if [ "$USE_SSL" = 'true' ]; then
  echo -e "  ${GREEN}SSL certificate installed for $DOMAIN${NC}"
  echo '  Certificates auto-renew via certbot timer.'
  echo ''
fi
echo -e "  ${CYAN}Thank you for using VoltexaHub!${NC}"
echo ''
