# Pull

## Description

This module should be used on remote nodes where the connection has to be opened from the node to the Central Gorgone.

## Configuration

No specific configuration.

#### Example

```yaml
name: pull
package: "gorgone::modules::core::pull::hooks"
enable: true
target_type: tcp
target_path: 10.30.2.203:5556
ping: 1
```

## Events

No events.

## API

No API endpoints.
