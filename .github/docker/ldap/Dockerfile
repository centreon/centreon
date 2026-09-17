ARG GLAUTH_VERSION=v2.4.0

# Download stage — fetch the statically-linked glauth binary
FROM debian:bookworm-slim AS downloader
ARG GLAUTH_VERSION
RUN apt-get update && \
    apt-get install -y --no-install-recommends curl ca-certificates && \
    curl -fsSL \
        "https://github.com/glauth/glauth/releases/download/${GLAUTH_VERSION}/glauth-linux-amd64" \
        -o /glauth && \
    chmod +x /glauth && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Runtime stage — minimal image, no OpenLDAP/slapd
FROM debian:bookworm-slim
COPY --from=downloader /glauth /usr/local/bin/glauth
COPY config/ /config/

EXPOSE 389

CMD ["glauth", "-c", "/config/config.cfg"]
