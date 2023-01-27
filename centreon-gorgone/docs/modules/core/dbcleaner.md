# DB Cleaner

## Description

This module aims to maintain the Gorgone daemon database by purging entries cyclically.

The module is loaded by default. Adding it to the configuration will overload daemon default configuration.

## Configuration

| Directive           | Description                                                              | Default value |
| :------------------ | :----------------------------------------------------------------------- | :------------ |
| purge_sessions_time | Time in seconds before deleting sessions in the `gorgone_identity` table | `3600`        |
| purge_history_time  | Time in seconds before deleting history in the `gorgone_history` table   | `604800`      |

#### Example

```yaml
name: dbcleaner
package: "gorgone::modules::core::dbcleaner::hooks"
enable: true
purge_sessions_time: 3600
purge_history_time: 604800
```

## Events

| Event          | Description                       |
| :------------- | :-------------------------------- |
| DBCLEANERREADY | Internal event to notify the core |

## API

No API endpoints.
