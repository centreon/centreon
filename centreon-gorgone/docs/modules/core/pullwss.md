# Pullwss

## Description

This module should be used on remote nodes where the connection has to be http/https and must be opened from the node to the Central Gorgone.

## Configuration

| Directive | Description                                                   | Default value |
|:----------|:--------------------------------------------------------------|:--------------|
| ssl       | should connection be made over tls/ssl or not                 | `false`       |
| address   | ip address to connect to                                      |               |
| port      | tcp port to connect to                                        |               |
| token     | token to authenticate to the central gorgone                  |               |
| proxy     | http(s) proxy to access central gorgone                       |               |

#### Example

```yaml
name: pullwss
package: "gorgone::modules::core::pullwss::hooks"
enable: true
ssl: true
port: 8086
token: "1234"
address: 192.168.56.105
```

## Events

| Event          | Description                                             |
|:---------------|:--------------------------------------------------------|
| PULLWSSREADY   | Internal event to notify the core this module is ready. |

## API

No API endpoints.
