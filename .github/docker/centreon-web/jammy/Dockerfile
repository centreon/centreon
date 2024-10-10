ARG REGISTRY_URL=docker.centreon.com/centreon
ARG FROM_IMAGE_VERSION=develop

FROM ${REGISTRY_URL}/centreon-web-dependencies-jammy:${FROM_IMAGE_VERSION}

ARG STABILITY

ENV DEBIAN_FRONTEND=noninteractive

COPY *.deb /tmp/debs-centreon/

COPY --chmod=755 ./.github/docker/centreon-web/jammy/entrypoint /usr/share/centreon
COPY --chown=www-data:www-data ./.github/docker/centreon-web/jammy/configuration /usr/share/centreon/www/install/tmp

COPY ./.github/docker/centreon-web/sql/* /usr/local/src/sql/data/

RUN --mount=type=bind,src=packages-centreon,dst=/tmp/packages-centreon bash -e <<EOF

if [[ "$STABILITY" == "testing" ]]; then
  for i in $( ls /etc/apt/sources.list.d/centreon*unstable* ); do mv $i $i.disabled; done
elif [[ "$STABILITY" == "stable" ]];
  for i in $( ls /etc/apt/sources.list.d/centreon*{unstable,testing}* ); do mv $i $i.disabled; done
fi

apt-get update

apt-get install -y /tmp/packages-centreon/centreon-*.deb

sed -i -E 's#^date\.timezone.+#date.timezone = Europe/Paris#g' /etc/php/8.2/mods-available/timezone.ini
phpenmod -v 8.2 timezone

touch /var/log/php8.2-fpm-centreon-error.log
chown www-data:www-data /var/log/php8.2-fpm-centreon-error.log

systemctl stop gorgoned
systemctl stop centengine
systemctl stop cbd
systemctl stop apache2
systemctl stop php8.2-fpm

apt-get clean

EOF

EXPOSE 80 3306

ENTRYPOINT ["/usr/share/centreon/container.sh"]

ENV CENTREON_DATASET="1"
