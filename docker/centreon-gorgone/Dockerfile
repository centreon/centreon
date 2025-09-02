FROM debian:12-slim AS gorgone

# Install runtime dependencies specific to the new container

RUN groupadd -g 6000 centreon && \
    useradd -u 6000 -g centreon -m -r -d /var/spool/centreon -c "Centreon" -s /bin/bash centreon && \
    groupadd -g 6001 centreon-engine && \
    useradd -u 6001 -g centreon-engine -m -r -d /var/lib/centreon-engine -c "Centreon Engine" -s /bin/bash centreon-engine && \
    groupadd -g 6002 centreon-broker && \
    useradd -u 6002 -g centreon-broker -m -r -d /var/lib/centreon-broker -c "Centreon Broker"  -s /bin/bash centreon-broker && \
    groupadd -g 6003 centreon-gorgone && \
    useradd -u 6003 -g centreon-gorgone -m -r -d /var/lib/centreon-gorgone -c "Centreon Gorgone"  -s /bin/bash centreon-gorgone && \
    usermod -aG centreon centreon-gorgone

COPY packages/ /tmp/packages-centreon
 
RUN apt update && \
    apt install -y \
    perl-modules \
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
    iputils-ping && \
    echo "deb https://packages.centreon.com/apt-plugins-stable/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/centreon-plugins.list && \
    wget -O- https://apt-key.centreon.com | gpg --dearmor | tee /etc/apt/trusted.gpg.d/centreon.gpg > /dev/null 2>&1 && \
    apt update && \
    apt install -y \
    libnet-curl-perl \
    libcrypt-openssl-aes-perl \
    libssh-session-perl \
    libxml-simple-perl \
    sudo && \
    apt install -y /tmp/packages-centreon/centreon-*.deb && \
    apt clean && \
    rm -rf /var/lib/apt/lists/* /tmp/packages-centreon


#     mkdir -p /etc/centreon/config.d/ && \
#     chown centreon. /etc/centreon/config.d/ && \
#     chmod 775 /etc/centreon/config.d/ &&\
#     mv /usr/sbin/service /usr/sbin/service.orig && \
#     ln -s /usr/local/bin/service /usr/sbin/service

# # Copy specific binaries or libraries from the builder stage
# COPY --from=builder --chown=centreon-gorgone:centreon-gorgone --chmod=775 /etc/centreon-gorgone /etc/centreon-gorgone
# COPY --from=builder --chown=centreon-gorgone:centreon-gorgone --chmod=775 /var/log/centreon-gorgone /var/log/centreon-gorgone
# COPY --from=builder --chown=centreon-gorgone:centreon-gorgone /var/lib/centreon-gorgone /var/lib/centreon-gorgone
# COPY --from=builder --chown=centreon-gorgone:centreon-gorgone /var/cache/centreon-gorgone /var/cache/centreon-gorgone
# COPY --from=builder --chown=centreon-gorgone:centreon-gorgone /centreon-collect/perl-libs/lib/centreon/ /usr/share/perl5/centreon/
# COPY --from=builder /usr/share/perl5/gorgone /usr/share/perl5/gorgone
# COPY --from=builder /usr/bin/gorgoned /usr/bin/gorgoned
COPY --chmod=755 service /usr/local/bin/service
# COPY --chown=centreon-gorgone:centreon-gorgone --chmod=775 centreon-gorgone/config/ /etc/centreon-gorgone/config.d/ 
COPY --chmod=600 centreon /etc/sudoers.d/centreon


# Set the working directory
WORKDIR /etc/centreon-gorgone/
#USER centreon-gorgone

# Set a different entrypoint for the new container
CMD ["/usr/bin/perl", "/usr/bin/gorgoned", "--config=/etc/centreon-gorgone/config.yaml", "--severity=debug"]
