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

$help = [];
$help['mc_update'] = dgettext(
    'help',
    'Choose the update mode for the below field: incremental adds the selected values, replacement overwrites '
    . 'the original values.'
);

// Host Configuration
$help['downtime_name'] = dgettext('help', 'The name of the recurrent downtime rule.');
$help['downtime_description'] = dgettext('help', 'Description of the downtime');
$help['downtime_activate'] = dgettext('help', 'Option to enable or disable this downtime');
$help['downtime_period'] = dgettext(
    'help',
    'This field give the possibility to configure the frequency of this downtime.'
);

$help['host_relation'] = dgettext(
    'help',
    'This field give you the possibility to select all hosts implied by this downtime'
);
$help['hostgroup_relation'] = dgettext(
    'help',
    'This field give you the possibility to select all hostgroups and all hosts contained into the selected '
    . 'hostgroups implied by this downtime'
);
$help['svc_relation'] = dgettext(
    'help',
    'This field give you the possibility to select all services implied by this downtime'
);
$help['svcgroup_relation'] = dgettext(
    'help',
    'This field give you the possibility to select all servicegroups and all services contained into the '
    . 'servicegroups implied by this downtime'
);
