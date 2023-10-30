Provider Guide
##############

Some providers had been developed to use API provided by the ticketting software.
It can happen you need to get/set a value not managed by the provider. So you can:

* open an issue/pull-request on github for the provider
* extend the provider and do your own development

OTRS
----

Introduction
~~~~~~~~~~~~

The provider had been tested with OTRS 4.0 and 5.0. By default, OTRS API webservice is really poor.
To enhance the user experience, OTRS API had been extended.

Installation
~~~~~~~~~~~~

Copy directories ``providers/Otrs/extra/Custom`` and ``providers/Otrs/extra/Kernel``
(can be found on your centreon-web server) on your OTRS Server.

Then copy it:
::

    # cp -Rf providers/Otrs/extra/Custom /opt/otrs/
    # cp -Rf providers/Otrs/extra/Kernel /opt/otrs/

Add following lines in ``/opt/otrs/Kernel/Config.pm`` file:
::

    # ---------------------------------------------------- #
    # insert your own config settings "here"               #
    # config settings taken from Kernel/Config/Defaults.pm #
    # ---------------------------------------------------- #
    # $Self->{SessionUseCookie} = 0;
    # $Self->{CheckMXRecord} = 0;
    $Self->{'GenericInterface::Operation::Module'}->{'Priority::PriorityGet'} =  {
        'ConfigDialog' => 'AdminGenericInterfaceOperationDefault',
        'Controller' => 'Priority',
        'Name' => 'PriorityGet'
    };
    $Self->{'GenericInterface::Operation::Module'}->{'Queue::QueueGet'} =  {
        'ConfigDialog' => 'AdminGenericInterfaceOperationDefault',
        'Controller' => 'Queue',
        'Name' => 'QueueGet'
    };
    $Self->{'GenericInterface::Operation::Module'}->{'State::StateGet'} =  {
        'ConfigDialog' => 'AdminGenericInterfaceOperationDefault',
        'Controller' => 'State',
        'Name' => 'StateGet'
    };
    $Self->{'GenericInterface::Operation::Module'}->{'Type::TypeGet'} =  {
        'ConfigDialog' => 'AdminGenericInterfaceOperationDefault',
        'Controller' => 'Type',
        'Name' => 'TypeGet'
    };
    $Self->{'GenericInterface::Operation::Module'}->{'CustomerUser::CustomerUserGet'} =  {
        'ConfigDialog' => 'AdminGenericInterfaceOperationDefault',
        'Controller' => 'CustomerUser',
        'Name' => 'CustomerUserGet'
    };

Eventually, create ``centreon`` webservice. Connect on your OTRS web interface and
use ``Import web service`` button. Choose the file ``providers/Otrs/extra/export/otrs4/centreon.yml``.

On your centreon-web server, php installation must have curl module.
It will depends of your operating system (It's by default on Centos/Rhel 6).

Configuration
~~~~~~~~~~~~~

Define **Rule name** and select **Otrs**.
A new form appear and define dedicated field linked to the provider:

* **Address** is OTRS server address
* **Path** is the url path of OTRS server
* **Rest link** is the complete path for the webservice (shouldn't be changed)
* **Webservice name** is the name of the webservice used (linked to the name from installation part)
* **Username** and **Password** is the user used

.. image:: /_static/provider_guide/otrs/configure.png
    :align: center

Configure a ``open-tickets`` widget to see if the configuration is well done. Try to open a ticket:

.. image:: /_static/provider_guide/otrs/widget.png
    :align: center

GLPI
----

Introduction
~~~~~~~~~~~~

The provider had been tested with GLPI 0.80.x and 0.90.x. The GLPI plugin webservice 1.6.0 should be installed.

Installation
~~~~~~~~~~~~

Configure the GLPI plugin webservice to accept connections from Centreon Web server.

On your centreon-web server, php installation must have XML-RPC module.
For Centos 6.x:
::

    # yum install php-xmlrpc.x86_64
    # /etc/init.d/httpd reload

Configuration
~~~~~~~~~~~~~

Define **Rule name** and select **Glpi**.
A new form appear and define dedicated field linked to the provider:

* **Address** is GLPI server address
* **Path** is the url path of the webservice
* **Username** and **Password** is the user used

.. image:: /_static/provider_guide/glpi/configure.png
    :align: center


ServiceNow
----------

Introduction
~~~~~~~~~~~~

This provider allows to create a ticket to ServiceNow Incidents.

Configuration
~~~~~~~~~~~~~

Define **Rule name** and select **ServiceNow**.
A new form appear and define dedicated field linked to the provider:

* **Instance name** is ServiceNow instance name
* **OAuth client ID** and **OAuth client secret** is the OAuth client information, you can get the tutorial to create it https://docs.servicenow.com/bundle/jakarta-servicenow-platform/page/administer/security/task/t_SettingUpOAuth.html?title=OAuth_Setup
* **OAuth username** and **OAuth password** is the user used (with ServiceNow role **personalize_choices** and **catalog**).

.. image:: /_static/provider_guide/servicenow/configure.png
    :align: center
