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

package gorgone::modules::centreon::mbi::libs::centreon::ServiceCategory;

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

# returns two references to two hash tables => services indexed by id and services indexed by name
sub getCategory {
	my $self = shift;
	my $db = $self->{"centreon"};
	my $etlProperties = $self->{'etlProperties'};
	my $scName = "";
	if (@_) {
		$scName  = shift;
	}
	
    my $result = "";
	# getting services linked to hosts
	my $query = "SELECT sc_id from service_categories WHERE sc_name='".$scName."'";
	if(!defined($etlProperties->{'dimension.all.servicecategories'}) && $etlProperties->{'dimension.servicecategories'} ne ''){
		$query .= " WHERE `sc_id` IN (".$etlProperties->{'dimension.servicecategories'}.")"; 
	}
	my $sth = $db->query({ query => $query });
    if(my $row = $sth->fetchrow_hashref()) {
		$result = $row->{"sc_id"};
	}else {
		($self->{"logger"})->writeLog("error", "Cannot find service category '" . $scName . "' in database");
	}
	$sth->finish();
		
	return ($result);
}

sub getAllEntries {
	my $self = shift;
	my $db = $self->{"centreon"};
	my $etlProperties = $self->{'etlProperties'};

	my $query = "SELECT `sc_id`, `sc_name`";
	$query .= " FROM `service_categories`";
	if(!defined($etlProperties->{'dimension.all.servicecategories'}) && $etlProperties->{'dimension.servicecategories'} ne ''){
		$query .= " WHERE `sc_id` IN (".$etlProperties->{'dimension.servicecategories'}.")"; 
	}
	my $sth = $db->query({ query => $query });
	my @entries = ();
	while (my $row = $sth->fetchrow_hashref()) {
		push @entries, $row->{"sc_id"}.";".$row->{"sc_name"};
	}
	$sth->finish();
	return (\@entries);
}

1;
