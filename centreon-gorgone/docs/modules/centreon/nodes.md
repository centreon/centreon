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

| Event | Description |
| :- | :- |
| CENTREONNODESREADY | Internal event to notify the core |

## API

No API endpoints.
