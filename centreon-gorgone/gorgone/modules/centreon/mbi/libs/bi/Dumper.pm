# 
# Copyright 2019 Centreon (http://www.centreon.com/)
#
# Centreon is a full-fledged industry-strength solution that meets
# the needs in IT infrastructure and application monitoring for
# service performance.
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
#     http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.
#

use strict;
use warnings;

package gorgone::modules::centreon::mbi::libs::bi::Dumper;

# Constructor
# parameters:
# $logger: instance of class CentreonLogger
# $centreon: Instance of centreonDB class for connection to Centreon database
# $centstorage: (optionnal) Instance of centreonDB class for connection to Centstorage database
sub new {
	my $class = shift;
	my $self  = {};
	$self->{"logger"}	= shift;
	$self->{"centstorage"} = shift;
	if (@_) {
		$self->{"centreon"}  = shift;
	}
	$self->{'tempFolder'} = "/tmp/";
	bless $self, $class;
	return $self;
}

sub setStorageDir {
	my $self = shift;
	my $logger = $self->{'logger'};
	my $tempFolder = shift;
	
	if (!defined($tempFolder)) {
		$logger->writeLog("ERROR", "Temporary storage folder is not defined");
	}
	if (! -d $tempFolder && ! -w $tempFolder) {
		$logger->writeLog("ERROR", "Cannot write into directory ".$tempFolder);
	}
	if ($tempFolder !~ /\/$/) {
		$tempFolder .= "/";
	}
	$self->{'tempFolder'} = $tempFolder;
}

# Dump data in a MySQL table. (db connection,table name, [not mandatory] start column, end column,start date,end date,exclude end time?)
# and return the file name created
# Ex $file = $dumper->dumpData($hostCentreon, 'toto', 'data_start', 'date_end', '2015-01-02', '2015-02-01', 0);
sub dumpData {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my ($hostCentreon, $tableName) = (shift, shift);
	my ($day,$month,$year,$hour,$min) = (localtime(time))[3,4,5,2,1];
	my $fileName = $self->{'tempFolder'}.$tableName;
	my $query = "SELECT * FROM ".$tableName." ";
	my $logger = $self->{'logger'};
	if (@_) {
	    my ($startColumn, $endColumn, $startTime, $endTime, $excludeEndTime) = @_;
	    $query .= " WHERE ".$startColumn." >= UNIX_TIMESTAMP('".$startTime."') ";
	    if ($excludeEndTime == 0) {
		$query .= "AND ".$endColumn." <= UNIX_TIMESTAMP('".$endTime."')";
	    }else {
		$query .= "AND ".$endColumn." < UNIX_TIMESTAMP('".$endTime."')";
	    }
	}
	my @loadCmdArgs = ('mysql', "-q", "-u", $hostCentreon->{'Censtorage_user'}, "-p".$hostCentreon->{'Censtorage_pass'},
						"-h", $hostCentreon->{'Censtorage_host'}, $hostCentreon->{'Censtorage_db'},
						"-e", $query.">".$fileName);
	system("mysql -q -u".$hostCentreon->{'Censtorage_user'}." -p".$hostCentreon->{'Censtorage_pass'}." -P".$hostCentreon->{'Censtorage_port'}." -h".$hostCentreon->{'Censtorage_host'}.
			" ".$hostCentreon->{'Censtorage_db'}." -e \"".$query."\" > ".$fileName);
	$logger->writeLog("DEBUG","mysql -q -u".$hostCentreon->{'Censtorage_user'}." -p".$hostCentreon->{'Censtorage_pass'}." -P".$hostCentreon->{'Censtorage_port'}." -h".$hostCentreon->{'Censtorage_host'}.
			" ".$hostCentreon->{'Censtorage_db'}." -e \"".$query."\" > ".$fileName);
	return ($fileName);
}

sub dumpRequest{
	my $self = shift;
	my $db = $self->{"centstorage"};
	my ($hostCentreon, $requestName,$query) = (shift, shift,shift);
	my $fileName = $self->{'tempFolder'}.$requestName;
	my $logger = $self->{'logger'};
	system("mysql -q -u".$hostCentreon->{'Censtorage_user'}." -p".$hostCentreon->{'Censtorage_pass'}." -h".$hostCentreon->{'Censtorage_host'}. " -P".$hostCentreon->{'Censtorage_port'}.
			" ".$hostCentreon->{'Censtorage_db'}." -e \"".$query."\" > ".$fileName);
	return ($fileName);
}

sub dumpTableStructure {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my $logger = $self->{'logger'};
	my ($tableName) = (shift);
	
	my $sql = "";
	my $sth = $db->query({ query => "SHOW CREATE TABLE ".$tableName });
    if (my $row = $sth->fetchrow_hashref()) {
	  $sql = $row->{'Create Table'};
	  $sql =~ s/(CONSTRAINT.*\n)//g;
      $sql =~ s/(\,\n\s+\))/\)/g;
    }else {
    	$logger->writeLog("WARNING", "Cannot get structure for table : ".$tableName);
    	return (undef);
    }
    $sth->finish;
    return ($sql);
}

sub insertData {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my $logger =  $self->{"logger"};
	
	my ($tableName, $inFile) = (shift, shift);
	my $query = "LOAD DATA INFILE '".$inFile."' INTO TABLE `".$tableName."`";
	my $sth = $db->query({ query => $query });
}

1;
