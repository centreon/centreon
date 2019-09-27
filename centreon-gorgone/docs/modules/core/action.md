# Action

## Description

This module aims to execute actions on the server running the Gorgone daemon or remotly using SSH.

## Configuration

| Directive | Description | Default value |
| :- | :- | :- |
| command_timeout | Time in seconds before a command is considered timed out | 30 |

#### Example

```yaml
name: action
package: "gorgone::modules::core::action::hooks"
enable: true
command_timeout: 30
```

## Events

| Event | Description |
| :- | :- |
| ACTIONREADY | Internal event to notify the core |
| PROCESSCOPY | Process file or archive received from another daemon |
| COMMAND | Execute a shell command on the server running the daemon or on another server using SSH |

## API

### Execute a Command Line

| Endpoint | Method |
| :- | :- |
| /api/core/action/command | `POST` |

#### Headers

| Header | Value |
| :- | :- |
| Accept | application/json |
| Content-Type | application/json |

#### Body

| Key | Value |
| :- | :- |
| command | Command to execute |
| timeout | Time in seconds before a command is considered timed out |

```json
{
    "command": "<command to execute>",
    "timeout": "<timeout in seconds>"
}
```

#### Example

```bash
curl --request POST "https://hostname:8443/api/core/action/command" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data "{
    \"command\": \"echo 'Test command' >> /tmp/here.log\"
}"
```
