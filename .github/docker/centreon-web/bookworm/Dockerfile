FROM php:8.2-fpm-bookworm

ARG STABILITY
ARG VERSION
ARG IS_CLOUD

ENV DEBIAN_FRONTEND=noninteractive


# Install required dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    sudo \
    default-mysql-client \
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
    gosu \
    rsync \
    jq \
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
    && apt-get autoremove -y \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
           /tmp/* \
           /var/tmp/* \
           /usr/share/doc/* \
           /usr/share/man/* \
           /usr/share/locale/* \
           /var/cache/debconf/* \
           /var/cache/apt/*

RUN groupadd -g 900 centreon &&\
    useradd -u 900 -g centreon -m -r -d /var/spool/centreon -c "Centreon" -s /bin/bash centreon &&\
    groupadd -g 901 centreon-engine &&\
    useradd -u 901 -g centreon-engine -m -r -d /var/lib/centreon-engine -c "Centreon Engine" -s /bin/bash centreon-engine &&\
    groupadd -g 902 centreon-broker &&\
    useradd -u 902 -g centreon-broker -m -r -d /var/lib/centreon-broker -c "Centreon Broker"  -s /bin/bash centreon-broker &&\
    groupadd -g 903 centreon-gorgone &&\
    useradd -u 903 -g centreon-gorgone -m -r -d /var/lib/centreon-gorgone -c "Centreon Gorgone"  -s /bin/bash centreon-gorgone &&\
    usermod -aG centreon www-data && \
    usermod -aG centreon-engine www-data && \
    usermod -aG centreon-broker www-data && \
    usermod -aG www-data centreon && \
    usermod -aG www-data centreon-engine && \
    usermod -aG www-data centreon-broker

RUN --mount=type=secret,id=ARTIFACTORY_INTERNAL_REPO_USERNAME \
    --mount=type=secret,id=ARTIFACTORY_INTERNAL_REPO_PASSWORD \
    --mount=type=bind,src=packages-centreon,dst=/tmp/packages-centreon bash -e <<EOF

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
  echo "deb https://$(cat /run/secrets/ARTIFACTORY_INTERNAL_REPO_USERNAME):$(cat /run/secrets/ARTIFACTORY_INTERNAL_REPO_PASSWORD)@packages.centreon.com/apt-standard-internal/ \$VERSION_CODENAME-${VERSION}-unstable main" | tee -a /etc/apt/sources.list.d/centreon-unstable.list
else
  echo "deb https://packages.centreon.com/apt-standard/ \$VERSION_CODENAME-${VERSION}-stable main" | tee -a /etc/apt/sources.list.d/centreon-stable.list
  echo "deb https://packages.centreon.com/apt-standard/ \$VERSION_CODENAME-${VERSION}-testing main" | tee -a /etc/apt/sources.list.d/centreon-testing.list
  echo "deb https://packages.centreon.com/apt-standard/ \$VERSION_CODENAME-${VERSION}-unstable main" | tee -a /etc/apt/sources.list.d/centreon-unstable.list
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
apt-get autoremove -y
apt-get clean
rm -rf /var/lib/apt/lists/* \
       /tmp/* \
       /var/tmp/* \
       /usr/share/doc/* \
       /usr/share/man/* \
       /usr/share/locale/* \
       /var/cache/debconf/* \
       /var/cache/apt/*

EOF

COPY --chmod=755 ./.github/docker/centreon-web/bookworm/entrypoint /usr/share/centreon
COPY --chown=www-data:www-data ./.github/docker/centreon-web/bookworm/configuration /usr/share/centreon/www/install/tmp
COPY --chown=www-data:www-data ./.github/docker/centreon-web/scripts/ /usr/share/centreon/www/install
COPY --chmod=600 ./centreon/packaging/src/sudoersCentreon /etc/sudoers.d/centreon
COPY --chmod=755 ./.github/docker/centreon-web/init/systemctl /usr/local/bin/systemctl 
COPY ./.github/docker/centreon-web/sql/* /usr/local/src/sql/data/

RUN sed -i 's/User_Alias *CENTREON=%@CENTREON_GROUP@/User_Alias      CENTREON=%centreon/' /etc/sudoers.d/centreon &&\
    ln -s /usr/local/bin/systemctl /usr/bin/systemctl

WORKDIR /var/www/html

ENV CENTREON_DATASET="1"
ENV CENTREON_LANG="en_US"

# Allow users to specify custom PUID/PGID to match host user
# Defaults to the existing centreon user (900:900)
ENV PUID=900
ENV PGID=900

ENTRYPOINT ["/usr/share/centreon/container.sh"]

CMD ["php-fpm"]
