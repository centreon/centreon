#!/usr/bin/env bash
# Specific setup for the Real_Time_Monitoring_Server collection.
#
# GET /monitoring/servers joins instances to nagios_server on uid, falling back
# to nagios_server.id. We seed two pollers to cover both branches: an up-to-date
# one (instance_id == uid) and a legacy one (instance_id == config id). uids are
# large and config ids small, so each poller matches once (no duplicate).
#
# mysql connects to the "db" service host, as in the other fixture scripts.

set -euo pipefail

# JS-safe values (below 2^53) so Bruno parses the returned id without precision
# loss, while staying above any auto-increment nagios_server.id.
POLLER_UID=900000001
LEGACY_POLLER_UID=900000002

# Up-to-date poller: instance_id == nagios_server.uid.
echo "Seeding realtime poller (uid=${POLLER_UID}) for /monitoring/servers regression coverage"
mysql -hdb -uroot -pcentreon -e "
  DELETE FROM centreon_storage.instances WHERE instance_id = ${POLLER_UID};
  DELETE FROM centreon.nagios_server WHERE uid = ${POLLER_UID};
  INSERT INTO centreon.nagios_server (name, localhost, ns_activate, ssh_port, uid)
    VALUES ('QA-realtime-poller', '0', '1', 22, ${POLLER_UID});
  INSERT INTO centreon_storage.instances (instance_id, name, running, last_alive, deleted)
    VALUES (${POLLER_UID}, 'QA-realtime-poller', 1, UNIX_TIMESTAMP(), 0);
"

# Legacy poller: instance_id == nagios_server.id (auto-incremented, captured via
# LAST_INSERT_ID), reproducing what an older Broker writes.
echo "Seeding legacy realtime poller (instance_id == config id) for JOIN fallback coverage"
mysql -hdb -uroot -pcentreon -e "
  DELETE FROM centreon_storage.instances WHERE name = 'QA-legacy-poller';
  DELETE FROM centreon.nagios_server WHERE name = 'QA-legacy-poller';
  INSERT INTO centreon.nagios_server (name, localhost, ns_activate, ssh_port, uid)
    VALUES ('QA-legacy-poller', '0', '1', 22, ${LEGACY_POLLER_UID});
  SET @legacyConfigId = LAST_INSERT_ID();
  INSERT INTO centreon_storage.instances (instance_id, name, running, last_alive, deleted)
    VALUES (@legacyConfigId, 'QA-legacy-poller', 1, UNIX_TIMESTAMP(), 0);
"

echo "Real_Time_Monitoring_Server setup-web.sh: done."
