# Register

## Description

This module aims to provide a way to register nodes manually, in opposition to the [pollers](../centreon/pollers.md) module.

Nodes are either servers running Gorgone daemon or simple equipment with SSH server.

## Configuration

There is no specific configuration in the Gorgone daemon configuration file, only a directive to set a path to a dedicated configuration file.

| Directive    | Description                                  | Default value |
| :----------- | :------------------------------------------- | :------------ |
| config\_file | Path to the configuration file listing nodes |               |

#### Example

```yaml
name: register
package: "gorgone::modules::core::register::hooks"
enable: true
config_file: config/registernodes.yaml
```

Nodes are listed in a separate configuration file in a `nodes` table as below:

##### Using ZMQ (Gorgone running on node)

| Directive       | Description                                                                |
| :-------------- | :------------------------------------------------------------------------- |
| id              | Unique identifier of the node (can be Poller’s ID if using prevail option) |
| type            | Way for the daemon to connect to the node (push\_zmq)                      |
| address         | IP address of the node                                                     |
| port            | Port to connect to on the node                                             |
| server\_pubkey  | Server public key (Default: ask the server pubkey when it connects)        |
| client\_pubkey  | Client public key (Default: use global public key)                         |
| client\_privkey | Client private key (Default: use global private key)                       |
| cipher          | Cipher used for encryption (Default: “Cipher::AES”)                        |
| vector          | Encryption vector (Default: 0123456789012345)                              |
| prevail         | Defines if this configuration prevails on `nodes` module configuration     |
| nodes           | Table to register subnodes managed by node (pathscore is not mandatory)    |

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

| Directive                | Description                                                                                       |
| :----------------------- | :------------------------------------------------------------------------------------------------ |
| id                       | Unique identifier of the node (can be Poller’s ID if using prevail option)                        |
| type                     | Way for the daemon to connect to the node (push\_ssh)                                             |
| address                  | IP address of the node                                                                            |
| ssh\_port                | Port to connect to on the node                                                                    |
| ssh\_directory           | Path to the SSH directory, used for files like known\_hosts and identity (private and public key) |
| ssh\_known\_hosts        | Path to the known hosts file                                                                      |
| ssh\_identity            | Path to the identity file                                                                         |
| ssh\_username            | SSH username                                                                                      |
| ssh\_password            | SSH password (if no SSH key)                                                                      |
| ssh\_connect\_timeout    | Time is seconds before a connection is considered timed out                                       |
| strict\_serverkey\_check | Boolean to strictly check the node fingerprint                                                    |
| prevail                  | Defines if this configuration prevails on `nodes` module configuration                            |

#### Example

```yaml
nodes:
  - id: 8
    type: push_ssh
    address: 10.4.5.6
    ssh_port: 2222
    ssh_identity: ~/.ssh/the_rsa_key
    ssh_username: user
    strict_serverkey_check: false
    prevail: 1
```

## Events

No events.

## API

No API endpoints.
