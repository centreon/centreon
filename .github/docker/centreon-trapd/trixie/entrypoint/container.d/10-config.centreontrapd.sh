#!/bin/sh
SPOOL="/var/spool/centreontrapd"
SDB="/etc/snmp/centreon_traps/centreontrapd.sdb"

echo "=== Generating centreontrapd configuration ==="

# Minimal conf.pm required by centreon::script base class
cat > /etc/centreon/conf.pm <<EOF
\$mysql_user = "";
\$mysql_passwd = "";
\$mysql_host = "";
\$mysql_port = "3306";
\$mysql_database_oreon = "";
\$mysql_database_ods = "";
1;
EOF
chmod 660 /etc/centreon/conf.pm

# Main centreontrapd configuration — mode 1 = poller (SQLite, no MySQL)
cat > /etc/centreon/centreontrapd.pm <<EOF
our %centreontrapd_config = (
    mode              => 1,
    spool_directory   => "${SPOOL}/",
    db_type           => "SQLite",
    centreon_db       => "dbname=${SDB}",
    centstorage_db    => "dbname=${SDB}",
);
1;
EOF
chmod 640 /etc/centreon/centreontrapd.pm

echo "✓ conf.pm generated"
echo "✓ centreontrapd.pm generated"
echo ""
