#!/bin/sh
SPOOL="${SPOOL_DIR:-/var/spool/centreontrapd}"

echo "=== Generating snmptrapd configuration ==="
echo "Spool dir : $SPOOL"

# Generate snmptrapd.conf
cat > /etc/snmp/snmptrapd.conf <<EOF
# SNMP community authentication
disableAuthorization yes

# Forward all traps to centreontrapdforward which writes to spool
traphandle default su -l centreon -c "/usr/share/centreon/bin/centreontrapdforward"
EOF

# Generate minimal centreontrapdforward config (spool directory location)
mkdir -p /etc/centreon
cat > /etc/centreon/centreontrapd.pm <<EOF
our %centreontrapd_config = (
    spool_directory => "${SPOOL}/"
);
1;
EOF

echo "✓ snmptrapd.conf generated"
echo "✓ centreontrapd.pm (forward config) generated"
echo ""
