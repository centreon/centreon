<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

// Be Careful with internal_name, it's case sensitive (with directory module name)
$module_conf['centreon-dsm']['name'] = 'centreon-dsm';
$module_conf['centreon-dsm']['rname'] = 'Dynamic Services Management';
$module_conf['centreon-dsm']['mod_release'] = '25.07.0';
$module_conf['centreon-dsm']['infos'] = 'Centreon Dynamic Service Management (Centreon-DSM) is a module to manage '
    . 'alarms with an event logs system. With DSM, Centreon can receive events such as SNMP traps resulting from the '
    . 'detection of a problem and assign events dynamically to a slot defined in Centreon, like a tray events.

A resource has a set number of “slots” (containers) on which alerts will be assigned (stored). While this event has '
    . 'not been taken into account by a human action, it will remain visible in the interface Centreon. When event is '
    . 'acknowledged manually or by a recovery event, the slot becomes available for new events.

The goal of this module is to overhead the basic trap management system of Centreon. The basic function run '
    . 'with a single service and alarm crashed by successive alarms.
';
$module_conf['centreon-dsm']['is_removeable'] = '1';
$module_conf['centreon-dsm']['author'] = 'Centreon';
$module_conf['centreon-dsm']['stability'] = 'stable';
$module_conf['centreon-dsm']['last_update'] = '2025-05-28';
$module_conf['centreon-dsm']['release_note']
    = 'https://docs.centreon.com/23.10/en/releases/centreon-os-extensions.html';
$module_conf['centreon-dsm']['images'] = [
    'images/dsm_snmp_events_tray.png',
];
