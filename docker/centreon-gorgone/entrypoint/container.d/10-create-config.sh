#!/bin/sh
set -e
TYPE="${TYPE:-central}"

if [ "$TYPE" = "central" ]; then
    echo "Configuring for Central"

    # Wait for database config file with timeout
    TIMEOUT="${CONFIG_WAIT_TIMEOUT:-300}"
    ELAPSED=0
    while [ ! -f /etc/centreon/config.d/10-database.yaml ]; do
      if [ $ELAPSED -ge $TIMEOUT ]; then
        echo "ERROR: Timeout waiting for /etc/centreon/config.d/10-database.yaml after ${TIMEOUT}s"
        exit 1
      fi
      echo "Waiting for /etc/centreon/config.d/10-database.yaml to be present... (${ELAPSED}s/${TIMEOUT}s)"
      sleep 2
      ELAPSED=$((ELAPSED + 2))
    done
    echo "Database config file found after ${ELAPSED}s"
    cat <<EOF > /etc/centreon-gorgone/config.d/40-gorgoned.yaml
name: gorgoned-Central
description: Configuration for remote server Central
gorgone:
  gorgonecore:
    privkey: "/var/lib/centreon-gorgone/.keys/rsakey.priv.pem"
    pubkey: "/var/lib/centreon-gorgone/.keys/rsakey.pub.pem"
    id: 1
  modules:
    - name: httpserver
      package: "gorgone::modules::core::httpserver::hooks"
      enable: true
      address: "0.0.0.0"
      port: "8085"
      ssl: false
      auth:
        enabled: false
      allowed_hosts:
        enabled: true
        subnets:
          - 10.0.0.0/8
    - name: cron
      package: "gorgone::modules::core::cron::hooks"
      enable: true
      cron: !include cron.d/*.yaml
    - name: nodes
      package: "gorgone::modules::centreon::nodes::hooks"
      enable: true
    - name: proxy
      package: "gorgone::modules::core::proxy::hooks"
      enable: true
    - name: legacycmd
      package: "gorgone::modules::centreon::legacycmd::hooks"
      enable: true
      cmd_dir: "/var/lib/centreon/centcore/"
      cmd_file: "/var/lib/centreon/centcore.cmd"
      cache_dir: "/var/cache/centreon"
      cache_dir_trap: "/etc/snmp/centreon_traps"
      remote_dir: "/var/cache/centreon/config/remote-data/"
    - name: engine
      package: "gorgone::modules::centreon::engine::hooks"
      enable: true
      command_file: "/var/lib/centreon-engine/rw/centengine.cmd"
    - name: statistics
      package: "gorgone::modules::centreon::statistics::hooks"
      enable: true
      broker_cache_dir: "/var/cache/centreon/broker-stats/"
      cron:
        - id: broker_stats
          timespec: "*/5 * * * *"
          action: BROKERSTATS
          parameters:
            timeout: 10
        - id: engine_stats
          timespec: "*/5 * * * *"
          action: ENGINESTATS
          parameters:
            timeout: 10

EOF
    ls -lrh /var/lib/ | grep centreon

elif [ "$TYPE" = "poller" ]; then
    echo "Configuring for poller"
fi
