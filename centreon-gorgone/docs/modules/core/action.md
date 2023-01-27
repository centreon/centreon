# Action

## Description

This module aims to execute actions on the server running the Gorgone daemon or remotly using SSH.

## Configuration

| Directive        | Description                                                    | Default value |
| :--------------- | :------------------------------------------------------------- | :------------ |
| command_timeout  | Time in seconds before a command is considered timed out       | `30`          |
| whitelist_cmds   | Boolean to enable commands whitelist                           | `false`       |
| allowed_cmds     | Regexp list of allowed commands                                |               |
| paranoid_plugins | Block centengine restart/reload if plugin dependencies missing | `false`       |

#### Example

```yaml
name: action
package: "gorgone::modules::core::action::hooks"
enable: true
command_timeout: 30
whitelist_cmds: false
allowed_cmds:
  - ^sudo\s+(/bin/)?systemctl\s+(reload|restart)\s+(centengine|centreontrapd|cbd)\s*$
  - ^sudo\s+(/usr/bin/)?service\s+(centengine|centreontrapd|cbd)\s+(reload|restart)\s*$
  - ^/usr/sbin/centenginestats\s+-c\s+/etc/centreon-engine/centengine.cfg\s*$
  - ^cat\s+/var/lib/centreon-engine/[a-zA-Z0-9\-]+-stats.json\s*$ 
```

## Events

| Event       | Description                                                                             |
| :---------- | :-------------------------------------------------------------------------------------- |
| ACTIONREADY | Internal event to notify the core                                                       |
| PROCESSCOPY | Process file or archive received from another daemon                                    |
| COMMAND     | Execute a shell command on the server running the daemon or on another server using SSH |

## API

### Execute a command line

| Endpoint             | Method |
| :------------------- | :----- |
| /core/action/command | `POST` |

#### Headers

| Header       | Value            |
| :----------- | :--------------- |
| Accept       | application/json |
| Content-Type | application/json |

#### Body

| Key               | Value                                                    |
| :---------------- | :------------------------------------------------------- |
| command           | Command to execute                                       |
| timeout           | Time in seconds before a command is considered timed out |
| continue_on_error | Behaviour in case of execution issue                     |

```json
[
    {
        "command": "<command to execute>",
        "timeout": "<timeout in seconds>",
        "continue_on_error": "<boolean>"
    }
]
```

#### Example

```bash
curl --request POST "https://hostname:8443/api/core/action/command" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data "[
    {
        \"command\": \"echo 'Test command' >> /tmp/here.log\"
    }
]"
```
