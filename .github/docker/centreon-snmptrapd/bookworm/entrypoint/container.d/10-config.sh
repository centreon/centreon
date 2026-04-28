#!/bin/sh
SPOOL="/var/spool/centreontrapd"

echo "=== Generating snmptrapd configuration ==="

# Generate snmptrapd.conf
cat > /etc/snmp/snmptrapd.conf <<EOF
# SNMP community authentication
disableAuthorization yes

# Forward all traps to centreontrapdforward which writes to spool
traphandle default /usr/share/centreon/bin/centreontrapdforward
EOF

# Generate minimal centreontrapdforward config (spool directory location)
cat > /etc/centreon/centreontrapd.pm <<EOF
our %centreontrapd_config = (
    spool_directory => "${SPOOL}/"
);
1;
EOF

echo "✓ snmptrapd.conf generated"
echo "✓ centreontrapd.pm (forward config) generated"
echo ""
