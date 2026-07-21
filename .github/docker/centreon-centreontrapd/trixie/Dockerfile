# syntax=docker/dockerfile:1

ARG USE_SOURCE_TRAPD=false

#############################################
# Stage 1: Extract files from .deb packages
#############################################
FROM debian:13-slim AS extractor

RUN --mount=type=bind,source=packages-centreon,target=/packages \
    bash -e <<EOF
mkdir -p /extracted
cd /packages
for deb in centreon-trap*.deb centreon-perl-libs*.deb; do
    echo "Extracting \$deb..."
    dpkg-deb -x "\$deb" /extracted/
done
# This image only runs centreontrapd — trim the sibling centreontrapdforward binary and the
# systemd/sysconfig/logrotate files (inert here: no systemd, logs go to stdout) so the runtime
# stage only ever copies a directory containing what it actually needs, rather than us having
# to name individual files to include.
rm -f /extracted/usr/share/centreon/bin/centreontrapdforward \
      /extracted/lib/systemd/system/centreontrapd.service \
      /extracted/etc/default/centreontrapd \
      /extracted/etc/logrotate.d/centreontrapd
echo "=== Extracted binaries (trimmed) ==="
ls -lah /extracted/usr/share/centreon/bin/ || true
echo "=== Extracted Perl modules ==="
ls -lah /extracted/usr/share/perl5/ || true
EOF

#############################################
# Stage 1b/1c: Centreon Perl libs selector
# USE_SOURCE_TRAPD=false → perl modules already come from the broad COPY --from=extractor below
#                          (this branch is an empty no-op merge)
# USE_SOURCE_TRAPD=true  → copy from local repo source (dev/testing), overriding the .deb install
#############################################
FROM debian:13-slim AS centreon-perl-false
RUN mkdir -p /centreon-modules

FROM scratch AS centreon-perl-true
COPY centreon-collect/perl-libs/lib/centreon/ /centreon-modules/

ARG USE_SOURCE_TRAPD=false
# hadolint ignore=DL3006
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
useradd -u 900 -g centreon -m -r -d /var/spool/centreon -s /bin/bash centreon
groupadd -g 901 centreon-engine
useradd -u 901 -g centreon-engine -m -r -d /var/lib/centreon-engine -s /bin/bash centreon-engine
groupadd -g 903 centreon-gorgone
useradd -u 903 -g centreon-gorgone -m -r -d /var/lib/centreon-gorgone -s /bin/bash centreon-gorgone
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
rm -rf /tmp/* /var/tmp/* /usr/share/doc/* /usr/share/man/*
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

# Copy everything the extractor kept (already trimmed to just what this image needs) in one
# shot, instead of naming individual destination paths — a file added anywhere under /extracted
# by a future packaging change lands here automatically, no Dockerfile update required.
COPY --from=extractor --link /extracted/ /

# Override with local dev source when USE_SOURCE_TRAPD=true; a no-op empty merge otherwise
# (perl modules already arrived via the COPY above).
COPY --from=centreon-perl-selected --link /centreon-modules/ /usr/share/perl5/centreon/

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
