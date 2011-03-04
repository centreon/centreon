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

use vars qw($mysql_database_oreon $mysql_database_ods $mysql_host $mysql_user $mysql_passwd $ndo_conf $LOG $NAGIOSCMD $CECORECMD $LOCKDIR $MAXDATAAGE $CACHEDIR $EXCLUDESTR $MACRO_ID_NAME $FORCEFREE @pattern_output @action_list @macroList @statusList @idList @slotList $debug);

$debug = 0;

############################################
# To the config file
require "@CENTREON_ETC@/conf.pm";
require "@CENTREON_ETC@/conf_dsm.pm";

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

##################################################
# get slot by host and id
sub get_slot {
    my ($host, $id, $dbh, $dbh2) = @_;
    my $service_id = "nil";
    my $count_services;
    my @list_services;

    my $query_get = "SELECT varvalue " .
	"FROM " . $ndo_conf->{'db_prefix'} . "customvariablestatus " .
	"WHERE varname = 'SERVICE_ID' AND object_id = (SELECT object_id " .
	"FROM " . $ndo_conf->{'db_prefix'} . "customvariablestatus " .
	"WHERE varname = '" . $MACRO_ID_NAME . "' AND varvalue = '" . $id . "' LIMIT 1)";
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
	    "WHERE hsr.host_host_id = h.host_id AND h.host_name = '" . $host . "' AND h.host_register = '1' AND hsr.service_service_id = s.service_id AND hsr.service_service_id IN (" . join(",", @list_services) . ")";
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

    my $request = "SELECT no.name1, no.name2 ".
	"FROM ".$ndo_conf->{'db_prefix'}."servicestatus nss , ".$ndo_conf->{'db_prefix'}."objects no, ".$ndo_conf->{'db_prefix'}."services ns ".
	"WHERE no.object_id = nss.service_object_id AND no.name1 like '" . $host . "' AND no.object_id = ns.service_object_id ".
	"AND nss.current_state = 0 AND no.name2 LIKE '" . $pool_prefix . "%' ORDER BY name2";
    my $sth2 = $dbh2->prepare($request);
    if (!defined($sth2)) {
	writeLogFile($DBI::errstr, "EE");
	exit(1);
    }
    if (!$sth2->execute()){
	writeLogFile("Error when getting perfdata file : " . $sth2->errstr . "", "EE");
	exit(1);
    }
    return 1;
}

#############################################
# send a command to the poller
sub send_command {
    my ($host_name, $service, $status, $timeRequest, $output, $macros, $id, $dbh) = @_;

    my $host_id = getHostID($host_name, $dbh);
    my $data_poller = getHostPoller($host_id, $dbh);

    my $externalMacro = "";
    my $sendMacro = 0;
    if (defined($MACRO_ID_NAME) && $MACRO_ID_NAME ne "nil") {
	if ($status == 0) {
	    $id = "empty";
	}
	$externalMacro = "[$timeRequest] CHANGE_CUSTOM_SVC_VAR;$host_name;$service;$MACRO_ID_NAME;$id";
	$sendMacro = 1;
    }
    
    if ($FORCEFREE && $status == 0) {
	$output = "Free slot";
    }

    $output =~ s/\'//g;
    my $externalCMD = "[$timeRequest] PROCESS_SERVICE_CHECK_RESULT;$host_name;$service;$status;$output";
    
    print $externalCMD . "\n";

    if ($data_poller->{'localhost'} == 0) {
	my $externalCMD = "EXTERNALCMD:".$data_poller->{'id'}.":".$externalCMD;
	writeLogFile("Send external command : $externalCMD");
	if (system("echo \"$externalCMD\" >> $CECORECMD")) {
	    writeLogFile("Cannot Write external command for centcore");
	}
	if ($sendMacro) {
	    writeLogFile("Send external command : $externalMacro");
	    if (system("echo \"$externalMacro\" >> $CECORECMD")) {
		writeLogFile("Cannot Write external command for centcore");
	    }
	}
    } else {
	writeLogFile("Send external command in local poller : $externalCMD");
	if (system("echo \"$externalCMD\" >> $NAGIOSCMD")) {
	    writeLogFile("Cannot Write external command for local nagios");
	}
	if ($sendMacro) {
	    writeLogFile("Send external command in local poller : $externalMacro");
	    if (system("echo \"$externalMacro\" >> $NAGIOSCMD")) {
		writeLogFile("Cannot Write external command for centcore");
	    }
	}
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
# Declare functions
# host / service / localhost / macro / var / time / $poller
sub updateMacro($$$$$$) {
    my $externalCMD = "[".$_[5]."] CHANGE_CUSTOM_SVC_VAR;".$_[0].";".$_[1].";".$_[3].";".$_[4];
    print $externalCMD . "\n";
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

## Get help
my $action = "nil";
my $host_name = "nil";
my $id = "nil";
my $timeRequest = "nil";
my $status = -1;
my $output = "";
my $macros = "";

my $host_name = '';
my $pool_prefix = "";

############################################
# Check Temporary lock files directory
if (!-d $LOCKDIR){ 
    writeLogFile("Cannot find temporary lock files directory. I create it : $LOCKDIR.");
    mkpath($LOCKDIR);
}

############################################
# Purge Slot locks
my @fileList = glob($LOCKDIR."/*");
foreach (@fileList) {
    my ($dev,$ino,$mode,$nlink,$uid,$gid,$rdev,$size,$atime,$mtime,$ctime,$blksize,$blocks) = lstat($_);
    if (time() - $mtime > $MAXDATAAGE) {
	writeLogFile("remove old lock file: ".$_." (normal behavior)");
	unlink($_);
    }
}
undef(@fileList);

#############################################
# Connect to Centreon databases
my $dbh = DBI->connect("dbi:mysql:".$mysql_database_oreon.";host=".$mysql_host, $mysql_user, $mysql_passwd) or die "Data base connexion impossible : $mysql_database_oreon => $! \n";

#############################################
# Connect to NDO databases
my $sth2 = $dbh->prepare("SELECT db_host,db_name,db_port,db_prefix,db_user,db_pass FROM cfg_ndo2db");
if (!$sth2->execute) {
    writeLogFile("Error when getting drop and perfdata properties : ".$sth2->errstr."");
}
$ndo_conf = $sth2->fetchrow_hashref();
undef($sth2);

############################################
# get ndo configuration
my $dbh2 = DBI->connect("dbi:mysql:".$ndo_conf->{'db_name'}.";host=".$ndo_conf->{'db_host'}, $ndo_conf->{'db_user'}, $ndo_conf->{'db_pass'});
if (!defined($dbh2)) {
    writeLogFile($DBI::errstr, "EE");
    writeLogFile("Data base connexion impossible : ".$ndo_conf->{'db_name'}." => $!", "EE");
}

############################################
# Get hosts List
$sth2 = $dbh->prepare("SELECT h.host_name, mdp.pool_prefix FROM mod_dsm_pool mdp, host h WHERE mdp.pool_host_id = h.host_id AND mdp.pool_activate = '1'");
if (!defined($sth2)) {
    print "ERROR : ".$DBI::errstr."\n";
}
if ($sth2->execute()){
    my $host;
    while ($host = $sth2->fetchrow_hashref()) {
	$host_name = $host->{'host_name'};
	$pool_prefix = $host->{'pool_prefix'};

	############################################
	# Get slot free
	my $request = "SELECT no.name1, no.name2 ".
	    "FROM ".$ndo_conf->{'db_prefix'}."servicestatus nss , ".$ndo_conf->{'db_prefix'}."objects no, ".$ndo_conf->{'db_prefix'}."services ns ".
	    "WHERE no.object_id = nss.service_object_id AND no.name1 like '" . $host_name . "' AND no.object_id = ns.service_object_id ".
	    "AND nss.current_state = 0 AND no.name2 LIKE '" . $pool_prefix . "%' ORDER BY name2";
	my $sth3 = $dbh2->prepare($request);
	if (!defined($sth3)) {
	    writeLogFile($DBI::errstr, "EE");
	    exit(1);
	}
	if (!$sth3->execute()){
	    writeLogFile("Error when getting perfdata file : " . $sth3->errstr . "", "EE");
	    exit(1);
	}
	############################################
	# Sort Data
	my $data;
	my @slotList;
	my $i;
	for ($i = 0;$data = $sth3->fetchrow_hashref();$i++) {
	    $slotList[$i] = $data->{'name2'};
	}
	undef($data);

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
			$timeRequest = $timeList[$t];
			print "TIME: ".trim($timeRequest)."\n";
		    } elsif ($i == 1) {
			$statusList[$t] = $_;
			print "STATUS: ".trim($_)."\n";
		    } elsif ($i == 2) {
			print "MACRO: ".trim($_)."\n";
			$macroList[$t] = $_;
		    } elsif ($i == 3) {
			print "ID: ".trim($_)."\n";
			$idList[$t] = $_;
		    } else {
			if (defined($outputList[$t])) {
			    $outputList[$t] = $_; 
			} else {
			    $outputList[$t] .= $_; 
			}
			print "OUTPUT: ".trim($outputList[$t])."\n";
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
	
	foreach my $str (@timeList) {
	    print $str . '\n';
	}

	############################################
	# Send data to Nagios serveurs
	my $y = 0;
	foreach my $str (@slotList) {
	    if (defined($timeList[$y])) {
		# Check if I can use this slot
		if (length($str) && !-e $LOCKDIR."$str.lock") {
		    my $time_now = $timeList[$y];

		    # Write Tempory lock
		    if (system("touch ".$LOCKDIR."$str.lock")) {
			writeLogFile("Cannot write lock file... Be carefull, some data can be loose.");
		    }
		    
		    # Build external command
		    send_command($host_name, $str, trim($statusList[$y]), trim($timeList[$y]), $outputList[$y], trim($macroList[$y]), trim($idList[$y]), $dbh);
		    
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
		if (length($str) && $timeList[$y] != "" && $statusList[$y] != "-1") {
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
    }
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
