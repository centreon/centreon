FROM alpine:3.20

RUN apk add --no-cache mkcert openssl ca-certificates

WORKDIR /out

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
