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

package gorgone::modules::centreon::mbi::libs::bi::BIMetric;

# Constructor
# parameters:
# $logger: instance of class CentreonLogger
# $centreon: Instance of centreonDB class for connection to Centreon database
# $centstorage: (optionnal) Instance of centreonDB class for connection to Centstorage database
sub new {
	my $class = shift;
	my $self  = {};

	$self->{logger}	= shift;
	$self->{centstorage} = shift;
	if (@_) {
		$self->{centreon}  = shift;
	}
	$self->{today_table} = "mod_bi_tmp_today_servicemetrics";
	$self->{tmpTable} = "mod_bi_tmp_servicemetrics";
	$self->{CRC32} = "mod_bi_tmp_servicemetrics_crc32";
	$self->{table} = "mod_bi_servicemetrics";
	
	bless $self, $class;
	return $self;
}

sub insert {
	my $self = shift;
	my $db = $self->{centstorage};

	$self->insertMetricsIntoTable("mod_bi_servicemetrics");
	$self->createTodayTable("false");
	my $query = "INSERT INTO ".$self->{today_table}. " (id, metric_id, metric_name, sc_id,hg_id,hc_id)";
	$query .= " SELECT id, metric_id, metric_name,sc_id,hg_id,hc_id FROM " . $self->{table} . " ";
	$db->query($query);
}

sub update {
	my ($self,$useMemory) = @_;

	my $db = $self->{centstorage};
	
	$self->createTempTable($useMemory);
    $self->insertMetricsIntoTable($self->{tmpTable});
	$self->createCRC32Table();
	$self->insertNewEntries();
	$self->createCRC32Table();
    $self->createTodayTable("false");
	$self->insertTodayEntries();
	$db->query("DROP TABLE `".$self->{"tmpTable"}."`");
	$db->query("DROP TABLE `".$self->{"CRC32"}."`");
}

sub insertMetricsIntoTable {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my $table = shift;
	my $query = "INSERT INTO `".$table."` (`metric_id`, `metric_name`, `metric_unit`, `service_id`, `service_description`,";
	$query .= " `sc_id`, `sc_name`, `host_id`, `host_name`, `hc_id`, `hc_name`, `hg_id`, `hg_name`)";
	$query .= " SELECT `metric_id`, `metric_name`, `unit_name`, s.`service_id`, s.`service_description`, ";
	$query .= " s.`sc_id`, s.`sc_name`, s.`host_id`, s.`host_name`, `hc_id`, `hc_name`, `hg_id`, `hg_name`";
	$query .= " FROM `mod_bi_tmp_today_services` s, `metrics` m, `index_data` i";
	$query .= " WHERE i.id = m.index_id and i.host_id=s.host_id and i.service_id=s.service_id";
	$query .= " group by s.hg_id, s.hc_id, s.sc_id, m.index_id, m.metric_id";
	my $sth = $db->query($query);
	return $sth;
}

sub createTempTable {
	my ($self, $useMemory) = @_;

	my $db = $self->{"centstorage"};
	$db->query("DROP TABLE IF EXISTS `".$self->{"tmpTable"}."`");
	my $query = "CREATE TABLE `".$self->{"tmpTable"}."` (";
	$query .= "`metric_id` int(11) NOT NULL,`metric_name` varchar(255) NOT NULL,`metric_unit` char(10) DEFAULT NULL,";
	$query .= "`service_id` int(11) NOT NULL,`service_description` varchar(255) DEFAULT NULL,";
	$query .= "`sc_id` int(11) DEFAULT NULL,`sc_name` varchar(255) DEFAULT NULL,";
	$query .= "`host_id` int(11) DEFAULT NULL,`host_name` varchar(255) DEFAULT NULL,";
	$query .= "`hc_id` int(11) DEFAULT NULL,`hc_name` varchar(255) DEFAULT NULL,";
	$query .= "`hg_id` int(11) DEFAULT NULL,`hg_name` varchar(255) DEFAULT NULL";
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
	my $query = "CREATE TABLE `".$self->{"CRC32"}."`  CHARSET=utf8 COLLATE=utf8_general_ci";
	$query .= " SELECT `id`, CRC32(CONCAT_WS('-', COALESCE(metric_id, '?'),";
	$query .= " COALESCE(service_id, '?'),COALESCE(service_description, '?'),";
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
	my $fields = "metric_id, metric_name, metric_unit, service_id, service_description, host_name, host_id, sc_id, sc_name, hc_id, hc_name, hg_id, hg_name";
	my $tmpTableFields = "tmpTable.metric_id, tmpTable.metric_name,tmpTable.metric_unit,";
	$tmpTableFields .= " tmpTable.service_id, tmpTable.service_description, tmpTable.host_name, tmpTable.host_id, tmpTable.sc_id,";
	$tmpTableFields .= "tmpTable.sc_name, tmpTable.hc_id, tmpTable.hc_name, tmpTable.hg_id, tmpTable.hg_name";
	my $query = "  INSERT INTO `".$self->{"table"}."` (".$fields.") ";
	$query .= " SELECT ".$tmpTableFields." FROM ".$self->{"tmpTable"}." as tmpTable";
	$query .= " LEFT JOIN (".$self->{"CRC32"}. " INNER JOIN ".$self->{"table"}." as finalTable using (id))";
	$query .= " ON CRC32(CONCAT_WS('-', COALESCE(tmpTable.metric_id, '?'), COALESCE(tmpTable.service_id, '?'),COALESCE(tmpTable.service_description, '?'),";
	$query .= " COALESCE(tmpTable.host_id, '?'),COALESCE(tmpTable.host_name, '?'), COALESCE(tmpTable.sc_id, '?'),COALESCE(tmpTable.sc_name, '?'),";
	$query .= " COALESCE(tmpTable.hc_id, '?'),COALESCE(tmpTable.hc_name, '?'), COALESCE(tmpTable.hg_id, '?'),COALESCE(tmpTable.hg_name, '?'))) = mycrc";
	$query .= " AND tmpTable.metric_id=finalTable.metric_id";
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
	my $query = "CREATE TABLE `" . $self->{"today_table"} . "` (";
	$query .= "`id` INT NOT NULL,";
	$query .= "`metric_id` int(11) NOT NULL,";
	$query .= "`metric_name` varchar(255) NOT NULL,";
	$query .= "`sc_id` int(11) NOT NULL,";
	$query .= "`hg_id` int(11) NOT NULL,";
	$query .= "`hc_id` int(11) NOT NULL,";
	$query .= " KEY `metric_id` (`metric_id`),";
	$query .= " KEY `schghc_id` (`sc_id`,`hg_id`,`hc_id`)";
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
	my $query = "INSERT INTO ".$self->{"today_table"}. " (id, metric_id, metric_name, sc_id,hg_id,hc_id)";
	$query .= " SELECT finalTable.id, finalTable.metric_id, finalTable.metric_name, finalTable.sc_id, finalTable.hg_id, finalTable.hc_id FROM ".$self->{"tmpTable"}." t";
	$query .= " LEFT JOIN (".$self->{"CRC32"}." INNER JOIN ".$self->{"table"}." finalTable USING (id))";
	$query .= " ON CRC32(CONCAT_WS('-',  COALESCE(t.metric_id, '?'), COALESCE(t.service_id, '?'),COALESCE(t.service_description, '?'),";
	$query .= " COALESCE(t.host_id, '?'),COALESCE(t.host_name, '?'), COALESCE(t.sc_id, '?'),COALESCE(t.sc_name, '?'),";
	$query .= " COALESCE(t.hc_id, '?'),COALESCE(t.hc_name, '?'), COALESCE(t.hg_id, '?'),COALESCE(t.hg_name, '?'))) = mycrc";
	$query .= " AND finalTable.metric_id=t.metric_id";
	$query .= " AND finalTable.service_id=t.service_id AND finalTable.service_description=t.service_description ";
	$query .= " AND finalTable.host_id=t.host_id AND finalTable.host_name=t.host_name ";
	$query .= " AND finalTable.sc_id=t.sc_id AND finalTable.sc_name=t.sc_name ";
	$query .= " AND finalTable.hc_id=t.hc_id AND finalTable.hc_name=t.hc_name ";
	$query .= " AND finalTable.hg_id=t.hg_id AND finalTable.hg_name=t.hg_name ";
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
