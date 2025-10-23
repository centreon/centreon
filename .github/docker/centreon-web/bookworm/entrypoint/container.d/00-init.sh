#!/bin/sh

rm -f /tmp/docker.ready

# Set default PUID/PGID if not provided
PUID=${PUID:-900}
PGID=${PGID:-900}

# Function to safely change ownership
safe_chown() {
    if [ "$(id -u)" = 0 ]; then
        chown "$@"
    else
        echo "Not running as root, skipping chown for: $*"
    fi
}

# Function to safely change permissions
safe_chmod() {
    chmod "$@" 2>/dev/null || echo "Cannot change permissions for: $*"
}

# If running as root, adjust user/group IDs if needed
if [ "$(id -u)" = 0 ]; then
    CURRENT_CENTREON_UID=$(id -u centreon 2>/dev/null || echo "900")
    CURRENT_CENTREON_GID=$(getent group centreon | cut -d: -f3 2>/dev/null || echo "900")

    # Update centreon user/group if PUID/PGID differ from current
    if [ "$CURRENT_CENTREON_GID" != "$PGID" ]; then
        echo "Updating centreon group GID from $CURRENT_CENTREON_GID to $PGID"
        groupmod -g "$PGID" centreon
    fi

    if [ "$CURRENT_CENTREON_UID" != "$PUID" ]; then
        echo "Updating centreon user UID from $CURRENT_CENTREON_UID to $PUID"
        usermod -u "$PUID" centreon
    fi

    # Ensure centreon user is in necessary groups
    usermod -aG www-data centreon 2>/dev/null || true
fi

# Create necessary directories
mkdir -p /etc/centreon/config.d /var/cache/centreon/ /var/lib/centreon/centcore

# Set ownership based on whether we're running as root
if [ "$(id -u)" = 0 ]; then
    # Running as root - set proper ownership with potentially updated UID/GID
    safe_chown -R centreon:centreon /etc/centreon /var/lib/centreon/centcore
    safe_chown -R www-data:www-data /var/cache/centreon/
    safe_chmod 775 /etc/centreon/ /etc/centreon/config.d/ /var/lib/centreon/centcore
else
    # Running as non-root user - skip chown, just set permissions where possible
    echo "Running as non-root user (UID: $(id -u), GID: $(id -g))"
    safe_chmod 775 /etc/centreon/ /etc/centreon/config.d/ /var/lib/centreon/centcore
fi

# Verify that app data exists (should be populated by init container)
if [ ! -d "/var/www/html/centreon/www" ]; then
    echo "ERROR: Application files not found in volume!"
    echo "The centreon-sync init container should have populated /var/www/html/centreon/"
    echo "This suggests the init container did not run or failed."
    exit 1
fi

# Quick verification that version matches image
IMAGE_VERSION=$(dpkg -l | grep centreon-web | awk '{print $3}' | head -1 || echo "unknown")
if [ -f "/var/www/html/centreon/.image_version" ]; then
    VOLUME_VERSION=$(cat /var/www/html/centreon/.image_version)
    if [ "$IMAGE_VERSION" != "$VOLUME_VERSION" ]; then
        echo "WARNING: Volume version ($VOLUME_VERSION) doesn't match image ($IMAGE_VERSION)"
        echo "The centreon-sync init container should have updated the volume!"
    else
        echo "Application files verified (version: $IMAGE_VERSION)"
    fi
else
    echo "WARNING: No version marker found in volume"
fi
