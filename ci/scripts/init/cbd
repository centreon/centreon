#!/bin/sh

pidfile='/var/run/cbwd.pid'

service_start() {
    echo "cbddevscript: starting cbwd"
    if [ \! -f "$pidfile" ] ; then
	pid=`su - centreon-broker -c "/usr/sbin/cbwd /etc/centreon-broker/watchdog.json > /dev/null 2>&1 & echo \\$!"`
	echo "$pid" > "$pidfile"
	echo "cbddevscript: cbwd started"
    else
	echo "cbddevscript: cbwd is already started"
    fi
}

service_stop() {
    echo "cbddevscript: stopping cbwd"
    kill -TERM `cat "$pidfile"`
    sleep 2
    rm -f "$pidfile"
    echo "cbddevscript: cbwd stopped"
}

service_restart() {
    service_stop
    service_start
}

service_reload() {
    echo "cbddevscript: reloading cbwd"
    kill -HUP `cat "$pidfile"`
    echo "cbddevscript: cbwd reloaded"
}

case "$1" in
    start)
	service_start
	;;

    stop)
	service_stop
	;;

    restart)
	service_restart
	;;

    reload)
	service_reload
	;;

    *)
	echo "cbddevscript: invalid arguent"
	exit 3
	;;
esac

exit 0
