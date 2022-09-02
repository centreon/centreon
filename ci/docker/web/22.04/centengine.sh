#!/bin/sh

pidfile='/var/run/centengine.pid'

service_start() {
    echo "centenginedevscript: starting centengine"
    if [ \! -f "$pidfile" ] ; then
	pid=`su - centreon-engine -c "/usr/sbin/centengine /etc/centreon-engine/centengine.cfg > /dev/null 2>&1 & echo \\$!"`
	echo "$pid" > "$pidfile"
	echo "centenginedevscript: centengine started"
    else
	echo "centenginedevscript: centengine is already started"
    fi
}

service_stop() {
    echo "centenginedevscript: stopping centengine"
    kill -TERM `cat "$pidfile"`
    sleep 2
    rm -f "$pidfile"
    echo "centenginedevscript: centengine stopped"
}

service_restart() {
    service_stop
    service_start
}

service_reload() {
    echo "centenginedevscript: reloading centengine"
    kill -HUP `cat "$pidfile"`
    echo "centenginedevscript: centengine reloaded"
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
        echo "centenginedevscript: invalid argument"
	exit 3
	;;
esac

exit 0
