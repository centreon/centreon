# Microsoft SCOM

## Description

This module aims to retreive alerts from Microsoft SCOM and store them in Centreon DSM slots.

## Configuration

| Directive | Description | Default value |
| :- | :- | :- |
| dsmclient_bin | Path to the Centreon DSM client | `/usr/share/centreon/bin/`dsmclient.pl|
| centcore_cmd | Path to centcore command file | `/var/lib/centreon/centcore.cmd` |
| check_containers_time | Time in seconds between two containers synchronisation | `3600` |

#### Example

```yaml
name: scom
package: "gorgone::modules::plugins::scom::hooks"
enable: false
check_containers_time: 3600
dsmclient_bin: /usr/share/centreon/bin/dsmclient.pl
centcore_cmd: /var/lib/centreon/centcore.cmd
```

Add an entry in the *containers* table with the following attributes per SCOM server:

| Directive | Description |
| :------------ | :---------- |
| name | Name of the SCOM configuration entrie |
| api_version | SCOM API version |
| url | URL of the SCOM API |
| username | Username to connect to SCOM API |
| password | Username's password |
| httpauth | API authentication type |
| resync_time | Time in seconds between two SCOM/Centreon synchronisations |
| dsmhost | Name of the Centreon host to link alerts to |
| dsmslot | Name of the Centreon DSM slots to link alerts to |
| dsmmacro | Name of the Centreon DSM macro to fill |
| dsmalertmessage | Output template for Centreon DSM service when there is an alert |
| dsmrecoverymessage | Output template for Centreon DSM service when alert is recovered |
| curlopts | Options table for Curl library |

#### Example

```yaml
containers:
  - name: SCOM_prod
    api_version: 2016
    url: "http://scomserver/api/"
    username: user
    password: pass
    httpauth: basic
    resync_time: 300
    dsmhost: ADH3
    dsmslot: Scom-%
    dsmmacro: ALARM_ID
    dsmalertmessage: "%{monitoringobjectdisplayname} %{name}"
    dsmrecoverymessage: slot ok
    curlopts:
      CURLOPT_SSL_VERIFYPEER: 0
```

## Events

| Event | Description |
| :- | :- |
| SCOMREADY | Internal event to notify the core |
| SCOMRESYNC | Synchronise SCOM and Centreon realtime |

## API

### Force synchronisation between SCOM endpoints and Centreon realtime

| Endpoint | Method |
| :- | :- |
| /plugins/scom/resync | `GET` |

#### Headers

| Header | Value |
| :- | :- |
| Accept | application/json |

#### Example

```bash
curl --request GET "https://hostname:8443/api/plugins/scom/resync" \
  --header "Accept: application/json"
```
