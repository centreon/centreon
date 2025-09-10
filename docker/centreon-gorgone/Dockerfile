FROM debian:12-slim

ENV TYPE=

# Install runtime dependencies specific to the new container
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
    sudo

COPY --chmod=755 systemctl /usr/local/bin/systemctl
COPY --chmod=755 entrypoint /var/lib/centreon-gorgone/
COPY --chmod=600 centreon /etc/sudoers.d/centreon
COPY packages/ /tmp/packages-centreon

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
    mv /usr/bin/systemctl /usr/bin/systemctl.orig && \
    ln -s /usr/local/bin/systemctl /usr/bin/systemctl && \
    apt install -y /tmp/packages-centreon/centreon-*.deb && \
    apt clean && \
    rm -rf /var/lib/apt/lists/* /tmp/packages-centreon




# Set the working directory
WORKDIR /var/lib/centreon-gorgone
USER centreon-gorgone

ENTRYPOINT [ "/var/lib/centreon-gorgone/container.sh" ]
# Set a different entrypoint for the new container
CMD ["/usr/bin/perl", "/usr/bin/gorgoned", "--config=/etc/centreon-gorgone/config.yaml", "--severity=debug"]
