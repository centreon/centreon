# Broker

## Description

This module aims to deal with Centreon Broker daemon.

## Configuration

| Directive | Description | Default value |
| :- | :- | :- |
| cache_dir | Path to the Centreon Broker statistics directory (local) use to store target's broker statistics | `/var/lib/centreon/broker-stats/` |

The configuration needs a cron definition to unsure that statistics collection will be done cyclically.

#### Example

```yaml
name: broker
package: "gorgone::modules::centreon::broker::hooks"
enable: false
cache_dir: "/var/lib/centreon/broker-stats/"
cron:
  - id: broker_stats
    timespec: "*/2 * * * *"
    action: BROKERSTATS
    parameters:
      timeout: 10
      collect_localhost: false
```

## Events

| Event | Description |
| :- | :- |
| BROKERREADY | Internal event to notify the core |
| BROKERSTATS | Collect Centreon Broker statistics files on target |

## API

### Collect Centreon Broker statistics on one or several targets

| Endpoint | Method |
| :- | :- |
| /api/centreon/broker/statistics | `GET` |
| /api/centreon/broker/statistics/:id | `GET` |

#### Headers

| Header | Value |
| :- | :- |
| Accept | application/json |

#### Path variables

| Variable | Description |
| :- | :- |
| id | Identifier of the target |

#### Example

```bash
curl --request POST "https://hostname:8443/api/centreon/broker/statistics" \
  --header "Accept: application/json"
```

```bash
curl --request POST "https://hostname:8443/api/centreon/broker/statistics/2" \
  --header "Accept: application/json"
```
