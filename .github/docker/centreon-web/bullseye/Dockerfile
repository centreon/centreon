ARG REGISTRY_URL=docker.centreon.com/centreon
ARG FROM_IMAGE_VERSION=develop

FROM ${REGISTRY_URL}/centreon-web-dependencies-bullseye:${FROM_IMAGE_VERSION}

ARG VERSION

ENV DEBIAN_FRONTEND=noninteractive

COPY --chmod=755 ./.github/docker/centreon-web/bullseye/entrypoint /usr/share/centreon
COPY --chown=apache:apache ./.github/docker/centreon-web/bullseye/configuration /usr/share/centreon/www/install/tmp

COPY ./.github/docker/centreon-web/sql/* /usr/local/src/sql/data/

RUN --mount=type=bind,src=packages-centreon,dst=/tmp/packages-centreon bash -e <<EOF

apt-get update

apt-get install -y /tmp/packages-centreon/centreon-*.deb

echo "date.timezone = Europe/Paris" >> /etc/php/8.1/mods-available/centreon.ini

touch /var/log/php8.1-fpm-centreon-error.log
chown www-data:www-data /var/log/php8.1-fpm-centreon-error.log

apt-get clean

EOF

EXPOSE 80 3306

ENTRYPOINT ["/usr/share/centreon/container.sh"]

ENV CENTREON_DATASET="1"
