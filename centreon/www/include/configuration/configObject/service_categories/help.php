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
$help['name'] = dgettext(
    'help',
    'Define a short name for this category. It will be displayed with this name in the ACL configuration.'
);
$help['description'] = dgettext('help', 'Use this field for a longer description of this category.');
$help['service_template'] = dgettext(
    'help',
    'Select the service templates this category should be linked to. Every service based on the selected '
    . 'templates will be automatically linked with this category.'
);
$help['sc_type'] = dgettext(
    'help',
    'Whether this category is a severity. Severities appear on the monitoring consoles.'
);
$help['sc_severity_level'] = dgettext(
    'help',
    'Severity level, must be a number. The items displayed will be sorted in ascending order. '
    . 'Thus the lowest severity is considered than the highest priority.'
);
$help['sc_severity_icon'] = dgettext('help', 'Icon for this severity.');
$help['sc_activate'] = dgettext('help', 'Whether or not this category is enabled.');
