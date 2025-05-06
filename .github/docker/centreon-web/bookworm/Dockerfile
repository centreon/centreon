FROM debian:bookworm

ARG STABILITY
ARG VERSION
ARG IS_CLOUD

ENV DEBIAN_FRONTEND=noninteractive

RUN --mount=type=secret,id=ARTIFACTORY_INTERNAL_REPO_USERNAME \
    --mount=type=secret,id=ARTIFACTORY_INTERNAL_REPO_PASSWORD \
    bash -e <<EOF

######################################
# additional configuration for apt   #
######################################

echo '
Acquire::Retries "10";
Acquire::https::Timeout "300";
Acquire::http::Timeout "300";
APT::Install-Recommends "false";
APT::Install-Suggests "false";
' > /etc/apt/apt.conf.d/99custom

######################################
# install and configure repositories #
######################################

apt-get update

apt-get install -y wget gnupg2 ca-certificates

VERSION_CODENAME=\$(
  . /etc/os-release
  echo \$VERSION_CODENAME
)

echo "deb https://packages.sury.org/php/ \$VERSION_CODENAME main" | tee /etc/apt/sources.list.d/sury-php.list
wget -O- https://packages.sury.org/php/apt.gpg | gpg --dearmor | tee /etc/apt/trusted.gpg.d/php.gpg  > /dev/null 2>&1

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

EOF

COPY --chmod=755 ./.github/docker/centreon-web/init/systemctl /bin/systemctl

COPY --chmod=755 ./.github/docker/centreon-web/init/* /etc/init.d/

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

apt-get install -y \
  php8.2-common \
  php8.2-cli \
  php8.2-pdo \
  php8.2-mysqlnd \
  php8.2-gd \
  php8.2-xml \
  php8.2-mbstring \
  php8.2-ldap \
  php8.2-snmp \
  php8.2-intl \
  php8.2-fpm \
  php8.2-curl \
  php8.2-zip

apt-get install -y /tmp/packages-centreon/centreon-*.deb

sed -i -E 's#^date\.timezone.+#date.timezone = Europe/Paris#g' /etc/php/8.2/mods-available/timezone.ini
phpenmod -v 8.2 timezone

touch /var/log/php8.2-fpm-centreon-error.log
chown www-data:www-data /var/log/php8.2-fpm-centreon-error.log

apt-get clean

EOF

EXPOSE 80

ENTRYPOINT ["/usr/share/centreon/container.sh"]

ENV CENTREON_DATASET="1"
ENV CENTREON_LANG="en_US"
