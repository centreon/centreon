# Migrate from Centreon *centcore*

To build a configuration file based on */etc/centreon/conf.pm*, execute the following command line.

If using package:

```bash
$ perl /usr/local/bin/gorgone_config_init.pl
2019-09-30 11:00:00 - INFO - file '/etc/centreon/gorgoned.yml' created success
```

If using sources:

```bash
$ perl ./contrib/gorgone_config_init.pl
2019-09-30 11:00:00 - INFO - file '/etc/centreon/gorgoned.yml' created success
```

As a result the following configuration will be created in */etc/centreon/gorgoned.yml*:

```yaml
name: gorgoned
description: Configuration init by gorgone_config_init
database:
  db_centreon:
    dsn: "mysql:host=localhost;port=3306;dbname=centreon"
    username: "centreon"
    password: "centreon"
  db_centstorage:
    dsn: "mysql:host=localhost;port=3306;dbname=centreon_storage"
    username: "centreon"
    password: "centreon"
gorgonecore:
  hostname:
  id:
modules:
  - name: httpserver
    package: gorgone::modules::core::httpserver::hooks
    enable: false
    address: 0.0.0.0
    port: 8443
    ssl: true
    ssl_cert_file: /etc/pki/tls/certs/server-cert.pem
    ssl_key_file: /etc/pki/tls/server-key.pem
    auth:
      user: admin
      password: password

  - name: cron
    package: gorgone::modules::core::cron::hooks
    enable: false

  - name: action
    package: gorgone::modules::core::action::hooks
    enable: true

  - name: proxy
    package: gorgone::modules::core::proxy::hooks
    enable: true

  - name: pollers
    package: gorgone::modules::centreon::pollers::hooks
    enable: true

  - name: broker
    package: gorgone::modules::centreon::broker::hooks
    enable: false
    cache_dir: "/var/lib/centreon/broker-stats/"
    cron:
      - id: broker_stats
        timespec: "*/2 * * * *"
        action: BROKERSTATS
        parameters:
          timeout: 10

  - name: legacycmd
    package: gorgone::modules::centreon::legacycmd::hooks
    enable: true
    cmd_file: "/var/lib/centreon/centcore.cmd"
    cache_dir: "/var/cache/centreon/"
    cache_dir_trap: "/etc/snmp/centreon_traps/"
    remote_dir: "/var/lib/centreon/remote-data/"
```
