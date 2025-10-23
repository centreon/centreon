# Use AlmaLinux 9 as the base image
FROM debian:12 AS builder

# Set environment variables for non-interactive builds
ENV VCPKG_FORCE_SYSTEM_BINARIES=1

# Install necessary dependencies
RUN apt update && \
    apt install -y \
    build-essential \
    librrd-dev \
    libgnutls28-dev \
    liblua5.3-dev \
    libperl-dev \
    libgcrypt20-dev \
    libssl-dev \
    libssh2-1-dev \
    zlib1g-dev \
    libcurl4-openssl-dev \
    libmariadb-dev \
    git \
    cmake \
    g++ \
    gcc \
    make \
    ninja-build \
    curl && \
    apt clean

# Clone the Centreon Collect repository
WORKDIR /centreon-collect
RUN git clone https://github.com/centreon/centreon-collect.git . && \
    git checkout develop

# Run the build script
RUN chmod +x ./cmake-vcpkg.sh && ./cmake-vcpkg.sh -DCMAKE_BUILD_TYPE=Release -GNinja


# Build and install the project
RUN ninja -C build && ninja -C build install 

RUN gorgone/install.sh -s

# Use a minimal runtime image to serve the result
FROM debian:12-slim AS centengine

RUN apt update && \
    apt install -y --no-install-recommends \
    lsb-release \
    ca-certificates \
    apt-transport-https \
    software-properties-common \
    wget \
    gnupg2 \
    curl \
    libmariadb3 \
    monitoring-plugins-basic \
    python3-minimal \
    python3-venv \
    iputils-ping \
    sudo && \
    echo "deb https://packages.centreon.com/apt-plugins-stable/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/centreon-plugins.list &&\
    wget -O- https://apt-key.centreon.com | gpg --dearmor | tee /etc/apt/trusted.gpg.d/centreon.gpg > /dev/null 2>&1 && \
    apt update && \
    apt clean && \
    rm -rf /var/lib/apt/lists/* \
           /tmp/* \
           /var/tmp/* \
           /usr/share/doc/* \
           /usr/share/man/* \
           /var/cache/apt/*

# # Copy the built binaries from the builder stage

COPY --from=builder --chmod=755 /usr/lib64/centreon-engine/externalcmd.so /usr/lib64/centreon-engine/externalcmd.so
COPY --from=builder --chmod=755 /usr/sbin/centengine /usr/sbin/centengine
COPY --from=builder --chmod=755 /usr/sbin/centenginestats /usr/sbin/centenginestats
COPY --from=builder --chmod=755 /usr/lib/x86_64-linux-gnu/libcentreon_clib.so /usr/lib/libcentreon_clib.so
COPY --from=builder --chmod=775 /etc/centreon-engine/ /etc/centreon-engine/
COPY --from=builder --chmod=755 /centreon-collect/build/broker/neb/cbmod.so /usr/lib/cbmod.so
COPY --from=builder /usr/share/centreon/lib/centreon-broker/ /usr/share/centreon/lib/centreon-broker/

COPY centreon-engine/entrypoint.sh /entrypoint.sh
COPY centreon-engine/config/engine-context.json /etc/centreon-engine/engine-context.json

RUN groupadd -g 900 centreon &&\
    useradd -u 900 -g centreon -m -r -d /var/spool/centreon -c "Centreon" -s /bin/bash centreon &&\
    groupadd -g 901 centreon-engine &&\
    useradd -u 901 -g centreon-engine -m -r -d /var/lib/centreon-engine -c "Centreon Engine" -s /bin/bash centreon-engine &&\
    groupadd -g 902 centreon-broker &&\
    useradd -u 902 -g centreon-broker -m -r -d /var/lib/centreon-broker -c "Centreon Broker"  -s /bin/bash centreon-broker &&\
    groupadd -g 903 centreon-gorgone &&\
    useradd -u 903 -g centreon-gorgone -m -r -d /var/lib/centreon-gorgone -c "Centreon Gorgone"  -s /bin/bash centreon-gorgone &&\
    usermod -aG centreon-broker centreon-engine  && \
    usermod -aG centreon-engine centreon-broker && \
    usermod -aG centreon-gorgone centreon-engine && \
    usermod -aG sudo centreon-engine && \
    echo "centreon-engine ALL=(ALL) NOPASSWD: /usr/bin/apt, /usr/bin/apt-get" > /etc/sudoers.d/centreon-engine && \  
    chmod 440 /etc/sudoers.d/centreon-engine && \
    mkdir -p /etc/centreon-broker/ /var/log/centreon-engine/ /var/log/centreon-broker/ /var/lib/centreon-engine/rw /var/lib/centreon/centplugins/ && \
    chown -R centreon-engine:centreon-engine /etc/centreon-engine /var/log/centreon-engine /var/lib/centreon-engine /var/lib/centreon/centplugins/ && \
    chown -R centreon-broker:centreon-broker /etc/centreon-broker/ /var/log/centreon-broker/ && \
    chown -R centreon-engine:centreon-engine /etc/centreon-engine && \
    chmod 775 /etc/centreon-engine /etc/centreon-broker /var/log/centreon-broker/ /var/log/centreon-engine/ && \
    chmod 660 /etc/centreon-engine/engine-context.json && \
    chmod +x /usr/share/centreon/lib/centreon-broker/* && \
    chmod +x /entrypoint.sh

COPY centreon-engine/api_control.py /var/lib/centreon-engine/api_control.py

# # Set the working directory
WORKDIR /var/lib/centreon-engine

USER centreon-engine

# # Set the entrypoint (adjust as needed)
ENTRYPOINT ["/entrypoint.sh"]

CMD ["uvicorn", "api_control:app", "--host", "0.0.0.0", "--port", "8000"]

FROM debian:12-slim AS broker

RUN apt update && \
    apt install -y --no-install-recommends \
    lsb-release \
    ca-certificates \
    apt-transport-https \
    software-properties-common \
    wget \
    gnupg2 \
    curl \
    librrd8 \
    python3-minimal \
    python3-venv \
    procps \
    libmariadb3 && \
    apt clean && \
    rm -rf /var/lib/apt/lists/* \
           /tmp/* \
           /var/tmp/* \
           /usr/share/doc/* \
           /usr/share/man/* \
           /var/cache/apt/*

# # Copy the built binaries from the builder stage

COPY --from=builder --chmod=755 /usr/lib64/centreon-engine/externalcmd.so /usr/lib64/centreon-engine/externalcmd.so
COPY --from=builder --chmod=755 /usr/lib/x86_64-linux-gnu/libcentreon_clib.so /usr/lib/libcentreon_clib.so
COPY --from=builder --chmod=755 /centreon-collect/build/broker/neb/cbmod.so /usr/lib/cbmod.so
COPY --from=builder --chmod=755 /usr/sbin/cbd /usr/sbin/cbd
COPY --from=builder --chmod=755 /usr/sbin/cbwd /usr/sbin/cbwd
COPY --from=builder --chmod=775 /etc/centreon-broker/ /etc/centreon-broker/
COPY --from=builder /usr/share/centreon/lib/centreon-broker/ /usr/share/centreon/lib/centreon-broker/

COPY centreon-broker/entrypoint.sh /entrypoint.sh
# COPY centreon-broker/watchdog.json /etc/centreon-broker/watchdog.json

RUN groupadd -g 900 centreon &&\
    useradd -u 900 -g centreon -m -r -d /var/spool/centreon -c "Centreon" -s /bin/bash centreon &&\
    groupadd -g 901 centreon-engine &&\
    useradd -u 901 -g centreon-engine -m -r -d /var/lib/centreon-engine -c "Centreon Engine" -s /bin/bash centreon-engine &&\
    groupadd -g 902 centreon-broker &&\
    useradd -u 902 -g centreon-broker -m -r -d /var/lib/centreon-broker -c "Centreon Broker"  -s /bin/bash centreon-broker &&\
    groupadd -g 903 centreon-gorgone &&\
    useradd -u 903 -g centreon-gorgone -m -r -d /var/lib/centreon-gorgone -c "Centreon Gorgone"  -s /bin/bash centreon-gorgone &&\
    usermod -aG centreon centreon-broker  && \
    mkdir -p /etc/centreon-broker/ /var/log/centreon-broker/ /var/lib/centreon/status/ /var/lib/centreon/metrics/ && \
    chown -R centreon-broker:centreon-broker /etc/centreon-broker/ /var/log/centreon-broker/ && \
    chmod +x /usr/share/centreon/lib/centreon-broker/* &&\
    chmod +x /entrypoint.sh

COPY centreon-broker/api_control.py /var/lib/centreon-broker/api_control.py

# # Set the working directory
WORKDIR /var/lib/centreon-broker

USER centreon-broker

# # Set the entrypoint (adjust as needed)
ENTRYPOINT ["/entrypoint.sh"]
#CMD ["/usr/sbin/cbwd", "/etc/centreon-broker/watchdog.json"]
CMD ["uvicorn", "api_control:app", "--host", "0.0.0.0", "--port", "8000"]

FROM debian:12-slim AS gorgone

# Install runtime dependencies specific to the new container
RUN apt update && \
    apt install -y --no-install-recommends \
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
    libcrypt-urandom-perl \
    iputils-ping && \
    echo "deb https://packages.centreon.com/apt-plugins-stable/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/centreon-plugins.list && \
    wget -O- https://apt-key.centreon.com | gpg --dearmor | tee /etc/apt/trusted.gpg.d/centreon.gpg > /dev/null 2>&1 && \
    apt update && \
    apt install -y --no-install-recommends \
    libnet-curl-perl \
    libcrypt-openssl-aes-perl \
    libssh-session-perl \
    libxml-simple-perl \
    sudo && \
    apt clean && \
    rm -rf /var/lib/apt/lists/* \
           /tmp/* \
           /var/tmp/* \
           /usr/share/doc/* \
           /usr/share/man/* \
           /var/cache/apt/*

RUN groupadd -g 900 centreon &&\
    useradd -u 900 -g centreon -m -r -d /var/spool/centreon -c "Centreon" -s /bin/bash centreon &&\
    groupadd -g 901 centreon-engine &&\
    useradd -u 901 -g centreon-engine -m -r -d /var/lib/centreon-engine -c "Centreon Engine" -s /bin/bash centreon-engine &&\
    groupadd -g 902 centreon-broker &&\
    useradd -u 902 -g centreon-broker -m -r -d /var/lib/centreon-broker -c "Centreon Broker"  -s /bin/bash centreon-broker &&\
    groupadd -g 903 centreon-gorgone &&\
    useradd -u 903 -g centreon-gorgone -m -r -d /var/lib/centreon-gorgone -c "Centreon Gorgone"  -s /bin/bash centreon-gorgone &&\
    usermod -aG centreon centreon-gorgone &&\
    usermod -aG www-data centreon-gorgone &&\
    usermod -aG centreon-engine centreon-gorgone && \
    usermod -aG centreon-broker centreon-gorgone && \
    mkdir -p /etc/centreon/config.d/ && \
    chown -R centreon: /etc/centreon/ && \
    chmod -R 775 /etc/centreon/ &&\
    mkdir -p /var/cache/centreon/ && \
    chown centreon:centreon /var/cache/centreon && \
    chmod 775 /var/cache/centreon && \
    mv /usr/bin/systemctl /usr/bin/systemctl.orig && \
    ln -s /usr/local/bin/systemctl /usr/bin/systemctl

# Copy specific binaries or libraries from the builder stage
COPY --from=builder --chown=centreon-gorgone:centreon-gorgone --chmod=775 /etc/centreon-gorgone /etc/centreon-gorgone
COPY --from=builder --chown=centreon-gorgone:centreon-gorgone --chmod=775 /var/log/centreon-gorgone /var/log/centreon-gorgone
COPY --from=builder --chown=centreon-gorgone:centreon-gorgone /var/lib/centreon-gorgone /var/lib/centreon-gorgone
COPY --from=builder --chown=centreon-gorgone:centreon-gorgone /var/cache/centreon-gorgone /var/cache/centreon-gorgone
COPY --from=builder --chown=centreon-gorgone:centreon-gorgone /centreon-collect/perl-libs/lib/centreon/ /usr/share/perl5/centreon/
COPY --from=builder /usr/share/perl5/gorgone /usr/share/perl5/gorgone
COPY --from=builder /usr/bin/gorgoned /usr/bin/gorgoned
# COPY --chmod=755 centreon-gorgone/service /usr/local/bin/service
COPY --chown=centreon-gorgone:centreon-gorgone --chmod=775 centreon-gorgone/config/ /etc/centreon-gorgone/config.d/ 
COPY --chmod=600 centreon-gorgone/centreon /etc/sudoers.d/centreon
COPY --chmod=755 centreon-gorgone/centenginestats /usr/sbin/centenginestats
COPY --chmod=755 centreon-gorgone/entrypoint /var/lib/centreon-gorgone/
COPY --chmod=755 centreon-gorgone/systemctl /usr/local/bin/systemctl

USER centreon-gorgone
# Set the working directory
WORKDIR /etc/centreon-gorgone/

ENTRYPOINT [ "/var/lib/centreon-gorgone/container.sh" ]
# Set a different entrypoint for the new container
CMD ["/usr/bin/perl", "/usr/bin/gorgoned", "--config=/etc/centreon-gorgone/config.yaml", "--severity=debug"]
