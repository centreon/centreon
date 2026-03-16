ARG REGISTRY_URL=docker.centreon.com/centreon
ARG FROM_IMAGE_VERSION=develop

FROM ${REGISTRY_URL}/centreon-web-dependencies-collect-bookworm:${FROM_IMAGE_VERSION}

ARG STABILITY
ARG MAJOR_VERSION
ARG WEB_MINOR_VERSION

ENV DEBIAN_FRONTEND=noninteractive

COPY *.deb /tmp/debs-centreon/

COPY --chmod=755 ./.github/docker/centreon-web/bookworm/entrypoint /usr/share/centreon
COPY --chown=www-data:www-data ./.github/docker/centreon-web/bookworm/configuration /usr/share/centreon/www/install/tmp

COPY ./.github/docker/centreon-web/sql/* /usr/local/src/sql/data/

RUN --mount=type=bind,src=packages-centreon,dst=/tmp/packages-centreon bash -e <<EOF

if [[ "$STABILITY" == "testing" ]]; then
  for i in \$( ls /etc/apt/sources.list.d/centreon*unstable* ); do mv \$i \$i.disabled; done
elif [[ "$STABILITY" == "stable" ]]; then
  for i in \$( ls /etc/apt/sources.list.d/centreon*{unstable,testing}* ); do mv \$i \$i.disabled; done
fi

apt-get update

if [[ "$STABILITY" == "stable" ]]; then
  apt-get install -y \
    centreon-poller=${MAJOR_VERSION}.${WEB_MINOR_VERSION}-*+deb12u1 \
    centreon-trap=${MAJOR_VERSION}.${WEB_MINOR_VERSION}-*+deb12u1 \
    centreon-web=${MAJOR_VERSION}.${WEB_MINOR_VERSION}-*+deb12u1
else
  apt-get install -y /tmp/packages-centreon/centreon-*.deb
fi

sed -i -E 's#^date\.timezone.+#date.timezone = Europe/Paris#g' /etc/php/8.2/mods-available/timezone.ini
phpenmod -v 8.2 timezone

cat > /etc/php/8.2/mods-available/opcache-centreon.ini <<'OPCACHE'
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=128
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.revalidate_freq=0
opcache.validate_timestamps=0
opcache.fast_shutdown=1
OPCACHE
phpenmod -v 8.2 opcache-centreon

touch /var/log/php8.2-fpm-centreon-error.log
chown www-data:www-data /var/log/php8.2-fpm-centreon-error.log

# Tune php-fpm for CI: ondemand mode to avoid spawning idle workers at startup
sed -i 's/^pm = .*/pm = ondemand/' /etc/php/8.2/fpm/pool.d/*.conf
sed -i 's/^pm\.max_children = .*/pm.max_children = 10/' /etc/php/8.2/fpm/pool.d/*.conf
grep -q 'pm.process_idle_timeout' /etc/php/8.2/fpm/pool.d/www.conf \
  && sed -i 's/^pm\.process_idle_timeout = .*/pm.process_idle_timeout = 10s/' /etc/php/8.2/fpm/pool.d/www.conf \
  || echo 'pm.process_idle_timeout = 10s' >> /etc/php/8.2/fpm/pool.d/www.conf

systemctl stop gorgoned
systemctl stop centengine
systemctl stop cbd
systemctl stop apache2
systemctl stop php8.2-fpm
systemctl stop snmpd

apt-get clean
rm -rf /usr/share/doc /usr/share/man /usr/share/info

EOF

EXPOSE 80

ENTRYPOINT ["/usr/share/centreon/container.sh"]

ENV CENTREON_DATASET="1"
ENV CENTREON_LANG="en_US"
