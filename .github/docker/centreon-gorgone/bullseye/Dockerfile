ARG REGISTRY_URL
ARG VERSION

FROM ${REGISTRY_URL}/centreon-web-dependencies-bullseye:23.04

ARG VERSION

ENV DEBIAN_FRONTEND noninteractive

COPY *.deb /tmp/debs-centreon-gorgone/

RUN bash -e <<EOF

apt-get update

apt-get install -y centreon-gorgone

dpkg --info /tmp/debs-centreon-gorgone/*.deb
#dpkg -c /tmp/debs-centreon-gorgone/*.deb

apt-get install -y /tmp/debs-centreon-gorgone/*.deb

apt-get clean

EOF
