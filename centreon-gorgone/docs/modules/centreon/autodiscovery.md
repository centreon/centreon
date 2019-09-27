# Autodiscovery

## Description

This module aims to extend Centreon Autodiscovery server functionalities.

## Configuration

No specific configuration.

#### Example

```yaml
name: autodiscovery
package: "gorgone::modules::centreon::autodiscovery::hooks"
enable: true
```

## Events

| Event | Description |
| :- | :- |
| AUTODISCOVERYREADY | Internal event to notify the core |
| GETDISCOVERYRESULTS | Internal event to retrieve discovery results from logs |
| UPDATEDISCOVERYRESULTS | Internal event to update tasks/jobs result table |
| GETDISCOVERYJOB | Get discovery job result |
| ADDDISCOVERYJOB | Add a discovery job |
| GETDISCOVERYTASK | Get discovery task result |
| ADDDISCOVERYTASK | Get a discovery task |

## API

### Add a discovery task

| Endpoint | Method |
| :- | :- |
| /api/centreon/autodiscovery/task | `POST` |

#### Headers

| Header | Value |
| :- | :- |
| Accept | application/json |
| Content-Type | application/json |

#### Body

| Key | Value |
| :- | :- |
| id | Identifier of the task (random if empty) |
| command | Command line to execute to perform the discovery |
| timeout | Time in seconds before the command is considered timed out |
| target | Identifier of the target on which to execute the command |

```json
{
    "id": "<id of the task>",
    "command": "<command to execute>",
    "timeout": "<timeout in seconds>",
    "target": "<target id>"
}
```

#### Example

```bash
curl --request POST "https://hostname:8443/api/centreon/autodiscovery/task" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data "{
    \"command\": \"perl /usr/lib/centreon/plugins/centreon_generic_snmp.pl --plugin=os::linux::local::plugin --mode=discovery-snmp --subnet='10.1.2.3/24' --snmp-port='161' --snmp-version='2c' --snmp-community='public'\",
    \"timeout\": 300,
    \"target\": 2
}"
```

### Get a discovery task results

| Endpoint | Method |
| :- | :- |
| /api/centreon/autodiscovery/task/:id | `GET` |

#### Headers

| Header | Value |
| :- | :- |
| Accept | application/json |

#### Path variables

| Variable | Description |
| :- | :- |
| id | Identifier of the task |

#### Example

```bash
curl --request GET "https://hostname:8443/api/centreon/autodiscovery/task/autodiscovery_task_3209230948" \
  --header "Accept: application/json"
```

### Add a discovery job

| Endpoint | Method |
| :- | :- |
| /api/centreon/autodiscovery/job | `POST` |

#### Headers

| Header | Value |
| :- | :- |
| Accept | application/json |
| Content-Type | application/json |

#### Body

| Key | Value |
| :- | :- |
| id | Identifier of the task (random if empty) |
| timespec | Cron-like time specification |
| command | Command line to execute to perform the discovery |
| timeout | Time in seconds before the command is considered timed out |
| target | Identifier of the target on which to execute the command |

```json
{
    "id": "<id of the task>",
    "timespec": "<cron-like time specification>",
    "command": "<command to execute>",
    "timeout": "<timeout in seconds>",
    "target": "<target id>"
}
```

#### Example

```bash
curl --request POST "https://hostname:8443/api/centreon/autodiscovery/job" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data "{
    \"timespec\": \"0 10 * * *\",
    \"command\": \"perl /usr/lib/centreon/plugins/centreon_generic_snmp.pl --plugin=os::linux::local::plugin --mode=discovery-snmp --subnet='10.1.2.3/24' --snmp-port='161' --snmp-version='2c' --snmp-community='public'\",
    \"timeout\": 300,
    \"target\": 2
}"
```

### Get a discovery job results

| Endpoint | Method |
| :- | :- |
| /api/centreon/autodiscovery/job/:id | `GET` |

#### Headers

| Header | Value |
| :- | :- |
| Accept | application/json |

#### Path variables

| Variable | Description |
| :- | :- |
| id | Identifier of the job |

#### Example

```bash
curl --request GET "https://hostname:8443/api/centreon/autodiscovery/job/autodiscovery_job_40894092083" \
  --header "Accept: application/json"
```
