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

package gorgone::modules::centreon::mbi::libs::bi::LiveService;

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
	bless $self, $class;
	return $self;
}

sub getLiveServicesByName {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my $name = shift;
	my $interval = shift;
	my $query = "SELECT `id`, `name`";
	$query .= " FROM `mod_bi_liveservice`";
	$query .= " WHERE `name` like '".$name."%'";
	my $sth = $db->query({ query => $query });
	my %result = ();
	while (my $row = $sth->fetchrow_hashref()) {
		$result{ $row->{name} } = $row->{id};
	}
	return (\%result);
}

sub getLiveServicesByTpId {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my $name = shift;
	my $interval = shift;
	my $query = "SELECT `id`, `timeperiod_id`";
	$query .= " FROM `mod_bi_liveservice` ";
	my $sth = $db->query({ query => $query });
	my %result = ();
	while (my $row = $sth->fetchrow_hashref()) {
		$result{$row->{'timeperiod_id'}} = $row->{"id"};
	}
	return (\%result);
}

sub getLiveServicesByNameForTpId {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my $tpId = shift;
	my $query = "SELECT `id`, `name`";
	$query .= " FROM `mod_bi_liveservice` ";
	$query .= "WHERE timeperiod_id = ".$tpId;
	my $sth = $db->query({ query => $query });
	my ($name, $id);
	
	while (my $row = $sth->fetchrow_hashref()) {
		($name, $id) = ($row->{'name'}, $row->{'id'});
	}
	return ($name,$id);
}

sub getLiveServiceIdsInString {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my $logger = $self->{'logger'};
	my $ids = shift;
	
	my $idStr = "";
	
	my $query = "SELECT `id`";
	$query .= " FROM mod_bi_liveservice";
	$query .= " WHERE timeperiod_id IN (".$ids.")";
	my $sth = $db->query({ query => $query });
	my %result = ();
    while (my $row = $sth->fetchrow_hashref()) {
    	$idStr .= $row->{'id'}.",";
    }
    $idStr =~ s/\,$//;
    return $idStr;
}

sub getLiveServicesByNameForTpIds {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my $ids = shift;

	my $idStr = "";

	foreach my $key (keys %$ids) {
		if ($idStr eq "") {
		$idStr .= $key;			
		}else {
			$idStr .= ",".$key;
		}
	}
	if ($idStr eq "") {
		$self->{logger}->writeLog("ERROR", "Select a timeperiod in the ETL configuration menu");
	}
	my $query = "SELECT `id`, `name`";
	$query .= " FROM mod_bi_liveservice";
	$query .= " WHERE timeperiod_id IN (".$idStr.")";
	my $sth = $db->query({ query => $query });
	my %result = ();
    while (my $row = $sth->fetchrow_hashref()) {
    	$result{ $row->{name} } = $row->{id};
    }
    return \%result;
}

sub getTimeperiodName {
	my $self = shift;
	my $db = $self->{"centstorage"};
	
	my $id = shift;
	my $query = "SELECT name FROM mod_bi_liveservice WHERE timeperiod_id=".$id;
	my $sth = $db->query({ query => $query });
	my $name = "";
	if (my $row = $sth->fetchrow_hashref()) {
		$name = $row->{'name'};
	}
	return($name);
}

sub getTimeperiodId {
	my $self = shift;
	my $db = $self->{"centstorage"};
	
	my $name = shift;
	my $query = "SELECT timeperiod_id FROM mod_bi_liveservice WHERE name='".$name."'";
	my $sth = $db->query({ query => $query });
	my $id = 0;
	if (my $row = $sth->fetchrow_hashref()) {
		$id = $row->{'timeperiod_id'};
	}
	return($id);
}

sub insert {
	my $self = shift;
	my $db = $self->{"centstorage"};
	
	my $name = shift;
	my $id = shift;
	my $query = "INSERT INTO `mod_bi_liveservice` (`name`, `timeperiod_id`) VALUES ('".$name."', ".$id.")";
	my $sth = $db->query({ query => $query });
}

sub insertList {
	my $self = shift;
	my $db = $self->{"centstorage"};
	my $list = shift;
	
	while (my ($id, $name) = each %$list) {
		my $tpName = $self->getTimeperiodName($id);
		my $tpId = $self->getTimeperiodId($name);
		if ($tpName ne "" && $name ne $tpName) {
				$self->updateById($id, $name);	
		}elsif ($tpId > 0 && $tpId != $id) {
			$self->update($name, $id);
		}elsif ($tpId == 0 && $tpName eq "") {
			$self->insert($name, $id);
		}
	}
}

sub update {
	my $self = shift;
	my $db = $self->{"centstorage"};
	
	my $name = shift;
	my $id = shift;
	my $query = "UPDATE `mod_bi_liveservice` SET `timeperiod_id`=".$id." WHERE name='".$name."'";
	$db->query({ query => $query });
}

sub updateById {
	my $self = shift;
	my $db = $self->{"centstorage"};
	
	my ($id, $name) = (shift, shift);
	my $query = "UPDATE `mod_bi_liveservice` SET `name`='".$name."' WHERE timeperiod_id=".$id;
	$db->query({ query => $query });
}

sub truncateTable {
	my $self = shift;
	my $db = $self->{"centstorage"};
	
	my $query = "TRUNCATE TABLE `mod_bi_liveservice`";
	$db->query({ query => $query });
	$db->query({ query => "ALTER TABLE `mod_bi_liveservice` AUTO_INCREMENT=1" });
}

1;
