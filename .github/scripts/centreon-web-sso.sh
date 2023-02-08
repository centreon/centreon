dnf install --allowerasing -y wget gcc gcc-c++ make autoconf automake httpd-devel libcurl libcurl-devel libcurl curl-devel pcre-devel check-devel jansson-devel openssl-devel cjose mod_auth_openidc

cat <<EOF >>/etc/httpd/conf.d/auth_openidc.conf
OIDCProviderMetadataURL http://172.17.0.2:8080/realms/Centreon_SSO/.well-known/openid-configuration
OIDCClientID        "centreon-oidc-frontend"
OIDCClientSecret    "IKbUBottl5eoyhf0I5Io2nuDsTA85D50"
OIDCRedirectURI      http://localhost:4000/centreon/websso
OIDCCryptoPassphrase "4444444444444444444444444444444444444444"
# Keep sessions alive for 8 hours
OIDCSessionInactivityTimeout 28800
OIDCSessionMaxDuration 28800
# Set REMOTE_USER
OIDCRemoteUserClaim preferred_username
# Don't pass claims to backend servers
OIDCPassClaimsAs environment
# Strip out session cookies before passing to backend
OIDCStripCookies mod_auth_openidc_session mod_auth_openidc_session_chunks mod_auth_openidc_session_0 mod_auth_openidc_session_1
EOF