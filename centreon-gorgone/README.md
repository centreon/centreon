# Centreon Gorgone

Centreon Gorgone and his "gorgoned" daemon is a lightweight, distributed, modular tasks handler.

It provides a set of actions like:

* Execute commands
* Send files/directories,
* Schedule cron-like tasks,
* Push or execute tasks through SSH.

The daemon can be installed on Centreon environments like Centreon Central, Remote and Poller servers.

It uses ZeroMQ library.

To install it follow the [Getting started](docs/getting_started.md) documentation.

To understand the main principles of Gorgone protocol, follow the [guide](docs/guide.md).

## Modules

The Centreon Gorgone project encloses several built-in modules.

See the full list [here](docs/modules.md).

## API

The HTTP server module exposes a RestAPI.

See how to use it [here](docs/api.md).
