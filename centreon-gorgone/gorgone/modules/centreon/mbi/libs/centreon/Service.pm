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

package gorgone::modules::centreon::mbi::libs::centreon::Service;

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

sub setEtlProperties{
	my $self = shift;
	$self->{'etlProperties'} = shift;
}

# returns two references to two hash tables => services indexed by id and services indexed by name
sub getServicesWithHostAndCategory {
	my $self = shift;
	my $centreon = $self->{"centreon"};
	my $serviceId = "";
	my $hosts = shift;
	if (@_) {
		$serviceId = shift;
	}
	my $templateCategories = $self->getServicesTemplatesCategories;
	
    my (@results);
	# getting services linked to hosts
	my $query = "SELECT service_description, service_id, host_id, service_template_model_stm_id as tpl".
        " FROM host, service, host_service_relation".
        " WHERE host_id = host_host_id and service_service_id = service_id".
        " AND service_register = '1'".
        " AND host_activate = '1'".
        " AND service_activate = '1'";

	my $sth = $centreon->query({ query => $query });
    while(my $row = $sth->fetchrow_hashref()) {
    	# getting all host entries
    	my $serviceHostTable = $hosts->{$row->{"host_id"}};
    	# getting all Categories entries
    	my @categoriesTable = ();
    	# getting categories directly linked to service
    	my $categories = $self->getServiceCategories($row->{"service_id"});
    	while(my ($sc_id, $sc_name) = each(%$categories)) {
   			push @categoriesTable, $sc_id.";".$sc_name;
		}
		# getting categories linked to template
		if (defined($row->{"tpl"}) && defined($templateCategories->{$row->{"tpl"}})) {
	    	my $tplCategories = $templateCategories->{$row->{"tpl"}};
		    while(my ($sc_id, $sc_name) = each(%$tplCategories)) {
		    	if(!defined($categories->{$sc_id})) { 
	   				push @categoriesTable, $sc_id.";".$sc_name;
		    	}
			}
    	}
   		if (!scalar(@categoriesTable)) {
   			#ToDo push @categoriesTable, "0;NULL";
   		}	
    	if (defined($serviceHostTable)) {
    		foreach(@$serviceHostTable) {
    			my $hostInfos = $_;
    			foreach(@categoriesTable) {
 		   			push @results, $row->{"service_id"}.";".$row->{"service_description"}.";".$_.";".$hostInfos;
    			}
    		}
    	}
	}
	#getting services linked to hostgroup
	$query = "SELECT DISTINCT service_description, service_id, host_id, service_template_model_stm_id as tpl".
        " FROM host, service, host_service_relation hr, hostgroup_relation hgr".
        " WHERE  hr.hostgroup_hg_id is not null".
        " AND hr.service_service_id = service_id".
        " AND hr.hostgroup_hg_id = hgr.hostgroup_hg_id".
        " AND hgr.host_host_id = host_id".
        " AND service_register = '1'".
        " AND host_activate = '1'".
        " AND service_activate = '1'";

	$sth = $centreon->query({ query => $query });
    while(my $row = $sth->fetchrow_hashref()) {
		# getting all host entries
    	my $serviceHostTable = $hosts->{$row->{"host_id"}};
    	# getting all Categories entries
    	my @categoriesTable = ();
    	# getting categories directly linked to service
    	my $categories = $self->getServiceCategories($row->{"service_id"});
    	while(my ($sc_id, $sc_name) = each(%$categories)) {
   			push @categoriesTable, $sc_id.";".$sc_name;
		}
		# getting categories linked to template
		if (defined($row->{"tpl"}) && defined($templateCategories->{$row->{"tpl"}})) {
	    	my $tplCategories = $templateCategories->{$row->{"tpl"}};
		    while(my ($sc_id, $sc_name) = each(%$tplCategories)) {
	   			if(!defined($categories->{$sc_id})) { 
	   				push @categoriesTable, $sc_id.";".$sc_name;
		    	}
			}
    	}
   		if (!scalar(@categoriesTable)) {
   			push @categoriesTable, "0;NULL";
   		}	
    	if (defined($serviceHostTable)) {
    		foreach(@$serviceHostTable) {
    			my $hostInfos = $_;
    			foreach(@categoriesTable) {
 		   			push @results, $row->{"service_id"}.";".$row->{"service_description"}.";".$_.";".$hostInfos;
    			}
    		}
    	}
    }
	$sth->finish();
	return (\@results);
}

sub getServicesTemplatesCategories {
	my $self = shift;
	my $db = $self->{"centreon"};
	my %results = ();
	
	my $query = "SELECT service_id, service_description, service_template_model_stm_id FROM service WHERE service_register = '0'";
	my $sth = $db->query({ query => $query });
    while(my $row = $sth->fetchrow_hashref()) {
		my $currentTemplate = $row->{"service_id"};
		my $categories = $self->getServiceCategories($row->{"service_id"});
		my $parentId = $row->{"service_template_model_stm_id"};
		if (defined($parentId)) {
			my $hasParent = 1;
			# getting all parent templates category relations
			while ($hasParent) {
				my $parentQuery = "SELECT service_id, service_template_model_stm_id ";
				$parentQuery .= "FROM service ";
				$parentQuery .= "WHERE service_register = '0' and service_id=".$parentId;
				my $sthparentQuery = $db->query({ query => $parentQuery });
	   			if(my $parentQueryRow = $sthparentQuery->fetchrow_hashref()) {
	   				my $newCategories = $self->getServiceCategories($parentQueryRow->{"service_id"});
	   				while(my ($sc_id, $sc_name) = each(%$newCategories)) {
	   					if (!defined($categories->{$sc_id})) {
	   						$categories->{$sc_id} = $sc_name;
	   					}
	   				}
	   				if (!defined($parentQueryRow->{'service_template_model_stm_id'})) {
	   					$hasParent = 0;
	   					last;
	   				}
	   				$parentId = $parentQueryRow->{'service_template_model_stm_id'};
	   				$sthparentQuery->finish();
	   			}else {
	   				$hasParent = 0;
	   			}
			}
		}
		$results{$currentTemplate} = $categories;
	}
	$sth->finish();
	return \%results;
}

sub getServiceCategories {
	my $self = shift;
	my $db = $self->{"centreon"};
	my $id = shift;
	my %results = ();
	my $etlProperties = $self->{'etlProperties'};
	
	my $query = "SELECT sc.sc_id, sc_name ";
	$query .= " FROM service_categories sc, service_categories_relation scr";
	$query .= " WHERE service_service_id = ".$id;
	$query .= " AND sc.sc_id = scr.sc_id";
	if(!defined($etlProperties->{'dimension.all.servicecategories'}) && $etlProperties->{'dimension.servicecategories'} ne ''){
		$query .= " AND sc.sc_id IN (".$etlProperties->{'dimension.servicecategories'}.")"; 
	}
	my $sth = $db->query({ query => $query });
	while(my $row = $sth->fetchrow_hashref()) {
	 	$results{$row->{"sc_id"}} = $row->{"sc_name"};
	}
	return (\%results);
}

1;
