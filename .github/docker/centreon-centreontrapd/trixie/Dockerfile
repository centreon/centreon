# syntax=docker/dockerfile:1

ARG USE_SOURCE_TRAPD=false

#############################################
# Stage 1: Extract files from .deb packages
#############################################
FROM debian:13-slim AS extractor

RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt/lists,sharing=locked \
    bash -e <<EOF
apt-get update
apt-get install -y --no-install-recommends \
        dpkg
rm -rf /var/cache/apt/* /var/lib/apt/lists/* /tmp/* /var/tmp/*
EOF

# Extract .deb packages from bind mount
# NOTE: Uses --mount=type=bind to avoid copying large .deb files into build context
RUN --mount=type=bind,source=packages-centreon,target=/packages \
    mkdir -p /extracted && \
    cd /packages && \
    for deb in centreon-trap*.deb centreon-perl-libs*.deb; do \
        dpkg-deb -x "$deb" /extracted/; \
    done

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
FROM debian:13-slim AS centreontrapd

# Create system users and groups (UUIDs match other Centreon containers for volume consistency)
# centreon-engine group: centreontrapd writes to centengine.cmd pipe (owned by centreon-engine)
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

# Install runtime dependencies (pinned to Debian 13 trixie versions for reproducibility)
# Perl core + libraries: centreon trapd handlers and utilities
# snmp: SNMP OID resolution via Net::SNMP Perl bindings
# sqlite3: centreontrapd.sdb database (poller mode=1)
# Other: timezone, CA certs, file monitoring, process utilities
RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt/lists,sharing=locked \
    bash -e <<EOF
apt-get update
apt-get install -y --no-install-recommends \
    perl=5.40.1-6 \
    libdbi-perl=1.647-1+deb13u1 \
    libdbd-sqlite3-perl=1.76-1 \
    libconfig-inifiles-perl=3.000003-3+deb13u1 \
    libsnmp-perl=5.9.4+dfsg-2+deb13u1 \
    snmp=5.9.4+dfsg-2+deb13u1 \
    tzdata=2026b-0+deb13u1 \
    ca-certificates=20250419 \
    inotify-tools=4.23.9.0-2+b1 \
    procps=2:4.0.4-9 && \
apt-get clean
rm -rf /var/cache/apt/* /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/* /usr/share/man/*
EOF

# Create runtime directory structure and set permissions
# /var/spool/centreontrapd: Working directory for trap processing (world-writable for pipeline)
# /etc/snmp/centreon_traps: SNMP trap configurations
# /etc/centreon: Centreon application configs
# /var/lib/centreon-engine/rw: Named pipe for engine communication
RUN bash -e <<'EOF'
mkdir -p /var/spool/centreontrapd \
         /etc/snmp/centreon_traps \
         /etc/centreon \
         /var/log/centreon \
         /var/lib/centreon-engine/rw \
         /usr/share/centreon/bin \
         /usr/local/lib/centreon-centreontrapd
chmod 1777 /var/spool/centreontrapd
chown centreon:centreon /var/spool/centreontrapd
chown centreon:centreon /etc/snmp/centreon_traps
chmod 0775 /etc/snmp/centreon_traps
chown centreon:centreon /var/log/centreon
chown centreon:centreon /etc/centreon
chmod 0775 /etc/centreon
chown centreon-engine:centreon-engine /var/lib/centreon-engine
chmod 0755 /var/lib/centreon-engine
chown centreon-engine:centreon-engine /var/lib/centreon-engine/rw
chmod 0775 /var/lib/centreon-engine/rw
EOF

# Copy extracted binaries and Perl modules
# Using --link: hardlinks reduce layer size (no duplication across stages)
COPY --from=extractor --link --chmod=755 /extracted/usr/share/centreon/bin/centreontrapd /usr/share/centreon/bin/centreontrapd
COPY --from=centreon-perl-selected --link /centreon-modules/ /usr/share/perl5/centreon/

# Copy entrypoint scripts
COPY --link --chmod=755 .github/docker/centreon-centreontrapd/trixie/entrypoint /usr/local/lib/centreon-centreontrapd/

# Verify runtime integrity: Perl modules loadable, binary executable
RUN bash -e <<EOF
perl -I/usr/share/perl5 \
    -e "require centreon::script::centreontrapd or die 'centreontrapd module failed'"
perl -I/usr/share/perl5 \
    -e "require centreon::trapd::lib or die 'trapd::lib module failed'"
perl -I/usr/share/perl5 \
    -e "require DBI; require DBD::SQLite"
test -x /usr/share/centreon/bin/centreontrapd || exit 1
EOF

# Runtime environment and build metadata
ENV PERL5LIB=/usr/share/perl5:/usr/lib/perl5 \
    DEBUG=false

# OCI image metadata for supply chain traceability (used by Scout, registries, and auditing)
ARG BUILD_DATE
ARG BUILD_VERSION=unknown
ARG PACKAGE_SOURCES=unknown

LABEL org.opencontainers.image.title="Centreon TRAPD" \
      org.opencontainers.image.description="Centreon trap handler and SNMP v1/v2c/v3 receiver" \
      org.opencontainers.image.created="${BUILD_DATE}" \
      org.opencontainers.image.version="${BUILD_VERSION}" \
      org.opencontainers.image.vendor="Centreon" \
      org.centreon.package-sources="${PACKAGE_SOURCES}" \
      org.centreon.base-image="debian:13-slim"

WORKDIR /etc/centreon

# Run as non-root user for security (uid 900: centreon service account)
USER centreon

# Liveness probe: container.d/99-logs.sh signals readiness by creating /tmp/docker.ready
HEALTHCHECK --interval=10s --timeout=5s --start-period=30s --retries=3 \
    CMD test -f /tmp/docker.ready || exit 1

# Main process: SNMP trap handler daemon
# Entrypoint coordinates multi-container startup (wait for engine, etc.)
ENTRYPOINT ["/usr/local/lib/centreon-centreontrapd/container.sh"]
CMD ["/usr/bin/perl", "/usr/share/centreon/bin/centreontrapd", \
     "--logfile=/dev/stdout", \
     "--severity=error", \
     "--config=/etc/centreon/conf.pm", \
     "--config-extra=/etc/centreon/centreontrapd.pm"]
