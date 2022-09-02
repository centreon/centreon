#!/bin/sh

set -e
set -x

# Clean packages
dnf clean all

# Base yum configuration.
echo 'http_caching=none' >> /etc/yum.conf
echo 'assumeyes=1' >> /etc/yum.conf
dnf install dnf-plugins-core
dnf install langpacks-en glibc-all-langpacks

# Install base tools.
dnf install curl nc

# Install remi repository
curl -o remi-release-8.rpm https://rpms.remirepo.net/enterprise/remi-release-8.rpm
dnf install remi-release-8.rpm
dnf config-manager --set-enabled 'powertools'

# Install Centreon repositories.
curl -o centreon-release.rpm "https://yum.centreon.com/standard/22.04/el8/stable/noarch/RPMS/centreon-release-22.04-3.el8.noarch.rpm"
dnf install --nogpgcheck centreon-release.rpm
curl -o centreon-release-business.rpm https://yum.centreon.com/centreon-business/1a97ff9985262bf3daf7a0919f9c59a6/22.04/el8/stable/noarch/RPMS/centreon-business-release-22.04-3.el8.noarch.rpm
dnf install --nogpgcheck centreon-release-business.rpm
dnf config-manager --set-enabled 'centreon-testing*'
dnf config-manager --set-enabled 'centreon-unstable*'
dnf config-manager --set-enabled 'centreon-business-testing*'
dnf config-manager --set-enabled 'centreon-business-unstable*'
dnf config-manager --set-enabled 'centreon-business-testing-noarch'
dnf config-manager --set-enabled 'centreon-business-unstable-noarch'
# Switch AppStream to install php8.0
dnf module reset php
dnf module install php:remi-8.0

# Install Node.js.
curl --silent --location https://rpm.nodesource.com/setup_16.x | bash -

# Install dependencies.
xargs yum install < /tmp/dependencies.txt

# Configuration.
echo 'date.timezone = Europe/Paris' > /etc/php.d/centreon.ini

# Clean packages
dnf clean all
