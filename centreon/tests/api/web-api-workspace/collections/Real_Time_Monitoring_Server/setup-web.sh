#!/usr/bin/env bash
# Specific setup for the Real_Time_Monitoring_Server collection.
#
# GET /monitoring/servers joins centreon_storage.instances to
# centreon.nagios_server. Since the instance_id migration to a Snowflake UID,
# the matching key is nagios_server.uid (NOT nagios_server.id): Broker writes
# nagios_server.uid into instances.instance_id. A regression where the JOIN
# uses nagios_server.id silently returns an empty list.
#
# A fresh CI platform has no realtime instances row, so the endpoint would
# return an empty array either way and the tests could not tell a broken JOIN
# from a healthy-but-empty platform. We seed a dedicated poller whose
# instances.instance_id equals its nagios_server.uid and differs from its
# nagios_server.id, so the poller is returned only when the JOIN resolves on
# uid. This does not impact other collections: each bruno-test job runs in its
# own ephemeral Docker container.
#
# mysql connects to the "db" service host, as in the other fixture scripts.

set -euo pipefail

# JS-safe value (below 2^53) so Bruno can parse the returned id without losing
# precision, while staying well above any auto-increment nagios_server.id.
POLLER_UID=900000001

# Seed a dedicated poller for the Real_Time_Monitoring_Server collection.
echo "Seeding realtime poller (uid=${POLLER_UID}) for /monitoring/servers regression coverage"
mysql -hdb -uroot -pcentreon -e "
  DELETE FROM centreon_storage.instances WHERE instance_id = ${POLLER_UID};
  DELETE FROM centreon.nagios_server WHERE uid = ${POLLER_UID};
  INSERT INTO centreon.nagios_server (name, localhost, ns_activate, ssh_port, uid)
    VALUES ('QA-realtime-poller', '0', '1', 22, ${POLLER_UID});
  INSERT INTO centreon_storage.instances (instance_id, name, running, last_alive, deleted)
    VALUES (${POLLER_UID}, 'QA-realtime-poller', 1, UNIX_TIMESTAMP(), 0);
"

echo "Real_Time_Monitoring_Server setup-web.sh: done."
