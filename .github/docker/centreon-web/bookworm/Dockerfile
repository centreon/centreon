FROM php:8.2-fpm

ARG STABILITY
ARG VERSION
ARG IS_CLOUD

ENV DEBIAN_FRONTEND=noninteractive


# Install required dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libldap2-dev \
    libsnmp-dev \
    libicu-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    wget \
    gpg \
    && docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu \
    && docker-php-ext-install \
        pdo_mysql \
        gd \
        xml \
        mbstring \
        ldap \
        snmp \
        intl \
        curl \
        zip \
        gettext \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN --mount=type=secret,id=ARTIFACTORY_INTERNAL_REPO_USERNAME \
    --mount=type=secret,id=ARTIFACTORY_INTERNAL_REPO_PASSWORD \
    --mount=type=bind,src=packages-centreon,dst=/tmp/packages-centreon bash -e <<EOF

groupadd -g 6000 centreon
useradd -u 6000 -g centreon -m -r -d /var/spool/centreon -c "Centreon" -s /bin/bash centreon
groupadd -g 6001 centreon-engine
useradd -u 6001 -g centreon-engine -m -r -d /var/lib/centreon-engine -c "Centreon Engine" -s /bin/bash centreon-engine
groupadd -g 6002 centreon-broker
useradd -u 6002 -g centreon-broker -m -r -d /var/lib/centreon-broker -c "Centreon Broker"  -s /bin/bash centreon-broker
groupadd -g 6003 centreon-gorgone
useradd -u 6003 -g centreon-gorgone -m -r -d /var/lib/centreon-gorgone -c "Centreon Gorgone"  -s /bin/bash centreon-gorgone
usermod -aG centreon www-data
#usermod -aG centreon-engine www-data
#usermod -aG centreon-broker www-data

echo 'Acquire::Retries "10"; \
Acquire::https::Timeout "300"; \
Acquire::http::Timeout "300"; \
APT::Install-Recommends "false"; \
APT::Install-Suggests "false";' > /etc/apt/apt.conf.d/99custom

VERSION_CODENAME=\$(
  . /etc/os-release
  echo \$VERSION_CODENAME
)

if [[ "${IS_CLOUD}" == "true" ]]; then
  echo "deb https://$(cat /run/secrets/ARTIFACTORY_INTERNAL_REPO_USERNAME):$(cat /run/secrets/ARTIFACTORY_INTERNAL_REPO_PASSWORD)@packages.centreon.com/apt-standard-internal-unstable/ \$VERSION_CODENAME main" | tee -a /etc/apt/sources.list.d/centreon-unstable.list
else
  echo "deb https://packages.centreon.com/apt-standard-${VERSION}-stable/ \$VERSION_CODENAME main" | tee -a /etc/apt/sources.list.d/centreon-stable.list
  echo "deb https://packages.centreon.com/apt-standard-${VERSION}-testing/ \$VERSION_CODENAME main" | tee -a /etc/apt/sources.list.d/centreon-testing.list
  echo "deb https://packages.centreon.com/apt-standard-${VERSION}-unstable/ \$VERSION_CODENAME main" | tee -a /etc/apt/sources.list.d/centreon-unstable.list
fi
echo "deb https://packages.centreon.com/apt-plugins-stable/ \$VERSION_CODENAME main" | tee -a /etc/apt/sources.list.d/centreon-plugins-stable.list
echo "deb https://packages.centreon.com/apt-plugins-testing/ \$VERSION_CODENAME main" | tee -a /etc/apt/sources.list.d/centreon-plugins-testing.list
echo "deb https://packages.centreon.com/apt-plugins-unstable/ \$VERSION_CODENAME main" | tee -a /etc/apt/sources.list.d/centreon-plugins-unstable.list
wget -O- https://packages.centreon.com/api/security/keypair/APT-GPG-KEY/public | gpg --dearmor | tee /etc/apt/trusted.gpg.d/centreon.gpg > /dev/null 2>&1

if [[ "$STABILITY" == "testing" ]]; then
  for i in \$( ls /etc/apt/sources.list.d/centreon*unstable* ); do mv \$i \$i.disabled; done
elif [[ "$STABILITY" == "stable" ]]; then
  for i in \$( ls /etc/apt/sources.list.d/centreon*{unstable,testing}* ); do mv \$i \$i.disabled; done
fi

apt-get update
apt-get install -y /tmp/packages-centreon/centreon-web*.deb


mkdir -p /var/log/centreon/ /usr/lib/centreon/plugins /usr/lib64/centreon-connector /usr/share/centreon/lib/centreon-broker
touch /var/log/php8.2-fpm-centreon-error.log /var/log/centreon/centreon-web.log
chown  -R www-data:www-data /var/cache/centreon/ /var/log/centreon/ /var/log/php8.2-fpm-centreon-error.log /var/log/centreon/centreon-web.log 
chmod 1230 /var/log/centreon/centreon-web.log

EOF

COPY --chmod=755 ./.github/docker/centreon-web/bookworm/entrypoint /usr/share/centreon
COPY --chown=www-data:www-data ./.github/docker/centreon-web/bookworm/configuration /usr/share/centreon/www/install/tmp

COPY ./.github/docker/centreon-web/sql/* /usr/local/src/sql/data/
RUN mv /usr/share/centreon/ /var/www/html/ &&\
    chown -R www-data:www-data /var/www/html/centreon /etc/centreon &&\
    ln -s /var/www/html/centreon/ /usr/share/centreon

WORKDIR /var/www/html

ENV CENTREON_DATASET="1"
ENV CENTREON_LANG="en_US"

