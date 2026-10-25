#!/bin/sh
# Read-only production database version check; never prints the password.
set -eu
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
CONF="$ROOT/.secrets/ionos-db.cnf"
if [ ! -f "$CONF" ]; then
  echo "Missing private credentials file: $CONF" >&2
  exit 1
fi
exec mariadb --defaults-extra-file="$CONF" --batch --skip-column-names --execute='SELECT VERSION(), DATABASE(), CURRENT_USER()'
