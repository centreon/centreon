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

package gorgone::modules::centreon::mbi::libs::bi::DataQuality;

# Constructor
# parameters:
# $logger: instance of class CentreonLogger
# $centreon: Instance of centreonDB class for connection to Centreon database
# $centstorage: (optionnal) Instance of centreonDB class for connection to Centstorage database

sub new {
	my $class = shift;
	my $self  = {};

    $self->{logger}	= shift;
	$self->{centreon} = shift;
	bless $self, $class;
	return $self;
}

sub searchAndDeleteDuplicateEntries {
	my $self = shift;

	$self->{logger}->writeLog("INFO", "Searching for duplicate host/service entries");
	my $relationIDS = $self->getDuplicateRelations();
	if (@$relationIDS) {
		$self->deleteDuplicateEntries($relationIDS);
	}
}

# return table of IDs to delete
sub getDuplicateRelations {
	my $self = shift;

	my @relationIDS;
	#Get duplicated relations and exclude BAM or Metaservices data 
	my $duplicateEntriesQuery = "SELECT host_host_id, service_service_id, count(*) as nbRelations ".
        "FROM host_service_relation t1, host t2 WHERE t1.host_host_id = t2.host_id ".
        "AND t2.host_name not like '_Module%' group by host_host_id, service_service_id HAVING COUNT(*) > 1";

	my $sth = $self->{centreon}->query($duplicateEntriesQuery);
	while (my $row = $sth->fetchrow_hashref()) {
		if (defined($row->{host_host_id})) {
			$self->{logger}->writeLog(
                "WARNING",
                "Found the following duplicate data (host-service) : " . $row->{host_host_id}." - ".$row->{service_service_id}." - Cleaning data"
            );
			#Get all relation IDs related to duplicated data
			my $relationIdQuery = "SELECT hsr_id from host_service_relation ".
                "WHERE host_host_id = ".$row->{host_host_id}." AND service_service_id = ".$row->{service_service_id};
			my $sth2 = $self->{centreon}->query($relationIdQuery);
			while (my $hsr = $sth2->fetchrow_hashref()) {
				if (defined($hsr->{hsr_id})) {
					push(@relationIDS,$hsr->{hsr_id});
				}
			}
			$self->deleteDuplicateEntries(\@relationIDS);
			@relationIDS = ();
		}
	}
	return (\@relationIDS);
}

# Delete N-1 duplicate entry
sub deleteDuplicateEntries {
	my $self = shift;

	my @relationIDS = @{$_[0]};
    #WARNING : very important so at least 1 relation is kept
	pop @relationIDS; 
	foreach (@relationIDS) {
		my $idToDelete = $_;
		my $deleteQuery = "DELETE FROM host_service_relation WHERE hsr_id = ".$idToDelete;
		$self->{centreon}->query($deleteQuery)
	}
}

1;
