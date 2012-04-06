# String for exclusion
$EXCLUDESTR = "";

$LOG = "@CENTREON_LOG@/dynamicTrap.log";

$NAGIOSCMD = "@NAGIOS_CMD@/nagios.cmd";
$CECORECMD = "@CENTREON_VARLIB@/centcore.cmd";

$LOCKDIR = "@CENTREON_VARLIB@/centreon-dsm/tmp/";
$CACHEDIR = "@CENTREON_VARLIB@/centreon-dsm/cache/";
$MAXDATAAGE = 5;

# Force recovery if the service is ok 
$FORCEFREE = 1;

# Macro name for alam ID
$MACRO_ID_NAME = "ALARM_ID";

## Action 0 alarm Core
$ACTION_ALARM = 0;

# Pattern list
@pattern_output;
# The index of pattern_output is the id action
#$pattern_output[$ACTION_ALARM]="%%1%%_%%2%%";

@action_list;
#$action_list[$ACTION_ALARM]->{'host'}->{'run'} = 'rename';
#$action_list[$ACTION_ALARM]->{'host'}->{'pattern'} = "";


# Application conf
$DEBUG_ENABLED = 0;
$USE_LONG_OUTPUT = 0;

1;