#!/bin/sh

# allow docker networks to use gorgone api
sed -i '/127.0.0.1/a\          - 172.0.0.0\/8' /etc/centreon-gorgone/config.d/40-gorgoned.yaml

# enable the proxy module's httpserver: required for the "create poller"
# API/UI to generate an install command. Idempotent: skip if already present.
if ! awk '/gorgone::modules::core::proxy::hooks/{f=1} f && /httpserver:/{found=1} END{exit !found}' /etc/centreon-gorgone/config.d/40-gorgoned.yaml; then
  awk '
    /gorgone::modules::core::proxy::hooks/ { in_proxy=1 }
    in_proxy && /enable: true/ && !done {
      print
      print "      httpserver:"
      print "        enable: true"
      print "        ssl: false"
      print "        address: \"localhost\""
      print "        port: 8087"
      done=1
      in_proxy=0
      next
    }
    { print }
  ' /etc/centreon-gorgone/config.d/40-gorgoned.yaml > /tmp/40-gorgoned.yaml.new \
    && mv /tmp/40-gorgoned.yaml.new /etc/centreon-gorgone/config.d/40-gorgoned.yaml
fi

echo "Starting Gorgone daemon..."
systemctl start gorgoned
echo "Gorgone daemon started."
