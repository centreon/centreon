#FROM quay.io/keycloak/keycloak:17.0.0
FROM quay.io/keycloak/keycloak:22.0.1

#COPY .github/docker/openid/configs/openidrealm.json /realms/openid-config.json
COPY .github/docker/openid/configs/keycloakdb.mv.db /opt/keycloak/data/h2/keycloakdb.mv.db
COPY .github/docker/openid/configs/keycloakdb.trace.db /opt/keycloak/data/h2/keycloakdb.trace.db

# change these values to point to a running postgres instance
ENV KEYCLOAK_ADMIN=admin
ENV KEYCLOAK_ADMIN_PASSWORD=Centreon!2021
ENV KC_HEALTH_ENABLED=true
ENV KC_METRICS_ENABLED=true
ENV KC_FEATURES=token-exchange
#ENV KC_HOSTNAME=172.17.0.3:8080

RUN bash -e <<EOF

#/opt/keycloak/bin/kc.sh import --file /realms/openid-config.json
#/opt/keycloak/bin/kc.sh build

EOF

# The Keycloak server is configured to listen on port 8080
EXPOSE 8080

CMD ["start-dev"]
#ENTRYPOINT ["/bin/sh", "-c"]
#CMD ["sleep 99999"]
