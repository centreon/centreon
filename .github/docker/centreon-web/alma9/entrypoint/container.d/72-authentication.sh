#!/bin/sh

if [ ! -z ${LDAP_HOST} ] && getent hosts ${LDAP_HOST}; then
  MYSQL_PWD="${MYSQL_ROOT_PASSWORD}" mysql -h${MYSQL_HOST} -uroot centreon -e "UPDATE auth_ressource SET ar_enable = '1' WHERE ar_name = 'openldap'"
fi

if [ ! -z ${OPENID_HOST} ] && getent hosts ${OPENID_HOST}; then
  CONTACT_TEMPLATE_NAME="openid_contact_template"
  sudo -u apache centreon -u admin -p Centreon\!2021 -o CONTACTTPL -a ADD -v "$CONTACT_TEMPLATE_NAME;$CONTACT_TEMPLATE_NAME;;1;1;en_US.UTF-8;local"
  CONTACT_TEMPLATE_ID=$(sudo -u apache centreon -u admin -p Centreon\!2021 -o CONTACTTPL -a SHOW -v "$CONTACT_TEMPLATE_NAME" | grep "$CONTACT_TEMPLATE_NAME" | cut -d';' -f1)

  RESPONSE=$(curl -s -w "%{http_code}" -H 'Content-Type:application/json' -H 'Accept:application/json' -d '{"security":{"credentials":{"login":"admin","password":"Centreon!2021"}}}' -L "http://localhost:80/centreon/api/latest/login")
  TOKEN=$(echo "$RESPONSE" | head -c -4 | jq -r '.security.token')

  OPENID_IP_ADDRESS=$(getent hosts "${OPENID_HOST}" | awk '{print $1;}') || {
    echo "Failed to resolve ${OPENID_HOST} hostname"
    exit 1
  }

  OPENID_BASE_URL="http://${OPENID_IP_ADDRESS}:8085"

  curl -X PATCH \
      --fail-with-body \
      -H "Content-Type: application/json" \
      -H "X-AUTH-TOKEN: $TOKEN" \
      -L "http://localhost:80/centreon/api/latest/administration/authentication/providers/openid" \
      --data @- <<- PAYLOAD
{
  "is_active": true,
  "base_url": "$OPENID_BASE_URL/realms/Centreon_SSO/protocol/openid-connect",
  "authorization_endpoint": "/auth",
  "token_endpoint": "/token",
  "endsession_endpoint": "/logout",
  "client_id": "centreon-oidc-frontend",
  "client_secret": "IKbUBottl5eoyhf0I5Io2nuDsTA85D50",
  "introspection_token_endpoint": "/token/introspect",
  "userinfo_endpoint": "/userinfo",
  "login_claim": "preferred_username",
  "connection_scopes": [
    "openid"
  ],
  "email_bind_attribute": "email",
  "fullname_bind_attribute": "name",
  {
    "auto_import": true,
    "contact_template": {
      "id": $CONTACT_TEMPLATE_ID,
      "name": "$CONTACT_TEMPLATE_NAME"
    }
  }
}
PAYLOAD
fi

if [ ! -z ${SAML_HOST} ] && getent hosts ${SAML_HOST}; then
  CONTACT_TEMPLATE_NAME="saml_contact_template"
  sudo -u apache centreon -u admin -p Centreon\!2021 -o CONTACTTPL -a ADD -v "$CONTACT_TEMPLATE_NAME;$CONTACT_TEMPLATE_NAME;;1;1;en_US.UTF-8;local"
  CONTACT_TEMPLATE_ID=$(sudo -u apache centreon -u admin -p Centreon\!2021 -o CONTACTTPL -a SHOW -v "$CONTACT_TEMPLATE_NAME" | grep "$CONTACT_TEMPLATE_NAME" | cut -d';' -f1)

  RESPONSE=$(curl -s -w "%{http_code}" -H 'Content-Type:application/json' -H 'Accept:application/json' -d '{"security":{"credentials":{"login":"admin","password":"Centreon!2021"}}}' -L "http://localhost:80/centreon/api/latest/login")
  TOKEN=$(echo "$RESPONSE" | head -c -4 | jq -r '.security.token')

  SAML_IP_ADDRESS=$(getent hosts "${SAML_HOST}" | awk '{print $1;}') || {
    echo "Failed to resolve ${SAML_HOST} hostname"
    exit 1
  }

  SAML_BASE_URL="http://${SAML_IP_ADDRESS}:8086"

  curl -X PUT \
      --fail-with-body \
      -H "Content-Type: application/json" \
      -H "X-AUTH-TOKEN: $TOKEN" \
      -L "http://localhost:80/centreon/api/latest/administration/authentication/providers/saml" \
      --data @- <<- PAYLOAD
{
  "is_active": true,
  "is_forced": false,
  "remote_login_url": "$SAML_BASE_URL/realms/Centreon_SSO/protocol/saml/clients/centreon",
  "requested_authn_context": "minimum",
  "entity_id_url": "$SAML_BASE_URL/realms/Centreon_SSO",
  "certificate": "MIICpzCCAY8CBgGFydyVcDANBgkqhkiG9w0BAQsFADAXMRUwEwYDVQQDDAxDZW50cmVvbl9TU08wHhcNMjMwMTE5MTE0NzM0WhcNMzMwMTE5MTE0OTE0WjAXMRUwEwYDVQQDDAxDZW50cmVvbl9TU08wggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQCCpNndecGJI2xOaNQXDDvwDwo/beQ7Q4HW/ck1BNkE13IgPf5GRpvP2jp/1IZsx92vQ2Ub9g5urNG/jo3nZzsUUIdTICsN9Bq2OIjYU9Uxmc1PpHzklN/SqZWbKXOw8EzqXkQ3YNXHqL9omJJ5JMxe4zg758zlvOUh3I44XhMy6PKgeReJIm+HxYJ8SKeu/XVRI7Uiyav5L2M85ED3kqiI3iPrGfLQzv8zqkTeNfuZIeigqI+M8MqRxR3Qf0UlmWA3ZAzsoxJUU+e0tHnD7MhgyRLfg76FjQ1U7Tv7X/h8uqRthjTbva5v0k0M85z21C85UrHxpS3e/HJFInrkJredAgMBAAEwDQYJKoZIhvcNAQELBQADggEBADQANd/iYhefXpcqXC+co3fEe7IaZ93XelZzJ5S4OAR5dHnhMMlMQnnscW/nH8NAEwWRImJPfOEcKun8rBUphZZJxi2WHHj5ilhGdNtcyZzh0sufyIQav/QMreGmDEj/J/uRfmG15Lj1wJB6mw+O4kuwJj/8DzxK6/sQYPisJuXrSWrDmcpvShvbo59JbVjdYK49WXVDbl++7hrwiOYuCQ/uodQYgvChZnIQbL4O6TbG4OLy+prFd5FBsEQds8ZNXoLWM5bCUz+bz4N68fAqhtPR8+yR+pIrE7/cvRaRCmgnG0s61JBZVxHoT4dbMJUTTSSS4dWCUUNhMCIFtEKL06c=",
  "user_id_attribute": "urn:oid:1.2.840.113549.1.9.1",
  "logout_from": true,
  "logout_from_url": "$SAML_BASE_URL/realms/Centreon_SSO/protocol/saml",
  "auto_import": true,
  "contact_template": {
    "id": $CONTACT_TEMPLATE_ID,
    "name": "$CONTACT_TEMPLATE_NAME"
  },
  "email_bind_attribute": "urn:oid:1.2.840.113549.1.9.1",
  "fullname_bind_attribute": "urn:oid:2.5.4.42",
  "authentication_conditions": {
    "is_enabled": false,
    "attribute_path": "",
    "authorized_values": []
  },
  "groups_mapping": {
    "is_enabled": false,
    "attribute_path": "",
    "relations": []
  },
  "roles_mapping": {
    "is_enabled": false,
    "apply_only_first_role": false,
    "attribute_path": "",
    "relations": []
  }
}
PAYLOAD
fi
