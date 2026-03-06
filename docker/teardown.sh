#!/bin/bash
# teardown.sh — destroy a VoltexaHub forum instance completely
# Usage: ./teardown.sh <subdomain>
set -e

SUBDOMAIN="${1}"
BASE_DIR="/opt/voltexahub"
FORUMS_DIR="${BASE_DIR}/forums"
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-rootsecret}"
POOL_STATE="${BASE_DIR}/pool/state.json"

if [ -z "$SUBDOMAIN" ]; then
  echo "Usage: $0 <subdomain>"
  exit 1
fi

INSTANCE_ID="${SUBDOMAIN}"
INSTANCE_DIR="${FORUMS_DIR}/${INSTANCE_ID}"

echo "==> Tearing down forum: ${SUBDOMAIN}.voltexahub.com"

# Stop and remove container + volumes
if [ -d "${INSTANCE_DIR}" ]; then
  echo "==> Stopping container..."
  cd "${INSTANCE_DIR}" && docker compose down -v 2>/dev/null || true
  cd / && rm -rf "${INSTANCE_DIR}"
  echo "==> Container and volumes removed."
else
  echo "==> No instance directory found, skipping container teardown."
fi

# Drop MySQL database and user
echo "==> Dropping database..."
docker exec voltexahub-mysql mysql -u root -p"${MYSQL_ROOT_PASSWORD}" -e "
DROP DATABASE IF EXISTS \`forum_${INSTANCE_ID}\`;
DROP USER IF EXISTS 'forum_${INSTANCE_ID}'@'%';
FLUSH PRIVILEGES;
" 2>/dev/null && echo "==> Database dropped." || echo "==> Warning: could not drop database."

# Remove Traefik route
echo "==> Removing Traefik route..."
python3 - <<PYEOF
import yaml, os
path = "${BASE_DIR}/traefik/dynamic/forums.yml"
if not os.path.exists(path):
    print("No forums.yml found, skipping.")
    exit()
d = yaml.safe_load(open(path)) or {"http": {"routers": {}, "services": {}}}
d["http"]["routers"].pop("forum-${INSTANCE_ID}", None)
d["http"]["services"].pop("forum-${INSTANCE_ID}", None)
yaml.dump(d, open(path, "w"), default_flow_style=False)
print("Route removed.")
PYEOF

# Update pool state
echo "==> Updating pool state..."
python3 - <<PYEOF
import json, os
path = "${POOL_STATE}"
if not os.path.exists(path):
    print("No pool state found, skipping.")
    exit()
d = json.load(open(path))
for key in ("warm", "active"):
    if "${INSTANCE_ID}" in d.get(key, []):
        d[key].remove("${INSTANCE_ID}")
if "${INSTANCE_ID}" not in d.get("used", []):
    d.setdefault("used", []).append("${INSTANCE_ID}")
json.dump(d, open(path, "w"), indent=2)
print("Pool state updated.")
PYEOF

echo ""
echo "✅ ${SUBDOMAIN}.voltexahub.com has been destroyed."
