yum install -y wget gcc gcc-c++ make autoconf automake httpd24-httpd-devel httpd24-libcurl httpd24-libcurl-devel libcurl curl-devel pcre-devel check-devel jansson-devel openssl-devel

cd /tmp/
wget https://github.com/zmartzone/mod_auth_openidc/releases/download/v2.3.0/cjose-0.5.1.tar.gz
tar xzf cjose-0.5.1.tar.gz
cd cjose-0.5.1

./configure --prefix=/opt/rh/httpd24/root/usr \
--libdir=/opt/rh/httpd24/root/usr/lib64 \
--with-apxs=/opt/rh/httpd24/root/usr/bin/apxs

make
make install

wget https://github.com/zmartzone/mod_auth_openidc/releases/download/v2.4.11/mod_auth_openidc-2.4.11.tar.gz
tar xzf mod_auth_openidc-2.4.11.tar.gz
cd mod_auth_openidc-2.4.11/

export MODULES_DIR=/opt/rh/httpd24/root/usr/lib64/httpd/modules
export APXS2_OPTS="-S LIBEXECDIR=${MODULES_DIR}"
export APXS2=/opt/rh/httpd24/root/usr/bin/apxs
export PKG_CONFIG_PATH=/opt/rh/httpd24/root/usr/lib64/pkgconfig

./configure --prefix=/opt/rh/httpd24/root/usr \
--exec-prefix=/opt/rh/httpd24/root/usr \
--bindir=/opt/rh/httpd24/root/usr/bin \
--sbindir=/opt/rh/httpd24/root/usr/sbin \
--sysconfdir=/opt/rh/httpd24/root/etc \
--datadir=/opt/rh/httpd24/root/usr/share \
--includedir=/opt/rh/httpd24/root/usr/include \
--libdir=/opt/rh/httpd24/root/usr/lib64 \
--libexecdir=/opt/rh/httpd24/root/usr/libexec \
--localstatedir=/opt/rh/httpd24/root/var \
--sharedstatedir=/opt/rh/httpd24/root/var/lib \
--mandir=/opt/rh/httpd24/root/usr/share/man \
--infodir=/opt/rh/httpd24/root/usr/share/info \
--without-hiredis \
--with-apxs=/opt/rh/httpd24/root/usr/bin/apxs

make
make install

cat <<EOF >>/opt/rh/httpd24/root/etc/httpd/conf.modules.d/auth_openidc.conf
LoadModule auth_openidc_module modules/mod_auth_openidc.so
EOF

cat <<EOF >>/opt/rh/httpd24/root/etc/httpd/conf.d/auth_openidc.conf
OIDCProviderMetadataURL http://localhost:8080/realms/Centreon_SSO/.well-known/openid-configuration
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

sed -i "/TraceEnable Off/a \    RequestHeader set X-Forwarded-Proto 'http' early\n\n \    <Location '/centreon'>\n\tAuthType openid-connect\n\tRequire valid-user\n\    </Location>\n" /opt/rh/httpd24/root/etc/httpd/conf.d/10-centreon.conf

sed -e '/LoadModule http2_module modules\/mod_http2.so/ s/^#*/#/' /opt/rh/httpd24/root/etc/httpd/conf.modules.d/00-base.conf

pkill httpd
sh /usr/share/centreon/container.d/60-apache.sh
