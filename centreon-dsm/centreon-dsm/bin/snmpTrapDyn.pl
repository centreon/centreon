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

use vars qw($mysql_database_oreon $mysql_database_ods $mysql_host $mysql_user $mysql_passwd $ndo_conf $LOG $NAGIOSCMD $DBType $CECORECMD $LOCKDIR $MAXDATAAGE $CACHEDIR $EXCLUDESTR $MACRO_ID_NAME $FORCEFREE @pattern_output @action_list @macroList @statusList @idList $debug $DBType $DEBUG_ENABLED $USE_LONG_OPT);

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

############################################
# log files management function
sub writeLogFile {
    my ($msg, $lvl) = @_;
    if (!defined($lvl)) {
	$lvl = 'II';
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
    my $dbh = $_[0];

    my $request = "SELECT `value` FROM options WHERE `key` = 'broker'";
    my $sth = $dbh->prepare($request);
    if (!defined($sth)) {
	writeLogFile($DBI::errstr, "EE");
    } else {
	if ($sth->execute()) {
	    my $row = $sth->fetchrow_hashref();
	    if (defined $row && $row->{'value'} eq 'broker') {
		writeLogFile("Broker mode: Centreon Broker") if ($debug);
		return 1;
	    } else {
		writeLogFile("Broker mode: NDO") if ($debug);
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

##########################################
# get a free slot for a host
sub get_free_slot {
    my ($host, $pool_prefix, $dbh, $dbh2, $ndo_conf) = @_;
    my $request;

    if ($DBType == 1) {
    	$request = "SELECT description FROM services, hosts WHERE hosts.host_id = services.host_id AND state IN ('0', '4') AND (hosts.name LIKE '$host' OR hosts.address LIKE '$host') ORDER BY description";
	writeLogFile("$request", "II");    
    } else {
	$request = "SELECT no.name1, no.name2 ".
	    "FROM ".$ndo_conf->{'db_prefix'}."servicestatus nss , ".$ndo_conf->{'db_prefix'}."objects no, ".$ndo_conf->{'db_prefix'}."services ns ".
	    "WHERE no.object_id = nss.service_object_id AND no.name1 like '" . $host . "' AND no.object_id = ns.service_object_id ".
	    "AND nss.current_state = 0 AND no.name2 LIKE '" . $pool_prefix . "%' ORDER BY name2";
    }
    writeLogFile("$request") if ($debug);
    my $sth2 = $dbh2->prepare($request);
    if (!defined($sth2)) {
	writeLogFile($DBI::errstr, "EE");
	exit(1);
    }
    if (!$sth2->execute()){
	writeLogFile("Error when getting info : " . $sth2->errstr . "", "EE");
	exit(1);
    }
    return 1;
}

#############################################
# send a command to the poller
sub send_command {
    my ($host_name, $service, $status, $timeRequest, $output, $macros, $id, $dbh) = @_;

    my $externalCMD;

    my $host_id = getHostID($host_name, $dbh);
    my $data_poller = getHostPoller($host_id, $dbh);

    my $externalMacro = "";
    if (defined($MACRO_ID_NAME) && $MACRO_ID_NAME ne "nil" && $id ne "nil") {
	if ($status == 0) {
	    $id = "empty";
	}
	updateMacro($host_name, $service, $data_poller->{'localhost'}, $MACRO_ID_NAME, $id, $timeRequest);
    }

    # Prepare to send update of Service
    $output =~ s/\'//g;
    $externalCMD = "[$timeRequest] PROCESS_SERVICE_CHECK_RESULT;$host_name;$service;$status;$output";

    sendExternalCommad($data_poller->{'id'}, $externalCMD);

    if ($FORCEFREE && $status == 0) {
	$externalCMD = "[$timeRequest] SCHEDULE_FORCED_SVC_CHECK;$host_name;$service;$timeRequest";
	sendExternalCommad($data_poller->{'id'}, $externalCMD);
    }

    my @tab = split(/\|/, $macros);
    foreach my $string (@tab) {
	my @tab2 = split(/\=/, $string);
	if ($FORCEFREE && $status == 0) {
	    $tab2[1] = "empty";
	}
	updateMacro($host_name, $service, $data_poller->{'localhost'}, $tab2[0], $tab2[1], $timeRequest);
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
        writeLogFile("Cannot Write external command for centcore");
	return 0;
    }
    return 1;
}

##########################################################
# Declare functions
# host / service / localhost / macro / var / time / $poller
sub updateMacro($$$$$$) {
    my $externalCMD = "[".$_[5]."] CHANGE_CUSTOM_SVC_VAR;".$_[0].";".$_[1].";".$_[3].";".$_[4];

    if ($_[2] == 0) {
	my $externalCMD = "EXTERNALCMD:".$_[6].":".$externalCMD;
	writeLogFile("Send external command : $externalCMD");
	if (system("echo '$externalCMD' >> $CECORECMD")) {
	    writeLogFile("Cannot Write external command for centcore");
	}
    } else {
	writeLogFile("Send external command in local poller : $externalCMD");
	if (system("echo '$externalCMD' >> $NAGIOSCMD")) {
	    writeLogFile("Cannot Write external command for local nagios");
	}
    }
}



##########################################
### DEFAULT ACTION
sub action_host_rename  {
    my $hostname = shift;
    my $pattern = shift;
    my @arg = @_;

    my $output = $pattern;
    while ($pattern =~ /%%(\d+)%%/g) {
	if (defined($arg[$1 - 1])) {
	    my $ss = $arg[$1 - 1];
	    $output =~ s/$&/$ss/g;
	} else {
	    writeLogFile("Missing argument $1", "EE");
	    exit 1;
	}
    }
    return $output;
}

############################################
# Set arguments
writeLogFile("Command args @ARGV", "DD");

## Get help
my $action = "nil";
my $hostname = "nil";
my $id = "nil";
my $timeRequest = "nil";
my $status = -1;
my $output = "";
my $macros = "";
if ($longopt) {
   my $result = GetOptions("action=i" => \$action,
			   "Host=s" => \$hostname,
			   "output=s" => \$output,
			   "id=s" => \$id,
			   "time=s" => \$timeRequest,
			   "status=i" => \$status,
			   "macros=s" => \$macros,
			   "help" => \&help);
   
   if ($hostname eq "nil" || $id eq "nil" || $timeRequest eq "nil") {
       writeLogFile("An option isn't set", "II");
       $longopt = 0;
   } else {
       if ($action ne "nil" && $output eq "") {
	   writeLogFile("Action num : " . $action, "DD");
	   my @opt_args = @ARGV;
	   # Generate output
	   if (!defined($pattern_output[$action])) {
	       $output = join(' ', @opt_args);
	   } else {
	       writeLogFile("Pattern Output : " . $pattern_output[$action], "DD");
	       $output = $pattern_output[$action];
	       while ($pattern_output[$action] =~ /%%(\d+)%%/g) {
		   if (defined($opt_args[$1 - 1])) {
		       my $ss = $opt_args[$1 - 1];
		       $output =~ s/$&/$ss/g;
		   } else {
		       writeLogFile("Missing argument $1", "WW");
		       $output =~ s/$&/ /g;
		   }
	       }
	   }
       }
       if ($action ne "nil") {
	   if (defined($action_list[$action]->{'host'})) {
	       no strict 'refs';
	       my $action_run = 'action_host_' . $action_list[$action]->{'host'}->{'run'};
	       writeLogFile("Action call : " . $action_run, "DD");
	       $hostname = &$action_run($hostname, $action_list[$action]->{'host'}->{'pattern'}, @ARGV);
	   }
       }
   }
} else {
    $hostname = $ARGV[0];
    $timeRequest = $ARGV[2];
    $status = $ARGV[1];
    $output = $ARGV[3];
    $macros = $ARGV[4];
}

#############################################
# Exclude some hosts in exclude list
foreach (split(',', $EXCLUDESTR)) {
    if ($output =~ /$_/) {
        writeLogFile("Exclusion case found ($_)");
        writeLogFile("Alerte for host $hostname not accepted (output : $output)");
        exit(1);
    }
}

#############################################
# Connect to Centreon Database
my $dbh = DBI->connect("dbi:mysql:".$mysql_database_oreon.";host=".$mysql_host, $mysql_user, $mysql_passwd) or die "Data base connexion impossible : $mysql_database_oreon => $! \n";

#############################################
# Define DBTyper
$DBType = getDBType($dbh);

#############################################
# Get host/address
my $host_name;
my $sth2 = $dbh->prepare("SELECT host_name FROM host WHERE (host_address LIKE '" . $hostname . "' OR host_name LIKE '" . $hostname . "')");
if (!defined($sth2)) {
    writelogFile($DBI::errstr, "EE");
}
if ($sth2->execute()){
    my $hostDataTemp = $sth2->fetchrow_hashref();
    $host_name = $hostDataTemp->{'host_name'};
} else {
    writeLogFile("Can get hosts Informations $!", "EE");
    exit(1);
}
undef($sth2);
writeLogFile("Real hostname = " . $host_name, "DD");

# MySQL Broker Handle
my $dbh2;
my $pool_prefix;


#############################################
# Get module trap on this host

$sth2 = $dbh->prepare("SELECT pool_prefix FROM mod_dsm_pool mdp, host h WHERE mdp.pool_host_id = h.host_id AND ( h.host_name LIKE '" . $hostname . "' OR h.host_address LIKE '" . $hostname . "')");
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

if ($DBType == 0) {
    #############################################
    # Connect to NDO databases
    $sth2 = $dbh->prepare("SELECT db_host,db_name,db_port,db_prefix,db_user,db_pass FROM cfg_ndo2db");
    if (!$sth2->execute) {
	writeLogFile("Error when getting drop and perfdata properties : ".$sth2->errstr."");
    }
    $ndo_conf = $sth2->fetchrow_hashref();
    undef($sth2);
    
    ############################################
    # get ndo configuration
    $dbh2 = DBI->connect("dbi:mysql:".$ndo_conf->{'db_name'}.";host=".$ndo_conf->{'db_host'}, $ndo_conf->{'db_user'}, $ndo_conf->{'db_pass'});
    if (!defined($dbh2)) {
	writeLogFile($DBI::errstr, "EE");
	writeLogFile("Data base connexion not possible : ".$ndo_conf->{'db_name'}." => $!", "EE");
    }
    
    if ($longopt && $id ne "nil")  {
	my $slot_service;
	$slot_service = get_slot($hostname, $id, $dbh, $dbh2);
	
	if ($slot_service ne "nil") {
	    if (($status == 0 && $id ne "nil" && $FORCEFREE) || $status != 0) {
		send_command($host_name, $slot_service, $status, $timeRequest, $output, $macros, $id, $dbh);
		exit(0);
	    }
	}
    }
} else {
    ############################################
    # get Broker configuration
    
    $dbh2 = DBI->connect("dbi:mysql:".$mysql_database_ods.";host=".$mysql_host, $mysql_user, $mysql_passwd) or die "Data base connexion impossible : $mysql_database_oreon => $! \n";
    if (!defined($dbh2)) {
	writeLogFile($DBI::errstr, "EE");
	writeLogFile("Data base connexion not possible : ".$mysql_database_ods." => $!", "EE");
    }
    if ($longopt && $id ne "nil")  {
	my $slot_service;
	$slot_service = get_slot($hostname, $id, $dbh, $dbh2);
	
	if ($slot_service ne "nil") {
	    if (($status == 0 && $id ne "nil" && $FORCEFREE) || $status != 0) {
		send_command($host_name, $slot_service, $status, $timeRequest, $output, $macros, $id, $dbh);
		exit(0);
	    }
	}
    }
}

############################################
# Get slot free
my $request = "";
if ($DBType == 1) {
    $request = "SELECT hosts.name AS host_name, services.description AS service_description FROM hosts, services WHERE hosts.host_id = services.host_id AND hosts.name LIKE '" . $host_name . "' AND services.state = 0 AND description LIKE '" . $pool_prefix . "%' AND services.enabled = 1 ";
} else {
    $request = "SELECT no.name1 AS host_name, no.name2 AS service_description ".
	"FROM ".$ndo_conf->{'db_prefix'}."servicestatus nss , ".$ndo_conf->{'db_prefix'}."objects no, ".$ndo_conf->{'db_prefix'}."services ns ".
	"WHERE no.object_id = nss.service_object_id AND no.name1 like '" . $host_name . "' AND no.object_id = ns.service_object_id ".
	"AND nss.current_state = 0 AND no.name2 LIKE '" . $pool_prefix . "%' ORDER BY name2";
}
my $sth2 = $dbh2->prepare($request);
if (!defined($sth2)) {
    writeLogFile($DBI::errstr, "EE");
    exit(1);
}
if (!$sth2->execute()){
    writeLogFile("Error when getting perfdata file : " . $sth2->errstr . "", "EE");
    exit(1);
}

############################################
# Sort Data
my $data;
my @slotList;
my $i;
for ($i = 0;$data = $sth2->fetchrow_hashref();$i++) {
    $slotList[$i] = $data->{'host_name'}."\;".$data->{'service_description'};
}
undef($data);

############################################
# Check Temporary lock files directory
if (!-d $LOCKDIR){ 
    writeLogFile("Cannot find temporary lock files directory. I create it : $LOCKDIR.");
    mkpath($LOCKDIR);
}

############################################
# Purge Slot locks
my @fileList = glob($LOCKDIR."/".$host_name."-*");
foreach (@fileList) {
    my ($dev,$ino,$mode,$nlink,$uid,$gid,$rdev,$size,$atime,$mtime,$ctime,$blksize,$blocks) = lstat($_);
    if (time() - $mtime > $MAXDATAAGE) {
	writeLogFile("remove old lock file: ".$_." (normal behavior)");
	unlink($_);
    }
}
undef(@fileList);

############################################
# Read cache of results
my @timeList;
my @outputList;
my $t = 0;
@fileList = glob($CACHEDIR."/".$host_name."-*");
foreach (@fileList) {
    if (open(FILE, $_)) {
	my $filename = $_;
	$fileList[$t] = $filename;
	my $i = 0;
	while (<FILE>) {
	    if ($i == 0) {
		$timeList[$t] = $_;
		$timeList[$t] =~ s/\n//g;
	    } elsif ($i == 1) {
		$statusList[$t] = $_;
	    } elsif ($i == 2) {
                $macroList[$t] = $_;
	    } elsif ($i == 3) {
                $idList[$t] = $_;
	    } else {
		if (defined($outputList[$t])) {
		    $outputList[$t] = $_; 
		} else {
		    $outputList[$t] .= $_; 
		}
	    }
	    $i++;
	}
	close FILE;
	writeLogFile("DELETE File : ".$filename);
	unlink($filename);
    }
    $t++;
}
undef(@fileList);

############################################
# Add Current entry in List
$timeList[$t] = $timeRequest;
$outputList[$t] = $output;
$statusList[$t] = $status;
$macroList[$t] = $macros;
$idList[$t] = $id;

############################################
# Send data to Nagios servers
my $y = 0;
foreach my $str (@slotList) {
    my @tab = split(";", $str);
    if (defined($timeList[$y])) {
	# Check if I can use this slot
	if (length($str) && !-e $LOCKDIR."$str.lock") {
	    my $time_now = $timeList[$y];
	    $output = $outputList[$y];
	    
	    # Check Lock Files
	    @fileList = glob($LOCKDIR."/".$host_name."*");
	    foreach (@fileList) {
		open(FILE, $_);
		my $a = 1;
		my $tmpName;
		my $tmpID;
		while (<FILE>) {
		    if ($a eq 2) {
			$tmpName = trim($_);
		    } elsif ($a eq 3) {
			$tmpID = trim($_);
		    }
		    $a++;
		    writeLogFile(" * line : $_") if ($debug);
		}
		close(FILE);
		
		if ($id =~ /$\$tmpID^/) {
		    # print "We find the same id into the data cache. We update the actual slot...\n";
		    $tab[1] = $tmpName;
		}
	    }
	    
	    # Build external command
	    if (($status == 0 && $id != "nil" && $FORCEFREE) || $status != 0) {
		# Write Tempory lock file
		open (CACHE, ">> ".$LOCKDIR.$tab[0].";".$tab[1].".lock") || print "can't write $LOCKDIR.$tab[0];$tab[1].lock: $!";
		print CACHE trim($host_name)."\n";
		print CACHE trim($tab[1])."\n";
		print CACHE trim($id)."\n";
		close CACHE;
		
		# Send Command
		send_command($host_name, $tab[1], $status, $timeRequest, $output, $macros, $id, $dbh);
	    }
	    
	    undef($fileList[$y]);
	    undef($timeList[$y]);
	    undef($outputList[$y]);
	    undef($statusList[$y]);
	    undef($macroList[$y]);
	    $y++;
	} else {
	    if (-e $LOCKDIR."-$str") {
		;#print "$str : already used !";
	    }
	}
    } else {
	if (defined($fileList[$y]) && length($fileList[$y])) {
	    
	    writeLogFile("Slot system busy... all slots are already in use...");
	    writeLogFile("Add alert in cache...");
	    
	    my $CACHEFILE = $CACHEDIR.$host_name.'-'.time().".cache";
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
	}
    }
}
my $count = @timeList;


if ($count) {
    my $y = 0;
    foreach my $str (@timeList) { 
	if (length($str)) {
	    ###########################################
	    # Put data in cache
	    writeLogFile("Slot system busy... all slots are already in use...");
	    writeLogFile("Add alert in cache...");
	    
	    # Check Temporary lock files directory
	    if (!-d $CACHEDIR){
		writeLogFile("Cannot find temporary cache files directory. I create it : $CACHEDIR.");
		mkpath($CACHEDIR);
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
	}
	$y++;
    }
} else {
    exit 0;
}


############################################
# Declare functions

sub getHostID($$) {
    my $con = $_[1];

    # Request
    my $sth2 = $con->prepare("SELECT `host_id` FROM `host` WHERE `host_name` = '".$_[0]."' AND `host_register` = '1'");
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

sub getHostPoller($$) {
    my $con = $_[1];
    my $sth2 = $con->prepare("SELECT ns.id, ns.localhost FROM nagios_server ns, ns_host_relation nsh WHERE nsh.host_host_id = '".$_[0]."' AND ns.id = nsh.nagios_server_id");
    if (!$sth2->execute) {
        writeLogFile("Error:" . $sth2->errstr . "\n");
    }
    my $data_poller = $sth2->fetchrow_hashref();
    undef($sth2);
    return $data_poller;
}

sub trim {
    my $s = shift();
    $s =~ s/^\n*|\n*$//g;
    return $s;
}
