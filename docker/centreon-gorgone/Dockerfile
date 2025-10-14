FROM debian:12-slim AS deb_installer

WORKDIR /tmp

COPY packages .

RUN dpkg-deb -x centreon-gorgone_25.09.0-1756392628.5184088+deb12u1_all.deb /extracted-deb &&\
    dpkg-deb -x centreon-gorgone-centreon-config_25.09.0-1756392628.5184088+deb12u1_all.deb /extracted-deb &&\
    dpkg-deb -x centreon-perl-libs_25.09.0-1756392628.5184088+deb12u1_all.deb /extracted-deb &&\
    dpkg-deb -x centreon-perl-libs-common_25.09.0-1756392628.5184088+deb12u1_all.deb /extracted-deb

FROM perl:slim-threaded-bookworm AS gorgone

ENV TYPE=

COPY --from=deb_installer /extracted-deb /tmp/extracted-deb
COPY --chmod=755 systemctl /usr/local/bin/systemctl
COPY --chmod=755 entrypoint /var/lib/centreon-gorgone/
COPY --chmod=600 centreon /etc/sudoers.d/centreon
COPY --chmod=755 centenginestats /usr/sbin/centenginestats

# Install runtime dependencies specific to the new container
RUN apt update && \
    apt install -y --no-install-recommends\
    libcryptx-perl \
    libzmq-ffi-perl \
    libev-perl \
    libyaml-libyaml-perl \
    libclass-dbi-perl \
    libdbd-sqlite3-perl \
    libcryptx-perl \
    ca-certificates \
    apt-transport-https \
    software-properties-common \
    wget \
    gnupg2 \
    curl \
    libdbd-mysql-perl \
    libhttp-daemon-perl \
    libdigest-md5-file-perl \
    libschedule-cron-perl \
    librrds-perl \
    libhash-merge-perl \
    sudo \
    iputils-ping && \
    echo "deb https://packages.centreon.com/apt-plugins-stable/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/centreon-plugins.list && \
    wget -O- https://apt-key.centreon.com | gpg --dearmor | tee /etc/apt/trusted.gpg.d/centreon.gpg > /dev/null 2>&1 && \
    apt update && \
    apt install -y \
    libnet-curl-perl \
    libcrypt-openssl-aes-perl \
    libssh-session-perl \
    libjson-xs-perl \
    libxml-simple-perl &&\
    apt clean && \
    rm -rf /var/lib/apt/lists/*

RUN groupadd -g 900 centreon &&\
    useradd -u 900 -g centreon -m -r -d /var/spool/centreon -c "Centreon" -s /bin/bash centreon &&\
    groupadd -g 901 centreon-engine &&\
    useradd -u 901 -g centreon-engine -m -r -d /var/lib/centreon-engine -c "Centreon Engine" -s /bin/bash centreon-engine &&\
    groupadd -g 902 centreon-broker &&\
    useradd -u 902 -g centreon-broker -m -r -d /var/lib/centreon-broker -c "Centreon Broker"  -s /bin/bash centreon-broker &&\
    groupadd -g 903 centreon-gorgone &&\
    useradd -u 903 -g centreon-gorgone -m -r -d /var/lib/centreon-gorgone -c "Centreon Gorgone"  -s /bin/bash centreon-gorgone &&\
    usermod -aG centreon centreon-gorgone && \
    usermod -aG www-data centreon-gorgone && \
    usermod -aG centreon-engine centreon-gorgone && \
    set -eux; \
    # etc/centreon-gorgone
    if [ -d /tmp/extracted-deb/etc/centreon-gorgone ]; then \
      mkdir -p /etc/centreon-gorgone && \
      tar -C /tmp/extracted-deb/etc/centreon-gorgone -cf - . | tar -C /etc/centreon-gorgone -xpf -; \
      chown -R centreon-gorgone:centreon-gorgone /etc/centreon-gorgone; \
    fi; \
    # systemd unit and sudoers (etc)
    if [ -d /tmp/extracted-deb/etc/sudoers.d ]; then \
      mkdir -p /etc/sudoers.d; \
      tar -C /tmp/extracted-deb/etc -cf - sudoers.d | tar -C /etc -xpf - || true; \
    fi; \
    # /usr/bin from package (install binaries)
    if [ -d /tmp/extracted-deb/usr/bin ]; then \
      mkdir -p /usr/bin; \
      install -m755 /tmp/extracted-deb/usr/bin/* /usr/bin/ 2>/dev/null || true; \
    fi; \
    # /usr/local/bin scripts
    if [ -d /tmp/extracted-deb/usr/local/bin ]; then \
      mkdir -p /usr/local/bin; \
      install -m755 /tmp/extracted-deb/usr/local/bin/* /usr/local/bin/ 2>/dev/null || true; \
    fi; \
    # Perl libs -> /usr/share/perl5
    if [ -d /tmp/extracted-deb/usr/share/perl5 ]; then \
      mkdir -p /usr/share/perl5/gorgone && \
      tar -C /tmp/extracted-deb/usr/share/perl5 -cf - . | tar -C /usr/share/perl5 -xpf -; \
    fi; \
    # var paths (logs, cache)
    if [ -d /tmp/extracted-deb/var ]; then \
      tar -C /tmp/extracted-deb/var -cf - . | tar -C /var -xpf -; \
    fi; \
    rm -rf /tmp/extracted-deb && \
    mv /usr/bin/systemctl /usr/bin/systemctl.orig && \
    ln -s /usr/local/bin/systemctl /usr/bin/systemctl && \
    mkdir -p /var/cache/centreon/ && \
    chown www-data:www-data /var/cache/centreon && \
    chmod 775 /var/cache/centreon && \
    chown -R centreon-gorgone:centreon-gorgone /var/lib/centreon-gorgone

# Set the working directory
WORKDIR /var/lib/centreon-gorgone
USER centreon-gorgone

ENTRYPOINT [ "/var/lib/centreon-gorgone/container.sh" ]
# Set a different entrypoint for the new container
CMD ["/usr/bin/perl", "/usr/bin/gorgoned", "--config=/etc/centreon-gorgone/config.yaml", "--severity=debug"]
