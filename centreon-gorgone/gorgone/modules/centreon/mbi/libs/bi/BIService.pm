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

package gorgone::modules::centreon::mbi::libs::bi::BIService;

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
	$self->{"today_table"} = "mod_bi_tmp_today_services";
	$self->{"tmpTable"} = "mod_bi_tmp_services";
	$self->{"CRC32"} = "mod_bi_tmp_services_crc32";
	$self->{"table"} = "mod_bi_services";
	
	bless $self, $class;
	return $self;
}

sub insert {
	my $self = shift;
	my $data = shift;
	my $db = $self->{"centstorage"};
	$self->insertIntoTable($self->{"table"}, $data);
	$self->createTodayTable("false");
	my $fields = "id, service_id, service_description, host_name, host_id, sc_id, sc_name, hc_id, hc_name, hg_id, hg_name";
	my $query = "INSERT INTO ".$self->{"today_table"}. "(".$fields.")";
	$query .= " SELECT ".$fields." FROM ".$self->{"table"};
	$db->query($query);
}

sub update {
	my ($self, $data, $useMemory) = @_;
	my $db = $self->{"centstorage"};
			
	$self->createTempTable($useMemory);
    $self->insertIntoTable($self->{"tmpTable"}, $data);
    $self->createCRC32Table();
	$self->insertNewEntries();
	$self->createCRC32Table();
	$self->createTodayTable("false");
	$self->insertTodayEntries();
	$db->query("DROP TABLE `".$self->{"tmpTable"}."`");
	$db->query("DROP TABLE `".$self->{"CRC32"}."`");
}

sub insertIntoTable {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my $logger =  $self->{"logger"};
	my $table = shift;
	my $data = shift;
	my $name = shift;
	my $id = shift;
	my $query = "INSERT INTO `".$table."`".
				" (`service_id`, `service_description`, `sc_id`, `sc_name`,".
				" `host_id`, `host_name`,`hg_id`, `hg_name`, `hc_id`, `hc_name`)".
				" VALUES (?,?,?,?,?,?,?,?,?,?)";
	my $sth = $db->prepare($query);	
	my $inst = $db->getInstance;
	$inst->begin_work;
	my $counter = 0;
	
	foreach (@$data) {
		my ($service_id, $service_description, $sc_id, $sc_name, $host_id, $host_name, $hg_id, $hg_name, $hc_id, $hc_name) = split(";", $_);
		$sth->bind_param(1, $service_id);
		$sth->bind_param(2, $service_description);
		$sth->bind_param(3, $sc_id);
		$sth->bind_param(4, $sc_name);
		$sth->bind_param(5, $host_id);
		$sth->bind_param(6, $host_name);
		$sth->bind_param(7, $hg_id);
		$sth->bind_param(8, $hg_name);
		$sth->bind_param(9, $hc_id);
		$sth->bind_param(10, $hc_name);
		$sth->execute;
		if (defined($inst->errstr)) {
	  		$logger->writeLog("FATAL", $table." insertion execute error : ".$inst->errstr);
		}
		if ($counter >= 1000) {
			$counter = 0;
			$inst->commit;
			if (defined($inst->errstr)) {
	  			$logger->writeLog("FATAL", $table." insertion commit error : ".$inst->errstr);
			}
			$inst->begin_work;
		}
		$counter++;
	}
	$inst->commit;
}
sub createTempTable {
	my ($self, $useMemory) = @_;
	my $db = $self->{"centstorage"};
	$db->query("DROP TABLE IF EXISTS `".$self->{"tmpTable"}."`");
	my $query = "CREATE TABLE `".$self->{"tmpTable"}."` (";
	$query .= "`service_id` int(11) NOT NULL,`service_description` varchar(255) NOT NULL,";
	$query .= "`sc_id` int(11) NOT NULL,`sc_name` varchar(255) NOT NULL,";
	$query .= "`host_id` int(11) DEFAULT NULL,`host_name` varchar(255) NOT NULL,";
	$query .= "`hc_id` int(11) DEFAULT NULL,`hc_name` varchar(255) NOT NULL,";
	$query .= "`hg_id` int(11) DEFAULT NULL,`hg_name` varchar(255) NOT NULL";
	if (defined($useMemory) && $useMemory eq "true") {
		$query .= ") ENGINE=MEMORY DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";
	}else {
		$query .= ") ENGINE=INNODB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";
	}
	$db->query($query);
}

sub createCRC32Table {
	my ($self) = @_;
	my $db = $self->{"centstorage"};
	
	$db->query("DROP TABLE IF EXISTS `".$self->{"CRC32"}."`");
	my $query = "CREATE TABLE `".$self->{"CRC32"}."` CHARSET=utf8 COLLATE=utf8_general_ci";
	$query .= " SELECT `id`, CRC32(CONCAT_WS('-', COALESCE(service_id, '?'),COALESCE(service_description, '?'),";
	$query .= " COALESCE(host_id, '?'),COALESCE(host_name, '?'), COALESCE(sc_id, '?'),COALESCE(sc_name, '?'),";
	$query .= " COALESCE(hc_id, '?'),COALESCE(hc_name, '?'), COALESCE(hg_id, '?'),COALESCE(hg_name, '?'))) as mycrc";
	$query .= " FROM ".$self->{"table"};
	$db->query($query);
	$query = "ALTER TABLE `".$self->{"CRC32"}."` ADD INDEX (`mycrc`)";
	$db->query($query);
}

sub insertNewEntries {
	my ($self) = @_;
	my $db = $self->{"centstorage"};
	my $fields = "service_id, service_description, host_name, host_id, sc_id, sc_name, hc_id, hc_name, hg_id, hg_name";
	my $tmpTableFields = "tmpTable.service_id, tmpTable.service_description, tmpTable.host_name, tmpTable.host_id, tmpTable.sc_id,";
	$tmpTableFields .= "tmpTable.sc_name, tmpTable.hc_id, tmpTable.hc_name, tmpTable.hg_id, tmpTable.hg_name";
	my $query = "  INSERT INTO `".$self->{"table"}."` (".$fields.") ";
	$query .= " SELECT ".$tmpTableFields." FROM ".$self->{"tmpTable"}." as tmpTable";
	$query .= " LEFT JOIN (".$self->{"CRC32"}. " INNER JOIN ".$self->{"table"}." as finalTable using (id))";
	$query .= " ON CRC32(CONCAT_WS('-', COALESCE(tmpTable.service_id, '?'),COALESCE(tmpTable.service_description, '?'),";
	$query .= " COALESCE(tmpTable.host_id, '?'),COALESCE(tmpTable.host_name, '?'), COALESCE(tmpTable.sc_id, '?'),COALESCE(tmpTable.sc_name, '?'),";
	$query .= " COALESCE(tmpTable.hc_id, '?'),COALESCE(tmpTable.hc_name, '?'), COALESCE(tmpTable.hg_id, '?'),COALESCE(tmpTable.hg_name, '?'))) = mycrc";
	$query .= " AND tmpTable.service_id=finalTable.service_id AND tmpTable.service_description=finalTable.service_description";
	$query .= " AND tmpTable.host_id=finalTable.host_id AND tmpTable.host_name=finalTable.host_name";
	$query .= " AND tmpTable.sc_id=finalTable.sc_id AND tmpTable.sc_name=finalTable.sc_name";
	$query .= " AND tmpTable.hc_id=finalTable.hc_id AND tmpTable.hc_name=finalTable.hc_name";
	$query .= " AND tmpTable.hg_id=finalTable.hg_id AND tmpTable.hg_name=finalTable.hg_name";
	$query .= " WHERE finalTable.id is null";
	$db->query($query);
}

sub createTodayTable {
	my ($self,$useMemory) = @_;
	my $db = $self->{"centstorage"};
	
	$db->query("DROP TABLE IF EXISTS `".$self->{"today_table"}."`");
	my $query = "CREATE TABLE `".$self->{"today_table"}."` (";
	$query .= "`id` INT NOT NULL,";
	$query .= "`service_id` int(11) NOT NULL,`service_description` varchar(255) NOT NULL,";
	$query .= "`sc_id` int(11) NOT NULL,`sc_name` varchar(255) NOT NULL,";
	$query .= "`host_id` int(11) DEFAULT NULL,`host_name` varchar(255) NOT NULL,";
	$query .= "`hc_id` int(11) DEFAULT NULL,`hc_name` varchar(255) NOT NULL,";
	$query .= "`hg_id` int(11) DEFAULT NULL,`hg_name` varchar(255) NOT NULL,";
	$query .= " KEY `host_service` (`host_id`, `service_id`)";
	if (defined($useMemory) && $useMemory eq "true") {
		$query .= ") ENGINE=MEMORY DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";
	}else {
		$query .= ") ENGINE=INNODB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";
	}
	$db->query($query);
}

sub insertTodayEntries {
	my ($self) = @_;
	my $db = $self->{"centstorage"};
	my $query = "INSERT INTO ".$self->{"today_table"}. " (id, service_id, service_description, host_name, host_id, sc_id, sc_name, hc_id, hc_name, hg_id, hg_name)";
	$query .= " SELECT s.id, t.service_id, t.service_description, t.host_name, t.host_id, t.sc_id, t.sc_name, t.hc_id, t.hc_name, t.hg_id, t.hg_name FROM ".$self->{"tmpTable"}." t";
	$query .= " LEFT JOIN (".$self->{"CRC32"}." INNER JOIN ".$self->{"table"}." s USING (id))";
	$query .= " ON CRC32(CONCAT_WS('-', COALESCE(t.service_id, '?'),COALESCE(t.service_description, '?'),";
	$query .= " COALESCE(t.host_id, '?'),COALESCE(t.host_name, '?'), COALESCE(t.sc_id, '?'),COALESCE(t.sc_name, '?'),";
	$query .= " COALESCE(t.hc_id, '?'),COALESCE(t.hc_name, '?'), COALESCE(t.hg_id, '?'),COALESCE(t.hg_name, '?'))) = mycrc";
	$query .= " AND s.service_id=t.service_id AND s.service_description=t.service_description ";
	$query .= " AND s.host_id=t.host_id AND s.host_name=t.host_name ";
	$query .= " AND s.sc_id=t.sc_id AND s.sc_name=t.sc_name ";
	$query .= " AND s.hc_id=t.hc_id AND s.hc_name=t.hc_name ";
	$query .= " AND s.hg_id=t.hg_id AND s.hg_name=t.hg_name ";
	$db->query($query);
}

sub truncateTable {
	my $self = shift;
	my $db = $self->{"centstorage"};
	
	my $query = "TRUNCATE TABLE `".$self->{"table"}."`";
	$db->query($query);
	$db->query("ALTER TABLE `".$self->{"table"}."` AUTO_INCREMENT=1");
}

1;
