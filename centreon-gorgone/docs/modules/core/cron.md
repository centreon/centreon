# Cron

## Description

This module aims to reproduce a cron-like scheduler that can send events to other Gorgone modules.

## Configuration

No specific configuration is needed.

Below the configuration to add cron definitions:

| Directive | Description |
| :- | :- |
| id | Unique identifier of the cron definition |
| timespec | Cron-like time specification |
| action | Action/event to call at job execution |
| parameters | Parameters needed by the called action/event |

#### Example

```yaml
name: cron
package: "gorgone::modules::core::cron::hooks"
enable: true
cron:
  - id: echo_date
    timespec: "* * * * *"
    action: COMMAND
    parameters:
      command: "date >> /tmp/date.log"
      timeout: 10
```

## Events

| Event | Description |
| :- | :- |
| CRONREADY | Internal event to notify the core |
| GETCRON | Get one or all cron definitions |
| ADDCRON | Add one or several cron definitions |
| DELETECRON | Delete a cron definition |
| UPDATECRON | Update a cron definition |

## API

### Get one or all definitions

| Endpoint | Method |
| :- | :- |
| /api/core/cron/definitions | `GET` |
| /api/core/cron/definitions/:id | `GET` |

#### Headers

| Header | Value |
| :- | :- |
| Accept | application/json |

#### Body

Not needed.

#### Example

```bash
curl --request GET "https://hostname:8443/api/core/cron/definitions" \
  --header "Accept: application/json"
```

```bash
curl --request GET "https://hostname:8443/api/core/cron/definitions/echo_date" \
  --header "Accept: application/json"
```

### Add one or several cron definitions

| Endpoint | Method |
| :- | :- |
| /api/core/cron/definitions | `POST` |

#### Headers

| Header | Value |
| :- | :- |
| Accept | application/json |
| Content-Type | application/json |

#### Body

| Key | Value |
| :- | :- |
| id | ID of the definition |
| timespec | Cron-like time specification |
| command | Action/event to call at job execution |
| parameters | Parameters needed by the called action/event |

```json
[
    {
        "id": "<id of the definition>",
        "timespec": "<cron-like time specification>",
        "command": "<action/event>",
        "parameters": "<parameters for the action/event>"
    }
]
```

#### Example

```bash
curl --request POST "https://hostname:8443/api/core/cron/definitions" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data "[
    {
        \"timespec\": \"*/15 * * * *\",
        \"id\": \"job_123\",
        \"action\": \"COMMAND\"
        \"parameters\": {
            \"command\": \"date >> /tmp/the_date_again.log\",
            \"timeout\": 5
        }
    }
]"
```
