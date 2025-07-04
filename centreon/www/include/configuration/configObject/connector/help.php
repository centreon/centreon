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

$help['connector_name'] = dgettext('help', 'Name which will be used for identifying the connector.');
$help['connector_description'] = dgettext('help', 'A short description of the connector.');
$help['command_line'] = dgettext('help', 'The connector binary that centreon-engine will launch.');
$help['connector_status'] = dgettext('help', 'Whether or not the connector is enabled.');
