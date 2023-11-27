ARG REGISTRY_URL

FROM ${REGISTRY_URL}/centreon-web-dependencies-bullseye:23.10

ARG VERSION

ENV DEBIAN_FRONTEND noninteractive

RUN bash -e <<EOF

apt-get update

apt-get install centreon-gorgone

sed -i 's#enable: true#enable: false#' /etc/centreon-gorgone/config.d/50-centreon-audit.yaml

EOF

COPY *.deb /tmp/debs-centreon/

RUN bash -ex <<EOF

echo "deb https://packages.centreon.com/apt-standard-24.04-unstable/ bullseye main" | tee -a /etc/apt/sources.list.d/centreon-unstable.list

apt-get update

dpkg -L centreon-gorgone

# FILES="/tmp/debs-centreon/*"
# for f in $FILES
# do
#   echo "Processing file $f ..."
#   dpkg --contents "$f"
# done

echo "centreon gorgone !!!"
dpkg -I /tmp/debs-centreon/centreon-gorgone_24.04.0-1_all.deb
dpkg --contents /tmp/debs-centreon/centreon-gorgone_24.04.0-1_all.deb
echo "centreon gorgone config !!!"
dpkg -I /tmp/debs-centreon/centreon-gorgone-centreon-config_24.04.0-1_all.deb
dpkg --contents /tmp/debs-centreon/centreon-gorgone-centreon-config_24.04.0-1_all.deb

apt-get install -y /tmp/debs-centreon/*.deb

#apt-get install -y /tmp/debs-centreon/*.deb

EOF
