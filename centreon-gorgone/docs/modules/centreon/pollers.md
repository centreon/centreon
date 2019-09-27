# Pollers

## Description

This module aims to automatically register Poller servers as Gorgone targets, in opposition to the [register](../core/register.md) module.

For now, targets will only be registered as SSH targets.

## Configuration

No specific configuration.

#### Example

```yaml
name: pollers
package: "gorgone::modules::centreon::pollers::hooks"
enable: true
```

## Events

| Event | Description |
| :- | :- |
| POLLERSREADY | Internal event to notify the core |

## API

No API endpoints.
