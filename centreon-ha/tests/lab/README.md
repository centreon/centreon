# centreon-ha database lab

Two VMs, run with [Lima](https://lima-vm.io), that form a Pacemaker cluster managing a MariaDB or MySQL replication with the centreon-ha files of this checkout. Use it to test changes to `bin/`, `lib/` and `ocf-scripts/` end to end, on AlmaLinux 9 or Debian 13.

The nodes are set up like the database nodes of the Centreon HA installation guide: the database from the repository the Centreon documentation uses (MariaDB 11.8 or MySQL 8.4), its datadir on LVM, the `server.cnf` settings and the replication account of the guide, read-only nodes before the first synchronization, and a `mysql` user with SSH keys. The lab covers the database cluster only: no Centreon central, quorum device or VIP. The credentials are fixed lab values, so do not expose the VMs.

## Requirements

- [Lima](https://lima-vm.io/docs/installation/) 2 or later:
  - macOS (tested): `brew install lima`, plus `brew install qemu` for the x86_64 emulation below;
  - Linux (expected to work, not tested yet): Lima with QEMU, and access to KVM (`/dev/kvm`).
- About 4 GB of free memory and 25 GB of disk.
- Internet access from the VMs, to install the packages.

## Usage

```bash
./lab.sh up mariadb            # or: ./lab.sh up mysql debian13
./lab.sh test
./lab.sh destroy
```

The engine is `mariadb` or `mysql`, the distribution `alma9` (default) or `debian13`. `up` runs every step. They can also be run one by one:

| Command                | What it does                                                                                           |
| ---------------------- | ------------------------------------------------------------------------------------------------------ |
| `create [os] [engine]` | Creates the `ha-node1` and `ha-node2` VMs, each with a data disk (x86_64 ones for MySQL on Debian 13). |
| `provision <engine>`   | Installs the engine on an LVM datadir, Pacemaker, and the accounts of centreon-ha.                     |
| `deploy`               | Installs the centreon-ha files of this checkout, at the paths of the package.                          |
| `sync`                 | Starts the replication with `mysql-sync-bigdb.sh`, as the guide does.                                  |
| `cluster`              | Creates the Pacemaker cluster and the promotable `ms_mysql` resource.                                  |
| `test`                 | Runs the test scenarios.                                                                               |
| `status`, `shell node` | Shows the cluster status, or opens a root shell on a node.                                             |

The MariaDB resource uses the `mariadb-centreon` agent name of the guide, which tests its compatibility symlink; the MySQL resource uses `db-centreon`.

`./lab.sh matrix` builds and tests every engine on every distribution, one after the other, and writes a summary and the logs to `matrix-logs/`.

MySQL publishes its Debian 13 packages for x86_64 only: on other hosts, such as Apple Silicon Macs, the lab emulates x86_64 VMs for MySQL on Debian 13, which makes that combination much slower (count about an hour). `./lab.sh matrix --skip-emulated` skips it.

To test a change, run `./lab.sh deploy` and then `./lab.sh test`: the VMs read the files of this checkout through the Lima home mount. The tests do not run `mysql-sync-bigdb.sh` again: to test a change to it, run `destroy` and then `up`. To switch engine or distribution, run `destroy` and then `up`.

## Test scenarios

`./lab.sh test` checks that:

- the cluster starts both databases and promotes one node;
- a write on the primary reaches the replica, and `mysql-check-status.sh` reports a healthy replication;
- the cluster fails over to the replica and back, and the replication resumes each time;
- `centreondb-smooth-backup.sh` backs up the replica, which replicates again afterwards;
- an incremental backup (`-i`) saves the binary logs after the checkpoint of the total backup and advances it, and a missing binary log fails the next one and drops the checkpoint;
- with MySQL, the GTID sets hold one member per source after the failovers;
- with MySQL, a missing prerequisite (`skip_replica_start`) fails the start on that node only, and the cluster recovers once it is restored.

## Settings

Environment variables of `lab.sh`:

| Variable        | Default                          |
| --------------- | -------------------------------- |
| `LIMA_TEMPLATE` | the template of the distribution |
| `DISK_SIZE`     | `10GiB` (data disk)              |
| `VM_CPUS`       | `2`                              |
| `VM_MEMORY`     | `2` (GiB)                        |

`./lab.sh test` waits up to `CLUSTER_TIMEOUT` seconds (default `240`) for the cluster and `REPLICATION_TIMEOUT` seconds (default `60`) for a row to replicate, and raises both on emulated VMs.

The scripts keep the lab settings of each node in `/etc/centreon-ha-lab.env`.
