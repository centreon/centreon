# Cron

## Description

This module aims to reproduce a cron-like scheduler that can send events to other Gorgone modules.

## Configuration

No specific configuration is needed.

Below the configuration to add cron definitions:

| Directive  | Description                                                                                     |
| :--------- | :---------------------------------------------------------------------------------------------- |
| id         | Unique identifier of the cron definition                                                        |
| timespec   | Cron-like time specification                                                                    |
| action     | Action/event to call at job execution                                                           |
| parameters | Parameters needed by the called action/event                                                    |
| keep_token | Boolean to define whether or not the ID of the definition will be used as token for the command |

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
      - command: "date >> /tmp/date.log"
        timeout: 10
    keep_token: true
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

### Get one or all definitions configuration

| Endpoint                   | Method |
| :------------------------- | :----- |
| /core/cron/definitions     | `GET`  |
| /core/cron/definitions/:id | `GET`  |

#### Headers

| Header | Value            |
| :----- | :--------------- |
| Accept | application/json |

#### Path variables

| Variable | Description                       |
| :------- | :-------------------------------- |
| id       | Identifier of the cron definition |

#### Example

```bash
curl --request GET "https://hostname:8443/api/core/cron/definitions" \
  --header "Accept: application/json"
```

```bash
curl --request GET "https://hostname:8443/api/core/cron/definitions/echo_date" \
  --header "Accept: application/json"
```

### Get one definition status

| Endpoint                          | Method |
| :-------------------------------- | :----- |
| /core/cron/definitions/:id/status | `GET`  |

#### Headers

| Header | Value            |
| :----- | :--------------- |
| Accept | application/json |

#### Path variables

| Variable | Description                       |
| :------- | :-------------------------------- |
| id       | Identifier of the cron definition |

#### Example

```bash
curl --request GET "https://hostname:8443/api/core/cron/definitions/echo_date/status" \
  --header "Accept: application/json"
```

### Add one or several cron definitions

| Endpoint               | Method |
| :--------------------- | :----- |
| /core/cron/definitions | `POST` |

#### Headers

| Header       | Value            |
| :----------- | :--------------- |
| Accept       | application/json |
| Content-Type | application/json |

#### Body

| Key        | Value                                                                                           |
| :--------- | :---------------------------------------------------------------------------------------------- |
| id         | ID of the definition                                                                            |
| timespec   | Cron-like time specification                                                                    |
| command    | Action/event to call at job execution                                                           |
| parameters | Parameters needed by the called action/event                                                    |
| keep_token | Boolean to define whether or not the ID of the definition will be used as token for the command |

```json
[
    {
        "id": "<id of the definition>",
        "timespec": "<cron-like time specification>",
        "command": "<action/event>",
        "parameters": "<parameters for the action/event>",
        "keep_token": "<boolean to keep id as token>"
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
        \"action\": \"COMMAND\",
        \"parameters\": [
            {
                \"command\": \"date >> /tmp/the_date_again.log\",
                \"timeout\": 5
            }
        ],
        \"keep_token\": true
    }
]"
```

### Update a definition

| Endpoint                   | Method  |
| :------------------------- | :------ |
| /core/cron/definitions/:id | `PATCH` |

#### Headers

| Header       | Value            |
| :----------- | :--------------- |
| Accept       | application/json |
| Content-Type | application/json |

#### Path variables

| Variable | Description                       |
| :------- | :-------------------------------- |
| id       | Identifier of the cron definition |

#### Body

One or several keys allowed by the add endpoint.

```json
{
    "id": "<id of the definition>",
    "timespec": "<cron-like time specification>",
    "command": "<action/event>",
    "parameters": "<parameters for the action/event>",
    "keep_token": "<boolean to keep id as token>"
}
```

#### Example

```bash
curl --request PATCH "https://hostname:8443/api/core/cron/definitions/job_123" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data "{
    \"timespec\": \"*/2 * * * *\"
}"
```

### Delete a definition

| Endpoint                   | Method   |
| :------------------------- | :------- |
| /core/cron/definitions/:id | `DELETE` |

#### Headers

| Header | Value            |
| :----- | :--------------- |
| Accept | application/json |

#### Path variables

| Variable | Description                       |
| :------- | :-------------------------------- |
| id       | Identifier of the cron definition |

#### Example

```bash
curl --request DELETE "https://hostname:8443/api/core/cron/definitions/job_123" \
  --header "Accept: application/json"
```
