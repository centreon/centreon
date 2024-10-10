FROM hashicorp/vault:latest

COPY --chmod=755 ./.github/docker/vault/entrypoint /tmp/entrypoint

USER root
RUN apk update && apk add jq

EXPOSE 8080
EXPOSE 8200

ENTRYPOINT ["/tmp/entrypoint/container.sh"]
