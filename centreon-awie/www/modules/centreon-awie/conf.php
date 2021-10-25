<?php

/*
 * Copyright 2021 Centreon
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

$module_conf['centreon-awie']['rname'] = 'Centreon Api Web Import Export';
$module_conf['centreon-awie']['name'] = 'centreon-awie';
$module_conf['centreon-awie']["mod_release"] = "21.10.0-beta.2";
$module_conf['centreon-awie']["infos"] = "The Centreon AWIE (Application Web Import Export) module has been " .
    "designed to help users configure several Centreon Web platforms in a faster and easier way, thanks to its " .
    "import/export mechanism.

From a properly configured source environment, you can use the AWIE module to export chosen objects towards a " .
    "target environment. Those objects will be replicated.

Centreon AWIE is based on CLAPI commands but its added value is to allow using Centreon Web UI instead of " .
    "commands lines.
";
$module_conf['centreon-awie']["is_removeable"] = "1";
$module_conf['centreon-awie']["author"] = "Centreon";
$module_conf['centreon-awie']["stability"] = "stable";
$module_conf['centreon-awie']["last_update"] = "2021-10-24";
$module_conf['centreon-awie']["release_note"] =
    "https://documentation.centreon.com/21.10/en/releases/centreon-os-extensions.html";
$module_conf['centreon-awie']["images"] = [
    'images/image1.png'
];
