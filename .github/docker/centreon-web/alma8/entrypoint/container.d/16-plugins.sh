#!/bin/sh

dnf install -y centreon-plugin-Operatingsystems-Linux-Snmp nrpe
dnf -y config-manager --set-enabled 'crb'
mkdir -p /var/lib/centreon/centplugins/
chown nrpe: /var/lib/centreon/centplugins/
sed -i 's/dont_blame_nrpe=0/dont_blame_nrpe=1/' /etc/nagios/nrpe.cfg
echo 'command[check_centreon_plugins]=/usr/lib/centreon/plugins/centreon_linux_local.pl --plugin=$ARG1$ --mode=$ARG2$ $ARG3$' > /etc/nrpe.d/centreon-commands.cfg
systemctl restart nrpe
systemctl enable nrpe
usermod -a -G systemd-journal nrpe
systemctl restart nrpe
dnf install -y centreon-plugin-Operatingsystems-Linux-Local.noarch nagios-plugins-nrpe
