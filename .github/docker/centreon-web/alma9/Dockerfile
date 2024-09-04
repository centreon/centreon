ARG REGISTRY_URL
ARG VERSION
ARG DEPENDENCIES_TAG

FROM ${REGISTRY_URL}/centreon-web-dependencies-alma9:${DEPENDENCIES_TAG}
#FROM ${REGISTRY_URL}/centreon-web-dependencies-alma9:${VERSION}

ARG VERSION

# COPY *.rpm /tmp/rpms-centreon/

COPY --chmod=755 ./.github/docker/centreon-web/alma9/entrypoint /tmp/entrypoint
COPY --chown=apache:apache ./.github/docker/centreon-web/alma9/configuration /tmp/configuration

COPY ./.github/docker/centreon-web/sql/* /usr/local/src/sql/data/

RUN --mount=type=bind,src=rpms-centreon,dst=/tmp/rpms-centreon bash -e <<EOF

rm -f /tmp/rpms-centreon/centreon{,-central,-mariadb,-mysql}-${VERSION}*.rpm
dnf install -y /tmp/rpms-centreon/centreon-*.rpm

mv /tmp/entrypoint/container.sh /usr/share/centreon/container.sh
mv /tmp/entrypoint/container.d /usr/share/centreon/container.d
chmod -R 755 /usr/share/centreon/container.*

mv /tmp/configuration/* /usr/share/centreon/www/install/tmp/
chown -R apache:apache /usr/share/centreon/www/install/tmp

echo 'date.timezone = Europe/Paris' > /etc/php.d/centreon.ini

touch /var/log/php-fpm/centreon-error.log
chown apache:apache /var/log/php-fpm/centreon-error.log

systemctl stop gorgoned
systemctl stop centengine
systemctl stop cbd
systemctl stop httpd
systemctl stop php-fpm

dnf clean all

EOF

EXPOSE 80 3306

ENTRYPOINT ["/usr/share/centreon/container.sh"]

ENV CENTREON_DATASET="1"
