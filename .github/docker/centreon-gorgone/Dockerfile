ARG REGISTRY_URL

FROM ${REGISTRY_URL}/centreon-web-dependencies-bullseye:23.04

ARG VERSION

ENV DEBIAN_FRONTEND noninteractive

RUN bash -e <<EOF

apt-get update

apt-get install centreon-gorgone

EOF

COPY *.deb /tmp/debs-centreon/

RUN bash -e <<EOF

apt-get update

apt-get install -y /tmp/debs-centreon/*.deb

EOF
