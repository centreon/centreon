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
$help['host'] = dgettext('help', 'Select the poller you would like to interact with.');
$help['gen'] = dgettext('help', 'Generates configuration files and stores them in cache directory.');
$help['debug'] = dgettext('help', 'Runs the scheduler debug mode.');
$help['move'] = dgettext('help', "Copies the generated files into the scheduler's configuration folder.");
$help['restart'] = dgettext('help', 'Restart the scheduler: Restart, Reload or External Command.');
$help['postcmd'] = dgettext(
    'help',
    'Run the commands that are defined in the poller configuration page (Configuration > Centreon > '
    . 'Poller > Post-Restart command).'
);
