#!/bin/sh

centreon -u admin -p Centreon\!2021 -a POLLERGENERATE -v 1
centreon -u admin -p Centreon\!2021 -a CFGMOVE -v 1
