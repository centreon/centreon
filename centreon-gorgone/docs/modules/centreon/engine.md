# Engine

## Description

This module aims to provide a bridge to communicate with Centreon Engine daemon.

## Configuration

| Directive    | Description                                   | Default value                                |
| :----------- | :-------------------------------------------- | :------------------------------------------- |
| command_file | Path to the Centreon Engine command file pipe | `/var/lib/centreon-engine/rw/centengine.cmd` |

#### Example

```yaml
name: engine
package: "gorgone::modules::centreon::engine::hooks"
enable: true
command_file: "/var/lib/centreon-engine/rw/centengine.cmd"
```

## Events

| Event         | Description                                                                  |
| :------------ | :--------------------------------------------------------------------------- |
| ENGINEREADY   | Internal event to notify the core                                            |
| ENGINECOMMAND | Send a Centreon external command to Centreon Engine daemon command file pipe |

## API

### Execute a command line

| Endpoint                 | Method |
| :----------------------- | :----- |
| /centreon/engine/command | `POST` |

#### Headers

| Header       | Value            |
| :----------- | :--------------- |
| Accept       | application/json |
| Content-Type | application/json |

#### Body

| Key          | Value                                         |
| :----------- | :-------------------------------------------- |
| command_file | Path to the Centreon Engine command file pipe |
| commands     | Array of external commands (old-style format) |

```json
{
    "command_file": "<command file path>",
    "commands": [
        "<external command>"
    ]
}
```

#### Example

```bash
curl --request POST "https://hostname:8443/api/centreon/engine/command" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data "{
    \"command_file\": \"/var/lib/centreon-engine/rw/centengine.cmd\",
    \"commands\": [
        \"[653284380] SCHEDULE_SVC_CHECK;host1;service1;653284380\"
    ]
}"
```
