ARG REGISTRY_URL
ARG VERSION

FROM ${REGISTRY_URL}/centreon-web-dependencies-bullseye:23.04

ARG VERSION

ENV DEBIAN_FRONTEND noninteractive

COPY *.deb /tmp/debs-centreon-gorgone/

RUN bash -e <<EOF

apt-get update

apt-get install -y centreon-gorgone

apt-get install -y /tmp/debs-centreon-gorgone/*.deb

apt-get clean

echo "ok"

sleep 20

ps aux | grep gorgone

cat /var/log/centreon-gorgone/gorgoned.log

echo "ok"

EOF
