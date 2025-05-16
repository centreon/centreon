#!/bin/sh

# allow docker networks to use gorgone api
sed -i '/127.0.0.1/a\          - 172.0.0.0\/8' /etc/centreon-gorgone/config.d/40-gorgoned.yaml

systemctl start gorgoned
