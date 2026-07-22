# syntax=docker/dockerfile:1

ARG USE_SOURCE_TRAPD=false

#############################################
# Stage 1: Extract files from .deb packages
#############################################
FROM debian:13-slim AS extractor

# Configure APT for optimal container usage
COPY <<EOF /etc/apt/apt.conf.d/99custom
Acquire::Retries "10";
Acquire::https::Timeout "300";
Acquire::http::Timeout "300";
APT::Install-Recommends "false";
APT::Install-Suggests "false";
EOF

RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt/lists,sharing=locked \
    bash -e <<EOF
apt-get update
apt-get install -y --no-install-recommends dpkg
EOF

WORKDIR /packages
RUN --mount=type=bind,source=packages-centreon,target=/packages \
    mkdir -p /extracted && \
    for deb in centreon-trap*.deb centreon-perl-libs*.deb; do \
        echo "Extracting $deb..."; \
        dpkg-deb -x "$deb" /extracted/; \
    done && \
    echo "=== Extracted binaries ===" && \
    ls -lah /extracted/usr/share/centreon/bin/ || true && \
    echo "=== Extracted Perl modules ===" && \
    ls -lah /extracted/usr/share/perl5/ || true

#############################################
# Stage 1b/1c: Centreon Perl libs selector
# USE_SOURCE_TRAPD=false → extract from .deb (production default)
# USE_SOURCE_TRAPD=true  → copy from local repo source (dev/testing)
#############################################
FROM scratch AS centreon-perl-false
COPY --from=extractor --link /extracted/usr/share/perl5/centreon /centreon-modules/

FROM scratch AS centreon-perl-true
COPY centreon-collect/perl-libs/lib/centreon/ /centreon-modules/

ARG USE_SOURCE_TRAPD=false
FROM centreon-perl-${USE_SOURCE_TRAPD} AS centreon-perl-selected

#############################################
# Stage 2: Runtime - Debian 13 slim image
#############################################
FROM debian:13-slim AS centreontrapd

# Create users and groups (same UIDs as other Centreon containers for volume consistency)
# centreon-engine group needed: centreontrapd writes to centengine.cmd pipe (owned by centreon-engine)
RUN bash -e <<EOF
groupadd -g 33 www-data 2>/dev/null || true
groupadd -g 900 centreon
useradd -u 900 -g centreon -m -r -l -d /var/spool/centreon -s /bin/bash centreon
groupadd -g 901 centreon-engine
useradd -u 901 -g centreon-engine -m -r -l -d /var/lib/centreon-engine -s /bin/bash centreon-engine
groupadd -g 903 centreon-gorgone
useradd -u 903 -g centreon-gorgone -m -r -l -d /var/lib/centreon-gorgone -s /bin/bash centreon-gorgone
usermod -aG centreon-gorgone centreon
usermod -aG centreon-engine centreon
EOF

# Configure APT for optimal container usage
COPY <<EOF /etc/apt/apt.conf.d/99custom
Acquire::Retries "10";
Acquire::https::Timeout "300";
Acquire::http::Timeout "300";
APT::Install-Recommends "false";
APT::Install-Suggests "false";
EOF

# Install runtime dependencies
# libdbd-sqlite3-perl: SQLite access for centreontrapd.sdb (poller mode=1)
# snmp: SNMP OID resolution via Net::SNMP Perl bindings
RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt/lists,sharing=locked \
    bash -e <<EOF
apt-get update
apt-get install -y --no-install-recommends \
    perl=5.36.0-7 \
    libdbi-perl=1.645-1 \
    libdbd-sqlite3-perl=1.72-1 \
    libconfig-inifiles-perl=3.000003-1 \
    libsnmp-perl=5.9.4+dfsg-1.1 \
    snmp=5.9.4+dfsg-1.1 \
    tzdata=2024a-1 \
    ca-certificates=20230311 \
    inotify-tools=3.24.6-1 \
    procps=2:4.0.4-4.1
rm -rf /var/cache/apt/* /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/* /usr/share/man/*
EOF

# Create directory structure
RUN bash -e <<EOF
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

# Copy Perl modules from selected source
COPY --from=centreon-perl-selected --link /centreon-modules/ /usr/share/perl5/centreon/

# Copy centreontrapd binary from extractor
COPY --from=extractor --link --chmod=755 /extracted/usr/share/centreon/bin/centreontrapd /usr/share/centreon/bin/centreontrapd

# Copy entrypoint scripts
COPY --link --chmod=755 .github/docker/centreon-centreontrapd/trixie/entrypoint /usr/local/lib/centreon-centreontrapd/

# Verify Perl modules are loadable
RUN echo "=== Verification ===" && \
    perl -e "require centreon::script::centreontrapd; print 'centreontrapd OK\n'" && \
    perl -e "require centreon::trapd::lib; print 'trapd::lib OK\n'" && \
    perl -e "require DBI; require DBD::SQLite; print 'SQLite OK\n'" && \
    ls -lah /usr/share/centreon/bin/centreontrapd && \
    echo "=== Files verified ==="

ENV PERL5LIB=/usr/share/perl5:/usr/lib/perl5
ENV DEBUG=false

# Build metadata for Scout/audit trail
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

USER centreon

# container.d/99-logs.sh touches this file once every startup script has run.
HEALTHCHECK --interval=10s --timeout=5s --start-period=30s --retries=3 \
    CMD test -f /tmp/docker.ready

ENTRYPOINT ["/usr/local/lib/centreon-centreontrapd/container.sh"]
CMD ["/usr/bin/perl", "/usr/share/centreon/bin/centreontrapd", \
     "--logfile=/dev/stdout", \
     "--severity=error", \
     "--config=/etc/centreon/conf.pm", \
     "--config-extra=/etc/centreon/centreontrapd.pm"]
