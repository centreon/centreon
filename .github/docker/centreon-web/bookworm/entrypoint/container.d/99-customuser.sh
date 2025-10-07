# #!/bin/sh

# : "${PUID:=1000}"
# : "${PGID:=1000}"
# APP_USER="www-data"
# APP_HOME="/var/lib/centreon"

# # ensure group with PGID exists (create if not)
# if ! getent group "$PGID" >/dev/null 2>&1; then
#   groupadd -g "$PGID" "$APP_USER" || groupadd -g "$PGID" "g$PGID"
# fi

# # ensure user with PUID exists (create if not)
# if ! getent passwd "$PUID" >/dev/null 2>&1; then
#   useradd -u "$PUID" -g "$PGID" -d "$APP_HOME" -m -s /sbin/nologin "$APP_USER" || \
#     useradd -u "$PUID" -g "$PGID" -M -s /sbin/nologin "u$PUID"
# fi

# # fix ownership for runtime-mounted volumes
# chown -R "$PUID:$PGID" /var/lib/centreon /etc/centreon /tmp/gorgone || true

# # finally exec the requested command as the user
# exec gosu "$PUID:$PGID" "$@"

# gosu www-data "$@"
