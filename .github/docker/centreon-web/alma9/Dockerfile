ARG REGISTRY_URL=ghcr.io/centreon/centreon
ARG FROM_IMAGE_VERSION=develop-alma9

FROM ${REGISTRY_URL}/centreon-web-dependencies-collect:${FROM_IMAGE_VERSION}

ARG STABILITY
ARG MAJOR_VERSION
ARG WEB_MINOR_VERSION

COPY --chmod=755 ./.github/docker/centreon-web/alma9/entrypoint /usr/share/centreon
COPY --chown=apache:apache ./.github/docker/centreon-web/alma9/configuration /usr/share/centreon/www/install/tmp

COPY ./.github/docker/centreon-web/sql/* /usr/local/src/sql/data/

RUN --mount=type=bind,src=packages-centreon,dst=/tmp/packages-centreon bash -e <<EOF

if ! grep -q '^tsflags=nodocs' /etc/dnf/dnf.conf; then
  echo 'tsflags=nodocs' >> /etc/dnf/dnf.conf
fi

if [[ "$STABILITY" == "testing" ]]; then
  dnf config-manager --set-disabled 'centreon*unstable*'
elif [[ "$STABILITY" == "stable" ]]; then
  dnf config-manager --set-disabled 'centreon*unstable*' --set-disabled 'centreon*testing*'
fi

if [[ "$STABILITY" == "stable" ]]; then
  dnf install -y \
    centreon-poller-${MAJOR_VERSION}.${WEB_MINOR_VERSION} \
    centreon-trap-${MAJOR_VERSION}.${WEB_MINOR_VERSION} \
    centreon-web-${MAJOR_VERSION}.${WEB_MINOR_VERSION}
else
  dnf install -y /tmp/packages-centreon/centreon-*.rpm
fi

sed -i -E 's#^date\.timezone.+#date.timezone = Europe/Paris#g' /etc/php.d/20-timezone.ini

touch /var/log/centreon/prod.web.log
chown apache:apache /var/log/centreon/prod.web.log

# Tune php-fpm for CI: ondemand mode to avoid spawning idle workers at startup
sed -i 's/^pm = .*/pm = ondemand/' /etc/php-fpm.d/*.conf
sed -i 's/^pm\.max_children = .*/pm.max_children = 10/' /etc/php-fpm.d/*.conf
if grep -Eq '^[;[:space:]]*pm\.process_idle_timeout\s*=' /etc/php-fpm.d/www.conf; then
  sed -Ei 's|^[;[:space:]]*pm\.process_idle_timeout\s*=.*|pm.process_idle_timeout = 10s|' /etc/php-fpm.d/www.conf
else
  echo 'pm.process_idle_timeout = 10s' >> /etc/php-fpm.d/www.conf
fi

systemctl stop gorgoned
systemctl stop centengine
systemctl stop cbd
systemctl stop httpd
systemctl stop php-fpm
systemctl stop crond

dnf clean all

EOF

EXPOSE 80

ENTRYPOINT ["/usr/share/centreon/container.sh"]

ENV CENTREON_DATASET="1"
ENV CENTREON_LANG="en_US"
