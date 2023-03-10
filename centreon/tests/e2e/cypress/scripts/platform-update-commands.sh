dnf remove \
centreon-collect-debuginfo \
centreon-clib-debuginfo \
centreon-engine-extcommands-debuginfo \
centreon-engine-daemon-debuginfo \
centreon-broker-cbmod-debuginfo\
centreon-broker-core-debuginfo \
centreon-broker-cbd-debuginfo

dnf clean all --enablerepo=*

dnf -y update centreon\*