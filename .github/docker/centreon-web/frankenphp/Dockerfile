# centreon-web as a single static FrankenPHP binary (Caddy + PHP 8.4, classic mode) on
# `scratch`: no Apache, PHP-FPM, crond, shell nor package manager. Same external contract as
# the slim image (alma9/Dockerfile.slim): GORGONE_API_ADDRESS and VICTORIA_METRICS_URL are
# required, port 80, readiness marker /tmp/docker.ready. The command picks the role:
#   serve   (default) FrankenPHP only; waits until the databases are installed
#   install one-shot: creates or upgrades the databases and sets the options, then exits
#   cron    runs the PHP cron jobs of centreon-web on their schedules
# See README.md (FrankenPHP image) for the compose services.
#
# - frankenphp: static (musl) FrankenPHP binary built by FrankenPHP's own static builder
#   (static-php-cli), with only the PHP extensions centreon-web uses;
# - app: the application as installed in the published monolith image, with the
#   application sources of the checkout laid over it (as Dockerfile.slim), then trimmed to
#   what is served or executed at run time;
# - rootfs: the files of the final image (binary, application, configuration, users);
# - final: that root filesystem on scratch.
#
# Build context: the repository root.
#   docker build -f .github/docker/centreon-web/frankenphp/Dockerfile \
#     -t centreon-web-frankenphp:gorgone-poc .

ARG WEB_IMAGE=docker.centreon.com/centreon/centreon-web-alma9:develop
# FrankenPHP v1.13.3 (2026-10-23) static builder, musl flavor.
ARG FRANKENPHP_BUILDER_IMAGE=dunglas/frankenphp:static-builder-musl-1.13.3@sha256:30e83214dcd19b1c772d5b05920b759d1c502af52b390b4e37217c66abb7b53d

FROM ${FRANKENPHP_BUILDER_IMAGE} AS frankenphp

# static-php-cli v3 has no release: its v3 branch is pinned to a commit (2026-10-27) and run
# from source with the PHP and Composer of the builder image, instead of the unpinned nightly
# binary build-static.sh downloads.
ARG SPC_COMMIT=f318424c245430b5562ff7ee142491bcc1088072
ARG PHP_VERSION=8.4.27
# Evidence: README.md (FrankenPHP image, PHP extensions).
ARG PHP_EXTENSIONS="ctype,curl,dom,fileinfo,filter,gd,gettext,iconv,intl,ldap,mbregex,mbstring,mysqlnd,opcache,openssl,pcntl,pdo,pdo_mysql,phar,posix,session,simplexml,tokenizer,xml,xmlwriter,zip,zlib"
# libjpeg: imagecreatefromjpeg/imagejpeg (gd suggests it only).
ARG PHP_EXTENSION_LIBS="brotli,watcher,libjpeg"

RUN bash -e <<'EOF'
git clone --quiet https://github.com/crazywhalecc/static-php-cli.git /opt/spc
git -C /opt/spc checkout --quiet "${SPC_COMMIT}"
composer install --quiet --working-dir=/opt/spc --no-dev --no-interaction --prefer-dist
EOF

# The build root of static-php-cli stays in a cache mount: a rebuild only relinks.
RUN --mount=type=cache,id=centreon-frankenphp-spc-dist,target=/go/src/app/dist bash -e <<'EOF'
mkdir -p dist
printf '#!/bin/sh\nexec php /opt/spc/bin/spc "$@"\n' > dist/spc
chmod +x dist/spc
# Only the extensions listed above, without their optional libraries (AVIF, WebP,
# FreeType, HTTP/3...), and no Mercure nor Vulcain module in Caddy.
sed -i 's/^\(  with-suggests:\).*/\1 false/' craft.yml
PHP_VERSION="${PHP_VERSION}" PHP_EXTENSIONS="${PHP_EXTENSIONS}" PHP_EXTENSION_LIBS="${PHP_EXTENSION_LIBS}" \
  XCADDY_ARGS="--with github.com/dunglas/caddy-cbrotli@v1.0.1" OUTPUT_DIR=/out \
  ./build-static.sh
mv /out/frankenphp-linux-* /out/frankenphp
/out/frankenphp php-cli -r 'echo implode(" ", get_loaded_extensions()), PHP_EOL;'
EOF

FROM ${WEB_IMAGE} AS app

# Application sources of the checkout over the installed ones, as in Dockerfile.slim.
RUN --mount=type=bind,src=centreon,dst=/tmp/centreon-src bash -e <<'EOF'
mkdir /tmp/overlay
tar -C /tmp/centreon-src \
  --exclude=./www/install --exclude=./www/front_src --exclude=./www/widgets/src \
  --exclude=./config/centreon.config.php --exclude=./config/centreon.conf.php \
  --exclude=./config.new/reference.php --exclude='node_modules' \
  -cf - ./src ./www ./config ./config.new \
  | tar -C /tmp/overlay -xf - --no-same-owner
cp /tmp/overlay/config/centreon.config.php.template /tmp/overlay/config/centreon.config.php
cp /tmp/centreon-src/packaging/src/centreon-macroreplacement.txt /tmp/macros.sed
echo 's#@WEB_USER@#apache#g' >> /tmp/macros.sed
find /tmp/overlay -type f -print0 | xargs -0 sed -i -f /tmp/macros.sed
tar -C /tmp/overlay -cf - . | tar -C /usr/share/centreon -xf - --no-overwrite-dir
chown -R centreon:centreon /usr/share/centreon/src /usr/share/centreon/www
rm -rf /tmp/overlay /tmp/macros.sed
EOF

COPY --chown=apache:apache ./.github/docker/centreon-web/alma9/configuration /usr/share/centreon/www/install/tmp
COPY ./.github/docker/centreon-web/sql/* /rootfs/usr/local/lib/centreon-container/sql/

RUN bash -e <<'EOF'
cd /usr/share/centreon

# Not served nor executed at run time: startup scripts of the monolith image, examples, the
# Broker modules, the Perl and shell tools of bin/, source maps of the frontend bundles,
# tests, documentation and CI files of the Composer packages.
rm -rf container.d container.sh examples lib/centreon-broker
find bin -type f ! -name '*.php' ! -name centreon ! -name console ! -name console.new -delete
find . -name '*.map' -type f -delete
find vendor -mindepth 3 -maxdepth 3 -type d \
  \( -iname tests -o -iname test -o -iname doc -o -iname docs -o -name .github \) -prune -exec rm -rf {} +
find vendor -type f \( -iname '*.md' -o -name 'phpunit.xml*' -o -name '.gitattributes' -o -name '.gitignore' \) -delete

# The installer is only used by the install role, which copies it back to www/install: a
# www/install directory makes centreon-web report itself as installable (no module routes).
mkdir -p /rootfs/usr/local/lib/centreon-container
mv www/install /rootfs/usr/local/lib/centreon-container/install

# Symfony caches of both kernels built in the image: the serve role starts without compiling
# them and the cache directory needs no write at run time.
rm -rf /var/cache/centreon/symfony /var/cache/centreon/symfony.new
su apache -s /bin/bash -c 'php bin/console cache:warmup --no-ansi -q && php bin/console.new cache:warmup --no-ansi -q'
ls /var/cache/centreon
EOF

FROM app AS rootfs

COPY --from=frankenphp /out/frankenphp /rootfs/usr/local/bin/frankenphp
COPY ./.github/docker/centreon-web/frankenphp/Caddyfile ./.github/docker/centreon-web/frankenphp/php.ini /rootfs/etc/frankenphp/
COPY ./.github/docker/centreon-web/frankenphp/container/ /rootfs/usr/local/lib/centreon-container/

RUN bash -e <<'EOF'
# Application and its directories (numeric owners: apache 48, centreon 998).
tar -C / --numeric-owner \
  --exclude=etc/centreon/conf.pm --exclude=etc/centreon/centreontrapd.pm \
  -cf - etc/centreon usr/share/centreon var/cache/centreon var/lib/centreon var/log/centreon \
  | tar -C /rootfs --numeric-owner -xpf -
rm -f /rootfs/var/log/centreon/*

# Written at run time by the web user: generated configuration, engine context, feature flags,
# logs, sessions.
install -d -o 48 -g 48 -m 0770 /rootfs/var/lib/php/session
chown 48:48 /rootfs/etc/centreon /rootfs/var/log/centreon /rootfs/usr/share/centreon/config/features.json
install -d -m 1777 /rootfs/tmp
install -d -m 0755 /rootfs/srv/http
ln -s /usr/share/centreon/www /rootfs/srv/http/centreon

# Users of the copied files, CA bundle (OpenSSL and curl), MIME types (Caddy file server).
printf '%s\n' 'root:x:0:0:root:/root:/sbin/nologin' 'apache:x:48:48:Apache:/usr/share/httpd:/sbin/nologin' \
  'centreon:x:998:998:Centreon:/var/spool/centreon:/sbin/nologin' > /rootfs/etc/passwd
printf '%s\n' 'root:x:0:' 'apache:x:48:centreon' 'centreon:x:998:apache' > /rootfs/etc/group
install -d /rootfs/etc/ssl
cp -L /etc/pki/tls/certs/ca-bundle.crt /rootfs/etc/ssl/cert.pem
cp /etc/mime.types /rootfs/etc/mime.types
EOF

FROM scratch

COPY --from=rootfs /rootfs /

ENV CENTREON_DATASET="1" \
    CENTREON_LANG="en_US" \
    XDG_CONFIG_HOME="/tmp/caddy/config" \
    XDG_DATA_HOME="/tmp/caddy/data"

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/frankenphp", "php-cli", "/usr/local/lib/centreon-container/container.php"]
CMD ["serve"]
