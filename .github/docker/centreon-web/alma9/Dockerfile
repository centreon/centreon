ARG REGISTRY_URL=docker.centreon.com/centreon
ARG FROM_IMAGE_VERSION=develop

FROM ${REGISTRY_URL}/centreon-web-dependencies-alma9:${FROM_IMAGE_VERSION}

ARG STABILITY

COPY --chmod=755 ./.github/docker/centreon-web/alma9/entrypoint /usr/share/centreon
COPY --chown=apache:apache ./.github/docker/centreon-web/alma9/configuration /usr/share/centreon/www/install/tmp

COPY ./.github/docker/centreon-web/sql/* /usr/local/src/sql/data/

RUN --mount=type=bind,src=packages-centreon,dst=/tmp/packages-centreon bash -e <<EOF

if [[ "$STABILITY" == "testing" ]]; then
  dnf config-manager --set-disabled 'centreon*unstable'
elif [[ "$STABILITY" == "stable" ]];
  dnf config-manager --set-disabled 'centreon*unstable' --set-disabled 'centreon*testing'
fi

dnf install -y /tmp/packages-centreon/centreon-*.rpm

sed -i -E 's#^date\.timezone.+#date.timezone = Europe/Paris#g' /etc/php.d/20-timezone.ini

touch /var/log/php-fpm/centreon-error.log
chown apache:apache /var/log/php-fpm/centreon-error.log

systemctl stop gorgoned
systemctl stop centengine
systemctl stop cbd
systemctl stop httpd
systemctl stop php-fpm

dnf clean all

EOF

EXPOSE 80

ENTRYPOINT ["/usr/share/centreon/container.sh"]

ENV CENTREON_DATASET="1"
