# syntax=docker/dockerfile:1
#
# Single image for both trap daemons: two containers, one image, role picked
# at runtime from the CMD the caller passes (see container.sh).
# container.d/ scripts named *.snmptrapd.sh or *.centreontrapd.sh only run for
# that role; unsuffixed scripts (00-init.sh) run for both.
#
# Package-less build: no .deb, no upstream image.
#   - centreontrapd / centreontrapdforward wrappers come from this repo
#     (centreon/bin/).
#   - Their Perl modules (centreon::script, centreon::trapd, centreon::common)
#     live in centreon-collect (perl-libs/lib/centreon) and MUST be provided
#     through the "perl-libs" named build context, pointing at a centreon-collect
#     checkout of the same branch (develop, dev-XX.XX.x, ...):
#
#   docker buildx build \
#     --build-context perl-libs=../centreon-collect/perl-libs/lib \
#     -f .github/docker/centreon-trapd/trixie/Dockerfile .

#############################################
# Centreon Perl modules (centreon-collect perl-libs/lib)
# Intentionally empty: replaced by --build-context perl-libs=<path>. No remote
# fallback, so the build fails loudly instead of silently mixing in Perl libs
# from another centreon-collect branch.
#############################################
FROM scratch AS perl-libs

#############################################
# Runtime - Debian 13 slim image
#############################################
FROM debian:13-slim AS trapd

COPY <<EOF /etc/apt/apt.conf.d/99custom
Acquire::Retries "10";
Acquire::https::Timeout "300";
Acquire::http::Timeout "300";
APT::Install-Recommends "false";
APT::Install-Suggests "false";
EOF

# Union of both images' users/groups (UIDs match other Centreon containers
# for volume consistency). centreon-engine/centreon-gorgone only matter for
# the centreontrapd role, harmless for the snmptrapd role.
RUN bash -e <<'EOF'
groupadd -g 900 centreon
useradd -u 900 -g centreon -m -r -l -d /var/spool/centreon -s /bin/bash centreon
groupadd -g 901 centreon-engine
useradd -u 901 -g centreon-engine -m -r -l -d /var/lib/centreon-engine -s /bin/bash centreon-engine
groupadd -g 903 centreon-gorgone
useradd -u 903 -g centreon-gorgone -m -r -l -d /var/lib/centreon-gorgone -s /bin/bash centreon-gorgone
usermod -aG centreon-gorgone centreon
usermod -aG centreon-engine centreon
EOF

# Union of both images' runtime dependencies.
RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt/lists,sharing=locked \
    bash -e <<EOF
apt-get update
apt-get install -y --no-install-recommends \
    snmptrapd \
    perl \
    libdbi-perl \
    libdbd-sqlite3-perl \
    libconfig-inifiles-perl \
    libsnmp-perl \
    snmp \
    tzdata \
    ca-certificates \
    inotify-tools \
    procps
apt-get clean
rm -rf /var/cache/apt/* /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/* /usr/share/man/*
EOF

# Union of both images' runtime directory structure and permissions.
RUN bash -e <<'EOF'
mkdir -p /var/spool/centreontrapd \
         /etc/snmp/centreon_traps \
         /etc/centreon \
         /var/log/centreon \
         /var/lib/centreon-engine/rw \
         /usr/share/centreon/bin \
         /usr/local/lib/centreon-trapd
chmod 1777 /var/spool/centreontrapd
chown centreon:centreon /var/spool/centreontrapd
chown -R centreon:centreon /etc/snmp
chmod 0775 /etc/snmp/centreon_traps
chown centreon:centreon /etc/centreon
chmod 0775 /etc/centreon
chown centreon:centreon /var/log/centreon
chown centreon-engine:centreon-engine /var/lib/centreon-engine
chmod 0755 /var/lib/centreon-engine
chown centreon-engine:centreon-engine /var/lib/centreon-engine/rw
chmod 0775 /var/lib/centreon-engine/rw
chmod 0755 /var/spool/centreon
EOF

# Daemon wrappers (same paths/modes as the centreon-trap package) and the
# Centreon Perl module tree (same layout as the centreon-perl-libs package).
COPY --link --chmod=755 centreon/bin/centreontrapd centreon/bin/centreontrapdforward /usr/share/centreon/bin/
COPY --from=perl-libs --link centreon/ /usr/share/perl5/centreon/

COPY --link --chmod=755 .github/docker/centreon-trapd/trixie/entrypoint /usr/local/lib/centreon-trapd/

# Verify runtime integrity for both roles.
RUN bash -e <<EOF
perl -I/usr/share/perl5 \
    -e "require centreon::script::centreontrapdforward or die 'centreontrapdforward module failed'"
perl -I/usr/share/perl5 \
    -e "require centreon::script::centreontrapd or die 'centreontrapd module failed'"
perl -I/usr/share/perl5 \
    -e "require centreon::trapd::lib or die 'trapd::lib module failed'"
perl -I/usr/share/perl5 \
    -e "require DBI; require DBD::SQLite"
test -x /usr/share/centreon/bin/centreontrapdforward || exit 1
test -x /usr/share/centreon/bin/centreontrapd || exit 1
EOF

ENV PERL5LIB=/usr/share/perl5:/usr/lib/perl5 \
    DEBUG=false \
    CENTREONTRAPD_LOG_LEVEL=error

ARG BUILD_DATE
ARG BUILD_VERSION=unknown
ARG PACKAGE_SOURCES=unknown
ARG GIT_REVISION=unknown

LABEL org.opencontainers.image.title="Centreon TRAPD" \
      org.opencontainers.image.description="Centreon SNMP trap receiver + trap handler, role picked by the container command" \
      org.opencontainers.image.created="${BUILD_DATE}" \
      org.opencontainers.image.version="${BUILD_VERSION}" \
      org.opencontainers.image.revision="${GIT_REVISION}" \
      org.opencontainers.image.source="https://github.com/centreon/centreon" \
      org.opencontainers.image.licenses="Apache-2.0" \
      org.opencontainers.image.vendor="Centreon" \
      org.centreon.package-sources="${PACKAGE_SOURCES}" \
      org.centreon.base-image="debian:13-slim" \
      org.centreon.release-stage="beta"

WORKDIR /etc/centreon

# NOTE: Requires CAP_NET_BIND_SERVICE at runtime for the snmptrapd role to
# bind UDP/162 (same requirement as today's centreon-snmptrapd image).
USER centreon

HEALTHCHECK --interval=10s --timeout=5s --start-period=30s --retries=3 \
    CMD test -f /tmp/docker.ready || exit 1

EXPOSE 162/udp

ENTRYPOINT ["/usr/local/lib/centreon-trapd/container.sh"]
# No meaningful default: the caller (compose/chart) always picks a role by
# overriding `command:`. Defaults to the centreontrapd role so an unconfigured
# `docker run` at least fails loudly instead of silently binding UDP/162.
CMD ["/usr/bin/perl", "/usr/share/centreon/bin/centreontrapd", \
     "--logfile=/dev/stdout", \
     "--severity=error", \
     "--config=/etc/centreon/conf.pm", \
     "--config-extra=/etc/centreon/centreontrapd.pm"]
