FROM alpine:3.18

RUN apk add --no-cache dcron tini php82 php82-cli php82-phar perl-dbi perl-dbd-mysql\
    php82-openssl php82-pdo_mysql php82-mbstring php82-xml php82-curl php82-zip perl sudo psmisc procps && \
    mkdir -p /usr/lib/perl5/vendor_perl

# RUN apk add --no-cache dcron tini php8 php8-cli php8-phar php8-openssl \
#     php8-pdo_mysql php8-mbstring php8-xml php8-curl php8-zip perl sudo && \
#     adduser -D -u 1000 apache && adduser -D -u 1001 centreon && \
#     mkdir -p /var/log/centreon /etc/cron.d && \
#     chown -R centreon:centreon /var/log/centreon
COPY crontab /etc/crontabs/root
COPY perl /usr/lib/perl5/vendor_perl/centreon

RUN chmod 0644 /etc/crontabs/root

ENTRYPOINT ["tini", "--"]
CMD ["crond", "-f", "-L", "/dev/stdout"]
