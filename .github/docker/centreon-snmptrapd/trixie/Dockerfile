# syntax=docker/dockerfile:1

ARG USE_SOURCE_TRAPD=false

#############################################
# Stage 1: Extract files from .deb packages
#############################################
FROM debian:13-slim AS extractor

# Install dpkg and extract .deb packages from bind mount in one layer
# NOTE: Uses --mount=type=bind to avoid copying large .deb files into build context
RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt/lists,sharing=locked \
    --mount=type=bind,source=packages-centreon,target=/packages \
    bash -e <<'EOF'
apt-get update
apt-get install -y --no-install-recommends dpkg
rm -rf /var/cache/apt/* /var/lib/apt/lists/* /tmp/* /var/tmp/*
mkdir -p /extracted
cd /packages
for deb in centreon-trap*.deb centreon-perl-libs*.deb; do
    dpkg-deb -x "$deb" /extracted/
done
EOF

#############################################
# Stage 1b/1c: Centreon Perl libs selector
# USE_SOURCE_TRAPD=false → extract from .deb (production: stable, tested)
# USE_SOURCE_TRAPD=true  → copy from local source (dev: rapid iteration)
#############################################
FROM scratch AS centreon-perl-false
COPY --from=extractor --link /extracted/usr/share/perl5/centreon /centreon-modules/

FROM scratch AS centreon-perl-true
COPY centreon-collect/perl-libs/lib/centreon/ /centreon-modules/

# Select perl libs source based on build argument
ARG USE_SOURCE_TRAPD=false
FROM centreon-perl-${USE_SOURCE_TRAPD} AS centreon-perl-selected

#############################################
# Stage 2: Runtime - Debian 13 slim image
#############################################
FROM debian:13-slim AS snmptrapd

# Create system user and group (UUID matches other Centreon containers for volume consistency)
# centreon: Primary service account for spool/config management
RUN bash -e <<'EOF'
groupadd -g 900 centreon
useradd -u 900 -g centreon -m -r -l -d /var/spool/centreon -s /bin/bash centreon
EOF

# Install runtime dependencies (unpinned: tracks trixie's stable-security channel,
# see MON ticket for the pinning-vs-drift tradeoff discussion)
# snmptrapd: SNMP trap receiver daemon
# perl + libs: Trap forwarding and configuration utilities
# Other: timezone, CA certs, process utilities
# NOTE: centreontrapdforward only writes spool files — no SQLite or SNMP OID libs needed
RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt/lists,sharing=locked \
    bash -e <<EOF
apt-get update
apt-get install -y --no-install-recommends \
    snmptrapd \
    perl \
    libdbi-perl \
    tzdata \
    ca-certificates \
    procps
apt-get clean
rm -rf /var/cache/apt/* /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/* /usr/share/man/*
EOF

# Create runtime directory structure and set permissions
# /var/spool/centreontrapd: Working directory for trap forwarding (world-writable for pipeline)
# /etc/snmp: SNMP trap daemon configuration
# /etc/centreon: Centreon application configs
# /var/log/centreon: Application logs
RUN bash -e <<'EOF'
mkdir -p /var/spool/centreontrapd \
         /etc/snmp \
         /etc/centreon \
         /var/log/centreon \
         /usr/share/centreon/bin \
         /usr/local/lib/centreon-snmptrapd
chmod 1777 /var/spool/centreontrapd
chown centreon:centreon /var/spool/centreontrapd
chown centreon:centreon /etc/centreon
chown centreon:centreon /var/log/centreon
chown -R centreon:centreon /etc/snmp
chmod 0755 /var/spool/centreon
EOF

# Copy extracted binaries and Perl modules
# Using --link: hardlinks reduce layer size (no duplication across stages)
COPY --from=extractor --link --chmod=755 /extracted/usr/share/centreon/bin/centreontrapdforward /usr/share/centreon/bin/centreontrapdforward
COPY --from=centreon-perl-selected --link /centreon-modules/ /usr/share/perl5/centreon/

# Copy entrypoint scripts
COPY --link --chmod=755 .github/docker/centreon-snmptrapd/trixie/entrypoint /usr/local/lib/centreon-snmptrapd/

# Verify runtime integrity: Perl modules loadable, binary executable
RUN bash -e <<EOF
perl -I/usr/share/perl5 \
    -e "require centreon::script::centreontrapdforward or die 'centreontrapdforward module failed'"
test -x /usr/share/centreon/bin/centreontrapdforward || exit 1
EOF

# Runtime environment and build metadata
ENV PERL5LIB=/usr/share/perl5:/usr/lib/perl5 \
    DEBUG=false

# OCI image metadata for supply chain traceability (used by Scout, registries, and auditing)
ARG BUILD_DATE
ARG BUILD_VERSION=unknown
ARG PACKAGE_SOURCES=unknown
ARG GIT_REVISION=unknown

LABEL org.opencontainers.image.title="Centreon SNMPTRAPD" \
      org.opencontainers.image.description="Centreon SNMP trap forwarder" \
      org.opencontainers.image.created="${BUILD_DATE}" \
      org.opencontainers.image.version="${BUILD_VERSION}" \
      org.opencontainers.image.revision="${GIT_REVISION}" \
      org.opencontainers.image.source="https://github.com/centreon/centreon" \
      org.opencontainers.image.licenses="Apache-2.0" \
      org.opencontainers.image.vendor="Centreon" \
      org.centreon.package-sources="${PACKAGE_SOURCES}" \
      org.centreon.base-image="debian:13-slim" \
      org.centreon.release-stage="beta"

WORKDIR /etc/snmp

# Run as non-root user for security (uid 900: centreon service account)
# NOTE: Requires CAP_NET_BIND_SERVICE at runtime to bind UDP/162
USER centreon

# Liveness probe: container.d/99-logs.sh signals readiness by creating /tmp/docker.ready
HEALTHCHECK --interval=10s --timeout=5s --start-period=30s --retries=3 \
    CMD test -f /tmp/docker.ready || exit 1

# SNMP trap receiver daemon: listens on UDP/162 for SNMP traps
EXPOSE 162/udp

# Main process: SNMP trap daemon with forwarding
# Entrypoint coordinates multi-container startup (wait for centreontrapdforward, etc.)
ENTRYPOINT ["/usr/local/lib/centreon-snmptrapd/container.sh"]
CMD ["/usr/sbin/snmptrapd", "-f", "-On", "-Lo", "-c", "/etc/snmp/snmptrapd.conf"]
