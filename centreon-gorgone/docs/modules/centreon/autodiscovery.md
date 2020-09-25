# Autodiscovery

## Description

This module aims to extend Centreon Autodiscovery server functionalities.

## Configuration

| Directive       | Description                                                            | Default value |
|:----------------|:-----------------------------------------------------------------------|:--------------|
| global\_timeout | Time in seconds before a discovery command is considered timed out     | `300`         |
| check\_interval | Time in seconds defining frequency at which results will be search for | `15`          |

#### Example

```yaml
name: autodiscovery
package: "gorgone::modules::centreon::autodiscovery::hooks"
enable: true
global_timeout: 60
check_interval: 10
```

## Events

| Event                    | Description                                     |
|:-------------------------|:------------------------------------------------|
| AUTODISCOVERYREADY       | Internal event to notify the core               |
| HOSTDISCOVERYLISTENER    | Internal event to get host discovery results    |
| SERVICEDISCOVERYLISTENER | Internal event to get service discovery results |
| ADDHOSTDISCOVERYJOB      | Add a host discovery job                        |
| DELETEHOSTDISCOVERYJOB   | Delete a host discovery job                     |
| LAUNCHHOSTDISCOVERY      | Execute a host discovery job                    |
| LAUNCHSERVICEDISCOVERY   | Execute a service discovery job                 |

## API

### Add a host discovery job

| Endpoint                      | Method |
|:------------------------------|:-------|
| /centreon/autodiscovery/hosts | `POST` |

#### Headers

| Header       | Value            |
|:-------------|:-----------------|
| Accept       | application/json |
| Content-Type | application/json |

#### Body

| Key             | Value                                                      |
|:----------------|:-----------------------------------------------------------|
| job\_id         | ID of the Host Discovery job                               |
| target          | Identifier of the target on which to execute the command   |
| command_line    | Command line to execute to perform the discovery           |
| timeout         | Time in seconds before the command is considered timed out |
| execution       | Execution settings                                         |
| post\_execution | Post-execution settings                                    |

With the following keys for the `execution` entry:

| Key        | Value                                           |
|:-----------|:------------------------------------------------|
| mode       | Execution mode ('0': immediate, '1': scheduled) |
| parameters | Parameters needed by execution mode             |

With the following keys for the `post_execution` entry:

| Key      | Value                            |
|:---------|:---------------------------------|
| commands | Array of commands to be executed |

```json
{
    "job_id": "<id of the job>",
    "target": "<target id>",
    "command_line": "<command to execute>",
    "timeout": "<timeout in seconds>",
    "execution": {
        "mode": "<execution mode>",
        "parameters": "<execution parameters>",
    },
    "post_execution": {
        "commands": "<array of commands>",
    }
}
```

#### Examples

#### Execute immediately without post-execution commands

```bash
curl --request POST "https://hostname:8443/api/centreon/autodiscovery/hosts" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data "{
    \"job_id\": 14,
    \"target\": 3,
    \"command_line\": \"perl /usr/lib/centreon/plugins/centreon_generic_snmp.pl --plugin=os::linux::local::plugin --mode=discovery-snmp --subnet='10.1.2.3/24' --snmp-port='161' --snmp-version='2c' --snmp-community='public'\",
    \"timeout\": 300,
    \"execution\": {
        \"mode\": 0,
        \"parameters\": {}
    },
    \"post_execution\": {}
}"
```

#### Execute immediately with post-execution commands

```bash
curl --request POST "https://hostname:8443/api/centreon/autodiscovery/hosts" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data "{
    \"job_id\": 14,
    \"target\": 3,
    \"command_line\": \"perl /usr/lib/centreon/plugins/centreon_generic_snmp.pl --plugin=os::linux::local::plugin --mode=discovery-snmp --subnet='10.1.2.3/24' --snmp-port='161' --snmp-version='2c' --snmp-community='public'\",
    \"timeout\": 300,
    \"execution\": {
        \"mode\": 0,
        \"parameters\": {}
    },
    \"post_execution\": {
        \"commands\": [
            {
                \"action\": \"COMMAND\",
                \"command_line\": \"/usr/share/centreon/www/modules/centreon-autodiscovery-server/script/run_save_discovered_host --job-id=14\"
            }
        ]
    }
}"
```

#### Schedule execution without post-execution commands

```bash
curl --request POST "https://hostname:8443/api/centreon/autodiscovery/hosts" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data "{
    \"job_id\": 14,
    \"target\": 3,
    \"command_line\": \"perl /usr/lib/centreon/plugins/centreon_generic_snmp.pl --plugin=os::linux::local::plugin --mode=discovery-snmp --subnet='10.1.2.3/24' --snmp-port='161' --snmp-version='2c' --snmp-community='public'\",
    \"timeout\": 300,
    \"execution\": {
        \"mode\": 1,
        \"parameters\": {
            \"cron_definition\": \"*/10 * * * *\"
        }
    },
    \"post_execution\": {}
}"
```

#### Schedule execution with post-execution commands

```bash
curl --request POST "https://hostname:8443/api/centreon/autodiscovery/hosts" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data "{
    \"job_id\": 14,
    \"target\": 3,
    \"command_line\": \"perl /usr/lib/centreon/plugins/centreon_generic_snmp.pl --plugin=os::linux::local::plugin --mode=discovery-snmp --subnet='10.1.2.3/24' --snmp-port='161' --snmp-version='2c' --snmp-community='public'\",
    \"timeout\": 300,
    \"execution\": {
        \"mode\": 1,
        \"parameters\": {
            \"cron_definition\": \"*/10 * * * *\"
        }
    },
    \"post_execution\": {
        \"commands\": [
            {
                \"action\": \"COMMAND\",
                \"command_line\": \"/usr/share/centreon/www/modules/centreon-autodiscovery-server/script/run_save_discovered_host --job-id=14\"
            }
        ]
    }
}"
```

### Launch a host discovery job

| Endpoint                                   | Method |
|:-------------------------------------------|:-------|
| /centreon/autodiscovery/hosts/:id/schedule | `GET`  |

#### Headers

| Header       | Value            |
|:-------------|:-----------------|
| Accept       | application/json |

#### Path variables

| Variable | Description           |
|:---------|:----------------------|
| id       | Identifier of the job |

#### Example

```bash
curl --request GET "https://hostname:8443/api/centreon/autodiscovery/hosts/:id/schedule" \
  --header "Accept: application/json"
```

### Delete a host discovery job

| Endpoint                             | Method   |
|:-------------------------------------|:---------|
| /centreon/autodiscovery/hosts/:token | `DELETE` |

#### Headers

| Header | Value            |
|:-------|:-----------------|
| Accept | application/json |

#### Path variables

| Variable | Description                |
|:---------|:---------------------------|
| token    | Token of the scheduled job |

#### Example

```bash
curl --request DELETE "https://hostname:8443/api/centreon/autodiscovery/hosts/discovery_14_6b7d1bb8" \
  --header "Accept: application/json"
```

### Execute a service discovery job

| Endpoint                         | Method |
|:---------------------------------|:-------|
| /centreon/autodiscovery/services | `POST` |

#### Headers

| Header       | Value            |
|:-------------|:-----------------|
| Accept       | application/json |
| Content-Type | application/json |

#### Body

| Key                  | Value | Description                                                                   |
|:---------------------|:------|:------------------------------------------------------------------------------|
| filter\_rules        | array | Run the selected rule of discovery                                            |
| force\_rule          | `1|0` | Run also disabled rules                                                       |
| filter\_hosts        | array | Run all discovery rules linked to all templates of host used by selected host |
| filter\_pollers      | array | Run all discovery rules linked to all poller linked with rule                 |
| manual               | `1|0` | Run discovery for manual scan                                                 |
| dry\_run             | `1|0` | Run discovery without configuration change                                    |
| no\_generate\_config | `1|0` | No configuration generation (even if there is some changes)                   |

```json
{
    "filter_rules": ["<rule1>", "<rule2>", ...],
    "force_rule": <1|0>,
    "filter_hosts": ["<host1>", "<host2>", ...],
    "filter_pollers": ["<poller1>", "<poller2>", ...],
    "manual": <1|0>,
    "dry_run": <1|0>,
    "no_generate_config": <1|0>
}
```

#### Example

```bash
curl --request POST "https://hostname:8443/api/centreon/autodiscovery/services" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data '{
    "filters_rules": ["OS-Linux-SNMP-Disk-Name", "OS-Linux-SNMP-Traffic-Name"]
}'
```
