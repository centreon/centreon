FROM hashicorp/vault:latest

COPY --chmod=755 ./.github/docker/centreon-web/vault/entrypoint /tmp/entrypoint

ENTRYPOINT ["/usr/share/centreon/container.sh"]
