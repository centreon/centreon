# Legacy Cmd

## Description

This module aims to mimick the behaviour of the antique `centcore` daemon.

As for `centcore`, it reads a file (called command file) and process every commands that it knows of.

The module relies on the following modules to process commands:

* [Action](docs/modules/core/action.md)
* [Proxy](docs/modules/core/proxy.md)
* [Engine](docs/modules/centreon/engine.md)

## Configuration

| Directive | Description | Default value |
| :- | :- | :- |
| cmd_file | *Command file* to read commands from | /var/lib/centreon/centcore.cmd |
| cache_dir | Directory where to process Centreon configuration files | /var/cache/centreon/ |
| cache_dir_trap | Directory where to process Centreontrapd databases | /etc/snmp/centreon_traps/ |
| remote_dir | Directory where to export Remote Servers configuration | /var/lib/centreon/remote-data/ |

#### Example

```yaml
name: legacycmd
package: "gorgone::modules::centreon::legacycmd::hooks"
enable: true
cmd_file: "/var/lib/centreon/centcore.cmd"
cache_dir: "/var/cache/centreon/"
cache_dir_trap: "/etc/snmp/centreon_traps/"
remote_dir: "/var/lib/centreon/remote-data/"
```

## Events

| Event | Description |
| :- | :- |
| LEGACYCMDREADY | Internal event to notify the core |

## API

No API endpoints.
