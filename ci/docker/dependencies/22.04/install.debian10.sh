#!/bin/sh

set -e

# Base apt configuration.
echo 'APT::Get::Assume-Yes "true";' > /etc/apt/apt.conf.d/90assumeyes

# Install base tools.
apt-get update
apt-get install curl gnupg netcat-openbsd ca-certificates

# Trust centreon internal certificate
mkdir /usr/local/share/ca-certificates/int.centreon.com
cp /tmp/ca-centreon-internal.pem /usr/local/share/ca-certificates/int.centreon.com/ca-centreon-internal.crt
update-ca-certificates

# Install internal repository.
curl https://srvi-repo.int.centreon.com/apt/centreon.apt.gpg | apt-key add -
echo 'deb https://srvi-repo.int.centreon.com/apt/internal/22.04 buster main' > /etc/apt/sources.list.d/centreon-internal.list

# Install Node.js repository.
curl -sL https://deb.nodesource.com/setup_16.x | bash -

# Install dependencies.
apt-get update
xargs apt-get install < /tmp/dependencies.txt

# Configuration.
echo 'date.timezone = Europe/Paris' > /etc/php/7.3/apache2/conf.d/50-centreon.ini
echo 'date.timezone = Europe/Paris' > /etc/php/7.3/cli/conf.d/50-centreon.ini
