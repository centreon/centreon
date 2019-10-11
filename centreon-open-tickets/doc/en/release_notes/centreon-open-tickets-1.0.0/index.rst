###########################
Centreon Open Tickets 1.0.0
###########################

Released February 8, 2016

Features
========

Widgets
-------

Centreon Open tickets includes widgets that can be configured in two ways:

The first way will display in the widget the hosts and services in a non-ok 
state. By selecting the objects, it is possible to request the creation of a 
ticket to an ITSM platform. A popup appears to allow the user to write a comment.
After creating tickets, objects disappear from this view.

The second way will display in the widget the hosts and services that have an 
associated ticket.

Configuration
-------------

The configuration menu in Centreon web allows to define provider. A provider
describe the API use to connect to the ITSM platform to create tickets. Many
providers can be defined. When a widget is use in a Centreon web custom view,
the user can select provider to use to create tickets.

In this first version, only email provider can be defined and you have to 
configure the content of the email that will be sent to ITSM platform.
