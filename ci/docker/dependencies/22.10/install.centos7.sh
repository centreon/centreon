#!/bin/sh

set -e
set -x

# Clean packages
yum clean all

# Base yum configuration.
echo 'http_caching=none' >> /etc/yum.conf
echo 'assumeyes=1' >> /etc/yum.conf
sed -i -e 's/\(override_install_langs=.*\)/\1:es_ES.utf8:fr_FR.utf8:pt_BR.utf8:pt_PT.utf8/' /etc/yum.conf
yum update glibc-common
yum reinstall glibc-common
localedef -i es_ES -f UTF-8 es_ES.UTF-8
localedef -i fr_FR -f UTF-8 fr_FR.UTF-8
localedef -i pt_BR -f UTF-8 pt_BR.UTF-8
localedef -i pt_PT -f UTF-8 pt_PT.UTF-8

# Install base tools.
yum install curl nc

# Install Centreon repositories.
curl -o centreon-release.rpm "https://yum.centreon.com/standard/22.10/el7/stable/noarch/RPMS/centreon-release-22.10-1.el7.centos.noarch.rpm"
yum install --nogpgcheck centreon-release.rpm
curl -o centreon-release-business.rpm https://yum.centreon.com/centreon-business/1a97ff9985262bf3daf7a0919f9c59a6/22.10/el7/stable/noarch/RPMS/centreon-business-release-22.10-1.el7.centos.noarch.rpm
yum install --nogpgcheck centreon-release-business.rpm
yum-config-manager --enable 'centreon-testing*'
yum-config-manager --enable 'centreon-unstable*'
yum-config-manager --enable 'centreon-business-testing*'
yum-config-manager --enable 'centreon-business-unstable*'
yum-config-manager --enable 'centreon-business-testing-noarch'
yum-config-manager --enable 'centreon-business-unstable-noarch'

# Install remi repository
curl -o remi-release-7.rpm https://rpms.remirepo.net/enterprise/remi-release-7.rpm
yum install remi-release-7.rpm
yum-config-manager --enable remi-php80

# Install Node.js.
curl --silent --location https://rpm.nodesource.com/setup_14.x | bash -
head -n 8 /etc/yum.repos.d/nodesource-el7.repo > /etc/yum.repos.d/nodesource-el7.repo.new
mv /etc/yum.repos.d/nodesource-el7.repo{.new,}
yum install --nogpgcheck -y nodejs
npm cache clean -f
npm install -g n
n 16
npm install -g npm@8.5.0

# Install Software Collections repository.
yum install centos-release-scl

# Install dependencies.
xargs yum install < /tmp/dependencies.txt

# Configuration.
echo 'date.timezone = Europe/Paris' > /etc/php.d/centreon.ini

# Clean packages
yum clean all
