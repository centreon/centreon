FROM hashicorp/vault:latest

COPY --chmod=755 ./.github/docker/vault/entrypoint /tmp/entrypoint

ENTRYPOINT ["/tmp/entrypoint/container.sh"]
