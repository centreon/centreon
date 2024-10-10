<?php
$help = [];

/**
 * Centreon Gorgone Settings
 */
$help['tip_gorgone_cmd_timeout'] = dgettext(
    'help',
    "Timeout value in seconds. Used to make actions calls timeout."
);
$help['tip_enable_broker_stats'] = dgettext(
    'help',
    "Enable Centreon Broker statistics collection into the central server in order to reduce the network load "
    . "generated by Centcore. Be carefull: broker statistics will not be available into Home > Broker statistics."
);
