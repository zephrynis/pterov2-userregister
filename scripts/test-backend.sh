#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
compose_file="${PTERODACTYL_COMPOSE_FILE:-/opt/pterodactyl/compose.yaml}"
test_path=$(docker compose -f "$compose_file" exec -T panel mktemp -d /tmp/userregister-test.XXXXXXXX)
trap 'docker compose -f "$compose_file" exec -T panel rm -rf -- "$test_path"' EXIT
tar --exclude=node_modules --exclude=vendor --exclude=dist --exclude=release -cf - . | docker compose -f "$compose_file" exec -T panel tar -xf - -C "$test_path"
docker compose -f "$compose_file" exec -T panel sh -c 'find "$1/src" "$1/routes" "$1/database" "$1/tests" -name "*.php" -exec php -l {} \;' sh "$test_path"
docker compose -f "$compose_file" exec -T -w /app panel php "$test_path/tests/backend.php"
