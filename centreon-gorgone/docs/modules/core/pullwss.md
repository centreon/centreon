# Pullwss

## Description

This module should be used on remote nodes where the connection has to be HTTP/HTTPS and must be opened from the node to the Central Gorgone.

This module requires proxy and register module to be configured on the central Gorgone.
The register Module will allow Gorgone to keep the state of every poller, and find out the connection mode. 
The proxy module has to bind to a tcp port for the pullwss module to connect to.

## Configuration

| Directive | Description                                       | Default value |
|:----------|:--------------------------------------------------|:--------------|
| ssl       | should the connection be made over TLS/SSL or not | `false`       |
| address   | IP address to connect to                          |               |
| port      | TCP port to connect to                            |               |
| token     | token to authenticate to the central gorgone      |               |
| proxy     | HTTP(S) proxy to access central gorgone           |               |

### Example

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
