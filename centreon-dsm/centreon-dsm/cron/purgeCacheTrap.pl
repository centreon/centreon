#! /usr/bin/perl
################################################################################
# Copyright 2005-2009 MERETHIS
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
use vars qw($NAGIOSCMD $DBType $CECORECMD $LOCKDIR $MAXDATAAGE $CACHEDIR $EXCLUDESTR $MACRO_ID_NAME $FORCEFREE);
use vars qw(@pattern_output @action_list @macroList @statusList @idList @hostList $debug $DBType $DEBUG_ENABLED);
use vars qw($alreadySent %macroCache @slotList $host_name $hostname);

############################################
# To the config file
require "@CENTREON_ETC@/conf.pm";
require "@CENTREON_ETC@/conf_dsm.pm";

$debug = $DEBUG_ENABLED;

############################################
# Set arguments
writeLogFile("=====================================================", "DD");
writeLogFile("Cron Purge Cache", "DD");

############################################
# Check Temporary lock files directory
if (!-d $LOCKDIR){ 
    writeLogFile("Cannot find temporary lock files directory. I create it : $LOCKDIR.");
    mkpath($LOCKDIR);
}

#############################################
# Connect to Centreon Database
my $dbh = MySQLConnect();

#############################################
# Define DBTyper
$DBType = getDBType($dbh);

############################################
# Purge Slot locks
purgeLockFiles();

############################################
## Connect to RT Database
my $dbh2;
if ($DBType == 0) {
    $dbh2 = ndoDBConnect();
} else {
    $dbh2 = storageDBConnect();
}

#############################################
## Purge old slot alarm_id
%macroCache = cleanEmptyMacros($dbh, $dbh2);

#############################################
## Check all hosts
#
my $pool_prefix;
my @timeList;
my @outputList;
my @outputList;
my @fileList;
my $sth2 = $dbh->prepare("SELECT pool_prefix, pool_host_id, host_name FROM mod_dsm_pool, host WHERE pool_host_id = host_id");
if (!defined($sth2)) {
    writeLogFile($DBI::errstr, "EE");
}
if ($sth2->execute()) {
    while (my $data = $sth2->fetchrow_hashref()) {
	writeLogFile("Check ".$data->{'host_name'}. " informations...", "DD");
	$pool_prefix = getPoolPrefix($data->{'host_name'});

	############################################
	# Set host_name
	$host_name = $data->{'host_name'};

	############################################
	## Get Free Slots
	getFreeSlot();

	############################################
	# Read cache of results
	my $t = 0;
	my @fileList = glob($CACHEDIR."/".$host_name."*");
	foreach my $filename (@fileList) {
	    if (open(FILE, $filename)) {
		$fileList[$t] = $filename;
		my $i = 0;
		while (<FILE>) {
		    if ($i == 0) {
			$timeList[$t] = trim($_);
			$timeList[$t] =~ s/\n//g;
		    } elsif ($i == 1) {
			$statusList[$t] = trim($_);
		    } elsif ($i == 2) {
			$macroList[$t] = trim($_);
		    } elsif ($i == 3) {
			$idList[$t] = trim($_);
		    } else {
			if (defined($outputList[$t])) {
			    $outputList[$t] = trim($_); 
			} else {
			    $outputList[$t] .= trim($_); 
			}
		    }
		    $i++;
		}
		close FILE;
		unlink($filename);
	    }
	    $t++;
	}
	undef(@fileList);

	############################################
	## Send data to Nagios servers if slot are 
	## available
	#
	my $showAlert = 0;
	my $output;
	my $timeRequest;
	my $id;
	my $status; 
	my $y = 0;
	if (@slotList ne 0) {
	    foreach my $str (@slotList) {
		my @tab = split(";", $str);
		if (defined($timeList[$y])) {
		    # Check if I can use this slot
		    if (length($str) && !-e $LOCKDIR."$str.lock") {
			my $time_now = $timeList[$y];
			$output = $outputList[$y];
			$host_name = $data->{'host_name'};

			# Check Lock Files
			my @fileList = glob($LOCKDIR."/".$data->{'host_name'}."*");
			my $tmpID;
			
			foreach (@fileList) {
			    open(FILE, $_);
			    my $a = 1;
			    my $tmpName;
			    while (<FILE>) {
				if ($a eq 2) {
				    $tmpName = trim($_);
				} elsif ($a eq 3) {
				    $tmpID = trim($_);
				}
				$a++;
			    }
			    close(FILE);
			    
			    if ($id eq $tmpID) {
				# print "We find the same id into the data cache. We update the actual slot...\n";
				$tab[1] = $tmpName;
			    }
			}

			# Build external command
			if (($statusList[$y] == 0 && $idList[$y] != "nil" && $FORCEFREE) || $statusList[$y] != 0) {
			    writeLogFile("3");
			    # Send Command
			    send_command($data->{'host_name'}, $tab[1], trim($statusList[$y]), trim($timeList[$y]), $outputList[$y], $macroList[$y], $idList[$y], $dbh);
			}
			
			undef($fileList[$y]);
			undef($timeList[$y]);
			undef($outputList[$y]);
			undef($statusList[$y]);
			undef($macroList[$y]);
			$y++;
		    }
		} else {
		    if (defined($fileList[$y]) && length($fileList[$y])) {
			$showAlert = saveAlarmInCache($showAlert, time(), $y);
		    }
		}
	    }
	} else {
	    $y = 0;
	    foreach my $time (@timeList) {
		if (defined($macroCache{$data->{'host_name'}.";".$idList[$y]})) {
		    my $slot_service = get_slot($data->{'host_name'}, $idList[$y], $dbh, $dbh2);
		    if ($slot_service ne "nil") {
			if (($status == 0 && $id ne "nil" && $FORCEFREE) || $status != 0) {
			    send_command($data->{'host_name'}, $slot_service, $statusList[$y], $timeList[$y], $outputList[$y], $macroList[$y], $idList[$y], $dbh);
			    $alreadySent = 1;
			    
			    # Free information
			    undef($fileList[$y]);
			    undef($timeList[$y]);
			    undef($outputList[$y]);
			    undef($statusList[$y]);
			    undef($macroList[$y]);
			}
		    }
		}
		$y++;
	    }
	}
	
	##############################################
	## Save Alarm in cache if they can not be send
	## to a slot (when all slot are busy). 
	#
	my $count = @timeList;
	
	$showAlert = 0;
	if ($count) {
	    my $y = 0;
	    foreach my $str (@timeList) { 
		if (length($str)) {
		    $host_name = $data->{'host_name'};
		    $showAlert = saveAlarmInCache($showAlert, $str, $y);
		    
		    # Free information
		    undef($fileList[$y]);
		    undef($timeList[$y]);
		    undef($outputList[$y]);
		    undef($statusList[$y]);
		    undef($macroList[$y]);
		}
		$y++;
	    }
	}
    }
}
exit();

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
		    if (!-e $LOCKDIR."/".$d->{'hostname'}.";".$d->{'description'}.".lock") {
			updateMacro($d->{'hostname'}, $d->{'description'}, $poller->{'localhost'}, $d->{'macro'}, "empty", time(), $poller->{'id'});
		    }
		}
	    }
	}
    }
    
    #################################
    # Build Macro Cache
    %macroCache = buildMacroCache($dbh, $dbh2)
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
                    #writeLogFile("CACHE LINK: ".$d->{'hostname'}.";".$d->{'value'}, "DD") if ($debug);
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
    return DBI->connect("dbi:mysql:".$mysql_database_oreon.";host=".$mysql_host, $mysql_user, $mysql_passwd) or MyDie "DB Connection Error: $mysql_database_oreon => $! \n";
}

#############################################
## Connect to storage database
#
sub storageDBConnect() {
    my $dbh2 = DBI->connect("dbi:mysql:".$mysql_database_ods.";host=".$mysql_host, $mysql_user, $mysql_passwd) or die "Storage DB connection impossible : $mysql_database_oreon => $DBI::errstr \n";
    return($dbh2);
}

#############################################
## Connect to NDO database
#    Get into cfg_ndo2db table for getting 
#    NDO connect info.
sub ndoDBConnect() {
    my $ndo_conf = ndoInfo();
    
    my $dbh2 = DBI->connect("dbi:mysql:".$ndo_conf->{'db_name'}.";host=".$ndo_conf->{'db_host'}, $ndo_conf->{'db_user'}, $ndo_conf->{'db_pass'}) 
	or MyDie("NDO DB Connection Error: $mysql_database_oreon => $DBI::errstr \n");
    undef($ndo_conf);
    return($dbh2);
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
    
    my $sth2 = $dbh->prepare("SELECT host_name FROM host WHERE (host_address LIKE '" . $hostname . "' OR host_name LIKE '" . $hostname . "')");
    if (!defined($sth2)) {
	writelogFile($DBI::errstr, "EE");
    } else {
	if ($sth2->execute()){
	    my $hostDataTemp = $sth2->fetchrow_hashref();
	    writeLogFile("Real hostname = " . $hostDataTemp->{'host_name'}, "DD");
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
    
    my $sth2 = $dbh->prepare("SELECT pool_prefix FROM mod_dsm_pool mdp, host h WHERE mdp.pool_host_id = h.host_id AND ( h.host_name LIKE '" . $hostname . "' OR h.host_address LIKE '" . $hostname . "')");
    if (!defined($sth2)) {
	writeLogFile($DBI::errstr, "EE");
    }
    if ($sth2->execute()){
	$pool_prefix = $sth2->fetchrow_hashref()->{'pool_prefix'};
    } else {
	writeLogFile("Can get DSM informations $!", "EE");
	exit(1);
    }
    undef($sth2);
    writeLogFile("Trap pool prefix = " . $pool_prefix, "DD");
    return($pool_prefix);
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
    if ($debug eq 0 && $lvl eq "DD") {
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

    print LOG ($year+1900) . "-" . $mon . "-" . $mday . " $hour:$min:$sec - [" . $lvl . "] " . $msg . "\n";
    close LOG or warn $!;
}

###########################################
# help
sub help {
    print <<EOF;
Usage: snmpTrapDyn [-h] -H hostname -t time [-a action] [-i id] [-s status] output
    -a|--action	The action id, the action is configured in config file
    -H|--host	The hostame
    -i|--id	The trap id
    -t|--time	The time of the trap
    -s|--status	The nagios status (0 - Ok, 1 - Warning, 2 - Critical, 3 - Unknown)
    -m|--macros The liste of Macros that you like to store in custom macro separated by "|"
    -h|--help	Help
EOF
    exit(0);
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
sub get_slot {
    my ($host, $id, $dbh, $dbh2) = @_;
    my $service_id = "nil";
    my $count_services;
    my @list_services;
    my $query_get;

    if ($DBType == 1) {
	$query_get = "SELECT services.service_id AS varvalue FROM customvariables, hosts, services WHERE hosts.host_id = services.host_id AND hosts.host_id = customvariables.host_id AND services.service_id = customvariables.service_id AND (hosts.name LIKE '$host' OR hosts.address LIKE '$host') AND customvariables.name LIKE '" . $MACRO_ID_NAME . "' AND value = '" . $id . "' LIMIT 1";
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
	    "WHERE hsr.host_host_id = h.host_id AND (h.host_name = '" . $host . "' OR h.host_address = '" . $host . "') AND h.host_register = '1' AND hsr.service_service_id = s.service_id AND hsr.service_service_id IN (" . join(",", @list_services) . ")";
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

    sendExternalCommad($data_poller->{'id'}, $externalCMD);

    writeLock($host_name, $service, $id);

    if ($FORCEFREE && $status == 0) {
	$externalCMD = "[$timeRequest] SCHEDULE_FORCED_SVC_CHECK;$host_name;$service;$timeRequest";
	sendExternalCommad($data_poller->{'id'}, $externalCMD);

	writeLogFile("Force free the following slot: $host_name;$service", "DD");
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
}

##########################################################
## Send external command to Centcore
#
sub sendExternalCommad($$) {
    my ($id, $command) = @_;
    
    my $externalCMD = "EXTERNALCMD:".$id.":".$command;

    writeLogFile("Send external command : $externalCMD");    
    if (system("echo \"$externalCMD\" >> $CECORECMD")) {
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

    my $externalCMD = "[".$time."] CHANGE_CUSTOM_SVC_VAR;".$host.";".$service.";".$macro.";".$var;

    if ($localhost == 0) {
	my $externalCMD = "EXTERNALCMD:".$poller.":".$externalCMD;
	writeLogFile("Send external command : $externalCMD");
	if (system("echo '$externalCMD' >> $CECORECMD")) {
	    writeLogFile("Cannot Write external command for centcore", 'II');
	}
    } else {
	writeLogFile("Send external command in local poller : $externalCMD");
	if (system("echo '$externalCMD' >> $NAGIOSCMD")) {
	    writeLogFile("Cannot Write external command for local nagios", 'II');
	}
    }
}

##################################################
## Write Locks
# in order to lock slot during the first minute in order
# to manage the refresh time of Nagios (UI and External Cmd)
#
sub writeLock($$$) {
    my ($host_name, $service, $id) = @_;

    if (-f $LOCKDIR.$host_name.";".$service.".lock") {
	unlink($LOCKDIR.$host_name.";".$service.".lock");
    }
    
    writeLogFile("Write lock file: ".$LOCKDIR.$host_name.";".$service.".lock", "DD");

    open (LOCK, ">> ".$LOCKDIR.$host_name.";".$service.".lock") || print "can't write $LOCKDIR.$host_name;$service.lock: $!";
    print LOCK trim($host_name)."\n";
    print LOCK trim($service)."\n";
    print LOCK trim($id)."\n";
    close LOCK;
}

##################################################
## Save all non-managed alarm into a cache file.
#
sub saveAlarmInCache($$$) {
    my ($showAlert, $str, $y) = @_;

    if ($showAlert == 0) {
	writeLogFile("Slot system busy... all slots are already in use...");
	writeLogFile("Add alert in cache...");
	$showAlert++;
    }
    my $CACHEFILE = $CACHEDIR.$host_name.'-'.$str.".cache";
    if (-e $CACHEFILE) {
	my $i = 0;
	while (-e $CACHEFILE."-".$i) {
	    $i++;
	}
	$CACHEFILE .= "-".$i;
    }   

    open (CACHE, ">> ".$CACHEFILE) || print "can't write $LOG: $!";
    print CACHE trim($timeList[$y])."\n";
    print CACHE trim($statusList[$y])."\n";
    print CACHE trim($macroList[$y])."\n";
    print CACHE trim($idList[$y])."\n";
    print CACHE trim($outputList[$y]);
    close CACHE;

    writeLogFile("Write Cache file: $CACHEFILE", "DD");

    return($showAlert);
}

###################################################
## Purge old Lock files
#
sub purgeLockFiles() {
    my @fileList = glob($LOCKDIR."/*");
    foreach (@fileList) {
	my ($dev,$ino,$mode,$nlink,$uid,$gid,$rdev,$size,$atime,$mtime,$ctime,$blksize,$blocks) = lstat($_);
	if (time() - $mtime > $MAXDATAAGE) {
	    writeLogFile("remove old lock file: ".$_." (normal behavior)");
	    unlink($_);
	}
    }
    undef(@fileList);
}

#################################################
## Get Free Slot list
#
sub getFreeSlot() {
    my $request = "";
    if ($DBType == 1) {
	$request = "SELECT hosts.name AS host_name, services.description AS service_description FROM hosts, services WHERE hosts.host_id = services.host_id AND hosts.name LIKE '" . $host_name . "' AND services.state IN ('0', '4') AND description LIKE '" . $pool_prefix . "%' AND services.enabled = 1 ";
    } else {
	$request = "SELECT no.name1 AS host_name, no.name2 AS service_description ".
	    "FROM ".$ndo_conf->{'db_prefix'}."servicestatus nss , ".$ndo_conf->{'db_prefix'}."objects no, ".$ndo_conf->{'db_prefix'}."services ns ".
	    "WHERE no.object_id = nss.service_object_id AND no.name1 like '" . $host_name . "' AND no.object_id = ns.service_object_id ".
	    "AND nss.current_state = 0 AND no.name2 LIKE '" . $pool_prefix . "%' ORDER BY name2";
    }
    #writeLogFile($request);
    my $sth2 = $dbh2->prepare($request);
    if (!defined($sth2)) {
	writeLogFile($DBI::errstr, "EE");
	exit(1);
    }
    if (!$sth2->execute()){
	writeLogFile("Error when getting perfdata file : " . $sth2->errstr . "", "EE");
	exit(1);
    }
    
    my $data;
    my $i;
    for ($i = 0;$data = $sth2->fetchrow_hashref();$i++) {
	$slotList[$i] = $data->{'host_name'}."\;".$data->{'service_description'};
    }
    undef($data);
}

__END__
