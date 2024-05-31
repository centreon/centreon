#!/bin/sh

su apache -s /bin/bash  -c "centreon -u admin -p Centreon\!2021 -a POLLERGENERATE -v 1"
su apache -s /bin/bash  -c "centreon -u admin -p Centreon\!2021 -a CFGMOVE -v 1"
