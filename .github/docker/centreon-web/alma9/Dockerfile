ARG REGISTRY_URL=docker.centreon.com/centreon
ARG FROM_IMAGE_VERSION=develop

FROM ${REGISTRY_URL}/centreon-web-dependencies-collect-alma9:${FROM_IMAGE_VERSION}

ARG STABILITY

COPY --chmod=755 ./.github/docker/centreon-web/alma9/entrypoint /usr/share/centreon
COPY --chown=apache:apache ./.github/docker/centreon-web/alma9/configuration /usr/share/centreon/www/install/tmp

COPY ./.github/docker/centreon-web/sql/* /usr/local/src/sql/data/

RUN --mount=type=bind,src=packages-centreon,dst=/tmp/packages-centreon bash -e <<EOF

if [[ "$STABILITY" == "testing" ]]; then
  dnf config-manager --set-disabled 'centreon*unstable*'
elif [[ "$STABILITY" == "stable" ]]; then
  dnf config-manager --set-disabled 'centreon*unstable*' --set-disabled 'centreon*testing*'
fi
dnf config-manager --set-disabled 'centreon-plugin*unstable*' --set-disabled 'centreon-plugin*testing*'

dnf install -y /tmp/packages-centreon/centreon-*.rpm

###################
# install plugins #
###################

dnf install -y centreon-plugin-Operatingsystems-Linux-Snmp nrpe centreon-plugin-Operatingsystems-Linux-Local.noarch nagios-plugins-nrpe
mkdir -p /var/lib/centreon/centplugins/ \
    && chown nrpe: /var/lib/centreon/centplugins/ \
    && sed -i 's/dont_blame_nrpe=0/dont_blame_nrpe=1/' /etc/nagios/nrpe.cfg \
    && echo 'command[check_centreon_plugins]=/usr/lib/centreon/plugins/centreon_linux_local.pl --plugin=$ARG1$ --mode=$ARG2$ $ARG3$' > /etc/nrpe.d/centreon-commands.cfg \
    && systemctl restart nrpe \
    && systemctl enable nrpe \
    && usermod -a -G systemd-journal nrpe \
    && systemctl restart nrpe

sed -i -E 's#^date\.timezone.+#date.timezone = Europe/Paris#g' /etc/php.d/20-timezone.ini

touch /var/log/php-fpm/centreon-error.log
chown apache:apache /var/log/php-fpm/centreon-error.log

systemctl stop gorgoned
systemctl stop centengine
systemctl stop cbd
systemctl stop httpd
systemctl stop php-fpm
systemctl stop snmpd
systemctl stop nrpe

dnf clean all

EOF

EXPOSE 80

ENTRYPOINT ["/usr/share/centreon/container.sh"]

ENV CENTREON_DATASET="1"
