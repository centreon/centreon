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
| AUTODISCOVERYREADY       | Internal event to notify the core               |
| AUTODISCOVERYLISTENER    | Internal event to get host discovery results    |
| SERVICEDISCOVERYLISTENER | Internal event to get service discovery results |
| ADDDISCOVERYJOB          | Add a host discovery job                        |
| LAUNCHDISCOVERY          | execute a host discovery job                    |
| LAUNCHSERVICEDISCOVERY   | execute a service discovery job                 |

## API

### Add a discovery job

| Endpoint | Method |
| :- | :- |
| /centreon/autodiscovery/job | `POST` |

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

### Execute a service discovery job

| Endpoint | Method |
| :- | :- |
| /centreon/autodiscovery/services | `POST` |

#### Headers

| Header | Value |
| :- | :- |
| Accept | application/json |
| Content-Type | application/json |

#### Body

| Key | Value | Description |
| :- | :- | :- |
| filter_rules       | array | Run the selected rule of discovery | 
| force_rule         | `1|0` | Run also disabled rules |
| filter_hosts       | array | Run all discovery rules linked to all templates of host used by selected host |
| filter_pollers     | array | Run all discovery rules linked to all poller linked with rule |
| manual             | `1|0` | Run discovery for manual scan |
| dry_run            | `1|0` | Run discovery without configuration change                  |
| no_generate_config | `1|0` | No configuration generation (even if there is some changes) |

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
