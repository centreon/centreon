FROM php:8.2-fpm

ARG STABILITY
ARG VERSION
ARG IS_CLOUD

ENV DEBIAN_FRONTEND=noninteractive

RUN --mount=type=secret,id=ARTIFACTORY_INTERNAL_REPO_USERNAME \
    --mount=type=secret,id=ARTIFACTORY_INTERNAL_REPO_PASSWORD \
    --mount=type=bind,src=packages-centreon,dst=/tmp/packages-centreon bash -e <<EOF

if [[ "$STABILITY" == "testing" ]]; then
  for i in \$( ls /etc/apt/sources.list.d/centreon*unstable* ); do mv \$i \$i.disabled; done
elif [[ "$STABILITY" == "stable" ]]; then
  for i in \$( ls /etc/apt/sources.list.d/centreon*{unstable,testing}* ); do mv \$i \$i.disabled; done
fi

ls -lrh /tmp/packages-centreon/*
exit 1
apt-get install -y /tmp/packages-centreon/centreon-web.deb

touch /var/log/php8.2-fpm-centreon-error.log
chown www-data:www-data /var/log/php8.2-fpm-centreon-error.log

EOF

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
    git \
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
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --chmod=755 ./.github/docker/centreon-web/bookworm/entrypoint /usr/share/centreon
COPY --chown=www-data:www-data ./.github/docker/centreon-web/bookworm/configuration /usr/share/centreon/www/install/tmp

COPY ./.github/docker/centreon-web/sql/* /usr/local/src/sql/data/
RUN  ln -nsf /usr/share/centreon /var/www/html

WORKDIR /var/www/html

ENV CENTREON_DATASET="1"
ENV CENTREON_LANG="en_US"
