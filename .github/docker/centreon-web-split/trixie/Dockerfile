# syntax=docker/dockerfile:1
#
# Pilier 1 (split httpd/php-fpm) — https://centreon.atlassian.net/wiki/spaces/RD/pages/3823730701
#
# Builds centreon-web FROM SOURCE (composer install + pnpm front build) into two
# independent runtime images with zero shared volume: `httpd` (static assets only,
# proxies everything else to php-fpm) and `fpm` (the full application tree).
#
# Build (from the repository root, same context as .github/docker/centreon-web):
#   docker build --target httpd -f .github/docker/centreon-web-split/trixie/Dockerfile -t centreon-web-httpd-trixie .
#   docker build --target fpm   -f .github/docker/centreon-web-split/trixie/Dockerfile -t centreon-web-fpm-trixie .
#
# Known TODOs (deliberately out of scope for this first pass, see the plan):
#   - www/locale (translation build) is not generated here yet.
#   - No Symfony cache warmup at build time on purpose (see the `fpm` stage below).
#
# APP_SECRET is never generated here — `.env` ships the literal `%APP_SECRET%`
# placeholder, resolved at container start by container.d/12-install.sh
# instead (caller-provided APP_SECRET env var, or a random one on first
# install), not a TODO.

ARG NODE_VERSION=24
ARG PHP_VERSION=8.4
# Matches the repo root's .version/.version.centreon-web (MAJOR=26.12,
# MINOR=0) — not read automatically from those files at build time since
# the build context only copies the centreon/ subdirectory (COPY centreon/
# . below), not the monorepo root they live at. Bump alongside a real
# version change, same spirit as NODE_VERSION/PHP_VERSION above.
ARG CENTREON_MAJOR_VERSION=26.12
ARG CENTREON_MINOR_VERSION=0
# Autodiscovery (T0) is SourceGuardian-encoded like the other 3 baked-in
# modules below, but published as its own package-less image by the
# centreon-autodisco stack rather than fetched from apt here — see
# autodisco_source below. amd64-only upstream image; the module itself is
# plain PHP, architecture-independent.
ARG AUTODISCO_IMAGE=docker.centreon.com/centreon/centreon-autodisco-trixie:develop
# Real centengine, for config-verification only (MON-214031) — see
# engine_verifier below. Multi-arch, Debian trixie, same base family as
# php:8.4-fpm, unlike AUTODISCO_IMAGE above: no --platform pin needed.
ARG ENGINE_IMAGE=docker.centreon.com/centreon/centreon-engine-trixie:develop

#############################################
# Stage 1: frontend build (pnpm / rspack)
#############################################
FROM node:${NODE_VERSION}-slim AS frontend_builder

WORKDIR /usr/src/centreon

# CI env flags (see .github/actions/frontend-build/action.yml): skip binary
# downloads we don't need for a production asset build.
ENV CYPRESS_INSTALL_BINARY=0 \
    PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1 \
    PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true \
    COREPACK_ENABLE_DOWNLOAD_PROMPT=0

COPY centreon/ .

RUN --mount=type=cache,target=/root/.local/share/pnpm/store,sharing=locked \
    bash -e <<'EOF'
corepack enable
corepack prepare pnpm@10 --activate
pnpm install --frozen-lockfile \
  --config.trustPolicy=no-downgrade \
  --config.minimumReleaseAge=2880 \
  --config.blockExoticSubdeps=true
pnpm centreon:build
EOF

#############################################
# Stage 2: backend build (composer)
#############################################
FROM php:${PHP_VERSION}-cli AS backend_builder

# Re-declared: a global ARG (declared before the first FROM) is only usable
# directly inside a stage's own instructions if re-declared here — it's
# already usable in the FROM line above without this, that's a separate
# Docker rule.
ARG CENTREON_MAJOR_VERSION
ARG CENTREON_MINOR_VERSION

WORKDIR /usr/src/centreon

# Same extension set as the fpm runtime stage below: composer's platform check
# (composer.json "require": "ext-*") needs them present to resolve, not just
# ship them at runtime.
RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt/lists,sharing=locked \
    bash -e <<'EOF'
apt-get update
apt-get install -y --no-install-recommends \
    libicu-dev libldap2-dev libsnmp-dev libzip-dev libpng-dev \
    libjpeg62-turbo-dev libwebp-dev libfreetype6-dev
docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp
docker-php-ext-install -j"$(nproc)" pdo_mysql mysqli gd intl ldap snmp zip gettext sysvsem
EOF

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY centreon/ .

RUN cp config/centreon.config.php.template config/centreon.config.php && \
    composer install --no-dev --optimize-autoloader --prefer-dist \
      --no-interaction --no-progress --quiet --no-scripts

# The "Add Poller" one-click install script (served at
# /centreon/poller/install.sh, referenced by PollerInstallationCommandFactory
# to build the curl|bash command shown in the UI) isn't a static source-tree
# file — it's assembled by install-poller/build.sh (concatenates
# main-head.sh + lib/*.sh + src/*.sh + main-tail.sh, sed-ing in the version)
# as its own dedicated CI step (.github/workflows/web.yml), with nfpm then
# just copying the already-built output into the package (packaging/src/
# centreon-web.yaml). Our from-source build never ran either step, so this
# path 404'd through to the SPA's catch-all instead of returning a script —
# confirmed by Fabien hitting it against this image directly. Explicit
# MAJOR/MINOR args, not the script's own repo-root .version fallback: that
# fallback resolves two directories above install-poller/, which lands
# outside what `COPY centreon/ .` above brought into this stage.
RUN bash -e <<EOF
cd install-poller
bash build.sh "${CENTREON_MAJOR_VERSION}" "${CENTREON_MINOR_VERSION}" unstable
EOF

#############################################
# Stage 2b: T0 baked-in modules (License Manager, IT Edition extensions,
# PP Manager/Monitoring Connector Manager — Autodiscovery comes from
# autodisco_source below, not this stage) + the SourceGuardian loader their
# PHP code needs to even run (they're not plain PHP — every one declares
# `Pre-Depends: php8.4-sourceguardian-loader` and `License: Proprietary`,
# confirmed by extracting and reading each .deb directly). Pulled from
# Centreon's own apt repo since none of these are vendored in this monorepo
# (separate repos, per T0's own module decision).
#
# Pinned exact versions (not "latest") so Docker's layer cache never
# re-issues the fetch on a rebuild unless one of these ARGs is deliberately
# bumped — same pattern as NODE_VERSION/PHP_VERSION above.
#
# Fetched via real `apt-get download` against Centreon's own signed apt
# repo, not a bare `curl` — corrected after a dockerfile-review pass flagged
# the original curl-only fetch as having no integrity verification (SEC-09).
# `packages.centreon.com/standard/apt-unstable` is a genuine GPG-signed repo
# (InRelease/Release/Release.gpg all present, confirmed directly), and this
# exact key+repo pattern is already proven elsewhere in this monorepo
# (.github/docker/centreon-web/trixie/Dockerfile.dependencies) — reused
# rather than hand-rolling sha256 pins we'd have had to source from this
# same signed Packages file anyway. `apt-get download` (not `install`) only
# fetches the verified .deb into the working directory without running its
# postinst — this is a throwaway builder stage, we only want the files.
#
# Known, accepted risk, not silently assumed fine: every module's control
# file declares `Depends: centreon-web (>= 26.10~) (<< 26.11~)`, but this
# codebase seeds `26.12.0` (insertBaseConf.sql) — apt-unstable has no
# 26.12.x build of any of these 4 yet. Extracting file trees directly
# (not `dpkg -i`) never enforces this constraint at build time, but the
# compatibility risk it's warning about is real; revisit once a matching
# build appears.
#
# Deliberately NOT copied forward: etc/logrotate.d/* (no logrotate in these
# images). NOT everything goes to stdout: fpm still writes eight files
# under /var/log/centreon (tailed to stdout by 02-log-tail_background.sh),
# never rotated, so they grow on the container layer until the container is
# recreated.
#############################################
FROM debian:trixie-slim AS modules_fetcher

# Interim fix (MON-213836 tracks the real one, owned by centreon-autodisco):
# Centreon's own php8.4-sourceguardian-loader .deb is amd64-only
# (`arch: amd64` in its own packaging) — the 3 other modules fetched below
# are all `all` (architecture-independent). Until a real arm64 build of the
# loader exists, arm64 vendors the official SourceGuardian loader archive
# directly (sha256-verified), installed at the exact same path the .deb
# would have used so the fpm/cron COPY lines below never need to know which
# branch ran. Switch back to a plain apt fetch on both arches once
# MON-213836 ships.
ARG TARGETARCH
ARG CENTREON_APT_SUITE=trixie-26.10-unstable
ARG SOURCEGUARDIAN_LOADER_VERSION=8.4-1+deb13u1
ARG SOURCEGUARDIAN_LOADER_ARM64_SHA256=85123735697faafc6adcaae7a4e9b093bcff1eec8708d0ed671c64c16171c411
ARG LICENSE_MANAGER_VERSION=26.10.0-1790657463.23c652c+deb13u1
ARG PP_MANAGER_VERSION=26.10.0-1790254134.1b7ac99+deb13u1
ARG IT_EDITION_VERSION=26.10.0-1790224770.5f9f105+deb13u1

WORKDIR /modules

# ARGs declared above are already exported into this RUN's shell
# environment by buildkit (standard behavior, not heredoc-specific) — plain
# $VAR expansion below, same quoted-heredoc convention as every other RUN
# in this file, no Dockerfile-level substitution needed.
RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt/lists,sharing=locked \
    bash -eo pipefail <<'EOF'
apt-get update
apt-get install -y --no-install-recommends curl ca-certificates gnupg
curl -sfL https://apt-key.centreon.com/ | gpg --dearmor > /etc/apt/trusted.gpg.d/centreon.gpg
echo "deb https://packages.centreon.com/standard/apt-unstable/ ${CENTREON_APT_SUITE} main" \
  > /etc/apt/sources.list.d/centreon-unstable.list
apt-get update
mkdir -p extracted
if [ "${TARGETARCH}" = "amd64" ]; then
  apt-get download \
    "php8.4-sourceguardian-loader=${SOURCEGUARDIAN_LOADER_VERSION}" \
    "centreon-license-manager=${LICENSE_MANAGER_VERSION}" \
    "centreon-pp-manager=${PP_MANAGER_VERSION}" \
    "centreon-it-edition-extensions=${IT_EDITION_VERSION}"
else
  apt-get download \
    "centreon-license-manager=${LICENSE_MANAGER_VERSION}" \
    "centreon-pp-manager=${PP_MANAGER_VERSION}" \
    "centreon-it-edition-extensions=${IT_EDITION_VERSION}"
fi
for deb in *.deb; do dpkg-deb -x "$deb" extracted; done
if [ "${TARGETARCH}" != "amd64" ]; then
  curl -sfLo /tmp/sg.tgz https://www.sourceguardian.com/loaders/download/loaders.linux-aarch64.tar.gz
  tar -xzf /tmp/sg.tgz -C /tmp ixed.8.4.lin
  echo "${SOURCEGUARDIAN_LOADER_ARM64_SHA256}  /tmp/ixed.8.4.lin" | sha256sum -c -
  install -D -m 644 /tmp/ixed.8.4.lin extracted/usr/lib/php/20240924/ixed.8.4.lin
fi
EOF

#############################################
# Plugin packs (centreon-pack-*): the JSON definitions centreon-pp-manager
# lists and installs from /usr/share/centreon-packs/ (EMS_PP_PATH, a plain
# glob of *.json, no dpkg lookup). Each package holds one JSON file and no
# maintainer script, and every one of them declares
# `Depends: centreon-pp-manager`, which is baked in above, not a package
# here: extracted like modules_fetcher (dpkg-deb -x) rather than installed,
# so the dependency never comes into play. Latest version of every pack in
# the signed apt-connectors-stable repo; a rebuild only refreshes them when
# this layer's cache is busted (--no-cache-filter packs_fetcher).
#############################################
FROM debian:trixie-slim AS packs_fetcher

WORKDIR /packs

RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt/lists,sharing=locked \
    bash -eo pipefail <<'EOF'
apt-get update
apt-get install -y --no-install-recommends curl ca-certificates gnupg
curl -sfL https://apt-key.centreon.com/ | gpg --dearmor > /etc/apt/trusted.gpg.d/centreon.gpg
. /etc/os-release
echo "deb https://packages.centreon.com/apt-connectors-stable/ ${VERSION_CODENAME} main" \
  > /etc/apt/sources.list.d/centreon-connectors.list
apt-get update
mkdir -p extracted
apt-cache pkgnames centreon-pack- | sort | xargs apt-get download
for deb in *.deb; do dpkg-deb -x "$deb" extracted; done
count=$(find extracted/usr/share/centreon-packs -name '*.json' | wc -l)
echo "Plugin packs extracted: ${count}"
[ "$count" -gt 0 ]
EOF

#############################################
# Autodiscovery (T0): extracted from the centreon-autodisco stack's own
# package-less image instead of apt (its PR stack owns building that image
# for real, package-less, from centreon-modules sources) — this just pulls
# the already-installed files back out via dpkg -L against that image's own
# dpkg database, same mechanism `apt-get download` + `dpkg-deb -x` above
# gives the other 3 modules, minus the network fetch. Covers everything the
# real .deb owns under /usr/share/centreon/, including
# config/{json_validator,packages,routes}/*CentreonAutoDiscovery* — dpkg -L
# lists every path the package owns, not just the obvious ones.
#############################################
FROM --platform=linux/amd64 ${AUTODISCO_IMAGE} AS autodisco_source

RUN bash -eo pipefail <<'EOF'
mkdir -p /out
dpkg -L centreon-auto-discovery-server | grep '^/usr/share/centreon/' | while read -r f; do
  [ -f "$f" ] && cp --parents -a "$f" /out/
done
EOF

# Guard, requested explicitly by Fabien: autodiscovery must stay
# SourceGuardian-encoded in the final image — never overlay clear-text
# module sources (worker or core patches) here. Fails the build if any
# module PHP file isn't encoded, except the 4 Application/Controller/
# *Controller.php files the official package itself ships in clear
# (encoder-side exclusion, not a gap in this extraction) — confirmed
# against centreon-autodisco-trixie:develop: 139/143 files under
# src/CentreonAutoDiscovery encoded, 71/71 under
# www/modules/centreon-autodiscovery-server encoded.
RUN bash -eo pipefail <<'EOF2'
clear=$(grep -rL 'sg_load' --include='*.php' \
  /out/usr/share/centreon/src/CentreonAutoDiscovery \
  /out/usr/share/centreon/www/modules/centreon-autodiscovery-server \
  | grep -v '/src/CentreonAutoDiscovery/Application/Controller/[A-Za-z]*Controller\.php$' || true)
if [ -n "$clear" ]; then echo "autodiscovery PHP not SourceGuardian-encoded:" >&2; echo "$clear" >&2; exit 1; fi
EOF2

#############################################
# Real centengine, for config-verification only (MON-214031). The split fpm
# image never runs centengine itself (Pilier 2 gives centengine its own
# container) — this exists purely so "Generate Configuration Files"'s debug
# step (common-Func.php's printDebug(), `$nagios_bin -v
# <cfgpath>/centengine.DEBUG`) reports a real syntax/consistency check
# instead of the fake binary's hardcoded "Total Warnings: 0 / Total Errors:
# 0" (see that COPY further down). ENGINE_TAG/ENGINE_IMAGE couples the fpm
# image's verification binary to the same engine version actually running
# the stack.
#############################################
FROM ${ENGINE_IMAGE} AS engine_verifier

#############################################
# Stage 3: assemble the full /usr/share/centreon tree, macro-resolved
#############################################
FROM debian:trixie-slim AS app_assembler

WORKDIR /usr/share/centreon

# Full backend tree (already has vendor/ from composer install above).
COPY --from=backend_builder --link /usr/src/centreon/ .
# Overlay the compiled front build on top (front_src/ itself is never shipped).
COPY --from=frontend_builder --link /usr/src/centreon/www/static ./www/static
COPY --from=frontend_builder --link /usr/src/centreon/www/index.html ./www/index.html
# The "Add Poller" one-click install script's real public path (see
# backend_builder's install-poller/build.sh step above for why this can't
# just be part of the bulk COPY above) — same dst/mode packaging uses
# (packaging/src/centreon-web.yaml), served directly as a static file, no
# Apache rewrite or Symfony route involved.
#
# `mkdir` first, not left to COPY's own auto-create: COPY --chmod applies
# its numeric mode to a destination directory it has to create along the
# way too, not just the file (same class of bug as SEC-10 in the
# dockerfile-review skill, just triggered by a single-file COPY instead of
# a directory-tree one) — without this, www/poller/ itself ended up mode
# 644 (no execute bit), making the directory untraversable and the file
# 403 even though the file's own mode was correct. Confirmed by hitting
# the real endpoint through httpd after the first version of this fix and
# getting a 403, not the expected script content.
RUN mkdir -p www/poller
COPY --from=backend_builder --link --chmod=644 \
    /usr/src/centreon/install-poller/install.sh ./www/poller/install.sh

# Macro replacement: reproduces what the "package" CI job does with
# packaging/src/centreon-macroreplacement.txt + the extra @WEB_USER@ etc.
# substitutions from .github/actions/package-nfpm — but here it's applied to a
# from-source tree instead of pre-packaging, since we never run nfpm.
RUN bash -e <<'EOF'
# Dead weight from the wholesale `COPY centreon/ .` in backend_builder — none
# of this is referenced at runtime, confirmed directly (du + a dedicated
# find for any other Tests/ dir under src/, only this one found) rather than
# assumed: E2E/unit test suites, the frontend monorepo's own source packages
# (already-compiled output lands separately in www/static via
# frontend_builder), docs, a release-cutting script, two copies of a
# decorative GitHub README image, and install-poller's own build sources
# (main-head.sh/lib/src/build.sh — the one artifact anything actually needs,
# install.sh, was already pulled out to www/poller/install.sh above, before
# this removes the rest).
rm -rf www/front_src www/widgets/src \
  cypress tests packages doc project install-poller \
  centreon-github-wall.jpg phpstan.core.test.baseline.neon pnpm-lock.yaml \
  src/CentreonLicense/Tests

# No pipe here on purpose (FMT-03 considered, not just missed): `grep -l`
# exits 1 on "no file matched" — a legitimate, expected outcome on every
# build (not an error), which `bash -eo pipefail` would wrongly turn into a
# hard build failure. Each stage instead runs against an intermediate file,
# so `find`/`sed` failures still abort the build via `bash -e` (the actual
# real-world risk this guards against) without `grep`'s benign exit code
# being misread as one.
find . -type f -not -path './vendor/*' -print0 > /tmp/all-files
xargs -0 -r grep -lZ '@[A-Z_]\+@' 2>/dev/null < /tmp/all-files > /tmp/macro-files || true
xargs -0 -r sed -i \
    -f packaging/src/centreon-macroreplacement.txt \
    -e 's/@WEB_USER@/www-data/g' \
    -e 's/@APACHE_USER@/www-data/g' \
    -e 's/@APACHE_GROUP@/www-data/g' \
    -e 's/@PHP_MIN_VERSION@/8.4/g' \
    -e 's/@PHP_MAX_VERSION@/8.5/g' \
    < /tmp/macro-files
rm -f /tmp/all-files /tmp/macro-files
EOF

# T0 baked-in modules (see modules_fetcher/autodisco_source above) —
# already fully macro-resolved by Centreon's own package build, nothing
# left to substitute here. Lands in the exact same tree the partition step
# below already scans, so module static assets reach httpd the same way
# core www/ files do, no module-specific logic needed there.
COPY --from=modules_fetcher --link /modules/extracted/usr/share/centreon/ .
COPY --from=autodisco_source --link /out/usr/share/centreon/ .

# Source maps for production JS/CSS — 106MB on their own (confirmed by
# `find ... -name '*.map' | du -ch`, bigger than every other dead-weight
# removal in this stage combined), covering both core www/static and the 4
# modules just copied in above (hence placed after those COPYs, not in the
# same block as the other dead-weight removal earlier in this stage). Not
# needed at runtime — nothing in this stack serves them to an error-tracking
# tool (no Sentry/equivalent configured), confirmed with Fabien before
# dropping them rather than assumed safe.
RUN find www -name '*.map' -delete

# bin/* executables lose their exec bit here — a real, found-by-running bug,
# not a theory: these files are tracked `100644` in the centreon.git index
# itself (confirmed with `git ls-files -s bin/generateSqlLite` etc.), not
# `100755`. A real RPM/DEB install never shows this because
# packaging/src/centreon-{web,trap}.yaml's nfpm `file_info.mode: 0755`
# entries fix it up at package-build time — a step this from-source image
# has no equivalent of. `COPY --chown` (used for the bulk
# `/usr/share/centreon` copy above) only changes ownership, never mode, so
# it faithfully reproduces git's own 644 — this isn't that COPY clobbering
# anything. Found by k8s-helm replaying C13 in k3d: formGenerateTraps.php's
# `exec generateSqlLite` failed with "Permission denied", and
# centreontrapd/centreontrapdforward (part of the same `bin/` tree, used
# only by the separate centreon-trap image, not this one) would hit the
# exact same bug there. List kept in exact sync with every `file_info.mode:
# 0755`/`0744` entry under `bin/` in both nfpm manifests — not just the
# handful an existing failure happened to surface.
RUN chmod 0755 \
    bin/centFillTrapDB bin/centreon_trap_send bin/centreon_health \
    bin/centreonSyncPlugins bin/centreonSyncArchives bin/generateSqlLite \
    bin/changeRrdDsName.pl bin/migrateWikiPages.php \
    bin/centreon-partitioning.php bin/logAnalyserBroker \
    bin/centreon-sanitize-images.php \
    bin/centreon-remove-duplicate-host-service-relations.php \
    bin/centreon bin/console bin/console.new bin/migration \
    bin/centreontrapd bin/centreontrapdforward && \
    chmod 0744 bin/migrateCredentials.php bin/revertCredentials.php && \
    test -x bin/generateSqlLite && test -x bin/centFillTrapDB && \
    test -x bin/centreontrapdforward

# cron/* scripts lose their exec bit for the exact same reason as bin/*
# above — tracked `100644` in the centreon.git index (confirmed with
# `git ls-files -s cron/outdated-token-removal.php` etc.), fixed up to 0755
# only by packaging/src/centreon-web.yaml's nfpm `file_info.mode` entries at
# real package-build time. Found by k8s-helm replaying C13 in k3d: the
# chart's cron pod invokes these directly (same as a VM's crontab would),
# so outdated-token-removal.php failed with "Permission denied" every
# minute. List kept in exact sync with every `file_info.mode: 0755` entry
# under `cron/` in centreon-web.yaml (the only nfpm manifest that packages
# this directory).
RUN chmod 0755 \
    cron/centreon-backup.pl cron/centAcl.php cron/centAcl-Func.php \
    cron/checkConfiguration.php cron/centstorage_purge.php \
    cron/eventReportBuilder cron/centreon-send-stats.php \
    cron/dashboardBuilder cron/centreon-backup-mysql.sh \
    cron/centreon-partitioning.php cron/downtimeManager.php \
    cron/centKnowledgeSynchronizer.php cron/outdated-token-removal.php && \
    test -x cron/outdated-token-removal.php && \
    test -x cron/eventReportBuilder && test -x cron/dashboardBuilder

#############################################
# Stage 4: httpd-only subset of www/ (T3 partition, decision #3)
#
# Extension-based exclusion, not directory-based: www/include/, www/class/ and
# www/lib/ each mix PHP with .js/.css actually loaded by the browser (the "3
# holes" documented in the pillar-1 page), so excluding *.php/*.ihtml instead
# of whole directories captures them by construction. Do NOT ship www/ whole
# into the httpd image even though mod_php isn't loaded: the vhost routes by
# <LocationMatch>/ProxyPassMatch, so anything that regex doesn't match is
# served as literal bytes by Apache — an unfiltered .php would be source
# disclosure in production.
#############################################
FROM app_assembler AS www_httpd_subset

WORKDIR /usr/share/centreon/www
RUN find . -type f \( -name '*.php' -o -name '*.ihtml' \) -delete && \
    rm -rf install widgets

#############################################
# Stage 5 (final): httpd — static assets + reverse proxy to php-fpm
#############################################
FROM httpd:2.4 AS httpd

RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt/lists,sharing=locked \
    bash -e <<'EOF'
apt-get update
apt-get install -y --no-install-recommends gettext-base
rm -rf /var/lib/apt/lists/*
EOF

# headers/setenvif/alias are already loaded by this image by default; only
# these are missing (see the pillar-1 page's Apache module list).
RUN bash -e <<'EOF'
sed -i \
    -e 's/^#LoadModule deflate_module/LoadModule deflate_module/' \
    -e 's/^#LoadModule proxy_module/LoadModule proxy_module/' \
    -e 's/^#LoadModule proxy_fcgi_module/LoadModule proxy_fcgi_module/' \
    -e 's/^#LoadModule proxy_wstunnel_module/LoadModule proxy_wstunnel_module/' \
    conf/httpd.conf
echo 'ServerName localhost' >> conf/httpd.conf
echo 'Include conf/extra/centreon.conf' >> conf/httpd.conf
ln -sf /dev/stdout logs/access_log
ln -sf /dev/stderr logs/error_log
EOF

COPY --from=www_httpd_subset --chown=www-data:www-data \
    /usr/share/centreon/www /usr/share/centreon/www
COPY --chown=www-data:www-data .github/docker/centreon-web-split/trixie/configuration/httpd/centreon.conf.template \
    /usr/local/apache2/conf/extra/centreon.conf.template
COPY --chmod=755 .github/docker/centreon-web-split/trixie/entrypoint/httpd \
    /usr/local/lib/centreon-httpd/

# ServerName/UseCanonicalName and the php-fpm upstream target are deployment-
# specific (compose service name, k8s Service name, ...) — never bake a value
# in the image, substitute it at container start instead (see 10-config.sh).
ENV HTTPD_SERVER_NAME=centreon-httpd \
    PHP_FPM_UPSTREAM=php-fpm:9000 \
    GORGONE_WSS_UPSTREAM=localhost:8087 \
    HTTPD_PROXY_TIMEOUT=300

# Note: `httpd -t` isn't run at build time on purpose — conf/extra/centreon.conf
# only exists after 10-config.sh resolves the template at container start, so
# a build-time configtest against the .template would fail on the unresolved
# Include. Validate with `docker run --rm <image> httpd -t` after templating.

ARG BUILD_DATE
ARG BUILD_VERSION=unknown
ARG GIT_REVISION=unknown

LABEL org.opencontainers.image.title="Centreon web (httpd)" \
      org.opencontainers.image.description="Centreon web UI — static assets + reverse proxy to php-fpm, built from source" \
      org.opencontainers.image.created="${BUILD_DATE}" \
      org.opencontainers.image.version="${BUILD_VERSION}" \
      org.opencontainers.image.revision="${GIT_REVISION}" \
      org.opencontainers.image.source="https://github.com/centreon/centreon" \
      org.opencontainers.image.licenses="Apache-2.0" \
      org.opencontainers.image.vendor="Centreon" \
      org.centreon.base-image="httpd:2.4" \
      org.centreon.release-stage="alpha"

HEALTHCHECK --interval=10s --timeout=5s --start-period=30s --retries=3 \
    CMD test -f /tmp/docker.ready || exit 1

EXPOSE 80

# No explicit USER here on purpose (SEC-01): httpd's own master process needs
# root to bind port 80 and fork workers, and already drops worker privilege
# to www-data internally via the base image's User/Group directives — the
# same reasoning the official httpd image itself relies on for not setting
# USER. Setting USER www-data here would break the master process, not
# harden it.
ENTRYPOINT ["/usr/local/lib/centreon-httpd/container.sh"]
CMD ["httpd-foreground"]

#############################################
# Stage 5b: PHP extensions for the fpm runtime. Compiled here so the fpm image
# ships only the resulting .so/.ini and their runtime libraries, not the -dev
# packages, headers and PHP sources the build needs (~250MB measured).
#############################################
FROM php:${PHP_VERSION}-fpm AS fpm_extensions

RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt/lists,sharing=locked \
    bash -e <<'EOF'
apt-get update
apt-get install -y --no-install-recommends \
    libicu-dev libldap2-dev libsnmp-dev libzip-dev libpng-dev \
    libjpeg62-turbo-dev libwebp-dev libfreetype6-dev libgpgme-dev
docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp
# pcntl: long-running console commands on this image (autodiscovery save
# worker) catch SIGTERM to finish the item in progress before stopping.
docker-php-ext-install -j"$(nproc)" pdo_mysql mysqli gd intl ldap snmp zip gettext sysvsem pcntl
# gnupg is PECL-only (never bundled in PHP's own source tree, so
# docker-php-ext-install can't build it) — needed by centreon-license-manager
# to verify the PGP-signed .license files bundled inside a real license zip
# (LicenseService::validateFromContent(), confirmed empirically: uploading a
# real license fatal'd with "Class \"gnupg\" not found" before this). Built
# via PECL against this exact PHP build (not the Debian php8.4-gnupg
# package, a different compile of PHP 8.4 with no ABI guarantee against the
# official image), same reasoning as the SourceGuardian loader below.
pecl install gnupg
docker-php-ext-enable gnupg
# redis (phpredis), for storing fpm's PHP sessions in Valkey instead of a
# shared file volume (MON-214095) — PECL-only like gnupg above, same
# reasoning (built against this exact PHP build, not a Debian package with
# no ABI guarantee). Valkey is protocol-compatible with Redis, so the same
# client extension works against either; no extra system -dev packages
# needed, phpredis has no required external library for its base build.
# pecl asks its configure questions on stdin, which is this heredoc: answer
# them with -D and close stdin, or it swallows the next line as an answer.
pecl install -D 'enable-redis-igbinary="no" enable-redis-lzf="no" enable-redis-zstd="no" enable-redis-msgpack="no" enable-redis-lz4="no" with-liblz4="no"' redis-6.3.0 </dev/null
docker-php-ext-enable redis
EOF

#############################################
# Stage 6 (final): fpm — full application tree
#############################################
FROM php:${PHP_VERSION}-fpm AS fpm

# Same fixed UIDs/GIDs as every other Centreon container image (see
# centreon-collect/.github/docker/centreon-gorgone/trixie/Dockerfile) — for
# volume consistency across containers, not just this one: www-data <->
# centreon-gorgone cross-group access on the shared /var/cache/centreon and
# /var/lib/centreon volumes (confirmed empirically — REMOTECOPY got
# "Permission denied" without it), and centreon-engine/centreon-broker
# matter the same way now that those containers are part of this topology
# too (the RRD volumes they share with fpm, see 03-rrd-volumes.sh). www-data
# here is already 33:33 (official php:8.4-fpm image), matching what
# gorgone's own Dockerfile assumes.
RUN bash -e <<'EOF'
groupadd -g 900 centreon
useradd -u 900 -g centreon -M -r -l -d /var/spool/centreon -s /usr/sbin/nologin centreon
groupadd -g 901 centreon-engine
useradd -u 901 -g centreon-engine -M -r -l -d /var/lib/centreon-engine -s /usr/sbin/nologin centreon-engine
groupadd -g 902 centreon-broker
useradd -u 902 -g centreon-broker -M -r -l -d /var/lib/centreon-broker -s /usr/sbin/nologin centreon-broker
groupadd -g 903 centreon-gorgone
useradd -u 903 -g centreon-gorgone -M -r -l -d /var/lib/centreon-gorgone -s /usr/sbin/nologin centreon-gorgone
usermod -aG centreon-gorgone,centreon www-data
usermod -aG centreon,centreon-engine,centreon-broker,www-data centreon-gorgone
EOF

# Runtime libraries of the extensions built in fpm_extensions (from `ldd` on
# each .so), plus the tools the entrypoint uses. mariadb-client-core, not
# default-mysql-client: the entrypoint only runs `mysql`, and the full client
# pulls Perl and ~80MB of admin tools (mysqldump is only used by
# centreon-backup.pl, which runs in the cron image).
RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt/lists,sharing=locked \
    bash -e <<'EOF'
apt-get update
apt-get install -y --no-install-recommends \
    libicu76 libldap2 libsnmp40t64 libzip5 libpng16-16t64 \
    libjpeg62-turbo libwebp7 libfreetype6 libgpgme11t64 \
    mariadb-client-core rrdtool gettext-base
EOF

COPY --from=fpm_extensions --link /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=fpm_extensions --link /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

COPY --from=app_assembler --chown=www-data:www-data \
    /usr/share/centreon /usr/share/centreon

# SourceGuardian loader the 4 baked-in T0 modules need to run their
# (proprietary, SourceGuardian-encoded) PHP at all — see modules_fetcher
# above. Own path + own ini rather than the package's own Debian-convention
# one (bare "zend_extension = ixed.8.4.lin", resolved against php.ini's
# extension_dir): the official php:8.4-fpm image doesn't use Debian's
# /etc/php/8.4/... layout, so a full absolute path here avoids depending on
# this base image's own extension_dir naming.
COPY --from=modules_fetcher --link /modules/extracted/usr/lib/php/20240924/ixed.8.4.lin \
    /usr/local/lib/php-sourceguardian/ixed.8.4.lin
COPY --chown=www-data:www-data <<EOF /usr/local/etc/php/conf.d/sourceguardian.ini
zend_extension = /usr/local/lib/php-sourceguardian/ixed.8.4.lin
EOF

# Plugin packs for centreon-pp-manager (see packs_fetcher), read-only.
COPY --from=packs_fetcher --link /packs/extracted/usr/share/centreon-packs/ \
    /usr/share/centreon-packs/

# Build-time verification (COMP-09): both extensions are silent-failure-prone
# at runtime (the gnupg crash and the SourceGuardian ini path above were both
# found by actually hitting the failing code path, not by the build failing)
# — fail the build itself instead of shipping an image where either silently
# didn't load.
# Same for the extensions copied from fpm_extensions: a missing runtime
# library only shows up as a PHP startup warning, so check ldd and the
# loaded module list here.
RUN bash -eo pipefail <<'EOF'
for so in "$(php -r 'echo ini_get("extension_dir");')"/*.so; do
  if ldd "$so" | grep 'not found'; then echo "missing runtime library for $so" >&2; exit 1; fi
done
mods=$(php -m 2>&1)
if grep -i 'warning' <<<"$mods"; then echo "PHP startup warning" >&2; exit 1; fi
for ext in gd gettext gnupg intl ldap mysqli pcntl pdo_mysql redis snmp sysvsem zip; do
  grep -qix "$ext" <<<"$mods" || { echo "$ext extension not loaded" >&2; exit 1; }
done
grep -qi 'SourceGuardian' <<<"$(php -v)" || { echo "SourceGuardian loader not active" >&2; exit 1; }
EOF

COPY --chown=www-data:www-data .github/docker/centreon-web-split/trixie/configuration/fpm/centreon.ini.template \
    /usr/local/etc/php/conf.d/centreon.ini.template
COPY --chown=www-data:www-data .github/docker/centreon-web-split/trixie/configuration/fpm/centreon-pool.conf \
    /usr/local/etc/php-fpm.d/centreon.conf
COPY --chmod=755 .github/docker/centreon-web-split/trixie/entrypoint/fpm \
    /usr/local/lib/centreon-fpm/

# Deployment-specific, resolved from the template at container start (see
# 01-config.sh) — same UTC fallback the real packaging postinstall's
# setPhpTimezone() uses when it can't detect a host timezone either.
ENV PHP_TIMEZONE=UTC
# MON-214472: same packaged default as centreon/packaging/src/php.ini,
# raised at deployment time (01-config.sh) for large inventories.
ENV PHP_MEMORY_LIMIT=256M
# MON-214473: same packaged default (300), must stay above the configuration
# generation timeout (CENTREON_CONFIGURATION_GENERATION_TIMEOUT).
ENV PHP_MAX_EXECUTION_TIME=300
# MON-214095: files by default — nothing changes for a deployment that
# never sets this. Switched to Valkey explicitly (PHP_SESSION_HANDLER=redis
# + PHP_SESSION_SAVE_PATH, see 01-config.sh/centreon.ini.template).
ENV PHP_SESSION_HANDLER=files

# Install fixtures, opt-in via RUN_INSTALL (see
# entrypoint/fpm/container.d/12-install.sh), mirroring the monolithic
# image's own container.d/15-installation.sh + JSON answer-file fixtures.
# This is the actual install mechanism both here and in the k8s chart: the
# chart's dedicated install Job runs this same fpm image/entrypoint with
# INSTALL_DB_STEPS=true, while regular fpm replicas run with it set to
# false — not a disposable dev/CI-only path with a separate "real" install
# step still to be built.
COPY --chown=www-data:www-data .github/docker/centreon-web/trixie/configuration \
    /usr/share/centreon/www/install/tmp

# The image's default `www` pool must go entirely, not just www.conf: the
# php-fpm.d/*.conf files are merged by pool NAME across files, and
# docker.conf ALSO declares a `[www]` section (listen=9000, no user/group) —
# leaving it in place after deleting www.conf produces an incomplete `[www]`
# pool with no user defined, which fails `php-fpm -t` (caught by the build's
# own sanity check, not a hypothetical).
RUN rm -f /usr/local/etc/php-fpm.d/www.conf /usr/local/etc/php-fpm.d/zz-docker.conf \
    /usr/local/etc/php-fpm.d/docker.conf

# Build-time requirement, made explicit rather than left implicit (pillar-1
# page): php-fpm runs as www-data and must be able to create its own cache,
# logs and /etc/centreon at runtime. /etc/centreon-engine/engine-context.json
# (app_secret+salt) is unrelated to having a real engine — it's just a JSON
# file (EngineContextWriter), needed by both the install flow and "Generate
# Configuration Files" in the legacy UI. /etc/snmp/centreon_traps: "Generate
# traps" writes <poller id>/centreontrapd.sdb there (generateSqlLite) before
# sending it to gorgone, and /etc/snmp is root-owned.
RUN bash -e <<'EOF'
mkdir -p /var/log/centreon /var/cache/centreon/symfony /var/cache/centreon/symfony.new \
    /etc/centreon /etc/centreon/config.d /etc/centreon/license.d /etc/centreon-engine /var/lib/centreon \
    /var/lib/php/sessions /var/lib/php/wsdlcache /etc/snmp/centreon_traps
touch /var/log/centreon/prod.web.log /var/log/centreon/prod.access.log \
    /var/log/centreon/prod.token.log /var/log/centreon/prod.password.log \
    /var/log/centreon/prod.upgrade.log /var/log/centreon/prod.plugin-pack-manager.log \
    /var/log/centreon/prod.poller-install.log /var/log/centreon/rrdtool.log
chown -R www-data:www-data /usr/share/centreon /var/log/centreon \
    /etc/centreon /etc/centreon-engine \
    /var/lib/php/sessions /var/lib/php/wsdlcache
chown -R centreon:centreon /var/lib/centreon /var/cache/centreon /etc/centreon/license.d /etc/snmp/centreon_traps
chmod -R 775 /var/cache/centreon /var/lib/centreon /etc/centreon/license.d /etc/snmp/centreon_traps
EOF

# /var/log/centreon/*.log are real on-disk files, NOT symlinked to
# /dev/stdout — corrected 2026-09-30 after finding, empirically, that this
# repo's own earlier symlink-based approach was broken for every one of
# these files. Confirmed directly: PHP's fopen()/proc_open() against a
# /dev/stdout symlink fails with "No such file or directory" (append-mode
# open of a pipe-by-path from userland behaves differently from a shell `>`
# redirect, which is why an earlier shell-only smoke test of this looked
# fine); /proc/1/fd/1 directly isn't a fix either, "Permission denied" for
# www-data (php-fpm's own workers), since it belongs to PID 1's owner. See
# container.d/02-log-tail_background.sh, which tails these files to the
# container's real stdout instead — same reasoning login.log already had
# for staying a real file (LoggerAuthentication.php's flock-based writes,
# for fail2ban-style external tailing), now extended to the rest for a
# different but equally real cause. Pre-`touch`ed above (with the other
# runtime dirs) so `tail -F` finds them immediately instead of printing its
# own "cannot open ... No such file or directory" retry messages first.

# engine-context.json must exist (even empty) BEFORE createEngineContextConfiguration.php
# runs, not just its parent directory: on a real RPM/DEB install, the
# centreon-engine package ships this file empty as a ghost/ownership marker.
# FsEngineRepository::engineSecretsHasContent() calls Symfony Filesystem's
# readFile(), which throws if the file itself is missing — that IOException
# is mishandled (rethrown as a RepositoryException instead of "no content"),
# so without this the install step fails with "Unable to get content of
# engine-context file. check that file exists" and never writes it.
RUN touch /etc/centreon-engine/engine-context.json && \
    chown www-data:www-data /etc/centreon-engine/engine-context.json

# Real centengine (MON-214031), for "Generate Configuration Files"'s
# optional debug=true step (common-Func.php's printDebug()): shells out to
# `$nagios_bin -v <cfgpath>/centengine.DEBUG 2>&1` and looks for "Total
# Errors:"/"Total Warnings:" lines via regex. Previously a fake shim
# hardcoding "Total Warnings: 0 / Total Errors: 0" (see trixie/README.md and
# git history) — meant debug=true could never report a real config error.
# `nagios_bin` itself already resolves to this real path via
# insertBaseConf.php's own macro substitution (no placeholder bug here,
# unlike rrdtool_path_bin). This image still never *runs* centengine as a
# service (Pilier 2 owns that, its own container) — only `-v`/`-V`
# invocations, so no centengine user/config/whitelist setup is needed here.
COPY --from=engine_verifier --link \
    /usr/sbin/centengine /usr/sbin/centengine
# /usr/lib/, NOT /lib/: on Debian trixie's merged-usr layout, /lib is a
# SYMLINK to usr/lib — found the hard way via a real arm64 CI failure
# ("exec /bin/sh: no such file or directory" on the RUN below). `COPY
# --link` creates its destination directory tree as a real directory
# instead of following a symlink across layers, so a `--link` COPY into
# /lib/ replaces that symlink with a genuine /lib directory containing only
# these 3 files — deleting every other file reachable through it,
# including /lib/ld-linux-aarch64.so.1 (arm64) or /lib/libc.so.6 (depending
# on arch). amd64 happened to still build because its own dynamic linker
# lives under /lib64/, untouched by this — but that image's /lib was
# exactly as broken, just not yet hit by anything that needed it.
COPY --from=engine_verifier --link \
    /lib/libcentreon_clib.so /lib/libcentreon_engine_conf.so /lib/cbmod.so \
    /usr/lib/
# All of centengine's other runtime libs (libmariadb3, libssl3t64, libzstd1,
# zlib1g — confirmed via `ldd`) are already present in this image (PHP's own
# pdo_mysql/openssl/zlib extensions), so no apt install is needed here.
# ldconfig: refreshes this image's own ld.so.cache so the dynamic linker
# finds the 3 Centreon libs just dropped into /usr/lib — COPY alone doesn't
# update it. test -L /lib: guards against the exact bug above recurring
# silently (a future edit reintroducing a /lib/-targeted --link COPY).
RUN test -L /lib && \
    ldconfig && \
    centengine -V && \
    missing=$(ldd /usr/sbin/centengine | grep 'not found' || true) && \
    if [ -n "$missing" ]; then echo "centengine missing libraries:" >&2; echo "$missing" >&2; exit 1; fi

# bin/console (and other CLI scripts) shebang is #!/usr/bin/php — that path
# only exists via update-alternatives on a real Debian package install
# (centreon-web-postinstall.sh's manageApacheAndPhpFpm); the official image
# only has /usr/local/bin/php.
RUN ln -sf /usr/local/bin/php /usr/bin/php

# Deployment-specific, resolved at container start via php-fpm's native
# env[] $VARNAME expansion in centreon-pool.conf — no templating needed here.
ENV CENTREON_INTERNAL_API_BASE_URL=http://centreon-httpd:80
# The web's feature flags file (the Helm chart points it at a per-pod copy
# with Vault on). Never left empty: php-fpm passes an unset variable as "",
# which Symfony would read as a path.
ENV FILE_FEATURE_FLAGS=/usr/share/centreon/config/features.json

# The official php:8.4-fpm base image keeps its own build toolchain
# ($PHPIZE_DEPS: gcc, g++, make, libc6-dev, dpkg-dev, autoconf, re2c,
# pkg-config) installed — confirmed directly on the base image itself, not
# assumed. libc6-dev alone pulls linux-libc-dev, which by itself accounted
# for all 611 OS CVEs (45 HIGH/CRITICAL) Trivy found on this image. Every
# PHP extension this image ships is compiled in the separate fpm_extensions
# stage above and copied in as a prebuilt .so (see the COPY --from=
# fpm_extensions block above) — nothing in this final stage ever compiles
# anything, so the toolchain this base image carries is pure dead weight
# here. Purge last, right before the final php-fpm/php -m sanity checks
# below, so a build-time failure here still proves nothing *runtime* broke:
# --auto-remove also drops linux-libc-dev (only pulled in by libc6-dev), but
# named explicitly too as a safety net in case that dependency chain ever
# changes upstream.
RUN apt-get purge -y --auto-remove $PHPIZE_DEPS libc6-dev linux-libc-dev

RUN php-fpm -t

ARG BUILD_DATE
ARG BUILD_VERSION=unknown
ARG GIT_REVISION=unknown

LABEL org.opencontainers.image.title="Centreon web (php-fpm)" \
      org.opencontainers.image.description="Centreon web UI — application tree (PHP), built from source" \
      org.opencontainers.image.created="${BUILD_DATE}" \
      org.opencontainers.image.version="${BUILD_VERSION}" \
      org.opencontainers.image.revision="${GIT_REVISION}" \
      org.opencontainers.image.source="https://github.com/centreon/centreon" \
      org.opencontainers.image.licenses="Apache-2.0" \
      org.opencontainers.image.vendor="Centreon" \
      org.centreon.base-image="php:8.4-fpm" \
      org.centreon.release-stage="alpha"

HEALTHCHECK --interval=10s --timeout=5s --start-period=30s --retries=3 \
    CMD test -f /tmp/docker.ready || exit 1

EXPOSE 9000

# No explicit USER here on purpose (SEC-01): php-fpm's master process needs
# root to create/chown its own runtime state (see the mkdir/chown block
# above) and to spawn workers as www-data per the pool's own `user =
# www-data` directive (centreon-pool.conf) — the same reasoning the
# official php-fpm image itself relies on for not setting USER. Setting
# USER www-data here would break the master process, not harden it.
ENTRYPOINT ["/usr/local/lib/centreon-fpm/container.sh"]
CMD ["php-fpm"]

#############################################
# Stage 6b: application tree for the cron image, without the compiled front
# (www/static): the cron jobs are PHP/Perl scripts, nothing serves assets.
# Removed here, not after the COPY in the cron stage: layers are additive.
#############################################
FROM app_assembler AS app_cron
RUN rm -rf /usr/share/centreon/www/static

#############################################
# Stage 7 (final): cron — scheduled jobs real RPM/DEB installs get for free
# via /etc/cron.d/{centreon,centstorage} + the cronie/cron package dependency
# (packaging/centreon-web.yaml), never ported to this split image before now
# (Fabien: "petit détail qu'on a jamais géré"). A dedicated image/container,
# not folded into fpm: 2 of the jobs are Perl (eventReportBuilder,
# dashboardBuilder) — fpm has no Perl at all and has no reason to — and a
# persistent scheduler daemon inside fpm would multiply
# per fpm replica if that image is ever scaled (the same class of problem
# INSTALL_DB_STEPS exists to avoid for the install step). On k8s, this
# image's jobs should become native CronJob resources with `command`
# overriding `cron -f` to invoke one script directly — no daemon needed
# there, the cluster's own scheduler replaces cron entirely.
#
# Debian's own PHP 8.4 (packages, nothing compiled) on debian:trixie-slim,
# not the official php image: that one keeps its build toolchain (gcc, g++,
# headers, PHP sources: ~300MB) for docker-php-ext-install, useless for
# running scripts. The SourceGuardian loader is the Debian
# php8.4-sourceguardian-loader build, so it matches this PHP.
# centreon-backup.pl is not supported (no MariaDB client, no mysqldump): back
# up the database and the volumes instead.
#############################################
# Centreon Perl modules (centreon-collect perl-libs/lib), for the Perl jobs
# (eventReportBuilder/dashboardBuilder: centreon::script::*). Intentionally
# empty: replaced by --build-context perl-libs=<centreon-collect>/perl-libs/lib,
# same convention as the centreon-trapd image. No package fallback, so the
# build fails instead of mixing in Perl modules from another collect branch.
FROM scratch AS perl-libs

FROM debian:trixie-slim AS cron

# Same UID/GID setup as fpm (volume/ownership consistency) — only `centreon`
# is actually needed here (these jobs never touch engine/broker/gorgone
# paths), but kept identical to fpm's block rather than a trimmed-down copy
# that could silently drift. www-data already exists in this base image
# (inherited from Debian, not created by fpm's own block either) — added to
# the `centreon` group so @WEB_USER@ (www-data) cron lines can still read
# the @CENTREON_USER@-owned /etc/centreon/centreon.conf.php below (mode 640).
RUN bash -e <<'EOF'
groupadd -g 900 centreon
useradd -u 900 -g centreon -M -r -l -d /var/spool/centreon -s /usr/sbin/nologin centreon
usermod -aG centreon www-data
EOF

# Same PHP extension set as fpm (the PHP cron scripts reuse the exact same
# legacy class library, e.g. CentreonDB), plus `cron` itself and the Perl
# stack 2 of the jobs need. No MySQL client: the PHP jobs use PDO and the
# Perl ones DBI. `ps` comes with ucf (pulled by the php8.4 packages): the
# Perl jobs' lock relies on it, see container.sh for the stale-lock release a
# container needs (same PID on every run).
#
# The Perl modules come from the perl-libs stage; their runtime dependencies
# are the Depends of the centreon-perl-libs and centreon-perl-libs-common
# .deb packages (centreon-collect perl-libs/packaging). Two of them,
# libcrypt-openssl-aes-perl/libnet-curl-perl, do not exist anywhere in
# Debian trixie's own repos — confirmed directly (`apt-get install
# --dry-run` fails even with contrib/non-free/non-free-firmware all
# enabled). Both are, however, published on
# packages.centreon.com/plugins/apt-stable (same repo centreon-plugins itself
# depends on for its own SNMP/crypto features). Added as a source rather
# than worked around via CPAN (would need a full gcc/make/libssl-dev/
# libcurl4-openssl-dev toolchain for 2 of 10, secondary, reporting-only
# jobs — not worth the extra surface for this).
#
# curl/gnupg are transient (fetch+dearmor the repo key only) — purged in
# this same layer (OPT-05b): a later, separate RUN removing them wouldn't
# shrink this layer at all, Docker layers are additive.
RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt/lists,sharing=locked \
    bash -e <<'EOF'
apt-get update
apt-get install -y --no-install-recommends \
    php8.4-cli php8.4-mysql php8.4-intl php8.4-ldap php8.4-snmp php8.4-zip \
    php8.4-gd php8.4-xml php8.4-mbstring php8.4-curl php8.4-sqlite3 \
    php8.4-readline php8.4-opcache \
    cron curl ca-certificates gnupg
curl -sfL https://apt-key.centreon.com/ | gpg --dearmor > /etc/apt/trusted.gpg.d/centreon.gpg
echo "deb https://packages.centreon.com/plugins/apt-stable/ trixie main" \
  > /etc/apt/sources.list.d/centreon-plugins-stable.list
apt-get update
apt-get install -y --no-install-recommends \
  libconfig-inifiles-perl libcrypt-des-perl libcrypt-openssl-aes-perl \
  libcrypt-urandom-perl libdigest-hmac-perl libgd-perl libjson-xs-perl \
  libnet-curl-perl librrds-perl \
  libdbi-perl libdbd-mysql-perl libfile-which-perl
apt-get remove -y --purge curl gnupg
apt-get autoremove -y
rm -rf /var/lib/apt/lists/* /tmp/*
EOF

# Same layout as the centreon-perl-libs{,-common} packages (Debian's vendor
# Perl directory). Build-time check: the modules the Perl jobs load, and
# their dependencies, resolve.
COPY --from=perl-libs --link centreon/ /usr/share/perl5/centreon/
RUN bash -e <<'EOF'
for module in centreon::script::eventReportBuilder centreon::script::dashboardBuilder \
    centreon::common::db centreon::reporting::CentreonHost; do
  perl -e "require $module" || { echo "Perl module $module does not load" >&2; exit 1; }
done
EOF

# Real bug found only by actually waiting for a scheduled run, not by the
# container merely starting cleanly: Debian's cron package's PAM session
# stack silently denies every job in a container, with zero visible error
# anywhere (no syslog here, and cron's own libc syslog() calls for its
# per-job logging go nowhere without one — cron itself stays alive, `ps aux`
# shows it running the whole time, every scheduled job just never executes).
# First tried loosening only pam_loginuid.so (the single most commonly-cited
# cause: loginuid is already set by the container runtime and can't be
# rewritten) to `optional` — confirmed applied (cat'd the file inside the
# built image) but jobs still silently never ran, so something else in the
# included common-auth/common-account/common-session-noninteractive chain
# is also denying the session, not pinned down further without syslog
# visibility. Replaced the whole file with an always-succeed stack instead
# of chasing which exact included module is the real blocker — cron.d's own
# per-line user column is already the actual access control for what each
# job can do, this file only gates whether cron is allowed to start a
# session at all.
COPY <<EOF /etc/pam.d/cron
auth     required   pam_permit.so
account  required   pam_permit.so
session  required   pam_permit.so
EOF

# Full application tree (same source as fpm/httpd): the PHP cron scripts
# reuse Centreon's legacy class library directly (require_once ../config/
# centreon.config.php, www/class/*), and the Perl scripts live in the same
# cron/ directory. From app_cron: the same tree without www/static.
COPY --from=app_cron --chown=www-data:www-data \
    /usr/share/centreon /usr/share/centreon

# Same SourceGuardian loader as fpm (see that stage for the full story) —
# needed here too, found only by waiting for a real scheduled run:
# centKnowledgeSynchronizer.php's own DI container bootstrap autoloads
# CentreonAutoDiscovery\ServiceProvider (a T0 module's SourceGuardian-encoded
# class) regardless of what that specific job actually does, same shared
# legacy bootstrap as fpm. gnupg (fpm's other PECL extension) is NOT needed
# here — nothing in the cron jobs touches license upload/verification.
# Debian's own extension dir (API 20240924) and CLI conf.d.
COPY --from=modules_fetcher --link /modules/extracted/usr/lib/php/20240924/ixed.8.4.lin \
    /usr/lib/php/20240924/ixed.8.4.lin
COPY <<EOF /etc/php/8.4/cli/conf.d/05-sourceguardian.ini
zend_extension = /usr/lib/php/20240924/ixed.8.4.lin
EOF
# Build-time check: the same PHP modules as the official-image build this
# replaced (a missing one only shows up when a job hits it), no startup
# warning, SourceGuardian active.
RUN bash -eo pipefail <<'EOF'
mods=$(php -m 2>&1)
if grep -i 'warning' <<<"$mods"; then echo "PHP startup warning" >&2; exit 1; fi
for ext in ctype curl date dom fileinfo filter gd gettext hash iconv intl json \
    ldap libxml mbstring mysqli mysqlnd openssl pcre PDO pdo_mysql pdo_sqlite Phar \
    posix random readline Reflection session SimpleXML snmp sodium SPL sqlite3 \
    standard sysvsem tokenizer xml xmlreader xmlwriter zip zlib; do
  grep -qix "$ext" <<<"$mods" || { echo "$ext extension not loaded" >&2; exit 1; }
done
grep -qi 'SourceGuardian' <<<"$(php -v)" || { echo "SourceGuardian loader not active" >&2; exit 1; }
EOF

# Same install-fixture JSON fpm's own container.sh reads (www/install/tmp/
# database.json) — fpm gets this via its own direct COPY from the build
# context (not part of app_assembler's output, see the fpm stage above),
# needed here too since container.sh (this stage's own entrypoint) derives
# this container's local centreon.conf.php/conf.pm from the same file.
COPY --chown=www-data:www-data .github/docker/centreon-web/trixie/configuration \
    /usr/share/centreon/www/install/tmp

COPY --chmod=755 .github/docker/centreon-web-split/trixie/entrypoint/cron \
    /usr/local/lib/centreon-cron/

# PHP's date.timezone, written by container.sh. Must match fpm's PHP_TIMEZONE
# (the timezone the install created the database partitions in).
ENV PHP_TIMEZONE=UTC

ARG BUILD_DATE
ARG BUILD_VERSION=unknown
ARG GIT_REVISION=unknown

LABEL org.opencontainers.image.title="Centreon web (cron)" \
      org.opencontainers.image.description="Centreon web UI — scheduled jobs (CentACL, downtimes, token/storage purge, partitioning, reporting), built from source" \
      org.opencontainers.image.created="${BUILD_DATE}" \
      org.opencontainers.image.version="${BUILD_VERSION}" \
      org.opencontainers.image.revision="${GIT_REVISION}" \
      org.opencontainers.image.source="https://github.com/centreon/centreon" \
      org.opencontainers.image.licenses="Apache-2.0" \
      org.opencontainers.image.vendor="Centreon" \
      org.centreon.base-image="debian:trixie-slim" \
      org.centreon.release-stage="alpha"

# No HEALTHCHECK: cron itself has no request/port to probe. `docker compose
# ps`'s own Up/Restarting state already reflects whether the daemon is
# alive (same reasoning a plain systemd cron unit would give you on a VM).

# No explicit USER here on purpose (SEC-01), same class of exception as
# fpm/httpd: cron's own daemon needs root to switch to each cron.d line's
# declared user (www-data/centreon/root) per job — that's the whole point
# of /etc/cron.d's per-line user column, not something a non-root PID 1
# could do itself.
ENTRYPOINT ["/usr/local/lib/centreon-cron/container.sh"]

#############################################
# Stage (final): rrdcached — TCP write-cache sidecar for centreon-broker's
# RRD output (MON-213837). Reached over TCP 42217: in compose it is its own
# `rrdcached` service (broker's rrd output gets that name through its
# `host` key, MON-213839); in the chart it is a sidecar of the broker pod
# (127.0.0.1), exposed to fpm by a Service for the graphs. Enabled by
# default (RRDCACHED_PORT=42217). The image only needs the binary plus the
# UID/GID of centreon-broker's own image (902), since it writes into the
# RRD tree broker owns (/var/lib/centreon/{metrics,status}), shared via
# volume. Built here rather than taken from a third-party image: there is
# no official rrdcached image, and this one runs non-root from PID 1.
#############################################
FROM debian:trixie-slim AS rrdcached

RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt/lists,sharing=locked \
    bash -e <<'EOF'
apt-get update
apt-get install -y --no-install-recommends rrdcached
rm -rf /var/lib/apt/lists/*
EOF

# Same fixed UID/GID as centreon-broker's own image (902) — rrdcached must
# write into the same RRD tree broker owns, as the same user, not just read
# it.
RUN groupadd -g 902 centreon-broker && \
    useradd -u 902 -g centreon-broker -M -r -l -d /var/lib/centreon-broker -s /usr/sbin/nologin centreon-broker

RUN mkdir -p /var/lib/rrdcached/journal && \
    chown -R centreon-broker:centreon-broker /var/lib/rrdcached

ARG BUILD_DATE
ARG BUILD_VERSION=unknown
ARG GIT_REVISION=unknown

LABEL org.opencontainers.image.title="Centreon web (rrdcached)" \
      org.opencontainers.image.description="TCP write-cache sidecar for centreon-broker's RRD output" \
      org.opencontainers.image.created="${BUILD_DATE}" \
      org.opencontainers.image.version="${BUILD_VERSION}" \
      org.opencontainers.image.revision="${GIT_REVISION}" \
      org.opencontainers.image.source="https://github.com/centreon/centreon" \
      org.opencontainers.image.licenses="Apache-2.0" \
      org.opencontainers.image.vendor="Centreon" \
      org.centreon.base-image="debian:trixie-slim" \
      org.centreon.release-stage="alpha"

HEALTHCHECK --interval=10s --timeout=5s --start-period=10s --retries=3 \
    CMD ["bash", "-c", "exec 3<>/dev/tcp/127.0.0.1/42217 && echo PING >&3 && read -t 2 r <&3 && [[ \"$r\" == *PONG* ]]"]

USER 902:902

# ENTRYPOINT fixed to the binary only, every flag in CMD: a compose/k8s
# override replaces the args wholesale (e.g. a different -w/-z flush
# timing) without having to repeat the binary name.
#
# -p is a correction to the original contract, found by actually running
# this: -g ("do not fork and run in the foreground") does NOT, by itself,
# skip pidfile handling in this rrdcached version (1.7.2) — it still tries
# to create/open its compiled-in default '/var/run/rrdcached.pid', which a
# non-root UID (902) can't write to ("Permission denied", then "daemonize
# failed, exiting", confirmed reproduced before this flag was added).
# Pointed at the journal dir, already owned by this image's own user.
ENTRYPOINT ["rrdcached"]
CMD ["-g", "-l", "0.0.0.0:42217", "-b", "/var/lib/centreon", "-B", "-j", "/var/lib/rrdcached/journal", "-F", "-w", "300", "-z", "60", "-f", "3600", "-p", "/var/lib/rrdcached/journal/rrdcached.pid"]
