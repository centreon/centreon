FROM quay.io/keycloak/keycloak:22.0.1

COPY .github/docker/openid/configs/openidrealm.json /realms/openid-config.json

# change these values to point to a running postgres instance
ENV KEYCLOAK_ADMIN=admin
ENV KEYCLOAK_ADMIN_PASSWORD=Centreon!2021
ENV KC_HEALTH_ENABLED=true
ENV KC_METRICS_ENABLED=true
ENV KC_FEATURES=token-exchange

RUN bash -e <<EOF

/opt/keycloak/bin/kc.sh build
/opt/keycloak/bin/kc.sh import --file /realms/openid-config.json

EOF

# The Keycloak server is configured to listen on port 8080
EXPOSE 8080

ENTRYPOINT ["/opt/keycloak/bin/kc.sh"]

CMD ["start", "--hostname-strict=false", "--http-enabled=true", "--optimized"]
