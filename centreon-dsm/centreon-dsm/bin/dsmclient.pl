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
use vars qw($MAXDATAAGE $EXCLUDESTR $MACRO_ID_NAME $FORCEFREE $USE_LONG_OPT);
use vars qw($dbh $dbh2 $debug $DEBUG_ENABLED);

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
# Set arguments
writeLogFile("=====================================================", "DD");
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
        exit(1);
    } 
} else {
    $hostname = $ARGV[0];
    $timeRequest = $ARGV[2];
    $status = $ARGV[1];
    $output = $ARGV[3];
    $macros = $ARGV[4];
}

#############################################
#############################################

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
            writeLogFile("Real hostname = " . $hostDataTemp->{'host_name'}, "DD");
            return($hostDataTemp->{'host_name'});
        } else {
            writeLogFile("Can get hosts Informations (name or address) $!", "EE");
            exit(1);
        }
    }
}

############################################
## Connect to storage MySQL Database.
#
sub MySQLConnectStorage() {
    $dbh2 = DBI->connect("dbi:mysql:".$mysql_database_ods.";host=".$mysql_host, $mysql_user, $mysql_passwd) 
        or MyDie("DB Connection Error: $mysql_database_ods => $! \n");
}

############################################
## Connect to Centreon MySQL Database.
#
sub MySQLConnect() {
    $dbh = DBI->connect("dbi:mysql:".$mysql_database_oreon.";host=".$mysql_host, $mysql_user, $mysql_passwd) 
        or MyDie("DB Connection Error: $mysql_database_oreon => $! \n");
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

    print LOG ($year+1900) . "-" . $mon . "-" . $mday . " $hour:$min:$sec - [" . $lvl . "] [client] " . $msg . "\n";
    close LOG or warn $!;
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

###############################################
###############################################

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
MySQLConnect();
MySQLConnectStorage();

#############################################
# Get host/address
my $host_name = getRealHostName($hostname);

if ($id ne 'nil') {   
    if (defined($host_name) && $host_name ne "") {
        my $request = "INSERT INTO mod_dsm_cache (cache_id, entry_time, host_name, ctime, status, macros, id, output, finished) ";
        $request .= " VALUES (NULL, '".time()."', '".$host_name."', '".$timeRequest."', '".$status."', '".$macros."', '".$id."', '".$output."', '0')";
        $dbh2->do($request);
        
        writeLogFile("Insert Alarm for host '".$host_name."' at '".$timeRequest."' with id '".$id."' and output '".$output."'.");
    }
} else {
    if (defined($host_name) && $host_name ne "") {
        my $request = "INSERT INTO mod_dsm_cache (cache_id, entry_time, host_name, ctime, status, macros, id, output, finished) ";
        $request .= " VALUES (NULL, '".time()."', '".$host_name."', '".$timeRequest."', '".$status."', NULL, NULL, '".$output."', '0')";
        $dbh2->do($request);
        
        writeLogFile("Insert Alarm for host '".$host_name."' at '".$timeRequest."' with output '".$output."'.");
    }
}

exit(1);
