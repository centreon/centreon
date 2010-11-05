# String for exclusion
$EXCLUDESTR = "";

$LOG = "@INSTALL_DIR_CENTREON@/log/dynamicTrap.log";

$NAGIOSCMD = "@NAGIOS_VAR@/rw/nagios.cmd";
$CECORECMD = "@CENTREON_VARLIB@/centcore.cmd";

$LOCKDIR = "@CENTREON_VARLIB@/tmp/";
$CACHEDIR = "@CENTREON_VARLIB@/cache/";
$MAXDATAAGE = 5;

$MACRO_ID_NAME = "ALARM_ID";

## Action 0 alarm Core
$ACTION_ALARM = 0;

# Pattern list
@pattern_output;
# The index of pattern_output is the id action
$pattern_output[$ACTION_ALARM]="%%1%%_%%2%%";

@action_list;
$action_list[$ACTION_ALARM]->{'host'}->{'run'} = 'rename';
$action_list[$ACTION_ALARM]->{'host'}->{'pattern'} = "";


1;