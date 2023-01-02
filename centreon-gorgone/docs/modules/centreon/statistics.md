# Broker

## Description

This module aims to deal with statistics collection of Centreon Engine and Broker.

## Configuration

| Directive        | Description                                                                                    | Default value                     |
| :--------------- | :--------------------------------------------------------------------------------------------- | :-------------------------------- |
| broker_cache_dir | Path to the Centreon Broker statistics directory (local) use to store node's broker statistics | `/var/lib/centreon/broker-stats/` |

The configuration needs a cron definition to unsure that statistics collection will be done cyclically.

#### Example

```yaml
name: statistics
package: "gorgone::modules::centreon::statistics::hooks"
enable: false
broker_cache_dir: "/var/lib/centreon/broker-stats/"
cron:
  - id: broker_stats
    timespec: "*/5 * * * *"
    action: BROKERSTATS
    parameters:
      timeout: 10
      collect_localhost: false
```

## Events

| Event           | Description                                      |
| :-------------- | :----------------------------------------------- |
| STATISTICSREADY | Internal event to notify the core                |
| BROKERSTATS     | Collect Centreon Broker statistics files on node |

## API

### Collect Centreon Broker statistics on one or several nodes

| Endpoint                        | Method |
| :------------------------------ | :----- |
| /centreon/statistics/broker     | `GET`  |
| /centreon/statistics/broker/:id | `GET`  |

#### Headers

| Header | Value            |
| :----- | :--------------- |
| Accept | application/json |

#### Path variables

| Variable | Description            |
| :------- | :--------------------- |
| id       | Identifier of the node |

#### Example

```bash
curl --request POST "https://hostname:8443/api/centreon/statistics/broker" \
  --header "Accept: application/json"
```

```bash
curl --request POST "https://hostname:8443/api/centreon/statistics/broker/2" \
  --header "Accept: application/json"
```
