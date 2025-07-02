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

$help['contactgroup_name'] = dgettext(
    'help',
    'The contact group name is a short name used to identify the contact group in other sections.'
);
$help['alias'] = dgettext('help', 'The alias is a longer name or description used to identify the contact group.');
$help['members'] = dgettext(
    'help',
    'The linked contacts define a list of contacts that should be included in this group. This definition is '
    . 'an alternative way to specifying the contact groups in contact definitions.'
);
$help['acl_groups'] = dgettext(
    'help',
    'Refers to the ACL groups this contact group is linked to. This parameter is mandatory if you are '
    . 'not an administrator.'
);

// unsupported in Centreon
$help['contactgroup_members'] = dgettext(
    'help',
    'This optional directive can be used to include contacts from other "sub" contact groups in this '
    . 'contact group. Specify a list of other contact groups whose members should be included in this group.'
);
