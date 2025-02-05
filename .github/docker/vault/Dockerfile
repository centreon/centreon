FROM hashicorp/vault:1.18

COPY --chmod=755 ./.github/docker/vault/entrypoint /tmp/entrypoint

RUN sh -e <<EOF

apk update
apk add jq

EOF

EXPOSE 8200

ENTRYPOINT ["/tmp/entrypoint/container.sh"]
