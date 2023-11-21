ARG REGISTRY_URL
ARG VERSION

FROM ${REGISTRY_URL}/centreon-web-dependencies-alma8:23.04

ARG VERSION

COPY *.rpm /tmp/rpms-centreon-gorgone/

RUN bash -e <<EOF

dnf install -y centreon-gorgone

dnf install -y /tmp/rpms-centreon-gorgone/*.rpm

dnf clean all --enablerepo=*

sleep 10

cat /var/log/centreon-gorgone/gorgoned.log

EOF
