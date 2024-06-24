# Modules

List of the available modules:

* Core
  * [Action](../docs/modules/core/action.md)
  * [Cron](../docs/modules/core/cron.md)
  * [DB Cleaner](../docs/modules/core/dbcleaner.md)
  * [HTTP Server](../docs/modules/core/httpserver.md)
  * [Proxy](../docs/modules/core/proxy.md)
  * [Pull](../docs/modules/core/pull.md)
  * [Register](../docs/modules/core/register.md)
* Centreon
  * [Autodiscovery](../docs/modules/centreon/autodiscovery.md)
  * [Broker](../docs/modules/centreon/statistics.md)
  * [Engine](../docs/modules/centreon/engine.md)
  * [Legacy Cmd](../docs/modules/centreon/legacycmd.md)
  * [Nodes](../docs/modules/centreon/nodes.md)
* Plugins
  * [Newtest](../docs/modules/plugins/newtest.md)
  * [Scom](../docs/modules/plugins/scom.md)

# Module implementation

Each module should have a hook.pm and a class.pm file with some mandatory functions implemented.


## hook.pm

Mainly used for creating the module process(es)
and route events to it each time a new message is received by gorgone.

### const EVENTS []

Array defining all events this module can process. Optionally add API endpoint for events.

### const NAME

### const NAMESPACE

### gently()

Called by gorgone-core when stopping the module.

### register()

### init()

Called by library::loadmodule to initialize the module, it should create a child process as it's not done by gorgone-core.

### routing()

### kill()

### check()

### broadcast()

### create_child()

Not strictly required, but present every time, used to instantiate a new child process by the init() function.\
Inside the child process, a class.pm object is created and the class->run method is started.

## class.pm

This class must inherit the module.pm package.


This object is most of the time a singleton (maybe every time).


It will be created by hook.pm when starting the module.
This is the workhorse that will process all events.

It seems like none of these methods will be called by gorgone-core, so naming is not required to follow this convention.

(Please keep the code base consistent if you make a new module).


### new()

Class constructor

### run()

Will be called by hook.pm. This method should wait for events and dispatch them accordingly.


Uses the EV library to wait for new things to do, either by waiting on the ZMQ file descriptor (fd)

or with a periodic timer.\
Generally waits for new data on ZMQ socket with EV::io(), and call event() when there is.

### event()

Reads data from ZMQ socket, and acts on it, generally by launching an action_* method to process the event.

module.pm parent class has an event() method, so it's not mandatory to implement it.

### action_*()

Method called by event() when a ZMQ message is found.

Method name is in the `action_eventname` form where eventname is the name of the event in lowercase, as defined by the constant in hook.pm  

