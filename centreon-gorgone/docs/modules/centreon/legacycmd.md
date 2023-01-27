# Legacy Cmd

## Description

This module aims to mimick the behaviour of the antique *centcore* daemon.

As for *centcore*, it reads a file (called command file) and process every commands that it knows of.

The module relies on the following modules to process commands:

* [Action](../core/action.md)
* [Proxy](../core/proxy.md)
* [Engine](engine.md)

## Configuration

| Directive                    | Description                                                  | Default value                             |
| :--------------------------- | :----------------------------------------------------------- | :---------------------------------------- |
| cmd_file                     | *Command file* to read commands from                         | `/var/lib/centreon/centcore.cmd`          |
| cmd_dir                      | Directory where to watch for *command files*                 | `/var/lib/centreon/`                      |
| cache_dir                    | Directory where to process Centreon configuration files      | `/var/cache/centreon/`                    |
| cache_dir_trap               | Directory where to process Centreontrapd databases           | `/etc/snmp/centreon_traps/`               |
| remote_dir                   | Directory where to export Remote Servers configuration       | `/var/cache/centreon/config/remote-data/` |
| bulk_external_cmd            | Bulk external commands (DOWNTIME, ACK,...)                   | `50`                                      |
| bulk_external_cmd_sequential | Order bulk external commands and other commands (Eg. RELOAD) | `1`                                       |

#### Example

```yaml
name: legacycmd
package: "gorgone::modules::centreon::legacycmd::hooks"
enable: true
cmd_file: "/var/lib/centreon/centcore.cmd"
cmd_dir: "/var/lib/centreon/"
cache_dir: "/var/cache/centreon/"
cache_dir_trap: "/etc/snmp/centreon_traps/"
remote_dir: "/var/cache/centreon/config/remote-data/"
```

## Events

| Event          | Description                       |
| :------------- | :-------------------------------- |
| LEGACYCMDREADY | Internal event to notify the core |

## API

No API endpoints.
