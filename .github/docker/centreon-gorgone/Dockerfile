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

RUN bash -e <<EOF

echo "deb https://packages.centreon.com/apt-standard-24.04-unstable/ bullseye main" | tee -a /etc/apt/sources.list.d/centreon-unstable.list

apt-get update

for f in /tmp/debs-centreon/*.deb; do
  echo $f
  dpkg -c $f
done

apt-get install -y centreon-gorgone-centreon-config

#apt-get install -y /tmp/debs-centreon/*.deb

EOF
