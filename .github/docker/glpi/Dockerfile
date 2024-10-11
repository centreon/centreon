FROM debian:bookworm

ENV DEBIAN_FRONTEND=noninteractive

ENV GLPI_VERSION="10.0.16"

RUN bash -e <<EOF

apt-get update
apt-get install -y ca-certificates wget curl gnupg2

echo "deb https://packages.sury.org/php/ bookworm main" | tee /etc/apt/sources.list.d/sury-php.list
wget -O- https://packages.sury.org/php/apt.gpg | gpg --dearmor | tee /etc/apt/trusted.gpg.d/php.gpg  > /dev/null 2>&1

apt-get update

apt-get install -y --no-install-recommends \
    apache2 \
    php8.3 \
    php8.3-mysql \
    php8.3-ldap \
    php8.3-xmlrpc \
    php8.3-imap \
    php8.3-curl \
    php8.3-gd \
    php8.3-mbstring \
    php8.3-xml \
    php-cas \
    php8.3-intl \
    php8.3-zip \
    php8.3-bz2 \
    php8.3-redis \
    cron \
    jq \
    mariadb-client-core

a2enmod rewrite

SRC_GLPI=\$(curl -s https://api.github.com/repos/glpi-project/glpi/releases/tags/\${GLPI_VERSION} | jq .assets[0].browser_download_url | tr -d \")
TAR_GLPI=\$(basename \${SRC_GLPI})

wget -P /var/www/html/ \${SRC_GLPI}
tar -xzf /var/www/html/\${TAR_GLPI} -C /var/www/html/
rm -Rf /var/www/html/\${TAR_GLPI}
chown -R www-data:www-data /var/www/html/glpi/

cat > /etc/apache2/sites-available/000-default.conf << EOT
<VirtualHost *:80>
    ServerName glpi.localhost

    DocumentRoot /var/www/html/glpi/public

    # If you want to place GLPI in a subfolder of your site (e.g. your virtual host is serving multiple applications),
    # you can use an Alias directive. If you do this, the DocumentRoot directive MUST NOT target the GLPI directory itself.
    # Alias "/glpi" "/var/www/html/glpi/public"

    <Directory /var/www/html/glpi/public>
        Require all granted

        RewriteEngine On

        # Ensure authorization headers are passed to PHP.
        # Some Apache configurations may filter them and break usage of API, CalDAV, ...
        RewriteCond %{HTTP:Authorization} ^(.+)\$
        RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

        # Redirect all requests to GLPI router, unless file exists.
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule ^(.*)\$ index.php [QSA,L]
    </Directory>

    # LogLevel warn
    # ErrorLog \${APACHE_LOG_DIR}/error-glpi.log
    # CustomLog \${APACHE_LOG_DIR}/access-glpi.log combined
</VirtualHost>
EOT

# Add scheduled task by cron and enable
echo "*/2 * * * * www-data /usr/bin/php /var/www/html/glpi/front/cron.php &>/dev/null" > /etc/cron.d/glpi

apt-get clean

EOF

COPY --chmod=755 ./.github/docker/glpi/start.sh /opt/glpi/bin/start.sh

EXPOSE 80

ENTRYPOINT ["/opt/glpi/bin/start.sh"]
