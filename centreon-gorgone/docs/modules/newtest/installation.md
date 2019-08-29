# Installation

## Prerequisites

### Software Recommandations 

The module "gorgonenewtest" has been tested on red-hat 7 with rpms.
Installation on other system is possible but is outside the scope of this document (Debian,...).

| Software           | Version      |
| :----------------- | :----------: |
| Perl SOAP::Lite    | 0.71         |
| Perl Date::Parse   | 1.16         |
| centreon           | 19.04        |
| gorgone            | 1.0          |

### Module location

The module "newtest" daemon must be installed on Centreon Central server. Minimal used ressources are :

* RAM : 128 MB.
* CPU : it depends the number of newtest scenarios.

## Module installation - centos/rhel 7 systems

### Requirements

| Dependency         | Version      | Repository         |
| :----------------- | :----------: | :----------------- |
| perl-SOAP-Lite     | 1.10         | centreon base      |
| perl-TimeDate      | 2.30         | redhat/centos base |

### Newtest installation

"newtest" is an official gorgone module. No installation needed.
