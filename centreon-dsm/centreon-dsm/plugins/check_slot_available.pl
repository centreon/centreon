#! /usr/bin/perl
################################################################################
# Copyright 2005-2010 MERETHIS
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
# SVN : $URL:$
# SVN : $Id:$
#
####################################################################################

use strict;
use DBI;
use vars qw($mysql_database_oreon $mysql_database_ods $mysql_host $mysql_user $mysql_passwd $ndo_conf $LOG $NAGIOSCMD $CECORECMD $LOCKDIR $MAXDATAAGE $CACHEDIR $DBType);

require "@CENTREON_ETC@/conf.pm";

# Set arguments
my $host_name = $ARGV[0];
my $warning = 5;
if (defined($ARGV[1]) && $ARGV[1]) {
    $warning = $ARGV[1];
}
my $critical = 3;
if (defined($ARGV[2]) && $ARGV[2]) {
    $critical = $ARGV[2];
}

my $dbh = DBI->connect("dbi:mysql:".$mysql_database_oreon.";host=".$mysql_host, $mysql_user, $mysql_passwd) or die "Data base connexion impossible : $mysql_database_oreon => $! \n";

##################################################
# Get DB type NDO / Broker
# NDO => 0 ; Broker => 1
sub getDBType($) {
	my $dbh = $_[0];
	
	my $request = "SELECT * FROM options WHERE `key` LIKE 'Broker'";
	my $sth = $dbh->prepare($request);
    if (!defined($sth)) {
		writeLogFile($DBI::errstr, "EE");
		print $DBI::errstr; 
	} else {
		if (!$sth->execute()) {
			my $row = $sth->fetchrow_hashref();
			if ($row->{'value'} == 'ndo') {
				return 0;
			} else {
				return 1;
			}
		}
		return 1;
	}
}

# Get Broker type
$DBType = getDBType($dbh);

my $dbh2;
my $sth2;
if ($DBType == 1) {
	$dbh2 = DBI->connect("dbi:mysql:".$mysql_database_ods.";host=".$mysql_host, $mysql_user, $mysql_passwd) or die "Data base connexion impossible : $mysql_database_oreon => $! \n";
} else {
	# Connect to NDO databases
	$sth2 = $dbh->prepare("SELECT db_host,db_name,db_port,db_prefix,db_user,db_pass FROM cfg_ndo2db");
	if (!$sth2->execute) {
	    writeLogFile("Error when getting drop and perfdata properties : ".$sth2->errstr."");
	}
	$ndo_conf = $sth2->fetchrow_hashref();
	undef($sth2);
	
	$dbh2 = DBI->connect("dbi:mysql:".$ndo_conf->{'db_name'}.";host=".$ndo_conf->{'db_host'}, $ndo_conf->{'db_user'}, $ndo_conf->{'db_pass'});
	if (!defined($dbh2)) {
	    print "ERROR : ".$DBI::errstr."\n";
	    print "Data base connexion impossible : ".$ndo_conf->{'db_name'}." => $! \n";
	}
}

# Get module trap on this host
my $confDSM;
$sth2 = $dbh->prepare("SELECT pool_prefix FROM mod_dsm_pool mdp, host h WHERE mdp.pool_host_id = h.host_id AND h.host_name LIKE '".$ARGV[0]."'");
if (!defined($sth2)) {
    print "ERROR : ".$DBI::errstr."\n";
}
if ($sth2->execute()){
    $confDSM = $sth2->fetchrow_hashref();
} else {
    print "Can get DSM informations $!\n";
    exit(1);
}

# Get slot free
my $request;
if ($DBType == 1) {
	$request = "SELECT description FROM services, hosts WHERE hosts.host_id = services.host_id AND services.state IN ('0', '4') AND hosts.name LIKE '".$ARGV[0]."' AND services.description LIKE '".$confDSM->{'pool_prefix'}."%' AND services.enabled = '1' ORDER BY description";
} else {
	$request = "SELECT no.name1, no.name2 ".
	    "FROM ".$ndo_conf->{'db_prefix'}."servicestatus nss , ".$ndo_conf->{'db_prefix'}."objects no, ".$ndo_conf->{'db_prefix'}."services ns ".
	    "WHERE no.object_id = nss.service_object_id AND no.name1 like '".$ARGV[0]."' AND no.object_id = ns.service_object_id ".
	    "AND nss.current_state = 0 AND no.name2 LIKE '".$confDSM->{'pool_prefix'}."%' ORDER BY name2";
}

$sth2 = $dbh2->prepare($request);
if (!defined($sth2)) {
    print "ERROR : ".$DBI::errstr."\n";
}
if (!$sth2->execute()){
    print("Error when getting perfdata file : " . $sth2->errstr . "");
    return "";
}

# Sort Data
my $data;
my @slotList;
my $i;
for ($i = 0;$data = $sth2->fetchrow_hashref();$i++) {
#    print "SLOT : ".$data->{'name2'}."\n";
    ;
}
undef($data);

my $status = 0;
if ($i <= $warning) {
    $status = 1;
}
if ($i <= $critical) {
    $status = 2;
}
print "Free slots : $i |free_slot=$i\n";
exit($status);



