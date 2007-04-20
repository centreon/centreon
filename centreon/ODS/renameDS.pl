#! /usr/bin/perl -w 
###################################################################
# Oreon is developped with GPL Licence 2.0 
#
# GPL License: http://www.gnu.org/licenses/gpl.txt
#
# Developped by : Julien Mathis - jmathis@merethis.com
#
###################################################################
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# 
#    For information : contact@merethis.com
####################################################################
#
# Script init
#

use strict;
use DBI;
use RRDs;

#my $installedPath = "@OREON_PATH@/ODS/";
my $installedPath = "/srv/oreon/ODS/";

# Init Globals
use vars qw($len_storage_rrd $RRDdatabase_path $mysql_user $mysql_passwd $mysql_host $mysql_database_oreon $mysql_database_ods $LOG %status $con_ods $con_oreon $generalcounter);

# Init value
my ($file, $line, @line_tab, @data_service, $hostname, $service_desc, $metric_id, $configuration);

require $installedPath."etc/conf.pm";
require $installedPath."lib/misc.pm";

sub writeLogFile($){
	print  time()." - ".$_[0];
}

CheckMySQLConnexion();

my ($sth2, $data, $ERR);

$sth2 = $con_ods->prepare("SELECT * FROM config");
if (!$sth2->execute) {writeLogFile("Error when getting perfdata file : " . $sth2->errstr . "\n");}
$data = $sth2->fetchrow_hashref();
$RRDdatabase_path = $data->{'RRDdatabase_path'};
$len_storage_rrd = $data->{'len_storage_rrd'};


$sth2 = $con_ods->prepare("SELECT metric_id, metric_name FROM metrics ORDER BY metric_id");
if (!$sth2->execute) {writeLogFile("Error when getting metrics list : " . $sth2->errstr . "\n");}
my $t;
for ($t = 0;$data = $sth2->fetchrow_hashref();$t++){
	system("rrdtool tune ".$RRDdatabase_path.$data->{'metric_id'}.".rrd --data-source-rename metric:".$data->{'metric_name'});
}
undef($sth2);
undef($t);
undef($data);


