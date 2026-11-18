# Centreon-HA

## About this project

Centreon-HA project's purpose is to provide a versioned repository of the files that are necessary to run a high availability Centreon cluster.

## Contents

Centreon-HA repository contains:

- scripts (`bin`)
- libraries (`lib`)
- default configuration files (`etc`)
- service definitions (`systemd`)
- custom cluster resource types (`ocf-scripts`)

## Supported databases

Both database nodes must run the same engine on the same distribution. Create the database resource with the `ocf:heartbeat:db-centreon` agent; `ocf:heartbeat:mariadb-centreon`, its previous name, still works.

- MariaDB.
- MySQL 8.4 or later, with `gtid_mode=ON`, `enforce_gtid_consistency=ON`, `skip_replica_start=ON`, `log_bin=ON`, `log_replica_updates=ON` (the MySQL defaults) and the `rpl_semi_sync_source` and `rpl_semi_sync_replica` plugins loaded (`semisync_source.so` and `semisync_replica.so`). The resource agent checks these prerequisites when it starts the database.
  The replication uses TLS: keep TLS enabled on the servers (MySQL generates a certificate by default).
  MySQL ships no `mysqld_safe`: set the `binary` parameter of the resource to the server binary (`/usr/sbin/mysqld`, or `/usr/libexec/mysqld` with the EL distribution packages), or leave it unset to let the agent find it. Set the `pid` and `socket` parameters to the `pid-file` and `socket` of the MySQL configuration.

## How to install Centreon-HA

Copying the Centreon-ha files manually to the places where they are meant to be would be a loss of your time. You'd rather install the `centreon-ha` RPM which can be found on [our public YUM repo](https://yum.centreon.com/standard/21.10/el7/stable/noarch/RPMS/).

But this won't be sufficient to make your Centreon servers work as a cluster. **It is very important that you carefully read [our documentation about Centreon-HA](https://docs.centreon.com/current/en/installation/installation-of-centreon-ha/overview.html)** before starting to install it.

## Contributing

Before opening an issue or a pull request, please have a look at our [contrinution code of conduct](https://github.com/centreon/centreon/blob/master/CONTRIBUTING.md). Remember that you can reach our [community Slack chatroom ;-)](https://centreon.github.io/register-slack/).
