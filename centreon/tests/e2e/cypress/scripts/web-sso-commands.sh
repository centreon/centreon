sed -i "/TraceEnable Off/a \    RequestHeader set X-Forwarded-Proto 'http' early\n\n \    <Location '/centreon'>\n\tAuthType openid-connect\n\tRequire valid-user\n\    </Location>\n" /etc/httpd/conf.d/10-centreon.conf

pkill httpd
sh /usr/share/centreon/container.d/60-apache.sh