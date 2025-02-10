#!/bin/sh

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
