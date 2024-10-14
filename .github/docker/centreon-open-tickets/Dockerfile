ARG REGISTRY_URL
ARG FROM_IMAGE_VERSION

FROM ${REGISTRY_URL}/centreon-web-alma9:${FROM_IMAGE_VERSION}

ARG RELEASE_CLOUD
ARG VERSION

COPY --chmod=755 ./.github/docker/install-centreon-module.php /tmp/install-centreon-module.php

COPY --chmod=755 ./.github/docker/centreon-open-tickets/entrypoint.sh /usr/share/centreon/container.d/80-open-tickets.sh

RUN --mount=type=bind,src=packages-centreon,dst=/tmp/packages-centreon bash -e <<EOF

rm -rf /var/cache/centreon/symfony/*

dnf install -y /tmp/packages-centreon/*.rpm
dnf clean all

EOF
