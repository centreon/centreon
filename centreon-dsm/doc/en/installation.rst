############
Installation
############

===================
On a central server
===================

This part is to install **Centreon DSM** on a central server. Centreon DSM server
and client will be installed on the main server.

Run the command::

    # yum install centreon-dsm-server centreon-dsm-client

After installing the rpm, you have to finish the module installation through the
web frontend. Go to **dministration > Extensions > Manager** menu and search
**dsm**:

.. image:: /_static/installation/module-setup.png
   :align: center

Your Centreon DSM Module is now installed.

.. image:: /_static/installation/module-setup-finished.png
   :align: center

===========
On a poller
===========

This part is to install **Centreon DSM** on a poller. Only client will be
installed.

Run the command::

    # yum install centreon-dsm-client

You now have to create an access from the poller to the DBMS server on the
**centreon_storage** database.
