# Nodes

## Description

This module aims to automatically register Poller servers as Gorgone nodes, in opposition to the [register](../core/register.md) module.

For now, nodes can be registered as SSH nodes or ZMQ nodes.

## Configuration

No specific configuration.

#### Example

```yaml
name: nodes
package: "gorgone::modules::centreon::nodes::hooks"
enable: true
```

## Events

| Event              | Description                       |
| :----------------- | :-------------------------------- |
| CENTREONNODESREADY | Internal event to notify the core |

## API

### Synchronize centreon nodes configuration

| Endpoint             | Method |
| :------------------- | :----- |
| /centreon/nodes/sync | `POST` |

#### Headers

| Header       | Value            |
| :----------- | :--------------- |
| Accept       | application/json |
| Content-Type | application/json |

#### Body

No parameters.

#### Example

```bash
curl --request POST "https://hostname:8443/api/centreon/nodes/sync" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data "{}"
```
