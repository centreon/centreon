# Migrate from Centreon *centcore*

To build a configuration file based on */etc/centreon/conf.pm*, execute the following command line.

If using package:

```bash
$ perl /usr/local/bin/gorgone_config_init.pl
2019-09-30 11:00:00 - INFO - file '/etc/centreon-gorgone/config.yaml' created success
```

If using sources:

```bash
$ perl ./contrib/gorgone_config_init.pl
2019-09-30 11:00:00 - INFO - file '/etc/centreon-gorgone/config.yaml' created success
```

As a result the following configuration file will be created at */etc/centreon-gorgone/config.yaml*:

```yaml
name: config.yaml
description: Configuration init by gorgone_config_init
configuration:
  centreon:
    database:
      db_configuration:
        dsn: "mysql:host=localhost;port=3306;dbname=centreon"
        username: "centreon"
        password: "centreon"
      db_realtime:
        dsn: "mysql:host=localhost;port=3306;dbname=centreon_storage"
        username: "centreon"
        password: "centreon"
  gorgone:
    gorgonecore:
      privkey: "/var/lib/centreon-gorgone/.keys/rsakey.priv.pem"
      pubkey: "/var/lib/centreon-gorgone/.keys/rsakey.pub.pem"
    modules:
      - name: httpserver
        package: gorgone::modules::core::httpserver::hooks
        enable: false
        address: 0.0.0.0
        port: 8085
        ssl: false
        auth:
          enabled: false
        allowed_hosts:
          enabled: true
          subnets:
            - 127.0.0.1/32

      - name: action
        package: gorgone::modules::core::action::hooks
        enable: true

      - name: cron
        package: gorgone::modules::core::cron::hooks
        enable: false
        cron: !include cron.d/*.yaml

      - name: proxy
        package: gorgone::modules::core::proxy::hooks
        enable: true
  
      - name: legacycmd
        package: gorgone::modules::centreon::legacycmd::hooks
        enable: true
        cmd_file: "/var/lib/centreon/centcore.cmd"
        cache_dir: "/var/cache/centreon/"
        cache_dir_trap: "/etc/snmp/centreon_traps/"
        remote_dir: "/var/lib/centreon/remote-data/"

      - name: engine
        package: "gorgone::modules::centreon::engine::hooks"
        enable: true
        command_file: "/var/lib/centreon-engine/rw/centengine.cmd"

      - name: pollers
        package: gorgone::modules::centreon::pollers::hooks
        enable: true

      - name: broker
        package: "gorgone::modules::centreon::broker::hooks"
        enable: true
        cache_dir: "/var/cache/centreon//broker-stats/"
        cron:
          - id: broker_stats
            timespec: "*/2 * * * *"
            action: BROKERSTATS
            parameters:
              timeout: 10
```
