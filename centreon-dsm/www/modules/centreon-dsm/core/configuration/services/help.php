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

$help['pool_name'] = dgettext('help', 'The pool name.');
$help['pool_description'] = dgettext('help', 'The pool description.');
$help['pool_host_id'] = dgettext(
    'help',
    'The pool slot will be attached to this host.<br/><b>The host can have only one pool.</b>'
);
$help['pool_service_template'] = dgettext('help', 'The service template for the slots.');
$help['pool_number'] = dgettext('help', 'The number of slots.');
$help['pool_prefix'] = dgettext('help', 'The prefix for create slots.<br/><i>prefix-</i>0000');
$help['pool_cmd_id'] = dgettext('help', 'The command for slots.');
$help['pool_activate'] = dgettext('help', 'If the pool and its slots are enable.');
