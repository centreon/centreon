#!/bin/sh
SPOOL="${SPOOL_DIR:-/var/spool/centreontrapd}"
SDB="${TRAP_SDB_PATH:-/etc/snmp/centreon_traps/centreontrapd.sdb}"

echo "=== Generating centreontrapd configuration ==="
echo "Spool dir  : $SPOOL"
echo "SQLite .sdb: $SDB"

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
chown centreon:centreon /etc/centreon/conf.pm

# Main centreontrapd configuration — mode 1 = poller (SQLite, no MySQL)
cat > /etc/centreon/centreontrapd.pm <<EOF
our %centreontrapd_config = (
    mode              => 1,
    db_type           => "SQLite",
    centreon_db       => "dbname=${SDB}",
    centstorage_db    => "dbname=${SDB}",
);
1;
EOF
chmod 640 /etc/centreon/centreontrapd.pm
chown centreon:centreon /etc/centreon/centreontrapd.pm

echo "✓ conf.pm generated"
echo "✓ centreontrapd.pm generated"
echo ""
