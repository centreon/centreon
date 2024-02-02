#!/bin/bash

if [ "$1" -lt "1" ]; then
  semodule -r centreon-gorgoned > /dev/null 2>&1 || :
fi
