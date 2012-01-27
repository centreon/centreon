<?php
$help = array();

$help["command_name"] = dgettext("help", "The command name is used to identify and display this command in contact, host and service definitions.");

$help["command_line_help"] = dgettext("help", "The command line specifies the real command line, that is actually executed by Nagios. Before execution, all valid macros are replaced with their respective values. To use the dollar sign (\$) on the command line, you have to escape it with another dollar sign (\$\$). A semicolon (;) is used as seperator for config file comments and must be worked around by setting a \$USER\$ macro to a semicolon and then referencing it here in place of the semicolon. If you want to pass arguments to commands during runtime, you can use \$ARGn\$ macros in the command line.");

$help["arg_example"] = dgettext("help", "The argument example defined here will be displayed together with the command selection and help in providing a hint of how to parametrize the command for execution.");
$help["command_type"] = dgettext("help", "Define the type of the command. The type will be used to show the command only in the relevant sections.");
$help["graph_template"] = dgettext("help", "The optional definition of a graph template will be used as default graph template, when no other is specified.");
$help["arg_description"] = dgettext("help", "The argument description provided here will be displayed instead of the technical names like ARGn.");

?>

