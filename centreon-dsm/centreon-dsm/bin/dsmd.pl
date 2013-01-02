#! /usr/bin/perl
################################################################################
# Copyright 2005-2011 MERETHIS
# Centreon is developped by : Julien Mathis and Romain Le Merlus under
# GPL Licence 2.0.
# 
# This program is free software; you can redistribute it and/or modify it under 
# the terms of the GNU General Public License as published by the Free Software 
# Foundation ; either version 2 of the License.
# 
# This program is distributed in the hope that it will be useful, but WITHOUT ANY
# WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A 
# PARTICULAR PURPOSE. See the GNU General Public License for more details.
# 
# You should have received a copy of the GNU General Public License along with 
# this program; if not, see <http://www.gnu.org/licenses>.
# 
# Linking this program statically or dynamically with other modules is making a 
# combined work based on this program. Thus, the terms and conditions of the GNU 
# General Public License cover the whole combination.
# 
# As a special exception, the copyright holders of this program give MERETHIS 
# permission to link this program with independent modules to produce an executable, 
# regardless of the license terms of these independent modules, and to copy and 
# distribute the resulting executable under terms of MERETHIS choice, provided that 
# MERETHIS also meet, for each linked independent module, the terms  and conditions 
# of the license of that module. An independent module is a module which is not 
# derived from this program. If you modify this program, you may extend this 
# exception to your version of the program, but you are not obliged to do so. If you
# do not wish to do so, delete this exception statement from your version.
# 
# For more information : contact@centreon.com
# 
# SVN : $URL
# SVN : $Id
#
####################################################################################

use strict;
use DBI;
use File::Path qw(mkpath);
use Time::HiRes qw(usleep ualarm gettimeofday tv_interval nanosleep clock_gettime clock_getres clock_nanosleep clock stat);

use vars qw($mysql_database_oreon $mysql_database_ods $mysql_host $mysql_user $mysql_passwd $ndo_conf $LOG);
use vars qw($DBType $CECORECMD $LOCKDIR $MAXDATAAGE $CACHEDIR $EXCLUDESTR $MACRO_ID_NAME $FORCEFREE);
use vars qw(@pattern_output @action_list @macroList @statusList @idList @hostList @cacheList @outputList @timeList $debug $DBType $DEBUG_ENABLED $USE_LONG_OPT);
use vars qw($alreadySent %macroCache @slotList $pool_prefix $dbh $dbhS);

############################################
# To the config file
require "@CENTREON_ETC@/conf.pm";
require "@CENTREON_ETC@/conf_dsm.pm";

#############################################
# Test for new release
my $longopt = $USE_LONG_OPT;
eval "use Getopt::Long qw(:config no_ignore_case)";
if ($@) {
    $longopt = 0;
}

$debug = $DEBUG_ENABLED;
$alreadySent = 0;

############################################
# Set arguments
writeLogFile("=====================================================", "DD");
writeLogFile("Starting DSMD", "II");

## Get help
my $action = "nil";
my $hostname = "nil";
my $id = "nil";
my $timeRequest = "nil";
my $status = -1;
my $output = "";
my $macros = "";
my $run = 1;

#############################################
# MySQL Broker Handle
my $dbh2;
my $sth2;

#############################################
# Connect to Centreon Database
MySQLConnect();
MySQLConnectStorage();

#############################################
# Define DBTyper
$DBType = getDBType($dbh);

############################################
## Connect to RT Database
if ($DBType == 0) {
    $dbh2 = ndoDBConnect();
} else {
    $dbh2 = storageDBConnect();
}

$SIG{'TERM'}  = \&catchStopSignal;

#############################################
## Define macro
my $lastCheck = time();
my $lastPurge = 0; 

# Event Loop
while ($run) {
    my $now = time();

    if ($now gt ($lastCheck + 5)) {
        ####################################
        # Check Alarms
        writeLogFile("Check alarm cache", "DD");

        #############################################
        ## Purge old lock in database
        purgeLockFiles();

        checkCackeList();
        processAlarm();
        
        $lastCheck = $now;
    } 
    if ($now gt ($lastPurge + 30) || $lastPurge eq 0) {
        ####################################
        # Purge info
        writeLogFile("Purge Locks & old macro status", "DD");
        
        #############################################
        ## Purge old slot alarm_id
        %macroCache = cleanEmptyMacros($dbh, $dbh2);
        
        $lastPurge = $now;
    }
    sleep(1);
}

################################
## Get Signal
#
sub catchStopSignal {
    $run = 0;
    writeLogFile("Receiving order to stop...", "II");
}

################################
##
#
sub processAlarm() {

    my $slot_service;
    my @fullHostList = getHostList();
    my $y = 0;
    foreach my $t (@cacheList) {

        $pool_prefix = getPoolPrefix($hostList[$y]);
        $hostname = getRealHostName($hostList[$y]);
        
        if ($idList[$y] eq 'nil' || $idList[$y] eq '') {
            #writeLogFile("I can't found an ID", "DD");
            
            writeLogFile("Processing[$y]: ".$hostList[$y] . "|".$outputList[$y]."|".$statusList[$y]."|", "DD");

            $slot_service = getFreeSlotWithoutID($hostList[$y]);
            
            if (!send_command($hostList[$y], $slot_service, $statusList[$y], $timeList[$y], $outputList[$y], $macroList[$y], $idList[$y], $dbh)) {
                removeAlarmInCache($cacheList[$y]);
            }
        } else {
            #writeLogFile("I can found an ID", "DD");

            writeLogFile("Processing[$y]: ".$hostList[$y] . "|".$outputList[$y]."|".$idList[$y]."|".$statusList[$y]."|", "DD");
            
            $slot_service = get_slot($hostname, $idList[$y], $dbh, $dbh2);

            my $lockState = checkLockState($hostList[$y], $idList[$y]);
           
            if ($statusList[$y] ne 0) {
                #writeLogFile("Status Non OK", "DD");                
                if ($slot_service eq "nil") {
                    if (defined($macroCache{$hostList[$y].";".$idList[$y]})) {
                        $slot_service = $macroCache{$hostList[$y].";".$idList[$y]};
                    } else {
                        $slot_service = getFreeSlotWithID($hostList[$y]);
                    }
                }
                #writeLogFile(" * HOST: $hostname - SLOT: ".$slot_service . " -> ID: ".$idList[$y]);
                if ($lockState) {
                    if (!send_command($hostList[$y], $slot_service, $statusList[$y], $timeList[$y], $outputList[$y], $macroList[$y], $idList[$y], $dbh)) {
                        removeAlarmInCache($cacheList[$y]);
                    }
                }
            } else {
                if ($statusList[$y] eq 0 && $idList[$y] ne "nil" && $FORCEFREE && $lockState) {
                    if (!send_command($hostList[$y], $slot_service, $statusList[$y], $timeList[$y], $outputList[$y], $macroList[$y], $idList[$y], $dbh)) {
                        removeAlarmInCache($cacheList[$y]);
                    }
                } 
            }
        }
        $y++;
    }

}

sub getHostList() {
    my @tmpList;
    my $request = "SELECT pool_host_id, host_name FROM mod_dsm_pool p, host h WHERE p.pool_host_id = h.host_id AND h.host_activate = '1' AND h.host_register = '1' AND p.pool_activate = '1'";
    my $sthC = $dbh->prepare($request);
    if (!$sthC->execute()) {
        writeLogFile("Error:" . $sthC->errstr . "\n");
    }
    my $i = 0;
    while (my $data = $sthC->fetchrow_hashref()) {
        $tmpList[$i] = $data->{'host_name'};
        $i++;
    }
    return @tmpList;
}


sub freeCacheInfo(){
    undef(@hostList);
    undef(@cacheList);
    undef(@timeList);
    undef(@statusList);
    undef(@macroList);
    undef(@idList);
    undef(@outputList);
}

sub checkCackeList() {
    my $t = 0;

    freeCacheInfo();

    my $data;
    my $request = "SELECT * FROM mod_dsm_cache WHERE finished = '0' ORDER BY entry_time";
    my $sthC = $dbhS->prepare($request);
    if (!$sthC->execute()) {
        writeLogFile("Error:" . $sthC->errstr . "\n");
    }
    while ($data = $sthC->fetchrow_hashref()) {
        $hostList[$t] = $data->{'host_name'};
        $cacheList[$t] = $data->{'cache_id'};
        $timeList[$t] = $data->{'ctime'};
        $statusList[$t] = $data->{'status'};
        $macroList[$t] = $data->{'macros'};
        $idList[$t] = $data->{'id'};
        $outputList[$t] = $data->{'output'};
        $t++;
    }   
}

sub removeAlarm() {
    my ($y) = @_;

    # Free information
    undef($hostList[$y]);
    undef($cacheList[$y]);
    undef($timeList[$y]);
    undef($outputList[$y]);
    undef($statusList[$y]);
    undef($macroList[$y]);
}

############################################################
## Declare functions
############################################################

###################################################
## Get a host_id from a name
#
sub getHostID($$) {
    my ($host_name, $con) = @_;

    # Request
    my $sth2 = $con->prepare("SELECT `host_id` FROM `host` WHERE `host_name` = '".$host_name."' AND `host_register` = '1'");
    if (!$sth2->execute) {
        writeLogFile("Error:" . $sth2->errstr . "\n");
    }

    my $data_host = $sth2->fetchrow_hashref();
    my $host_id = $data_host->{'host_id'};
    $sth2->finish();

    # free data
    undef($data_host);
    undef($con);

    # return host_id
    return $host_id;
}

###################################################
## Get the poller id when the host is monitored
#
sub getHostPoller($$) {
    my ($host_id, $con) = @_;

    my $sth2 = $con->prepare("SELECT ns.id, ns.localhost FROM nagios_server ns, ns_host_relation nsh WHERE nsh.host_host_id = '".$host_id."' AND ns.id = nsh.nagios_server_id");
    if (!$sth2->execute) {
        writeLogFile("Error:" . $sth2->errstr . "\n");
    }
    my $data_poller = $sth2->fetchrow_hashref();
    undef($sth2);
    return $data_poller;
}

#############################################
## Trim a string value
#
sub trim {
    my $s = shift();
    $s =~ s/^\n*|\n*$//g;
    return $s;
}

#############################################
## Clean macro content fo all ok service 
#
sub cleanEmptyMacros($$) {
    my ($dbh, $dbh2) = @_;
    
    my $sth = $dbhS->prepare("SELECT * from mod_dsm_locks");
    if (!defined($sth)) {
        writeLogFile($DBI::errstr, "EE");
    }
    my @locks;
    if ($sth->execute()) {
        while (my $lock = $sth->fetchrow_hashref()) {
            $locks[$lock->{'host_name'}.";".$lock->{'service_description'}] = 1;
        }
        $sth->finish();
    }

    my $sth2 = $dbh->prepare("SELECT pool_prefix, pool_host_id FROM mod_dsm_pool");
    if (!defined($sth2)) {
        writeLogFile($DBI::errstr, "EE");
    }
    if ($sth2->execute()) {
        while (my $data = $sth2->fetchrow_hashref()) {
            my $request;
            if ($DBType == 1) {
                $request = "SELECT h.name AS hostname, s.description, cv.name AS macro, cv.value FROM services s, customvariables cv, hosts h WHERE s.host_id = h.host_id AND s.host_id = '".$data->{'pool_host_id'}."' AND cv.host_id = s.host_id and s.service_id = cv.service_id AND s.description LIKE '".$data->{'pool_prefix'}."%' AND s.state IN('0', '4') AND cv.value <> 'empty' AND cv.name = '$MACRO_ID_NAME'";
            } else {
                $request = "SELECT varvalue AS value, varname AS macro, name1 AS hostname, name2 AS description FROM ".$ndo_conf->{'db_prefix'}."customvariablestatus cv, ".$ndo_conf->{'db_prefix'}."objects no, ".$ndo_conf->{'db_prefix'}."servicestatus ns WHERE no.object_id = ns.service_object_id AND no.object_id = cv.object_id AND ns.current_state IN ('0', '4') AND no.name2 LIKE '".$data->{'pool_prefix'}."%' AND varname = '$MACRO_ID_NAME' AND cv.object_id IN (SELECT object_id FROM ".$ndo_conf->{'db_prefix'}."customvariablestatus WHERE varname = '$MACRO_ID_NAME') AND no.name1 IN (select no.name1 from ".$ndo_conf->{'db_prefix'}."customvariablestatus nc, ".$ndo_conf->{'db_prefix'}."objects no WHERE varname = 'host_id' AND no.object_id = nc.object_id AND varvalue LIKE '".$data->{'pool_host_id'}."' AND no.name2 IS NULL AND no.objecttype_id = '1')";
            }
            my $sth3 = $dbh2->prepare($request);
            if ($sth3->execute()) {
                while (my $d = $sth3->fetchrow_hashref()) {
                    my $poller = getHostPoller($data->{'pool_host_id'}, $dbh);
                    if (!defined($d->{'hostname'}.";".$d->{'description'})) {
                        updateMacro($d->{'hostname'}, $d->{'description'}, $poller->{'localhost'}, $d->{'macro'}, "empty", time(), $poller->{'id'});
                    }
                }
            }
        }
    }

    #################################
    # Build Macro Cache
    %macroCache = buildMacroCache($dbh, $dbh2);
}

#############################################
## Build a macro cache in order to redirect 
## faster trap with a known ID.
#
sub buildMacroCache($$) {
    my ($dbh, $dbh2) = @_;

    my $sth2 = $dbh->prepare("SELECT pool_prefix, pool_host_id FROM mod_dsm_pool");
    if (!defined($sth2)) {
        writeLogFile($DBI::errstr, "EE");
    }
    if ($sth2->execute()) {
        while (my $data = $sth2->fetchrow_hashref()) {
            my $request;
            if ($DBType == 1) {
                $request = "SELECT h.name AS hostname, s.description, cv.name AS macro, cv.value FROM services s, customvariables cv, hosts h WHERE s.host_id = h.host_id AND s.host_id = '".$data->{'pool_host_id'}."' AND cv.host_id = s.host_id and s.service_id = cv.service_id AND s.description LIKE '".$data->{'pool_prefix'}."%' AND s.state NOT IN('0', '4') AND cv.value <> 'empty' AND cv.name = '$MACRO_ID_NAME'";
            } else {
                $request = "SELECT varvalue AS value, varname AS macro, name1 AS hostname, name2 AS description FROM ".$ndo_conf->{'db_prefix'}."customvariablestatus cv, ".$ndo_conf->{'db_prefix'}."objects no, ".$ndo_conf->{'db_prefix'}."servicestatus ns WHERE no.object_id = ns.service_object_id AND no.object_id = cv.object_id AND ns.current_state NOT IN ('0', '4') AND no.name2 LIKE '".$data->{'pool_prefix'}."%' AND varname = '$MACRO_ID_NAME' AND cv.object_id IN (SELECT object_id FROM ".$ndo_conf->{'db_prefix'}."customvariablestatus WHERE varname = '$MACRO_ID_NAME') AND no.name1 IN (select no.name1 from ".$ndo_conf->{'db_prefix'}."customvariablestatus nc, ".$ndo_conf->{'db_prefix'}."objects no WHERE varname = 'host_id' AND no.object_id = nc.object_id AND varvalue LIKE '".$data->{'pool_host_id'}."' AND no.name2 IS NULL AND no.objecttype_id = '1')";
            }
            my $sth3 = $dbh2->prepare($request);
            if ($sth3->execute()) {
                while (my $d = $sth3->fetchrow_hashref()) {
                    $macroCache{$d->{'hostname'}.";".$d->{'value'}} = $d->{'description'};
                }
            }
        }
    }
    return %macroCache;
}

############################################
## Our version of die
#    - add log into program log file
#    - exit with error code 1
#
sub MyDie($) {
    my ($error) = @_;
    writeLogFile($error, 'EE');
    exit(1);
}

############################################
## Connect to Centreon MySQL Database.
#
sub MySQLConnect() {
    while (!defined($dbh) || !$dbh->ping()) {
        if (!defined($dbh) || !$dbh->ping()) {
            $dbh = DBI->connect("DBI:mysql:database=".$mysql_database_oreon.";host=".$mysql_host, $mysql_user, $mysql_passwd, {'RaiseError' => 0, 'PrintError' => 0, 'AutoCommit' => 1});
            if (!defined($dbh)) {
                writeLogFile("Error when connecting to database : " . $DBI::errstr . "\n");
                sleep(2);
            }
        } else {
            sleep(2);    
            $dbh = DBI->connect("DBI:mysql:database=".$mysql_database_oreon.";host=".$mysql_host, $mysql_user, $mysql_passwd, {'RaiseError' => 0, 'PrintError' => 0, 'AutoCommit' => 1});
        }
        if ($run == 0) {
            return;
        }
    }
}


############################################
## Connect to storage MySQL Database.
#
sub MySQLConnectStorage() {
    while (!defined($dbhS) || !$dbhS->ping()) {
        if (!defined($dbhS) || !$dbhS->ping()) {
            $dbhS = DBI->connect("DBI:mysql:database=".$mysql_database_ods.";host=".$mysql_host, $mysql_user, $mysql_passwd, {'RaiseError' => 0, 'PrintError' => 0, 'AutoCommit' => 1});
            if (!defined($dbhS)) {
                writeLogFile("Error when connecting to database : " . $DBI::errstr . "\n");
                sleep(2);
            }
        } else {
            sleep(2);    
            $dbhS = DBI->connect("DBI:mysql:database=".$mysql_database_ods.";host=".$mysql_host, $mysql_user, $mysql_passwd, {'RaiseError' => 0, 'PrintError' => 0, 'AutoCommit' => 1});
        }
        if ($run == 0) {
            return;
        }
    }
}

#############################################
## Connect to storage database
#
sub storageDBConnect() {
    my $dbhTmp;
    while (!defined($dbhTmp) || !$dbhTmp->ping()) {
        if (!defined($dbhTmp) || !$dbhTmp->ping()) {
            $dbhTmp = DBI->connect("DBI:mysql:database=".$mysql_database_ods.";host=".$mysql_host, $mysql_user, $mysql_passwd, {'RaiseError' => 0, 'PrintError' => 0, 'AutoCommit' => 1});
            if (!defined($dbhTmp)) {
                writeLogFile("Error when connecting to database : " . $DBI::errstr . "\n");
                sleep(2);
            }
        } else {
            sleep(2);    
            $dbhTmp = DBI->connect("DBI:mysql:database=".$mysql_database_ods.";host=".$mysql_host, $mysql_user, $mysql_passwd, {'RaiseError' => 0, 'PrintError' => 0, 'AutoCommit' => 1});
        }
        if ($run == 0) {
            return;
        }
    }
    return $dbhTmp;
}

#############################################
## Connect to NDO database
#    Get into cfg_ndo2db table for getting 
#    NDO connect info.
sub ndoDBConnect() {
    my $ndo_conf = ndoInfo();

    my $dbhTmp;
    while (!defined($dbhTmp) || !$dbhTmp->ping()) {
        if (!defined($dbhTmp) || !$dbhTmp->ping()) {
            $dbhTmp = DBI->connect("dbi:mysql:".$ndo_conf->{'db_name'}.";host=".$ndo_conf->{'db_host'}, $ndo_conf->{'db_user'}, $ndo_conf->{'db_pass'}) 
                or MyDie("NDO DB Connection Error: $mysql_database_oreon => $DBI::errstr \n");
            if (!defined($dbhTmp)) {
                writeLogFile("Error when connecting to database : " . $DBI::errstr . "\n");
                sleep(2);
            }
        } else {
            sleep(2);    
            $dbhTmp = DBI->connect("dbi:mysql:".$ndo_conf->{'db_name'}.";host=".$ndo_conf->{'db_host'}, $ndo_conf->{'db_user'}, $ndo_conf->{'db_pass'}) 
                or MyDie("NDO DB Connection Error: $mysql_database_oreon => $DBI::errstr \n");
        }
        if ($run == 0) {
            return;
        }
    }
    return $dbhTmp;
}

###########################################
## Get NDO Mysql information
#
sub ndoInfo() {
    my $sth2 = $dbh->prepare("SELECT db_host,db_name,db_port,db_prefix,db_user,db_pass FROM cfg_ndo2db");
    if (!$sth2->execute) {
        writeLogFile("Error when getting drop and perfdata properties : ".$sth2->errstr."");
    }
    $ndo_conf = $sth2->fetchrow_hashref();
    undef($sth2);
    return($ndo_conf);
}

########################################
## Get host/address
#
sub getRealHostName($) {
    my ($host_name) = @_; 

    my $sth2 = $dbh->prepare("SELECT host_name FROM host WHERE (host_address LIKE '" . $host_name . "' OR host_name LIKE '" . $host_name . "')");
    if (!defined($sth2)) {
        writelogFile($DBI::errstr, "EE");
    } else {
        if ($sth2->execute()){
            my $hostDataTemp = $sth2->fetchrow_hashref();
            #writeLogFile("Real hostname = " . $hostDataTemp->{'host_name'}, "DD");
            return($hostDataTemp->{'host_name'});
        } else {
            writeLogFile("Can get hosts Informations (name or address) $!", "EE");
            exit(1);
        }
    }
}

########################################
## Get DSM Pool prefix.
#
sub getPoolPrefix($) {
    my ($hostname) = @_;
    my $prefix = "";
    
    my $sth2 = $dbh->prepare("SELECT pool_prefix FROM mod_dsm_pool mdp, host h WHERE mdp.pool_host_id = h.host_id AND (h.host_name LIKE '" . $hostname . "' OR h.host_address LIKE '" . $hostname . "')");
    if (!defined($sth2)) {
        writeLogFile($DBI::errstr, "EE");
    }
    if ($sth2->execute()){
        my $tmp = $sth2->fetchrow_hashref();
        $prefix = $tmp->{'pool_prefix'};
    } else {
        writeLogFile("Can get DSM informations $!", "EE");
        exit(1);
    }
    undef($sth2);
    return($prefix);
}

############################################
# log files management function
sub writeLogFile {
    my ($msg, $lvl) = @_;
    if (!defined($lvl)) {
        $lvl = 'II';
    }

    ##############################
    # Disable debug String write 
    # if not in debug mode
    if ($debug == 0 && $lvl eq "DD") {
        return(0);
    }

    my ($sec,$min,$hour,$mday,$mon,$year,$wday,$yday,$isdst) = localtime(time());
    open (LOG, ">> ".$LOG) || print "can't write $LOG: $!";

    # Add initial 0 if value is under 10
    $hour = "0".$hour if ($hour < 10);
    $min = "0".$min if ($min < 10);
    $sec = "0".$sec if ($sec < 10);
    $mday = "0".$mday if ($mday < 10);
    $mon += 1;
    $mon = "0".$mon if ($mon < 10);

    print LOG ($year+1900) . "-" . $mon . "-" . $mday . " $hour:$min:$sec - [" . $lvl . "] [Daemon] " . $msg . "\n";
    close LOG or warn $!;
}

##################################################
# Get DB type NDO / Broker
# NDO => 0 ; Broker => 1
sub getDBType($) {
    my ($dbh) = @_;

    my $request = "SELECT `value` FROM options WHERE `key` = 'broker'";
    my $sth = $dbh->prepare($request);
    if (!defined($sth)) {
        writeLogFile($DBI::errstr, "EE");
    } else {
        if ($sth->execute()) {
            my $row = $sth->fetchrow_hashref();
            if (defined $row && $row->{'value'} eq 'broker') {
                writeLogFile("Broker mode: Centreon Broker", "DD");
                return 1;
            } else {
                writeLogFile("Broker mode: NDO", "DD");
                return 0;
            }
        }
        return 0;
    }
}

##################################################
# get slot by host and id
sub get_slot($$$$) {
    my ($host, $id, $dbh, $dbh2) = @_;
    my $service_id = "nil";
    my $count_services;
    my @list_services;
    my $query_get;

	$id =~ s/\\/\\\\/g;

    if ($DBType == 1) {
        $query_get = "SELECT services.service_id AS varvalue ". 
            "FROM customvariables, hosts, services " .
            "WHERE hosts.host_id = services.host_id AND ".
            "hosts.host_id = customvariables.host_id AND ".
            "services.service_id = customvariables.service_id AND ".
            "(hosts.name = '$host' OR hosts.address = '$host') AND ".
            "customvariables.name = '" . $MACRO_ID_NAME . "' AND ".
            "value = '" . $id . "' LIMIT 1";
    } else {
        $query_get = "SELECT varvalue " .
            "FROM " . $ndo_conf->{'db_prefix'} . "customvariablestatus " .
            "WHERE varname = 'SERVICE_ID' AND object_id = (SELECT object_id " .
            "FROM " . $ndo_conf->{'db_prefix'} . "customvariablestatus " .
            "WHERE varname = '" . $MACRO_ID_NAME . "' AND varvalue = '" . $id . "' LIMIT 1)";
    }
    my $sth2 = $dbh2->prepare($query_get);
    if (!defined($sth2)) {
        writeLogFile($DBI::errstr, "EE");
        exit(1);
    } else {
        if (!$sth2->execute()){
            writeLogFile("Error when getting perfdata file : " . $sth2->errstr . "", "EE");
            exit(1);
        }
        @list_services;
        while (my $row = $sth2->fetchrow_hashref()) {
            push(@list_services, $row->{"varvalue"});
        }
        undef($sth2);
        $count_services = @list_services;
        if ($count_services == 0) {
            return "nil";
        }
        my $query_check = "SELECT s.service_description " .
            "FROM host_service_relation as hsr, host as h, service as s " .
            "WHERE hsr.host_host_id = h.host_id ".
            " AND (h.host_name = '" . $host . "' OR h.host_address = '" . $host . "') ". 
            " AND h.host_register = '1' AND hsr.service_service_id = s.service_id " .
            " AND hsr.service_service_id IN (" . join(",", @list_services) . ")";
        my $sth2 = $dbh->prepare($query_check);
        if (!defined($sth2)) {
            writeLogFile($DBI::errstr, "EE");
            exit(1);
        }
        if (!$sth2->execute()) {
            writeLogFile("Error when getting perfdata file : " . $sth2->errstr . "", "EE");
            exit(1);
        }
        if (my $row = $sth2->fetchrow_hashref()) {
            $service_id = $row->{"service_description"};
        }
        undef($sth2);
    }
    return $service_id;
}

#############################################
# send a command to the poller
sub send_command {
    my ($host_name, $service, $status, $timeRequest, $output, $macros, $id, $dbh) = @_;

    my $oldID = $id;

    if ($service eq 'nil') {
        writeLogFile("Can't find free service. This alarm will be processed next time.", "DD");
        return -1;
    }

    my $host_id = getHostID($host_name, $dbh);
    my $data_poller = getHostPoller($host_id, $dbh);
    
    my $externalMacro = "";
    if (defined($MACRO_ID_NAME) && $MACRO_ID_NAME ne "nil" && $id ne "nil") {
        if ($status == 0) {
            $id = "empty";
        }
        updateMacro($host_name, $service, $data_poller->{'localhost'}, $MACRO_ID_NAME, $id, $timeRequest, $data_poller->{'id'});
    }

    # Prepare to send update of Service
    $output =~ s/\'//g;
    my $externalCMD = "[$timeRequest] PROCESS_SERVICE_CHECK_RESULT;$host_name;$service;$status;$output";
    
    sendExternalCommad($data_poller->{'id'}, $externalCMD, $data_poller->{'localhost'});
    
    writeLock($host_name, $service, $id);
    
    if ($FORCEFREE && $status == 0) {
        $externalCMD = "[".time()."] PROCESS_SERVICE_CHECK_RESULT;$host_name;$service;0;Free slot";
        sendExternalCommad($data_poller->{'id'}, $externalCMD, $data_poller->{'localhost'});
        writeLogFile("Force to free the following slot: $host_name;$service", "DD");
    }

    my @tab = split(/\|/, $macros);
    foreach my $string (@tab) {
        my @tab2 = split(/\=/, $string);
        if ($FORCEFREE && $status == 0) {
            $tab2[1] = "empty";
        }
        updateMacro($host_name, $service, $data_poller->{'localhost'}, $tab2[0], $tab2[1], $timeRequest, $data_poller->{'id'});
        undef(@tab2);
    }
    
    # Add in Macro Cache
    if ($status eq 0) {
        writeLogFile(" -> Remove from Cache: $host_name;$id {$service}", "DD");
        undef($macroCache{$host_name.";".$oldID});
    } else {
        writeLogFile(" -> Add in Cache: $host_name;$id {$service}", "DD");
        $macroCache{$host_name.";".$oldID} = $service;
    }
    return 0;
}

sub getPollerExternalCmd($) {
    my ($poller_id) = @_;
    
    return ;
}

##########################################################
## Send external command to Centcore
#
sub sendExternalCommad($$) {
    my ($id, $command, $localhost) = @_;

    my $CMDFile;
    my $externalCMD;
    if ($localhost eq 1) {
        $CMDFile = getNagiosConfigurationField($id, "command_file");
		if (!defined($CMDFile) && $CMDFile eq "") {
        	writeLogFile("Can't find external command file for poller $id", "EE");
        	writeLogFile(" -> Drop command: $externalCMD", "EE");
        	return;
        }
        $externalCMD = $command;
    } else {
        $CMDFile = $CECORECMD;
        $externalCMD = "EXTERNALCMD:".$id.":".$command;
    }    

    writeLogFile("Send external command : $externalCMD ($CMDFile)");    
    if (system("echo \"$externalCMD\" >> $CMDFile")) {
        writeLogFile("Cannot Write external command for centcore", 'II');
        return 0;
    }
    return 1;
}

##########################################################
## Declare functions
# @Params: host / service / localhost / macro / var / time / $poller
sub updateMacro($$$$$$) {
    my ($host, $service, $localhost, $macro, $var, $time, $poller) = @_;

    my $externalCMD = "[".time()."] CHANGE_CUSTOM_SVC_VAR;".$host.";".$service.";".$macro.";".$var;

    my  $CMDFile;
    if ($localhost eq 1) {
        $CMDFile = getNagiosConfigurationField($poller, "command_file");
        if (!defined($CMDFile) && $CMDFile eq "") {
        	writeLogFile("Can't find external command file for poller $poller", "EE");
        	writeLogFile(" -> Drop command: $externalCMD", "EE");
        	return;
        }
    } else {
        $CMDFile = $CECORECMD;
        $externalCMD = "EXTERNALCMD:".$poller.":".$externalCMD;
    }    

	writeLogFile("Send external command : $externalCMD ($CMDFile)");
	if (system("echo '$externalCMD' >> $CMDFile")) {
	    writeLogFile("Cannot Write external command for centcore", 'II');
	}
}

sub getNagiosConfigurationField($$){
    my $sth2 = $dbh->prepare("SELECT ".$_[1]." FROM `cfg_nagios` WHERE `nagios_server_id` = '".$_[0]."' AND nagios_activate = '1'");
    if (!$sth2->execute()) {
        writeLogFile("Error when getting server properties : ".$sth2->errstr);
    }
    my $data = $sth2->fetchrow_hashref();
    $sth2->finish();
    return $data->{$_[1]};
}

##################################################
## Check slot Status with lock
#
sub checkLockState($$) {
    my ($host_name, $id) = @_;
    
    my $request = "SELECT host_name, service_description, id FROM mod_dsm_locks WHERE host_name = '$host_name' AND id = '$id'";
    my $sth = $dbhS->prepare($request);
    if (!defined($sth)) {
        writeLogFile($DBI::errstr, "EE");
    } else {
        if ($sth->execute()) {
            my $row = $sth->fetchrow_hashref();
            if (defined($row) && $row->{'id'} eq $id) {
                return 0;
            } else {
                return 1;
            }
        }
        return 0;
    }
}

##################################################
## Write Locks
# in order to lock slot during the first minute in order
# to manage the refresh time of Nagios (UI and External Cmd)
#
sub writeLock($$$) {
    my ($host_name, $service, $id) = @_;

    my $request = "INSERT INTO mod_dsm_locks (lock_id, host_name, service_description, id, ctime) VALUES (NULL, '".$host_name."', '".$service."', '".$id."', '".time()."')";
    $dbhS->do($request);
}

##################################################
## Remove Locks
#
sub removeLock($) {
    my ($lock_id) = @_;

    my $request = "DELETE FROM mod_dsm_locks WHERE lock_id = '".$lock_id."'";
    $dbhS->do($request);
}

##################################################
## Remove Alarms in Cache table
#
sub removeAlarmInCache($) {
    my ($cache_id) = @_;

    my $request = "UPDATE mod_dsm_cache SET `finished` = '1' WHERE cache_id = '".$cache_id."'";
    $dbhS->do($request);
}

###################################################
## Purge old Lock files
#
sub purgeLockFiles() {
    my ($dev,$ino,$mode,$nlink,$uid,$gid,$rdev,$size,$atime,$mtime,$ctime,$blksize,$blocks) = lstat($_);
    my $delta = time() - $mtime;

    $MAXDATAAGE = 30;
    
    my $request = "DELETE FROM mod_dsm_locks WHERE ctime < '".(time()-$MAXDATAAGE)."'";    
    my $sth2 = $dbhS->do($request);
    if (!defined($sth2)) {
        writeLogFile($DBI::errstr, "EE");
        return;
    }
}

#################################################
## Get Free Slot list
#
sub getFreeSlotWithoutID($) {
    my ($host_name) = @_;
    
    my %lockCache2;
    my $request = "SELECT host_name, service_description FROM mod_dsm_locks WHERE host_name = '$host_name'";
    my $sth = $dbhS->prepare($request);
    if (!defined($sth)) {
        writeLogFile($DBI::errstr, "EE");
    } else {
        if ($sth->execute()) {
            while (my $row = $sth->fetchrow_hashref()) {
                $lockCache2{$row->{'host_name'}.";".$row->{'service_description'}} = 1;
            }
        }
    }

    my $request = "";
    if ($DBType == 1) {
        $request = "SELECT hosts.name AS host_name, services.description AS service_description FROM hosts, services WHERE hosts.host_id = services.host_id AND hosts.name LIKE '" . $host_name . "' AND services.state IN ('0', '4') AND description LIKE '" . $pool_prefix . "%' AND services.enabled = 1 ORDER BY services.description";
    } else {
        $request = "SELECT no.name1 AS host_name, no.name2 AS service_description ".
            "FROM ".$ndo_conf->{'db_prefix'}."servicestatus nss , ".$ndo_conf->{'db_prefix'}."objects no, ".$ndo_conf->{'db_prefix'}."services ns ".
            "WHERE no.object_id = nss.service_object_id AND no.name1 like '" . $host_name . "' AND no.object_id = ns.service_object_id".
            "AND nss.current_state = 0 AND no.name2 LIKE '" . $pool_prefix . "%' ORDER BY name2";
    }
    my $sth2 = $dbh2->prepare($request);
    if (!$sth2->execute()){
        writeLogFile("Error when getting info : " . $sth2->errstr . "", "EE");
    }
    while (my $row = $sth2->fetchrow_hashref()) {
        if (!defined($lockCache2{$host_name.";".$row->{'service_description'}})) {
            return $row->{'service_description'};
        }
    }
    return "nil";
}

#################################################
## Get Free Slot list for slot with id
#
sub getFreeSlotWithID($$) {
    my ($host_name, $id) = @_;

    my %lockCache;
    my $request = "SELECT host_name, service_description, id FROM mod_dsm_locks WHERE host_name = '$host_name' AND id = '$id'";
    my $sth = $dbhS->prepare($request);
    if (!defined($sth)) {
        writeLogFile($DBI::errstr, "EE");
    } else {
        if ($sth->execute()) {
            while (my $row = $sth->fetchrow_hashref()) {
                $lockCache{$row->{'host_name'}.";".$row->{'id'}} = $row->{'service_description'};
            }
        }
    }

    my %lockCache2;
    $request = "SELECT host_name, service_description, id FROM mod_dsm_locks WHERE host_name = '$host_name'";
    my $sth = $dbhS->prepare($request);
    if (!defined($sth)) {
        writeLogFile($DBI::errstr, "EE");
    } else {
        if ($sth->execute()) {
            while (my $row = $sth->fetchrow_hashref()) {
                $lockCache2{$row->{'host_name'}.";".$row->{'service_description'}} = 1;
            }
        }
    }

    if (defined($lockCache{$host_name.";".$id})) {
        return $lockCache{$host_name.";".$id};
    }

    my $request = "";
    if ($DBType == 1) {
        $request = "SELECT hosts.name AS host_name, services.description AS service_description FROM hosts, services WHERE hosts.host_id = services.host_id AND hosts.name LIKE '" . $host_name . "' AND services.state IN ('0', '4') AND description LIKE '" . $pool_prefix . "%' AND services.enabled = 1 ORDER BY services.description";
    } else {
        $request = "SELECT no.name1 AS host_name, no.name2 AS service_description ".
            "FROM ".$ndo_conf->{'db_prefix'}."servicestatus nss , ".$ndo_conf->{'db_prefix'}."objects no, ".$ndo_conf->{'db_prefix'}."services ns ".
            "WHERE no.object_id = nss.service_object_id AND no.name1 like '" . $host_name . "' AND no.object_id = ns.service_object_id".
            "AND nss.current_state = 0 AND no.name2 LIKE '" . $pool_prefix . "%' ORDER BY name2";
    }
    my $sth2 = $dbh2->prepare($request);
    if (!$sth2->execute()){
        writeLogFile("Error when getting info : " . $sth2->errstr . "", "EE");
    }
    while (my $row = $sth2->fetchrow_hashref()) {
        if (!defined($lockCache2{$host_name.";".$row->{'service_description'}})) {
            return $row->{'service_description'};
        }
    }
    return "nil";
}

__END__
