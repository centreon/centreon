FROM quay.io/keycloak/keycloak:22.0.1
#FROM quay.io/keycloak/keycloak:22.0.1 as builder

COPY .github/docker/openid/configs/openidrealm.json /realms/openid-config.json

# change these values to point to a running postgres instance
ENV KEYCLOAK_ADMIN=admin
ENV KEYCLOAK_ADMIN_PASSWORD=Centreon!2021
ENV KC_HEALTH_ENABLED=true
ENV KC_METRICS_ENABLED=true
ENV KC_FEATURES=token-exchange
#ENV KC_HOSTNAME=172.17.0.3:8080

RUN bash -e <<EOF

/opt/keycloak/bin/kc.sh build --health-enabled=true --features=token-exchange --metrics-enabled=true
/opt/keycloak/bin/kc.sh import --file /realms/openid-config.json

EOF

#FROM quay.io/keycloak/keycloak:22.0.1 as openid_standard

#COPY --from=builder /opt/keycloak/ /opt/keycloak/

# The Keycloak server is configured to listen on port 8080
EXPOSE 8080

ENTRYPOINT ["/opt/keycloak/bin/kc.sh"]
CMD ["start", "--optimized", "–-hostname-strict=false"]
#ENTRYPOINT ["/bin/sh", "-c"]
#CMD ["sleep 99999"]
