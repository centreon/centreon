<?php

/*
 * Copyright 2005-2022 Centreon
 * Centreon is developed by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

// Be Careful with internal_name, it's case sensitive (with directory module name)
$module_conf['centreon-dsm']["name"] = "centreon-dsm";
$module_conf['centreon-dsm']["rname"] = "Dynamic Services Management";
$module_conf['centreon-dsm']["mod_release"] = "23.04.0";
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
$module_conf['centreon-dsm']["last_update"] = "2023-03-31";
$module_conf['centreon-dsm']["release_note"] =
    "https://docs.centreon.com/23.04/en/releases/centreon-os-extensions.html";
$module_conf['centreon-dsm']["images"] = [
    'images/dsm_snmp_events_tray.png'
];
