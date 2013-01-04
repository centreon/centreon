.. _install_from_packages:

==============
Using packages
==============

Merethis provides RPM for its products through Centreon Entreprise
Server (CES). Open source products are freely available from our
repository.

These packages have been successfully tested with CentOS 5 and RedHat 5.

*********************
Centreon installation
*********************

Install a central server
------------------------

This part is to install a central server. DSM server and client will be 
installed on the main server.

Run the commands::

  $ yum install centreon-dsm-server centreon-dsm-client


Install a poller
----------------

This part is to install dsm on a poller. Only client will be installed

Run the commands::

  $ yum install centreon-dsm-client


Base configuration of pollers
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

The communication between a poller and a central server is by MySQL. DSM Client 
need to have access to MySQL server in order to store new alarms. If traps are 
already running on pollers, consider that the conf.pm file is well configured. 

If traps are not working, please modify /etc/centreon/conf.pm file in order to 
configure MySQL access. You may have to grant user Centreon on your MySQL server
in order to give access to database tables from the poller IP. 