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

$help['command_name'] = dgettext(
    'help',
    'The command name is used to identify and display this command in contact, host and service definitions.'
);

$help['command_line_help'] = dgettext(
    'help',
    'The command line specifies the real command line which is actually executed by Monitoring engine. '
    . 'Before execution, all valid macros are replaced with their respective values. To use the dollar sign ($) '
    . 'on the command line, you have to escape it with another dollar sign ($$). A semicolon (;) '
    . 'is used as separator for config file comments and must be worked around by setting a $USER$ macro '
    . 'to a semicolon and then referencing it here in place of the semicolon. If you want to pass arguments '
    . 'to commands during runtime, you can use $ARGn$ macros in the command line.'
);
$help['enable_shell'] = dgettext(
    'help',
    'If your command requires shell features like pipes, redirections, globbing etc. check this box. '
    . 'If you are using Monitoring Engine this option cannot be disabled. Note that commands that require shell '
    . 'are slowing down the poller server.'
);
$help['arg_example'] = dgettext(
    'help',
    'The argument example defined here will be displayed together with the command selection and help in '
    . 'providing a hint of how to parametrize the command for execution.'
);
$help['command_type'] = dgettext(
    'help',
    'Define the type of the command. The type will be used to show the command only in the relevant sections.'
);
$help['graph_template'] = dgettext(
    'help',
    'The optional definition of a graph template will be used as default graph template, when no other is specified.'
);
$help['arg_description'] = dgettext(
    'help',
    'The argument description provided here will be displayed instead of the technical names like ARGn.'
);
$help['macro_description'] = dgettext(
    'help',
    'The macro description provided here will be displayed instead of the technical name.'
);
$help['command_comment'] = dgettext('help', 'Comments regarding the command.');
$help['connectors'] = dgettext(
    'help',
    'Connectors are run in background and execute specific commands without the need to execute a binary, '
    . 'thus enhancing performance. This feature is available in Centreon Engine (>= 1.3)'
);
