#!/bin/bash

if ! getent group nagios &>/dev/null; then
  groupadd -r nagios
fi
if ! id nagios &>/dev/null; then
  useradd -r -g nagios -s /sbin/nologin nagios
fi
