# Engine Configuration — auto-creation on poller creation

When a poller is created via the API (`POST /configuration/monitoring-servers/pollers`), the platform automatically provisions its Engine configuration rows: `cfg_nagios`, `cfg_nagios_logger`, and `cfg_nagios_broker_module`.

---

## Table of contents

1. [Flow](#1-flow)
2. [Default values](#2-default-values)
3. [Domain model](#3-domain-model)
4. [Transactional guarantee](#4-transactional-guarantee)
5. [File reference](#5-file-reference)

---

## 1. Flow

```
CreatePollerCommandHandler
  ├─ PollerRepository::add()              ← INSERT nagios_server
  └─ EventBus::fire(PollerCreated)
       └─ CreatePollerConfigurationsEventHandler
            └─ CommandBus::execute(CreateEngineConfigurationCommand)
                 └─ CreateEngineConfigurationCommandHandler
                      └─ EngineConfigurationRepository::add()
                           ├─ INSERT cfg_nagios
                           ├─ INSERT cfg_nagios_logger
                           └─ INSERT cfg_nagios_broker_module
```

The event handler is named plural (`CreatePollerConfigurations`) — future tickets will add broker and gorgone configuration dispatches in the same handler.

---

## 2. Default values

### Rationale

The legacy UI has two creation paths with different defaults:

- **Add (Advanced)** — almost everything `NULL` or `0`, requires manual configuration.
- **Wizard** — functional defaults, poller is immediately usable.

The API uses the **Wizard** defaults with four adjustments:

| Adjustment | Value | Reason |
|---|---|---|
| `check_service_freshness` | `1` | Engine default is `true`; Wizard set it to `0` by mistake. |
| `enable_flap_detection` | `1` | Engine default is `true`; Wizard set it to `0`. |
| Logger levels | Engine-native (`info` / `err`) | Wizard used legacy Centreon defaults (`warning` everywhere). Engine-native levels are more appropriate. |
| Deprecated fields | omitted (remain `NULL`) | `auto_rescheduling_interval`, `auto_rescheduling_window`, `log_initial_states`, `passive_host_checks_are_soft` — no longer used by Engine. |

### cfg_nagios (key fields)

| Field | Default | Source |
|---|---|---|
| `nagios_name` | poller name | Wizard |
| `nagios_activate` | `1` | Wizard |
| `broker_module_cfg_file` | `/etc/centreon-broker/{slug}-module.json` | Wizard (slug = lowercase, spaces → hyphens) |
| `check_service_freshness` | `1` | **API override** |
| `check_host_freshness` | `0` | Wizard |
| `enable_notifications` | `1` | Wizard |
| `enable_flap_detection` | `1` | **API override** |

All other boolean fields follow the Wizard defaults. Non-boolean defaults (thresholds, intervals, paths) are defined as constants on their respective VOs.

### cfg_nagios_logger

| Category | Level |
|---|---|
| config, events, checks, process, external_command | `info` |
| functions, notifications, eventbroker, commands, downtimes, comments, macros, runtime, otl | `err` |

Logger type: `file`.

### cfg_nagios_broker_module

Single row: `/usr/lib64/centreon-engine/externalcmd.so` (constant `BrokerOptions::MODULE_PATH`).

---

## 3. Domain model

The `EngineConfiguration` aggregate lives in `MonitoringConfiguration/Domain/Aggregate/EngineConfiguration/`.

The `createDefault(PollerId, string $pollerName)` static factory owns all defaults — no caller needs to specify them.

Configuration is split into 7 cohesive VOs, each with constructor defaults:

| VO | Covers |
|---|---|
| `CheckExecutionOptions` | notifications, host/service checks, event handlers, dependency checks, orphaned checks |
| `FreshnessAndFlapOptions` | freshness checks, flap detection, thresholds |
| `LoggingOptions` | syslog, log flags, debug settings + `EngineLoggerConfiguration` (15 log levels) |
| `RetentionOptions` | state retention, retention file, update interval |
| `SchedulingOptions` | inter-check delay, spread, timeouts, reaper frequency, cache horizons |
| `BrokerOptions` | event broker options, broker module path, broker config file path |
| `MiscOptions` | cfg_dir, log file, status file, command file, date format, illegal chars, macros filter |

Non-boolean defaults are defined as `public const` on each VO to avoid magic values in constructors.

---

## 4. Transactional guarantee

The `command.bus` wraps every handler in `DoctrineTransactionMiddleware`. The `event.bus` has no transaction middleware — events dispatch synchronously within the outer command bus transaction.

If the engine configuration INSERT fails:

1. Inner `DoctrineTransactionMiddleware` (from the `CreateEngineConfigurationCommand` dispatch) rolls back its savepoint.
2. The exception propagates through the event bus (no transaction handling).
3. Outer `DoctrineTransactionMiddleware` (from the `CreatePollerCommand` dispatch) rolls back the entire transaction — including the poller INSERT.

Result: the poller is not created. This is tested in `PollerCreatedEngineConfigurationTest::testPollerIsRolledBackWhenEngineConfigurationCreationFails`.

---

## 5. File reference

| Layer | Files |
|---|---|
| Domain | `EngineConfiguration.php`, `EngineConfigurationId.php`, `CheckExecutionOptions.php`, `FreshnessAndFlapOptions.php`, `LoggingOptions.php`, `RetentionOptions.php`, `SchedulingOptions.php`, `BrokerOptions.php`, `MiscOptions.php`, `EngineLoggerConfiguration.php`, `LogLevelEnum.php`, `LoggerTypeEnum.php` |
| Domain (repository) | `EngineConfigurationRepository.php` |
| Application | `CreateEngineConfigurationCommand.php`, `CreateEngineConfigurationCommandHandler.php`, `CreatePollerConfigurationsEventHandler.php` |
| Infrastructure | `DbalEngineConfigurationRepository.php` |
| Tests | `CreateEngineConfigurationCommandHandlerTest.php`, `CreatePollerConfigurationsEventHandlerTest.php`, `DbalEngineConfigurationRepositoryTest.php`, `PollerCreatedEngineConfigurationTest.php`, `FakeEngineConfigurationRepository.php` |

All files under `src/App/MonitoringConfiguration/` and `tests/php/App/MonitoringConfiguration/`.
