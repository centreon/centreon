# Action

## Description

This module aims to execute actions on the server running the Gorgone daemon or remotly using SSH.

## Configuration

| Directive        | Description                                                    | Default value |
| :--------------- | :------------------------------------------------------------- | :------------ |
| command_timeout  | Time in seconds before a command is considered timed out       | `30`          |
| whitelist_cmds   | Boolean to enable commands whitelist                           | `false`       |
| allowed_cmds     | Regexp list of allowed commands                                |               |
| paranoid_plugins | Block centengine restart/reload if plugin dependencies missing | `false`       |

#### Example

```yaml
name: action
package: "gorgone::modules::core::action::hooks"
enable: true
command_timeout: 30
whitelist_cmds: true
allowed_cmds:
  - ^sudo\s+(/bin/)?systemctl\s+(reload|restart)\s+(centengine|centreontrapd|cbd)\s*$
  - ^(sudo\s+)?(/usr/bin/)?service\s+(centengine|centreontrapd|cbd|cbd-sql)\s+(reload|restart)\s*$
  - ^/usr/sbin/centenginestats\s+-c\s+/etc/centreon-engine/centengine\.cfg\s*$
  - ^cat\s+/var/lib/centreon-engine/[a-zA-Z0-9\-]+-stats\.json\s*$
  - ^/usr/lib/centreon/plugins/.*$
  - ^/bin/perl /usr/share/centreon/bin/anomaly_detection --seasonality >> /var/log/centreon/anomaly_detection\.log 2>&1\s*$
  - ^/usr/bin/php -q /usr/share/centreon/cron/centreon-helios\.php >> /var/log/centreon-helios\.log 2>&1\s*$
  - ^centreon
  - ^mkdir
  - ^/usr/share/centreon/www/modules/centreon-autodiscovery-server/script/run_save_discovered_host
  - ^/usr/share/centreon/bin/centreon -u \"centreon-gorgone\" -p \S+ -w -o CentreonWorker -a processQueue$
```

## Events

| Event       | Description                                                                             |
| :---------- | :-------------------------------------------------------------------------------------- |
| ACTIONREADY | Internal event to notify the core                                                       |
| PROCESSCOPY | Process file or archive received from another daemon                                    |
| COMMAND     | Execute a shell command on the server running the daemon or on another server using SSH |

## API

### Execute a command line

| Endpoint             | Method |
| :------------------- | :----- |
| /core/action/command | `POST` |

#### Headers

| Header       | Value            |
| :----------- | :--------------- |
| Accept       | application/json |
| Content-Type | application/json |

#### Body

| Key               | Value                                                    |
| :---------------- | :------------------------------------------------------- |
| command           | Command to execute                                       |
| timeout           | Time in seconds before a command is considered timed out |
| continue_on_error | Behaviour in case of execution issue                     |

```json
[
    {
        "command": "<command to execute>",
        "timeout": "<timeout in seconds>",
        "continue_on_error": "<boolean>"
    }
]
```

#### Example

```bash
curl --request POST "https://hostname:8443/api/core/action/command" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data "[
    {
        \"command\": \"echo 'Test command' >> /tmp/here.log\"
    }
]"
```
