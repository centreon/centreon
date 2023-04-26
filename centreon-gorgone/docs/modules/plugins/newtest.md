# IP-Label Newtest

## Description

This module aims to retrieve Newtest services.

It uses the Newtest webservice in order to connect and retrieve the informations of one (or more) Newtest Management Console (NMC).

By default *newtest* starts X processes (it depends of the configuration).

Here are the steps done by one process:

1. Centreon configuration: get the robots and scenarios already configured,

2. Get the list of robots and scenarios from the NMC,

3. Create the needed configuration in Centreon with CLAPI (no disable or delete actions),

4. Get the last status of scenarios from the NMC,

5. Submit the result to Centreon through *centcore*.

#### Requirements

| Dependency         | Version      | Repository         |
| :----------------- | :----------: | :----------------- |
| perl-SOAP-Lite     | 1.10         | centreon base      |
| perl-TimeDate      | 2.30         | redhat/centos base |

## Configuration

| Directive | Description | Default value |
| :- | :- | :- |
| clapi_command | Path to the CLAPI binary | `/usr/bin/centreon` |
| clapi_timeout | Time in seconds before CLAPI command execution is considered timed out | `10` |
| clapi_username | CLAPI username | |
| clapi_password | CLAPI username's password | |
| centcore_cmd | Path to centcore command file | `/var/lib/centreon/centcore.cmd` |
| clapi_action_applycfg | CLAPI action used to apply Poller configuration | |
| clapi_generate_config_timeout | Time in seconds before the configuration generation is considered timed out | `180` |
| check_containers_time | Time in seconds between two containers synchronisation | `3600` |

#### Example

```yaml
name: newtest
package: "gorgone::modules::plugins::newtest::hooks"
enable: false
check_containers_time: 3600
clapi_command: /usr/bin/centreon
clapi_username: admin
clapi_password: centreon
clapi_action_applycfg: POLLERRELOAD
centcore_cmd: /var/lib/centreon/centcore.cmd
```

Add an entry in the *containers* table with the following attributes per NWC definition:

| Directive | Description |
| :------------ | :---------- |
| name | Name of the NWC configuration entrie |
| resync_time | Time in seconds between two NWC/Centreon synchronisations |
| nmc_endpoint | Address of the NMC endpoint |
| username | Username to connect to NWC endpoint |
| password | Username's password |
| host_template | Host template used when the daemon creates a host in Centreon |
| host_prefix | Name used when the daemon creates and looks for a host in Centreon |
| service_template | Service template used when the daemon creates a host in Centreon |
| service_prefix | Name used when the daemon creates and looks for a service in Centreon |
| poller_name | Poller used when the daemon creates a host in Centreon |
| list_scenario_status | Informations to look for from the NWC endpoint |

#### Example

```yaml
containers:
  - name: nwc_1
    resync_time: 300
    nmc_endpoint: "http://__NMC_ADDRESS__/nws/managementconsoleservice.asmx"
    username: user
    password: pass
    host_template: generic-active-host-custom
    host_prefix: Robot-%s
    service_template: generic-passive-service-custom
    service_prefix: Scenario-%s
    poller_name: Central
    list_scenario_status: '{ "search": "All", "instances": [] }'
  - name: nwc_2
    resync_time: 600
    nmc_endpoint: "http://__NMC_ADDRESS__/nws/managementconsoleservice.asmx"
    username: user
    password: pass
    host_template: generic-active-host-custom
    host_prefix: Robot-%s
    service_template: generic-passive-service-custom
    service_prefix: Scenario-%s
    poller_name: Central
    list_scenario_status: '{ "search": "Robot", "instances": ["XXXX"] }'
```

## Events

| Event | Description |
| :- | :- |
| NEWTESTREADY | Internal event to notify the core |
| NEWTESTRESYNC | Synchronise NWC and Centreon configuration |

## API

### Force synchronisation between NWC endpoints and Centreon configuration

| Endpoint | Method |
| :- | :- |
| /plugins/newtest/resync | `GET` |

#### Headers

| Header | Value |
| :- | :- |
| Accept | application/json |

#### Example

```bash
curl --request GET "https://hostname:8443/api/plugins/newtest/resync" \
  --header "Accept: application/json"
```

## Troubleshooting

It is possible to get this kind of error in logs of *newtest*:

```bash
die: syntax error at line 1, column 0, byte 0 at /usr/lib/perl5/vendor_perl/5.8.8/i386-linux-thread-multi/XML/Parser.pm line 189
```

It often means that a timeout occur.
