.. _install_from_packages:

==============
Using packages
==============

Centreon provides RPM for its products through CES. Open source products are freely available from our repository.

*********************
Centreon installation
*********************

Install a central server
------------------------

This part is to install **Centreon DSM** on a central server. Centreon DSM server and client will be installed on the main server.

Run the command::

  $ yum install centreon-dsm-server centreon-dsm-client


After installing the rpm, you have to finish the module installation via the web frontend. Go on : 

::

 Administration > Extensions

Install the Centreon-DSM module.

.. image:: /_static/installation/module-setup.png
   :align: center

Your Centreon DSM Module is now installed.

.. image:: /_static/installation/module-setup-finished.png
   :align: center


Install a poller
----------------

This part is to install **Centreon DSM** on a poller. Only client will be installed

Run the command::

  $ yum install centreon-dsm-client

You have now to configure MySQL access in order that your poller is enable to connect to central server with the centreon user to the centreon and centreon_storage database.

Base configuration of pollers
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

In Centreon DSM the communication between a poller and a central server is by MySQL. DSM Client need to have access to MySQL server in order to store new alarms.

.. note::
   The new trap system **centreontrapd** doesn't need an access to the database but Centreon-DSM does.

