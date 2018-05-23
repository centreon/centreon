Welcome to Centreon AWIE's documentation!
=========================================

The Centreon AWIE module has been designed to help users configure several Centreon Web platforms in a faster and easier way, thanks to its import/export mechanism.

From a properly configured source environment, you can use the AWIE module to export chosen objects towards a target environment. Those objects will be replicated.

Centreon AWIE is based on CLAPI commands but its added value is to allow using Centreon Web UI instead of commands lines. But, exactly like CLAPI, AWIE shouldn't be used to export changes on existing Centreon Web objects in the target platform. It is efficient only in creating new objects.  

Contents:

.. toctree::
   :maxdepth: 2

   Installation.rst
   Export.rst
   Import.rst
