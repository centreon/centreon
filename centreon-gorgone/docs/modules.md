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
work in progress, may not be complete nor true.

Each module should have a hook.pm and a class.pm file with some mandatory function implemented.

## hook.pm
mainly used for creating the module process(es)
and route event to it each time a new message is received by gorgone.


### const EVENTS []
array defining all events this module can process. Optionally add api endpoint for events.
### const NAME
### const NAMESPACE


### gently()
Called by gorgone-core when stopping the module.
### register()
### init()
called by library::loadmodule to initialise the module. Should create a child process as it's not made by gorgone-core.
### routing()
### kill()
### check()
### broadcast()
### create_child()
Not strictly required, but present every time, used to instantiate a new child process by the init() function.\
Inside the child process a class.pm object is created and the class->run method is started. 

## class.pm
this class must inherit module.pm package.\
this object is most of the time (maybe all ?) a singleton.\
It will be created by hook.pm when starting the module.
This is the workhorse who will process all event.

It seem like none of theeese method will be called by gorgone-core, so naming are not required to follow this convention
(please keep the code base consistent if you make a new module).
### new()
Class constructor

### run()
will be called by hook.pm. this method should wait for event and dispatch them accordingly.
use EV library to wait for new thing to do, either by waiting on the zmq file descriptor (fd) 
or with a periodic timer.\
Generally wait for new data on zmq socket with EV::io(), and call event() when there is.

### event()
read data from zmq socket, and act on it, generally by launching an action_* method to process the event.
module.pm parent class do have an event() method, so it's not mandatory to implement it. 
### action_*()
method called by event() when a zmq message is found.\
Method name is in the form `action_eventname` where eventname is the name of the event in lowercase, as defined by the constant in hook.pm  

