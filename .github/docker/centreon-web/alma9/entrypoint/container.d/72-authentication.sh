#!/bin/sh

if [ ! -z ${OPENID_HOST} ] && getent hosts ${OPENID_HOST}; then
  RESPONSE=$(curl -s -w "%{http_code}" -H 'Content-Type:application/json' -H 'Accept:application/json' -d '{"security":{"credentials":{"login":"admin","password":"Centreon!2021"}}}' -L "http://localhost:80/centreon/api/latest/login")
  TOKEN=$(echo "$RESPONSE" | head -c -4 | jq -r '.security.token')

  OPENID_IP_ADDRESS=$(getent hosts "${OPENID_HOST}" | awk '{print $1;}') || {
    echo "Failed to resolve ${OPENID_HOST} hostname"
    exit 1
  }

  curl -X PATCH \
      -H "Content-Type: application/json" \
      -H "X-AUTH-TOKEN: $TOKEN" \
      -L "http://localhost:80/centreon/api/latest/administration/authentication/providers/openid" \
      --data @- <<- PAYLOAD
{
  "is_active": true,
  "base_url": "http://${OPENID_IP_ADDRESS}:8080/realms/Centreon_SSO/protocol/openid-connect",
  "authorization_endpoint": "/auth",
  "token_endpoint": "/token",
  "end_session_endpoint": "/logout",
  "client_id": "centreon-oidc-frontend",
  "client_secret": "IKbUBottl5eoyhf0I5Io2nuDsTA85D50",
  "introspection_token_endpoint": "/token/introspect",
  "userinfo_endpoint": "/userinfo",
  "login_claim": "preferred_username",
  "connection_scopes": [
    "openid"
  ],
  "email_bind_attribute": "email",
  "fullname_bind_attribute": "name"
}
PAYLOAD

  if [ $CENTREON_DATASET = "1" ]; then
    curl -X PATCH \
      -H "Content-Type: application/json" \
      -H "X-AUTH-TOKEN: $TOKEN" \
      -L "http://localhost:80/centreon/api/latest/administration/authentication/providers/openid" \
      --data @- <<- PAYLOAD
{
  "auto_import": true,
  "contact_template": {
    "id": 19,
    "name": "contact_template"
  }
}
PAYLOAD
  fi
fi
