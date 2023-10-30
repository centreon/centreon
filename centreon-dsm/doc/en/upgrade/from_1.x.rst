.. _install_from_packages:

===============
From 1.x to 2.x
===============

*****************
From RPM packages
*****************

Merethis provides RPM for its products through CES. Open source products are freely available from our repository.

****************
Centreon upgrade
****************

Upgrade a central server
------------------------

This part is a how to upgrade a **Centreon DSM** for a central server. 

Centreon DSM server and client will be installed on the main server. 

The version 1.x of Centreon DSM doesn't contain a server and a client: the client embed the intelligence and the server is just a cron task. 

This organization was a problem due of load problems. That's why we completely change this module. That's why this version is a major version.

To upgrade run the following command::

  $ yum upgrade centreon-dsm-server centreon-dsm-client


After installing the rpm, you have to finish the module installation via the web frontend. Go on:

::

 Administration > Modules 

Install the Centreon-DSM module.

.. image:: /_static/installation/module-setup.png
   :align: center

Your Centreon DSM Module is now installed.

.. image:: /_static/installation/module-setup-finished.png
   :align: center

In order to migrate the trap configuration, you have to change all specific commands configured on your specific traps.
On each specific commands rename the following path: 

::

  /usr/share/centreon/bin/snmpTrapDyn.pl 

by 

::

  /usr/share/centreon/bin/dsmclient.pl

All parameters are the same.


Install a poller
----------------

This part is a howto install Centreon DSM on a poller. Only client will be installed on a poller.

To install centreon DSM, run the following commands:

::

  $ yum erase centreon-dsm
  $ yum install centreon-dsm-client

You have now to configure MySQL access in order that your poller is enable to connect to central server with the centreon user to the centreon and centreon_storage database.

In order to do that, connect you on MySQL with root user and launch the following request:

::

  $ GRANT SELECT ON `centreon`.`*` TO 'centreon'@'POLLER_IP';
  $ GRANT SELECT, INSERT, UPDATE ON `centreon_storage`.`*` TO 'centreon'@'POLLER_IP';

Now, from your poller, try to connect with MySQL client. If you have problem to configure MySQL connection,
please refer to the database documentation: http://dev.mysql.com/doc/refman/5.5/en/grant.html


Base configuration of pollers
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

The communication between a poller and a central server is by MySQL. DSM Client need to have access to MySQL server in order to store new alarms.

.. note::
   The new trap system **centreontrapd** doesn't need an access to the database but Centreon-DSM does.

