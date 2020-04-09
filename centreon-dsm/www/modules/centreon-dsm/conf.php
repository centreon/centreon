<?php
// Be Carefull with internal_name, it's case sensitive (with directory module name)
$module_conf['centreon-dsm']["name"] = "centreon-dsm";
$module_conf['centreon-dsm']["rname"] = "Dynamic Services Management";
$module_conf['centreon-dsm']["mod_release"] = "20.04.0-rc.1";
$module_conf['centreon-dsm']["infos"] = "Centreon Dynamic Service Management (Centreon-DSM) is a module to manage " .
    "alarms with an event logs system. With DSM, Centreon can receive events such as SNMP traps resulting from the " .
    "detection of a problem and assign events dynamically to a slot defined in Centreon, like a tray events.

A resource has a set number of “slots” (containers) on which alerts will be assigned (stored). While this event has " .
    "not been taken into account by a human action, it will remain visible in the interface Centreon. When event is " .
    "acknowledged manually or by a recovery event, the slot becomes available for new events.

The goal of this module is to overhead the basic trap management system of Centreon. The basic function run " .
    "with a single service and alarm crashed by successive alarms.
";
$module_conf['centreon-dsm']["is_removeable"] = "1";
$module_conf['centreon-dsm']["author"] = "Centreon";
$module_conf['centreon-dsm']["stability"] = "stable";
$module_conf['centreon-dsm']["last_update"] = "2020-04-09";
$module_conf['centreon-dsm']["release_note"] = "https://documentation.centreon.com/docs/centreon-dsm/en/latest/";
$module_conf['centreon-dsm']["images"] = [
    'images/dsm_snmp_events_tray.png'
];
