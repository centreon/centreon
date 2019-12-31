# Register

## Description

This module aims to provide a way to register nodes manually, in opposition to the [pollers](../centreon/pollers.md) module.

Nodes are either servers running Gorgone daemon or simple equipment with SSH server.

## Configuration

There is no specific configuration in the Gorgone daemon configuration file, only a directive to set a path to a dedicated configuration file.

| Directive | Description | Default value |
| :- | :- | :- |
| config_file | Path to the configuration file listing nodes | |

#### Example

```yaml
name: register
package: "gorgone::modules::core::register::hooks"
enable: true
config_file: config/registernodes.yml
```

Nodes are listed in a separate configuration file in a `nodes` table as below:

##### Using ZMQ (Gorgone running on node)

| Directive | Description |
| :- | :- |
| id | Unique identifier of the node (can be Poller's ID if [nodes](../centreon/nodes.md) module is not used) |
| type | Way for the daemon to connect to the node (push_zmq) |
| address | IP address of the node |
| port | Port to connect to on the node |
| server_pubkey | Server public key (Default: ask the server pubkey when it connects) |
| client_pubkey | Client public key (Default: use global public key) |
| client_privkey | Client private key (Default: use global private key) |
| cipher | Cipher used for encryption (Default: "Cipher::AES") |
| vector | Encryption vector (Default: 0123456789012345) |
| nodes | Table to register subnodes managed by node (pathscore is not mandatory) |

#### Example

```yaml
nodes:
  - id: 4
    type: push_zmq
    address: 10.1.2.3
    port: 5556
    nodes:
      - id: 2
        pathscore: 1
      - id: 20
        pathscore: 10
```

##### Using SSH

| Directive | Description |
| :- | :- |
| id | Unique identifier of the node (can be Poller's ID if [pollers](../centreon/pollers.md) module is not used) |
| type | Way for the daemon to connect to the node (push_ssh) |
| address | IP address of the node |
| ssh_port | Port to connect to on the node |
| ssh_username | SSH username (if no SSH key) |
| ssh_password | SSH password (if no SSH key) |
| strict_serverkey_check | Boolean to strictly check the node fingerprint |

#### Example

```yaml
nodes:
  - id: 8
    type: push_ssh
    address: 10.4.5.6
    ssh_port: 22
    ssh_username: user
    ssh_password: pass
    strict_serverkey_check: false
```

## Events

No events.

## API

No API endpoints.
