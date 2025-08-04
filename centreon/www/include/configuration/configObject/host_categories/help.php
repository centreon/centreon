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
$help['hc_name'] = dgettext(
    'help',
    'Define a short name for this category. It will be displayed with this name in the ACL configuration.'
);
$help['hc_alias'] = dgettext('help', 'Use this field for a longer description of this category.');
$help['hc_hosts'] = dgettext('help', 'Select the hosts that this category is linked to.');
$help['hc_hostsTemplate'] = dgettext('help', 'Select the host templates that this category is linked to.');
$help['hc_type'] = dgettext(
    'help',
    'Whether this category is a severity. Severities appear on the monitoring consoles.'
);
$help['hc_severity_level'] = dgettext(
    'help',
    'Severity level, must be a number. The items displayed will be sorted in ascending order. Thus the '
    . 'lowest severity is considered than the highest priority.'
);
$help['hc_severity_icon'] = dgettext('help', 'Icon for this severity.');
$help['hc_activate'] = dgettext('help', 'Whether or not this category is enabled.');
$help['hc_comment'] = dgettext('help', 'Comment regarding this category.');
