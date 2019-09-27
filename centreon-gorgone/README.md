# Centreon Gorgone

Centreon Gorgone and his "gorgoned" daemon is a lightweight, distributed, modular tasks handler.

It provides a set of actions like:

* Execute commands
* Send files/directories,
* Schedule cron-like tasks,
* Push or execute tasks through SSH.

The daemon can be installed on Centreon environments like Centreon Central, Remote and Poller servers.

It uses ZeroMQ library.

To install it and understand the main principles, follow the [guide](docs/guide.md).

## Modules

The Centreon Gorgone project encloses several built-in modules.

See the list above:

* Core
  * [Action](docs/modules/core/action.md)
  * [Cron](docs/modules/core/cron.md)
  * [DB Cleaner](docs/modules/core/dbcleaner.md)
  * [HTTP Server](docs/modules/core/httpserver.md)
  * [Proxy](docs/modules/core/proxy.md)
  * [Pull](docs/modules/core/pull.md)
  * [Register](docs/modules/core/register.md)
* Centreon
  * [Autodiscovery](docs/modules/centreon/autodiscovery.md)
  * [Broker](docs/modules/centreon/broker.md)
  * [Engine](docs/modules/centreon/engine.md)
  * [Legacy Cmd](docs/modules/centreon/legacycmd.md)
  * [Pollers](docs/modules/centreon/pollers.md)
* Plugins
  * [Newtest](docs/modules/plugins/newtest.md)
  * [Scom](docs/modules/plugins/scom.md)
