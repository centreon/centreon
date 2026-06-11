ARG USE_SOURCE_TRAPD=false

#############################################
# Stage 1: Extract files from .deb packages
#############################################
FROM debian:13-slim AS extractor

RUN apt-get update && apt-get install -y --no-install-recommends \
    dpkg \
    && rm -rf /var/lib/apt/lists/*

RUN --mount=type=bind,source=packages-centreon,target=/packages \
    mkdir -p /extracted && \
    cd /packages && \
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
COPY --from=extractor /extracted/usr/share/perl5/centreon /centreon-modules/

FROM scratch AS centreon-perl-true
COPY centreon-collect/perl-libs/lib/centreon/ /centreon-modules/

ARG USE_SOURCE_TRAPD=false
# hadolint ignore=DL3006
FROM centreon-perl-${USE_SOURCE_TRAPD} AS centreon-perl-selected

#############################################
# Stage 2: Runtime - Debian 13 slim image
#############################################
FROM debian:13-slim AS snmptrapd

# Install runtime dependencies
# centreontrapdforward only writes spool files — no SQLite or SNMP libs needed here
RUN apt-get update && apt-get install -y --no-install-recommends \
    snmptrapd \
    perl \
    libdbi-perl \
    tzdata \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# Create centreon user/group (same UID as other Centreon containers for volume consistency)
RUN groupadd -g 33 www-data 2>/dev/null || true && \
    groupadd -g 900 centreon && \
    useradd -u 900 -g centreon -m -r -d /var/spool/centreon -s /bin/bash centreon

# Create directory structure
RUN mkdir -p /var/spool/centreontrapd \
             /etc/snmp \
             /etc/centreon \
             /var/log/centreon \
             /usr/share/centreon/bin \
             /usr/local/lib/centreon-snmptrapd && \
    chmod 1777 /var/spool/centreontrapd && \
    chown centreon:centreon /var/spool/centreontrapd \
                            /etc/centreon \
                            /var/log/centreon && \
    chown -R centreon:centreon /etc/snmp && \
    chmod 0755 /var/spool/centreon

# Copy Perl modules from selected source
COPY --from=centreon-perl-selected /centreon-modules/ /usr/share/perl5/centreon/

# Copy centreontrapdforward binary from extractor
COPY --from=extractor --chmod=755 /extracted/usr/share/centreon/bin/centreontrapdforward /usr/share/centreon/bin/centreontrapdforward

# Copy entrypoint scripts
COPY --chmod=755 .github/docker/centreon-snmptrapd/trixie/entrypoint /usr/local/lib/centreon-snmptrapd/

# Verify Perl modules are loadable
RUN echo "=== Verification ===" && \
    perl -e "require centreon::script::centreontrapdforward; print 'centreontrapdforward OK\n'" && \
    ls -lah /usr/share/centreon/bin/centreontrapdforward && \
    echo "=== Files verified ==="

ENV PERL5LIB=/usr/share/perl5:/usr/lib/perl5
ENV DEBUG=false

EXPOSE 162/udp

WORKDIR /etc/snmp

# Requires CAP_NET_BIND_SERVICE at runtime to bind UDP/162
USER centreon

ENTRYPOINT ["/usr/local/lib/centreon-snmptrapd/container.sh"]
CMD ["/usr/sbin/snmptrapd", "-f", "-On", "-Lo", "-c", "/etc/snmp/snmptrapd.conf"]
