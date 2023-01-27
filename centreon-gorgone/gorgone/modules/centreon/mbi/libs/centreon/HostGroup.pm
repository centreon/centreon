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

package gorgone::modules::centreon::mbi::libs::centreon::HostGroup;

# Constructor
# parameters:
# $logger: instance of class CentreonLogger
# $centreon: Instance of centreonDB class for connection to Centreon database
# $centstorage: (optionnal) Instance of centreonDB class for connection to Centstorage database
sub new {
	my $class = shift;
	my $self  = {};
	$self->{"logger"}	= shift;
	$self->{"centreon"} = shift;
	$self->{'etlProperties'} = undef;
	if (@_) {
		$self->{"centstorage"}  = shift;
	}
	bless $self, $class;
	return $self;
}

#Set the etl properties as a variable of the class
sub setEtlProperties{
	my $self = shift;
	$self->{'etlProperties'} = shift;
}

# returns in a table all host/service of a group of host
sub getHostgroupServices {
	my $self = shift;
	my $db = $self->{"centreon"};
	my $etlProperties = $self->{'etlProperties'};
	my $hgId = 0;
	if (@_) {
		$hgId  = shift;
	}
	my %result = ();
	my $query = "SELECT h.`host_id`, h.`host_name`, s.`service_id`, s.`service_description`";
	$query .= " FROM `hostgroup` hg, `host_service_relation` hsr, `service` s, `hostgroup_relation` hgr, `host` h";
	$query .= " WHERE hg.`hg_id` = ".$hgId;
	$query .= " AND hg.`hg_id` = hsr.`hostgroup_hg_id`";
	$query .= " AND hsr.`service_service_id` = s.`service_id`";
	$query .= " AND s.`service_activate` = '1'";
	$query .= " AND s.`service_register` = '1'";
	$query .= " AND hg.hg_id = hgr.`hostgroup_hg_id`";
	$query .= " AND hgr.`host_host_id` = h.`host_id`";
	$query .= " AND h.`host_activate` = '1'";
	$query .= " AND h.`host_register` = '1'";
	if(!defined($etlProperties->{'dimension.all.hostgroups'}) && $etlProperties->{'dimension.hostgroups'} ne ''){
		$query .= " AND hg.`hg_id` IN (".$etlProperties->{'dimension.hostgroups'}.")"; 
	}
	my $sth = $db->query($query);
	while (my $row = $sth->fetchrow_hashref()) {
		$result{$row->{"host_id"}.";".$row->{"service_id"}} = 1;
	}
	$sth->finish();
	return (\%result);
}


# returns in a table all host/service of a group of host
sub getHostgroupHostServices {
	my $self = shift;
	my $db = $self->{"centreon"};
	my %etlProperties = $self->{'etlProperties'};
	
	my $hgId = 0;
	if (@_) {
		$hgId  = shift;
	}
	my %result = ();
	my $query = "SELECT h.`host_id`, s.`service_id`";
	$query .= " FROM `host` h, `hostgroup` hg, `hostgroup_relation` hgr, `host_service_relation` hsr, `service` s";
	$query .= " WHERE hg.`hg_id` = ".$hgId;
	$query .= " AND hgr.`hostgroup_hg_id` = hg.`hg_id`";
	$query .= " AND hgr.`host_host_id` = h.`host_id`";
	$query .= " AND h.`host_activate` = '1'";
	$query .= " AND h.`host_register` = '1'";
	$query .= " AND h.`host_id` = hsr.`host_host_id`";
	$query .= " AND hsr.`service_service_id` = s.`service_id`";
	$query .= " AND s.`service_activate` = '1'";
	$query .= " AND s.`service_register` = '1'";
	if(!defined($etlProperties{'etl.dimension.all.hostgroups'}) && $etlProperties{'etl.dimension.hostgroups'} ne ''){
		$query .= " AND hg.`hg_id` IN (".$etlProperties{'etl.dimension.hostgroups'}.")"; 
	}
	my $sth = $db->query($query);
	while (my $row = $sth->fetchrow_hashref()) {
		$result{$row->{"host_id"}.";".$row->{"service_id"}} = 1;
	}
	%result = (%result, $self->getHostgroupServices($hgId));
	return (\%result);
}

sub getAllEntries {
	my $self = shift;
	my $db = $self->{"centreon"};
	my $etlProperties = $self->{'etlProperties'};

	my $query = "SELECT `hg_id`, `hg_name`";
	$query .= " FROM `hostgroup`";
	if(!defined($etlProperties->{'dimension.all.hostgroups'}) && $etlProperties->{'dimension.hostgroups'} ne ''){
		$query .= " WHERE `hg_id` IN (".$etlProperties->{'dimension.hostgroups'}.")"; 
	}
	my $sth = $db->query($query);
	my @entries = ();
	while (my $row = $sth->fetchrow_hashref()) {
		push @entries, $row->{"hg_id"}.";".$row->{"hg_name"};
	}
	$sth->finish();
	return (\@entries);
}


1;
