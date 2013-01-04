.. _user_guide:

##########
User guide
##########

Overview
--------

Centreon module, Dynamic Service Management (Centreon-DSM) is an extention
to manage alarms with a eventlogs system. With DSM, Centreon can receive events 
as SNMP traps resulting from the detection of a problem and to assign these 
events to the slots dynamicaly defined in Centreon, the like a tray events.

A resource has a set number of "slots" (containers) on which alerts will be 
assigned (stored). While this event has not been taken into account by a human 
action, it will remain visible in the interface Centreon once event acknowledged, 
the slot becomes available for new events.

The goal of this module is to overhead the basic trap management system of
Centreon. The basic function run with a single service and alarm crashed by
successive alarms.


Architecture
------------

The event must be transmitted to the server via an SNMP trap Centreon format 
v2c. The SNMP trap is thus collected by the snmptrapd daemon. If reception 
parameters are valid (authorized community), then it sends snmptrapd trap
SNMP binary SNMPTT. Otherwise, the event is deleted.

SNMPTT will check these configuration files (in the directory "/etc/snmp/centreon_traps/") 
as the definition of the SNMP trap exists. Otherwise, the event is deleted. 
If a definition exists for the OID associated the event, it is then transmitted 
to the binary "centTrapHandler-2x."

Binary "centTrapHandler-2x" will then search in database "centreon" if the 
OID of the SNMP trap is attached to at least one service host that generated 
the SNMP trap. If this is not the case, the event is deleted, otherwise binary 
"centTrapHandler-2x" will then see if a special order ("Special Command") 
is associated with the definition of the SNMP trap.

This special command is executing binary "dsmclient.pl" with these arguments. 
This client will store the new trap in a slot queue that the daemon read every 
5 seconds. 

The daemon dsmd.pl will search in database "centreon" name slots (pool service 
liabilities) associated with the host. If no slot is created, the event 
is deleted. Otherwise, the binary will look if there is at least one free 
slot. If at least one slot is free, then it will transmit to Nagios external 
commands to change the state of the slot. Otherwise the data will be made no 
secret pending the release of a slot. A slot is releasable served by paying 
the liabilities. 

''ToBeCompleted''


Configure Slots
---------------




Configure traps
---------------






