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

package gorgone::modules::centreon::mbi::libs::bi::BIHost;

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
	$self->{"today_table"} = "mod_bi_tmp_today_hosts";
	$self->{"tmp_comp"} = "mod_bi_tmp_hosts";
	$self->{"tmp_comp_storage"} = "mod_bi_tmp_hosts_storage";
	$self->{"table"} = "mod_bi_hosts";
	bless $self, $class;
	return $self;
}

sub getHostsInfo {
	my $self = shift;
	my $db = $self->{"centstorage"};

	my $query = "SELECT `id`, `host_id`, `host_name`, `hc_id`, `hc_name`, `hg_id`, `hg_name`";
	$query .= " FROM `".$self->{"today_table"}."`";
	my $sth = $db->query($query);
	my %result = ();
	while (my $row = $sth->fetchrow_hashref()) {
		if (defined($result{$row->{'host_id'}})) {
			my $tab_ref = $result{$row->{'host_id'}};
			my @tab = @$tab_ref;
			push @tab , $row->{"host_id"}.";".$row->{"host_name"}.";".
									$row->{"hg_id"}.";".$row->{"hg_name"}.";".
									$row->{"hc_id"}.";".$row->{"hc_name"};
			$result{$row->{'host_id'}} = \@tab;
		}else {
			my @tab = ($row->{"host_id"}.";".$row->{"host_name"}.";".
									$row->{"hg_id"}.";".$row->{"hg_name"}.";".
									$row->{"hc_id"}.";".$row->{"hc_name"});
			$result{$row->{'host_id'}} = \@tab;
		}
	}
	$sth->finish();
	return (\%result);
}

sub insert {
	my $self = shift;
	my $data = shift;
	my $db = $self->{"centstorage"};
	$self->insertIntoTable("".$self->{"table"}."", $data);
	$self->createTempTodayTable("false");
	my $fields = "id, host_name, host_id, hc_id, hc_name, hg_id, hg_name";
	my $query = "INSERT INTO ".$self->{"today_table"}." (".$fields.")";
	$query .= " SELECT ".$fields." FROM ".$self->{"table"}." ";
	$db->query($query);
}

sub update {
	my ($self, $data, $useMemory) = @_;
	my $db = $self->{"centstorage"};
	my $logger =  $self->{"logger"};
			
	$self->createTempComparisonTable($useMemory);
    $self->insertIntoTable($self->{"tmp_comp"}, $data);
    $self->createTempStorageTable($useMemory);
	$self->joinNewAndCurrentEntries();
	$self->insertNewEntries();
	$db->query("DROP TABLE `".$self->{"tmp_comp_storage"}."`");
	$self->createTempTodayTable("false");
	$self->insertTodayEntries();
	$db->query("DROP TABLE `".$self->{"tmp_comp"}."`");
}

sub insertIntoTable {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my $logger =  $self->{"logger"};
	my $table = shift;
	my $data = shift;
	my $query = "INSERT INTO `".$table."`".
				" (`host_id`, `host_name`, `hg_id`, `hg_name`, `hc_id`, `hc_name`)".
				" VALUES (?,?,?,?,?,?)";
	my $sth = $db->prepare($query);	
	my $inst = $db->getInstance;
	$inst->begin_work;
	my $counter = 0;
	
	foreach (@$data) {
		my ($host_id, $host_name, $hg_id, $hg_name, $hc_id, $hc_name) = split(";", $_);
		$sth->bind_param(1, $host_id);
		$sth->bind_param(2, $host_name);
		$sth->bind_param(3, $hg_id);
		$sth->bind_param(4, $hg_name);
		$sth->bind_param(5, $hc_id);
		$sth->bind_param(6, $hc_name);
		$sth->execute;
		if (defined($inst->errstr)) {
	  		$logger->writeLog("FATAL", $self->{"table"}." insertion execute error : ".$inst->errstr);
		}
		if ($counter >= 1000) {
			$counter = 0;
			$inst->commit;
			if (defined($inst->errstr)) {
	  			$logger->writeLog("FATAL", $self->{"table"}." insertion commit error : ".$inst->errstr);
			}
			$inst->begin_work;
		}
		$counter++;
	}
	$inst->commit;
}

sub createTempComparisonTable {
	my ($self, $useMemory) = @_;
	my $db = $self->{"centstorage"};
	$db->query("DROP TABLE IF EXISTS `".$self->{"tmp_comp"}."`");
	my $query = "CREATE TABLE `".$self->{"tmp_comp"}."` (";
	$query .= "`host_id` int(11) NOT NULL,`host_name` varchar(255) NOT NULL,";
	$query .= "`hc_id` int(11) DEFAULT NULL, `hc_name` varchar(255) NOT NULL,";
	$query .= "`hg_id` int(11) DEFAULT NULL, `hg_name` varchar(255) NOT NULL";
	if (defined($useMemory) && $useMemory eq "true") {
		$query .= ") ENGINE=MEMORY DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";
	}else {
		$query .= ") ENGINE=INNODB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";
	}
	$db->query($query);
}

sub createTempStorageTable {
	my ($self,$useMemory) = @_;
	my $db = $self->{"centstorage"};
	
	$db->query("DROP TABLE IF EXISTS `".$self->{"tmp_comp_storage"}."`");
	my $query = "CREATE TABLE `".$self->{"tmp_comp_storage"}."` (";
	$query .= "`id` INT NOT NULL,";
	$query .= "`host_id` int(11) NOT NULL,`host_name` varchar(255) NOT NULL,";
	$query .= "`hc_id` int(11) DEFAULT NULL, `hc_name` varchar(255) NOT NULL,";
	$query .= "`hg_id` int(11) DEFAULT NULL, `hg_name` varchar(255) NOT NULL,";
	$query .= " KEY `id` (`id`)";
	if (defined($useMemory) && $useMemory eq "true") {
		$query .= ") ENGINE=MEMORY DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";
	}else {
		$query .= ") ENGINE=INNODB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";
	}
	$db->query($query);
}

sub createTempTodayTable {
	my ($self,$useMemory) = @_;
	my $db = $self->{"centstorage"};
	
	$db->query("DROP TABLE IF EXISTS `".$self->{"today_table"}."`");
	my $query = "CREATE TABLE `".$self->{"today_table"}."` (";
	$query .= "`id` INT NOT NULL,";
	$query .= "`host_id` int(11) NOT NULL,`host_name` varchar(255) NOT NULL,";
	$query .= "`hc_id` int(11) DEFAULT NULL, `hc_name` varchar(255) NOT NULL,";
	$query .= "`hg_id` int(11) DEFAULT NULL, `hg_name` varchar(255) NOT NULL,";
	$query .= " KEY `id` (`host_id`)";
	if (defined($useMemory) && $useMemory eq "true") {
		$query .= ") ENGINE=MEMORY DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";
	}else {
		$query .= ") ENGINE=INNODB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";
	}
	$db->query($query);
}

sub joinNewAndCurrentEntries {
	my ($self) = @_;
	my $db = $self->{"centstorage"};
	
	my $query = "INSERT INTO ".$self->{"tmp_comp_storage"}. " (id, host_name, host_id, hc_id, hc_name, hg_id, hg_name)";
	$query .= " SELECT IFNULL(h.id, 0), t.host_name, t.host_id, t.hc_id, t.hc_name, t.hg_id, t.hg_name FROM ".$self->{"tmp_comp"}." t";
	$query .= " LEFT JOIN ".$self->{"table"}." h USING (host_name, host_id, hc_id, hc_name, hg_id, hg_name)";
	$db->query($query);
}

sub insertNewEntries {
	my ($self) = @_;
	my $db = $self->{"centstorage"};
	my $fields = "host_name, host_id, hc_id, hc_name, hg_id, hg_name";
	my $query = "  INSERT INTO `".$self->{"table"}."` (".$fields.") ";
	$query .= " SELECT ".$fields." FROM ".$self->{"tmp_comp_storage"};
	$query .= " WHERE id = 0";
	$db->query($query);
}

sub insertTodayEntries {
	my ($self) = @_;
	my $db = $self->{"centstorage"};
	my $fields = "host_name, host_id, hc_id, hc_name, hg_id, hg_name";
	my $query = "INSERT INTO ".$self->{"today_table"}." (id, host_name, host_id, hc_id, hc_name, hg_id, hg_name)";
	$query .= " SELECT h.id, t.host_name, t.host_id, t.hc_id, t.hc_name, t.hg_id, t.hg_name FROM ".$self->{"tmp_comp"}." t";
	$query .= " JOIN ".$self->{"table"}." h USING (host_name, host_id, hc_id, hc_name, hg_id, hg_name)";
	$db->query($query);
}

sub truncateTable {
	my $self = shift;
	my $db = $self->{"centstorage"};
	
	$db->query("TRUNCATE TABLE `".$self->{"table"}."`");
	$db->query("ALTER TABLE `".$self->{"table"}."` AUTO_INCREMENT=1");
}

1;
