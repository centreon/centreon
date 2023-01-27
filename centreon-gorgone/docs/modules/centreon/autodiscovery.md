# Autodiscovery

## Description

This module aims to extend Centreon Autodiscovery server functionalities.

## Configuration

| Directive       | Description                                                            | Default value |
| :-------------- | :--------------------------------------------------------------------- | :------------ |
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
| :----------------------- | :---------------------------------------------- |
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
| :---------------------------- | :----- |
| /centreon/autodiscovery/hosts | `POST` |

#### Headers

| Header       | Value            |
| :----------- | :--------------- |
| Accept       | application/json |
| Content-Type | application/json |

#### Body

| Key             | Value                                                      |
| :-------------- | :--------------------------------------------------------- |
| job\_id         | ID of the Host Discovery job                               |
| target          | Identifier of the target on which to execute the command   |
| command_line    | Command line to execute to perform the discovery           |
| timeout         | Time in seconds before the command is considered timed out |
| execution       | Execution settings                                         |
| post\_execution | Post-execution settings                                    |

With the following keys for the `execution` entry:

| Key        | Value                                           |
| :--------- | :---------------------------------------------- |
| mode       | Execution mode ('0': immediate, '1': scheduled) |
| parameters | Parameters needed by execution mode             |

With the following keys for the `post_execution` entry:

| Key      | Value                            |
| :------- | :------------------------------- |
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

##### Execute immediately without post-execution commands

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

##### Execute immediately with post-execution commands

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

##### Schedule execution without post-execution commands

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

##### Schedule execution with post-execution commands

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
| :----------------------------------------- | :----- |
| /centreon/autodiscovery/hosts/:id/schedule | `GET`  |

#### Headers

| Header       | Value            |
| :----------- | :--------------- |
| Accept       | application/json |

#### Path variables

| Variable | Description           |
| :------- | :-------------------- |
| id       | Identifier of the job |

#### Example

```bash
curl --request GET "https://hostname:8443/api/centreon/autodiscovery/hosts/:id/schedule" \
  --header "Accept: application/json"
```

### Delete a host discovery job

| Endpoint                             | Method   |
| :----------------------------------- | :------- |
| /centreon/autodiscovery/hosts/:token | `DELETE` |

#### Headers

| Header | Value            |
| :----- | :--------------- |
| Accept | application/json |

#### Path variables

| Variable | Description                |
| :------- | :------------------------- |
| token    | Token of the scheduled job |

#### Example

```bash
curl --request DELETE "https://hostname:8443/api/centreon/autodiscovery/hosts/discovery_14_6b7d1bb8" \
  --header "Accept: application/json"
```

### Execute a service discovery job

| Endpoint                         | Method |
| :------------------------------- | :----- |
| /centreon/autodiscovery/services | `POST` |

#### Headers

| Header       | Value            |
| :----------- | :--------------- |
| Accept       | application/json |
| Content-Type | application/json |

#### Body

| Key                  | Value                                                                                             |
| :------------------- | :------------------------------------------------------------------------------------------------ |
| filter\_rules        | Array of rules to use for discovery (empty means all)                                             |
| force\_rule          | Run disabled rules ('0': not forced, '1': forced)                                                 |
| filter\_hosts        | Array of hosts against which run the discovery (empty means all)                                  |
| filter\_pollers      | Array of pollers for which linked hosts will be discovered against (empty means all)              |
| manual               | Run discovery for manual scan from web UI ('0': automatic, '1': manual)                           |
| dry\_run             | Run discovery without configuration change ('0': changes, '1': dry run)                           |
| no\_generate\_config | No configuration generation (even if there is some changes) ('0': generation, '1': no generation) |

```json
{
    "filter_rules": "<array of rules>",
    "force_rule": "<run disabled rules>",
    "filter_hosts": "<array of hosts>",
    "filter_pollers": "<array of pollers>",
    "manual": "<manual scan>",
    "dry_run": "<run without changes>",
    "no_generate_config": "<no configuration generation>"
}
```

#### Examples

##### Execute discovery with defined rules (even if disabled)

```bash
curl --request POST "https://hostname:8443/api/centreon/autodiscovery/services" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data "{
    \"filter_rules\": [
        \"OS-Linux-SNMP-Disk-Name\",
        \"OS-Linux-SNMP-Traffic-Name\"
    ],
    \"force_rule\": 1
}"
```

##### Execute discovery for defined hosts

```bash
curl --request POST "https://hostname:8443/api/centreon/autodiscovery/services" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data "{
    \"filter_hosts\": [
        \"Host-1\",
        \"Host-2\",
        \"Host-3\"
    ]
}"
```

##### Execute discovery for defined poller (without changes)

```bash
curl --request POST "https://hostname:8443/api/centreon/autodiscovery/services" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data "{
    \"filter_pollers\": [
        \"Poller-1\"
    ],
    \"dry_run\": 1
}"
```
