ARG REGISTRY_URL=docker.centreon.com/centreon
ARG FROM_IMAGE_VERSION=develop

FROM ${REGISTRY_URL}/centreon-web-dependencies-collect-bookworm:${FROM_IMAGE_VERSION}

ARG STABILITY

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

apt-get install -y /tmp/packages-centreon/centreon-*.deb

###################
# install plugins #
###################

apt install -y centreon-plugin-Operatingsystems-Linux-Snmp
wget -O- https://apt-key.centreon.com | gpg --dearmor | tee /etc/apt/trusted.gpg.d/centreon.gpg > /dev/null 2>&1
echo "deb https://packages.centreon.com/apt-plugins-stable/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/centreon-plugins.list
apt-key adv --fetch-keys 'https://packages.sury.org/php/apt.gpg' > /dev/null 2>&1
apt update 
apt -y install centreon-nrpe4-daemon centreon-plugin-operatingsystems-linux-local
systemctl restart centreon-nrpe4.service
systemctl enable centreon-nrpe4.service
usermod -a -G systemd-journal centreon-engine
systemctl restart centreon-nrpe4.service
apt install -y centreon-pack-operatingsystems-linux-nrpe4 nagios-nrpe-plugin

sed -i -E 's#^date\.timezone.+#date.timezone = Europe/Paris#g' /etc/php/8.2/mods-available/timezone.ini
phpenmod -v 8.2 timezone

touch /var/log/php8.2-fpm-centreon-error.log
chown www-data:www-data /var/log/php8.2-fpm-centreon-error.log

systemctl stop gorgoned
systemctl stop centengine
systemctl stop cbd
systemctl stop apache2
systemctl stop php8.2-fpm
systemctl stop snmpd
systemctl stop centreon-nrpe4.service

apt-get clean

EOF

EXPOSE 80

ENTRYPOINT ["/usr/share/centreon/container.sh"]

ENV CENTREON_DATASET="1"
