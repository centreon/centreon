ARG REGISTRY_URL
ARG VERSION

FROM ${REGISTRY_URL}/centreon-web-alma9:develop

RUN bash -e <<EOF

service mysql start

mysqldump centreon > /usr/local/src/centreon.sql
mysqldump centreon_storage > /usr/local/src/centreon_storage.sql

service mysql stop

dnf remove centreon-database mariadb-server

dnf install -y https://github.com/mydumper/mydumper/releases/download/v0.15.2-6/mydumper-0.15.2-6.el9.x86_64.rpm

EOF

COPY --chmod=755 ./.github/docker/centreon-web-without-database/entrypoint/10-mysql.sh /usr/share/centreon/container.d/10-mysql.sh
