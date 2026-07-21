ARG REGISTRY_URL
ARG FROM_IMAGE_VERSION

FROM ${REGISTRY_URL}/centreon-web-trixie:${FROM_IMAGE_VERSION}

COPY --chmod=755 ./.github/docker/install-centreon-module.php /tmp/install-centreon-module.php

COPY --chmod=755 ./.github/docker/centreon-awie/trixie/entrypoint.sh /usr/share/centreon/container.d/80-awie.sh

RUN --mount=type=bind,src=packages-centreon,dst=/tmp/packages-centreon bash -e <<EOF

rm -rf /var/cache/centreon/symfony/*

apt-get update
apt-get install -y /tmp/packages-centreon/*.deb
apt-get clean

EOF
