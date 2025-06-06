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
    apt install -y \
    lsb-release \
    ca-certificates \
    apt-transport-https \
    software-properties-common \
    wget \
    gnupg2 \
    curl \
    libmariadb3 \
    monitoring-plugins-basic \
    iputils-ping && \
    echo "deb https://packages.centreon.com/apt-plugins-stable/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/centreon-plugins.list &&\
    wget -O- https://apt-key.centreon.com | gpg --dearmor | tee /etc/apt/trusted.gpg.d/centreon.gpg > /dev/null 2>&1 && \
    apt update && \
    apt clean && \
    rm -rf /var/lib/apt/lists/*

# # Copy the built binaries from the builder stage

COPY --from=builder --chmod=755 /usr/lib64/centreon-engine/externalcmd.so /usr/lib64/centreon-engine/externalcmd.so
COPY --from=builder --chmod=755 /usr/sbin/centengine /usr/sbin/centengine
COPY --from=builder --chmod=755 /usr/sbin/centenginestats /usr/sbin/centenginestats
COPY --from=builder --chmod=755 /usr/lib/x86_64-linux-gnu/libcentreon_clib.so /usr/lib/libcentreon_clib.so
COPY --from=builder --chmod=775 /etc/centreon-engine/ /etc/centreon-engine/
COPY --from=builder --chmod=755 /centreon-collect/build/broker/neb/cbmod.so /usr/lib/cbmod.so
COPY --from=builder /usr/share/centreon/lib/centreon-broker/ /usr/share/centreon/lib/centreon-broker/

RUN groupadd -g 6000 centreon && \
    useradd -u 6000 -g centreon -m -r -d /var/spool/centreon -c "Centreon" -s /bin/bash centreon && \
    groupadd -g 6001 centreon-engine && \
    useradd -u 6001 -g centreon-engine -m -r -d /var/lib/centreon-engine -c "Centreon Engine" -s /bin/bash centreon-engine && \
    groupadd -g 6002 centreon-broker && \
    useradd -u 6002 -g centreon-broker -m -r -d /var/lib/centreon-broker -c "Centreon Broker"  -s /bin/bash centreon-broker && \
    groupadd -g 6003 centreon-gorgone && \
    useradd -u 6003 -g centreon-gorgone -m -r -d /var/lib/centreon-gorgone -c "Centreon Gorgone"  -s /bin/bash centreon-gorgone &&\
    mkdir -p /etc/centreon-broker/ /var/log/centreon-engine/ /var/log/centreon-broker/ /var/lib/centreon-engine/rw && \
    chown -R centreon-engine:centreon-engine /etc/centreon-engine /var/log/centreon-engine /var/lib/centreon-engine && \
    chown -R centreon-broker:centreon-broker /etc/centreon-broker/ /var/log/centreon-broker/ && \
    touch /var/log/centreon-engine/retention.dat && \
    chown -R centreon-engine:centreon-engine /etc/centreon-engine && \
    chmod 775 /etc/centreon-engine /etc/centreon-broker && \
    chmod +x /usr/share/centreon/lib/centreon-broker/*

# # Set the working directory
WORKDIR /etc/centreon-engine

#USER centreon-engine

# # Set the entrypoint (adjust as needed)
CMD ["/usr/sbin/centengine", "/etc/centreon-engine/centengine.cfg"]

FROM debian:12-slim AS broker

RUN apt update && \
    apt install -y \
    lsb-release \
    ca-certificates \
    apt-transport-https \
    software-properties-common \
    wget \
    gnupg2 \
    curl \
    librrd8 \
    libmariadb3 && \
    apt clean && \
    rm -rf /var/lib/apt/lists/*

# # Copy the built binaries from the builder stage

COPY --from=builder --chmod=755 /usr/lib64/centreon-engine/externalcmd.so /usr/lib64/centreon-engine/externalcmd.so
COPY --from=builder --chmod=755 /usr/lib/x86_64-linux-gnu/libcentreon_clib.so /usr/lib/libcentreon_clib.so
COPY --from=builder --chmod=755 /centreon-collect/build/broker/neb/cbmod.so /usr/lib/cbmod.so
COPY --from=builder --chmod=755 /usr/sbin/cbd /usr/sbin/cbd
COPY --from=builder --chmod=755 /usr/sbin/cbwd /usr/sbin/cbwd
COPY --from=builder --chmod=775 /etc/centreon-broker/ /etc/centreon-broker/
COPY --from=builder /usr/share/centreon/lib/centreon-broker/ /usr/share/centreon/lib/centreon-broker/

COPY centreon-broker/watchdog.json /etc/centreon-broker/watchdog.json

RUN groupadd -g 6000 centreon && \
    useradd -u 6000 -g centreon -m -r -d /var/spool/centreon -c "Centreon" -s /bin/bash centreon && \
    groupadd -g 6001 centreon-engine && \
    useradd -u 6001 -g centreon-engine -m -r -d /var/lib/centreon-engine -c "Centreon Engine" -s /bin/bash centreon-engine && \
    groupadd -g 6002 centreon-broker && \
    useradd -u 6002 -g centreon-broker -m -r -d /var/lib/centreon-broker -c "Centreon Broker"  -s /bin/bash centreon-broker && \
    groupadd -g 6003 centreon-gorgone && \
    useradd -u 6003 -g centreon-gorgone -m -r -d /var/lib/centreon-gorgone -c "Centreon Gorgone"  -s /bin/bash centreon-gorgone &&\
    mkdir -p /etc/centreon-broker/ /var/log/centreon-broker/ /var/lib/centreon/status/ /var/lib/centreon/metrics/ && \
    chown -R centreon-broker:centreon-broker /etc/centreon-broker/ /var/log/centreon-broker/ && \
    chmod +x /usr/share/centreon/lib/centreon-broker/*

# # Set the working directory
WORKDIR /etc/centreon-broker

#USER centreon-broker

# # Set the entrypoint (adjust as needed)
CMD ["/usr/sbin/cbwd", "/etc/centreon-broker/watchdog.json"]

# Use the same builder stage from the original Dockerfile
FROM debian:12-slim AS gorgone

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
    iputils-ping && \
    echo "deb https://packages.centreon.com/apt-plugins-stable/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/centreon-plugins.list && \
    wget -O- https://apt-key.centreon.com | gpg --dearmor | tee /etc/apt/trusted.gpg.d/centreon.gpg > /dev/null 2>&1 && \
    apt update && \
    apt install -y \
    libnet-curl-perl \
    libcrypt-openssl-aes-perl && \
    apt clean && \
    rm -rf /var/lib/apt/lists/*

RUN groupadd -g 6000 centreon && \
    useradd -u 6000 -g centreon -m -r -d /var/spool/centreon -c "Centreon" -s /bin/bash centreon && \
    groupadd -g 6001 centreon-engine && \
    useradd -u 6001 -g centreon-engine -m -r -d /var/lib/centreon-engine -c "Centreon Engine" -s /bin/bash centreon-engine && \
    groupadd -g 6002 centreon-broker && \
    useradd -u 6002 -g centreon-broker -m -r -d /var/lib/centreon-broker -c "Centreon Broker"  -s /bin/bash centreon-broker && \
    groupadd -g 6003 centreon-gorgone && \
    useradd -u 6003 -g centreon-gorgone -m -r -d /var/lib/centreon-gorgone -c "Centreon Gorgone"  -s /bin/bash centreon-gorgone && \
    mkdir -p /etc/centreon/config.d/ && \
    chown centreon. /etc/centreon/config.d/ && \
    chmod 775 /etc/centreon/config.d/
    # chown -R centreon-gorgone:centreon-gorgone /var/log/centreon-gorgone && \
    # chmod 775 /var/log/centreon-gorgone && \
    # touch /var/log/centreon-gorgone/gorgoned.log && \
    # chown centreon-gorgone:centreon-gorgone /var/log/centreon-gorgone/gorgoned.log && \
    # chmod 664 /var/log/centreon-gorgone/gorgoned.log

# Copy specific binaries or libraries from the builder stage
COPY --from=builder --chown=centreon-gorgone:centreon-gorgone --chmod=775 /etc/centreon-gorgone /etc/centreon-gorgone
COPY --from=builder --chown=centreon-gorgone:centreon-gorgone --chmod=775 /var/log/centreon-gorgone /var/log/centreon-gorgone
COPY --from=builder --chown=centreon-gorgone:centreon-gorgone /var/lib/centreon-gorgone /var/lib/centreon-gorgone
COPY --from=builder --chown=centreon-gorgone:centreon-gorgone /var/cache/centreon-gorgone /var/cache/centreon-gorgone
COPY --from=builder --chown=centreon-gorgone:centreon-gorgone /centreon-collect/perl-libs/lib/centreon/ /usr/share/perl5/centreon/ 
COPY --from=builder /usr/share/perl5/gorgone /usr/share/perl5/gorgone
COPY --from=builder /usr/bin/gorgoned /usr/bin/gorgoned

# Create necessary directories and set permissions

# RUN mkdir -p /var/log/centreon-gorgone/  && \
#     chown -R centreon-gorgone:centreon-gorgone /var/log/centreon-gorgone/

#USER centreon-gorgone
# COPY centreon-gorgone/40-gorgoned.yaml /etc/centreon-gorgone/40-gorgoned.yaml
# Set the working directory
WORKDIR /etc/centreon-gorgone/

# Set a different entrypoint for the new container
CMD ["/usr/bin/perl", "/usr/bin/gorgoned", "--config=/etc/centreon-gorgone/config.yaml", "--logfile=/dev/stdout", "--severity=error"]
