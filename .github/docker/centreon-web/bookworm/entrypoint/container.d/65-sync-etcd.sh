#!/bin/sh
# Sync configuration files to etcd
# This runs after export (60-export.sh) and YAML config creation (25-create-yaml-config.sh)

set -e

USE_ETCD="${USE_ETCD:-false}"

# Exit early if etcd is not enabled
if [ "$USE_ETCD" != "true" ] && [ "$USE_ETCD" != "1" ]; then
    echo "=== etcd Sync Disabled ==="
    echo "etcd integration disabled (USE_ETCD=${USE_ETCD})"
    echo "Configuration files are available via shared volume only"
    echo ""
    exit 0
fi

ETCD_HOST="${ETCD_HOST:-etcd}"
ETCD_PORT="${ETCD_PORT:-2379}"
ETCD_KEY_PREFIX="${ETCD_KEY_PREFIX:-/centreon/config}"
CONFIG_DIR="${CONFIG_DIR:-/etc/centreon/config.d}"

echo "=== Syncing Centreon Configs to etcd ==="
echo "Config directory: ${CONFIG_DIR}"
echo "etcd: http://${ETCD_HOST}:${ETCD_PORT}"
echo "Key prefix: ${ETCD_KEY_PREFIX}"
echo ""

# Function to write a file to etcd
write_config_to_etcd() {
    local file_path="$1"
    local etcd_key="$2"

    if [ ! -f "$file_path" ]; then
        echo "⚠ WARNING: Config file not found: $file_path"
        return 1
    fi

    echo "Writing $file_path to etcd key: $etcd_key"

    # Read file content
    local content=$(cat "$file_path")

    # Base64 encode for etcd v3 API
    local key_b64=$(echo -n "$etcd_key" | base64 | tr -d '\n')
    local value_b64=$(echo -n "$content" | base64 | tr -d '\n')

    # Write to etcd using wget (available in the image)
    local response=$(wget -q -O - \
        --post-data="{\"key\":\"${key_b64}\",\"value\":\"${value_b64}\"}" \
        --header="Content-Type: application/json" \
        "http://${ETCD_HOST}:${ETCD_PORT}/v3/kv/put" 2>/dev/null || echo "")

    if [ -n "$response" ]; then
        echo "  ✓ Successfully written to etcd"
        return 0
    else
        echo "  ✗ Failed to write to etcd"
        return 1
    fi
}

# Check if config directory exists
if [ ! -d "$CONFIG_DIR" ]; then
    echo "⚠ WARNING: Config directory not found: $CONFIG_DIR"
    echo "Skipping etcd sync"
    exit 0
fi

# Sync all YAML configuration files
failed=0
success=0

# Find all .yaml and .yml files and sync them
find "$CONFIG_DIR" -type f \( -name "*.yaml" -o -name "*.yml" \) 2>/dev/null | while read -r config_file; do
    # Get relative path from config_dir
    rel_path=$(echo "$config_file" | sed "s|^${CONFIG_DIR}/||")
    etcd_key="${ETCD_KEY_PREFIX}/${rel_path}"

    if write_config_to_etcd "$config_file" "$etcd_key"; then
        success=$((success + 1))
    else
        failed=$((failed + 1))
    fi
done

# Show summary
echo ""
if [ $failed -gt 0 ]; then
    echo "⚠ Completed with errors: $success succeeded, $failed failed"
else
    echo "✓ All configurations synced successfully to etcd"
fi

echo ""
echo "Gorgone containers can now fetch these configurations from etcd"
echo ""
