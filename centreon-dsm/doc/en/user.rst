.. _user_guide:

##########
User guide
##########

Overview
--------

Centreon module, Dynamic Service Management (Centreon-DSM) is an extension to manage alarms with an eventlogs system. With DSM, Centreon can receive events such as SNMP traps resulting from the detection of a problem and assign events dynamically to a slot defined in Centreon, like a tray events.

A resource has a set number of "slots" (containers) on which alerts will be  assigned (stored). While this event has not been taken into account by a human action, it will remain visible in the interface Centreon. When event is acknowledged, the slot becomes available for new events.

The goal of this module is to overhead the basic trap management system of Centreon. The basic function run with a single service and alarm crashed by successive alarms.


Architecture
------------

The event must be transmitted to the server via an SNMP trap.  The SNMP trap is thus collected by the **snmptrapd daemon**. If reception  parameters are valid (authorized community), then it sends snmptrapd trap SNMP binary SNMPTT. Otherwise, the event is deleted.

Once the SNMP trap has been received, it is sent to the **centreontrapdforward** script which writes the information received in a buffer folder (by default: /var/spool/centreontrapd/).

The **centreontrapd** service reads the information received in the buffer folder and interprets the traps received checking, in the centreon database, the actions necessary to process these events. In Centreon DSM we execute a **special command**.

This special command is executing binary **dsmclient.pl** with arguments. This client will store the new trap in a slot queue that the daemon read every 5 seconds. 

The daemon **dsmd.pl** will search in database "centreon" name slots (pool service liabilities) associated with the host. If no slot is created, the event is deleted. Otherwise, the binary will look if there is at least one free slot. If at least one slot is free, then it will transmit to monitoring engine external commands to change the state of the slot. Otherwise the data will be made no secret pending the release of a slot. A slot is releasable served by paying the liabilities. 


Configure Slots
---------------

In Centreon WebUI, go on: 

::

 Administration > Modules > Dynamic Services 


and click on the **add** link. In order to create or modify  a slot group, please follow the table below in order to understand the role of all parameters.

+------------------------------+--------------------------------+
| Parameters                   | Descriptions                   |
+==============================+================================+
| Name                         | This is the name of the slot   |
|                              | group.                         |
+------------------------------+--------------------------------+
| Description                  | This is the description of the |
|                              | group.                         |
+------------------------------+--------------------------------+
| Host Name                    | The name which host the slots. |
+------------------------------+--------------------------------+
| Service template bas         | The base service template use  |
|                              | to create service slots on the |
|                              | host. This template must have  |
|                              | been a passive template. This  |
|                              | template must be 100 % passive |
|                              | and a custom macro have to be  |
|                              | created on it. The macro is    |
|                              | named "ALARM_ID" and the       |
|                              | default value must be "empty". |
+------------------------------+--------------------------------+
| Number of slots              | The number of slot that        |
|                              | Centreon will create on the    |
|                              | selected host when the form    |
|                              | will be validated.             |
+------------------------------+--------------------------------+
| Slot name prefix             | The prefix is used to give the |
|                              | name of slots. The name will   |
|                              | be follow by a number          |
|                              | incremented from 0 to the      |
|                              | number of slots.               |
+------------------------------+--------------------------------+
| Check command                | This check command is used     |
|                              | when the service has to be     |
|                              | forced in order to free a      |
|                              | slot. The check command must   |
|                              | have to send a ok return code. |
+------------------------------+--------------------------------+
| Status                       | The status of the slot.        |
+------------------------------+--------------------------------+

You can find in the following picture, an example of form.

.. image:: /_static/use/form-slot.png
   :align: center

An example of passive service template is available below:
 
.. image:: /_static/use/form-passive-service.png
   :align: center


.. warning::
   The macro ALARM_ID is mandatory. The default empty is also necessary.


When you validate the form, Centreon will create or update all slot. If you don't have changed any value, you don't have to do other action. Else you have to go to:

::
  
 Configuration > Monitoring Engine 

In order to generate configuration of the poller who have been impacted by the changes. If you don't do that, you will not see your changes appears into Centreon Monitoring UI.

.. image:: /_static/use/conf-test.png
   :align: center

Now the configuration has been generated and validated by Centreon Engine. You can now push the configuration files and restart.

.. image:: /_static/use/conf-restart.png
   :align: center


Configure traps
---------------

The last step is to configure traps that you want to redirect to you slots. This configuration is a little complexe for the moment but we will try to simplify it for the next versions of Centreon DSM.

Edit a SNMP trap that you want to redirect to slots systems. Go on:

::
 
  Configuration > SNMP traps. 

You find the following form: 

.. image:: /_static/use/trap-form.png
   :align: center

In order to redirect alarms to slots, you have to enable **Execute special command** in the form and add the following command into the "special command" field 

::

  /usr/share/centreon/bin/dsmclient.pl -H @HOSTADDRESS@ -o 'Example output : $*' -i 'linkdown' -s 1 -t @TIME@

This command launch for each trap received this command in order to redirect alarms to dsmd daemon. 

This command take some parameters. You can find in the following table the list and the description of each parameter:

+------------------------------+------------------------------------------+
| Parameters                   | Description                              |
+------------------------------+------------------------------------------+
| -H                           | Host address (ip or name) in which you   |
|                              | want to redirect the alarm. You can pass |
|                              | the value @HOSTADDRESS@ in order to keep |
|                              | the same host or you can use whatever you|
|                              | want in order to centralized all alarms  |
|                              | on the same virtual host for example who |
|                              | host all alarms.                         |
+------------------------------+------------------------------------------+
| -o                           | This is the output that dsm will put when|
|                              | the command will submit the result in the|
|                              | good slot. This output can be built will |
|                              | all $* value and with a specific string  |
|                              | that you pass in parameter.              |
+------------------------------+------------------------------------------+
| -i                           | This is the id of the                    |
|                              | alarm. The alarm id can be built with the|
|                              | concatenation of some variables like     |
|                              | "$1-$4". The id enable the possibility to|
|                              | use the option of auto-acknowledgement of|
|                              | alarm when you have the possibility to   |
|                              | create the same id during the opening and|
|                              | the closing treatment of the alarm.      |
+------------------------------+------------------------------------------+
| -s                           | This is the status that you want to pass |
|                              | in parameter to the alarm. You can use   |
|                              | @STATUS@ in order to use the inherited   |
|                              | status build from matching rule system.  |
+------------------------------+------------------------------------------+
| -t                           | This is the time that you want to pass to|
|                              | dsm in order to keep the real trap       |
|                              | reception time.                          |
+------------------------------+------------------------------------------+
| -m                           | This is the list of macros and its values|
|                              | that you want to update during the       |
|                              | treatment of the alarm. Please follow the|
|                              | syntax below:                            |
|                              | macro1=value1|macro2=value2|macro3=value3|
|                              | This function is used to update some     |
|                              | parameters in live on the nagios or      |
|                              | Centreon-Engine core memory without a    |
|                              | restart.                                 |
+------------------------------+------------------------------------------+

Your form should now be like that: 

.. image:: /_static/use/trap-form-2.png
   :align: center

After saving the form, please generate the SNMP traps configuration file. Go on:

::

 Configuration > SNMP Traps > Generate 

Select your poller, select generate and validate the form. 

You can now start the daemon on your server:

::

  /etc/init.d/dsmd start

You should now have DSM activated for all traps you have configured.


Configure Traps links
---------------------

One thing is different compared to Centreon Trap system is that you cannot link directly the service template of the slot to the trap in order to not received x time the trap (x represent here the number of slots). 

You have to link traps to an active service of the resource, for example the Ping service.
