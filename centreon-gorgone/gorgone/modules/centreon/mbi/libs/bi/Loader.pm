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
use POSIX;

package gorgone::modules::centreon::mbi::libs::bi::Loader;

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
sub getStorageDir {
	my $self = shift;
	return $self->{'tempFolder'};
}
sub loadData {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my $logger =  $self->{"logger"};
	my ($tableName, $inFile) = (shift, shift);
	my $query = "LOAD DATA LOCAL INFILE '".$inFile."' INTO TABLE `".$tableName."` CHARACTER SET UTF8 IGNORE 1 LINES";
	my $sth = $db->query({ query => $query });
}
sub disableKeys {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my $tableName = shift;
	my $query = "ALTER TABLE `".$tableName."` DISABLE KEYS";
	my $sth = $db->query({ query => $query });
}

sub enableKeys {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my $tableName = shift;
	my $query = "ALTER TABLE `".$tableName."` ENABLE KEYS";
	my $sth = $db->query({ query => $query });
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
    }else {
    	$logger->writeLog("WARNING", "Cannot get structure for table : ".$tableName);
    	return (undef);
    }
    $sth->finish;
    return ($sql);
}

sub truncateTable {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my $tableName = shift;
	my $query = "TRUNCATE TABLE `".$tableName."`";
	my $sth = $db->query({ query => $query });
}
sub dropTable {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my $tableName = shift;
	my $query = "DROP TABLE IF EXISTS `".$tableName."`";
	my $sth = $db->query({ query => $query });
}

1;
