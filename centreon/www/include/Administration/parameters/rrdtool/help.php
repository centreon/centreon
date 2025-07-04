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

/**
 * RRDTool Configuration
 */
$help['tip_directory+rrdtool_binary'] = dgettext('help', 'RRDTOOL binary complete path.');
$help['tip_rrdtool_version'] = dgettext('help', 'RRDTool version.');

/**
 * RRDCached Properties
 */
$help['tip_rrdcached_enable'] = dgettext(
    'help',
    'Enable the rrdcached for Centreon. This option is valid only with Centreon Broker'
);
$help['tip_rrdcached_port'] = dgettext('help', 'Port for communicating with rrdcached');
$help['tip_rrdcached_unix_path'] = dgettext(
    'help',
    'The absolute path to unix socket for communicating with rrdcached'
);
