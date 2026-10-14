ARG REGISTRY_URL=ghcr.io/centreon/centreon
ARG FROM_IMAGE_VERSION=develop-trixie

FROM ${REGISTRY_URL}/centreon-web-dependencies-collect:${FROM_IMAGE_VERSION}

ARG PHP_VERSION=8.4
ARG STABILITY
ARG IS_CLOUD=false
ARG MAJOR_VERSION
ARG WEB_MINOR_VERSION

ENV DEBIAN_FRONTEND=noninteractive

COPY *.deb /tmp/debs-centreon/

COPY --chmod=755 ./.github/docker/centreon-web/trixie/entrypoint /usr/share/centreon
COPY --chown=www-data:www-data ./.github/docker/centreon-web/trixie/configuration /usr/share/centreon/www/install/tmp

COPY ./.github/docker/centreon-web/sql/* /usr/local/src/sql/data/

RUN --mount=type=bind,src=packages-centreon,dst=/tmp/packages-centreon bash -e <<EOF

if [[ "$STABILITY" == "testing" && "$IS_CLOUD" != "true" ]]; then
  for i in \$( ls /etc/apt/sources.list.d/centreon*unstable* ); do mv \$i \$i.disabled; done
elif [[ "$STABILITY" == "stable" ]]; then
  for i in \$( ls /etc/apt/sources.list.d/centreon*{unstable,testing}* ); do mv \$i \$i.disabled; done
fi

apt-get update

if [[ "$STABILITY" == "stable" ]]; then
  apt-get install -y \
    centreon-poller=${MAJOR_VERSION}.${WEB_MINOR_VERSION}-*+deb13u1 \
    centreon-trap=${MAJOR_VERSION}.${WEB_MINOR_VERSION}-*+deb13u1 \
    centreon-web=${MAJOR_VERSION}.${WEB_MINOR_VERSION}-*+deb13u1
else
  apt-get install -y /tmp/packages-centreon/centreon-*.deb
fi

sed -i -E 's#^date\.timezone.+#date.timezone = Europe/Paris#g' /etc/php/${PHP_VERSION}/mods-available/timezone.ini
phpenmod -v ${PHP_VERSION} timezone

touch /var/log/centreon/prod.web.log
chown www-data:www-data /var/log/centreon/prod.web.log

# Tune php-fpm for CI: ondemand mode to avoid spawning idle workers at startup
sed -i 's/^pm = .*/pm = ondemand/' /etc/php/${PHP_VERSION}/fpm/pool.d/*.conf
sed -i 's/^pm\.max_children = .*/pm.max_children = 10/' /etc/php/${PHP_VERSION}/fpm/pool.d/*.conf
for conf in /etc/php/${PHP_VERSION}/fpm/pool.d/*.conf; do
  if grep -Eq '^[;[:space:]]*pm\.process_idle_timeout\s*=' "\$conf"; then
    sed -Ei 's|^[;[:space:]]*pm\.process_idle_timeout\s*=.*|pm.process_idle_timeout = 10s|' "\$conf"
  else
    echo 'pm.process_idle_timeout = 10s' >> "\$conf"
  fi
done

systemctl stop gorgoned
systemctl stop centengine
systemctl stop cbd
systemctl stop apache2
systemctl stop php${PHP_VERSION}-fpm
systemctl stop snmpd
systemctl stop cron

apt-get clean

EOF

EXPOSE 80

ENTRYPOINT ["/usr/share/centreon/container.sh"]

ENV CENTREON_DATASET="1"
ENV CENTREON_LANG="en_US"
