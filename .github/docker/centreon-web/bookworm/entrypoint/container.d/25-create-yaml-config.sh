#!/bin/sh
# Create YAML configuration files for Gorgone compatibility
# This runs after installation (20-installation.sh)

set -e

: "${DB_HOST:=db}"
: "${DB_PORT:=3306}"
: "${CENTREONDB_PWD:=centreon}"

CONFIG_DIR="/etc/centreon/config.d"

echo "=== Creating YAML Configuration Files ==="
echo "Config directory: ${CONFIG_DIR}"
echo ""

# Create config directory if it doesn't exist
mkdir -p "${CONFIG_DIR}"

# Create database configuration YAML for Gorgone
cat > "${CONFIG_DIR}/10-database.yaml" <<EOF
---
# Database configuration for Centreon
# This file is used by Gorgone and other Centreon components
database:
  host: ${DB_HOST}
  port: ${DB_PORT}
  db_configuration: centreon
  db_storage: centreon_storage
  db_user: centreon
  db_password: ${CENTREONDB_PWD}
EOF

# Set proper permissions (readable by centreon group)
chown centreon:centreon "${CONFIG_DIR}/10-database.yaml" 2>/dev/null || true
chmod 644 "${CONFIG_DIR}/10-database.yaml"

echo "✓ Created ${CONFIG_DIR}/10-database.yaml"
echo ""

# Show file details for debugging
if [ "${DEBUG}" = "true" ] || [ "${DEBUG}" = "1" ]; then
  echo "Debug: Database config file details:"
  ls -l "${CONFIG_DIR}/10-database.yaml"
  echo ""
  echo "Content:"
  cat "${CONFIG_DIR}/10-database.yaml"
  echo ""
fi

echo "✓ YAML configuration files created successfully"
echo ""
