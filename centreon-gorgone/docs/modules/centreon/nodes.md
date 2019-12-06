# Nodes

## Description

This module aims to automatically register Poller servers as Gorgone targets, in opposition to the [register](../core/register.md) module.

For now, targets can be registered as SSH targets or ZMQ targets.

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
