#!/bin/sh
# Prepares the engine/broker config volumes (run as root, once per start).
#   central: shared with fpm (www-data, gid 33) for the bootstrap local copy.
#   poller:  engine-only.
# Leaves them empty otherwise, so the engine waits for its first delivery.
set -eu
role="${1:?central|poller}"

if [ "$role" = central ]; then
  group=33
else
  group=901
fi
chown 901:"$group" /mnt/engine-etc
chown 902:"$group" /mnt/broker-etc
chmod 2775 /mnt/engine-etc /mnt/broker-etc

# Central: fpm (12-install.sh) writes engine-context.json itself.
if [ "$role" = poller ] && [ ! -s /mnt/engine-etc/engine-context.json ]; then
  printf '{"app_secret":"%s","salt":"%s"}\n' "${APP_SECRET:-}" "${SALT:-}" > /mnt/engine-etc/engine-context.json
  chown 901:"$group" /mnt/engine-etc/engine-context.json
  chmod 640 /mnt/engine-etc/engine-context.json
fi
# Central: cbd generates its PKI in its cert volume at start (contracts § 8.1).
if [ "$role" = central ] && [ -d /mnt/broker-certs ]; then
  chown 902:902 /mnt/broker-certs
  chmod 0755 /mnt/broker-certs
fi
ls -lan /mnt/engine-etc /mnt/broker-etc
