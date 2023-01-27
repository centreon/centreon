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

package gorgone::modules::centreon::mbi::libs::centreon::Host;

use strict;
use warnings;
use Data::Dumper;

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
	#Hash that will contains all relation between host and hostcategories after calling the function getHostCategoriesWithTemplate 
	$self->{"hostCategoriesWithTemplates"} = undef;
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

# returns two references to two hash tables => hosts indexed by id and hosts indexed by name
sub getAllHosts {
	my $self = shift;
	my $centreon = $self->{"centreon"};
	my $activated = 1;
	if (@_) {
		$activated  = 0;
	}
	my (%host_ids, %host_names);
	
	my $query = "SELECT `host_id`, `host_name`".
				" FROM `host`".
				" WHERE `host_register`='1'";
				if ($activated == 1) {
					$query .= " AND `host_activate` ='1'";
				}
	my $sth = $centreon->query($query);
	while (my $row = $sth->fetchrow_hashref()) {
		$host_ids{$row->{"host_name"}} = $row->{"host_id"};
		$host_names{$row->{"host_id"}} = $row->{"host_name"};
	}
	$sth->finish();
	return (\%host_ids,\%host_names);
}

# Get all hosts, keys are IDs
sub getAllHostsByID {
	my $self = shift;
	my ($host_ids, $host_names) = $self->getAllHosts();	
	return ($host_ids);
}

# Get all hosts, keys are names
sub getAllHostsByName {
	my $self = shift;
	my ($host_ids, $host_names) = $self->getAllHosts();	
	return ($host_names);
}


# returns host groups linked to hosts
# all hosts will be stored in a hash table
# each key of the hash table is a host id
# each key is linked to a table containing entries like : "hostgroup_id;hostgroup_name"
sub getHostGroups {
	my $self = shift;
	my $centreon = $self->{"centreon"};
	my $activated = 1;
	my $etlProperties = $self->{'etlProperties'};
	if (@_) {
		$activated  = 0;
	}
	my %result = ();
	
	my $query = "SELECT `host_id`, `host_name`, `hg_id`, `hg_name`".
				" FROM `host`, `hostgroup_relation`, `hostgroup`".
				" WHERE `host_register`='1'".
				" AND `hostgroup_hg_id` = `hg_id`".
				" AND `host_id`= `host_host_id`";
				if ($activated == 1) {
					$query .= " AND `host_activate` ='1'";
				}
				if(!defined($etlProperties->{'dimension.all.hostgroups'}) && $etlProperties->{'dimension.hostgroups'} ne ''){
					$query .= " AND `hg_id` IN (".$etlProperties->{'dimension.hostgroups'}.")"; 
				}
	my $sth = $centreon->query($query);
	while (my $row = $sth->fetchrow_hashref()) {
		my $new_entry = $row->{"hg_id"}.";".$row->{"hg_name"};
		if (defined($result{$row->{"host_id"}})) {
			my $tab_ref = $result{$row->{"host_id"}};
			my @tab = @$tab_ref;
			my $exists = 0;
			foreach(@tab) {
				if ($_ eq $new_entry) {
					$exists = 1;
					last;
				}
			}
			if (!$exists) {
				push @tab, $new_entry;
			}
			$result{$row->{"host_id"}} = \@tab;
		}else {
			my @tab = ($new_entry);
			$result{$row->{"host_id"}} = \@tab;
		}
	}
	$sth->finish();
	return (\%result);
}

#Get the link between host and categories using templates
sub getRecursiveCategoriesForOneHost{
	my $self = shift;
	my $host_id = shift;
	my $ref_hostCat = shift;
	my $centreon = $self->{"centreon"};
	my $etlProperties = $self->{"etlProperties"};
	
	
	#Get all categories linked to the templates associated with the host or just template associated with host to be able to call the method recursively 
	
	my $query = "SELECT host_id, host_name, template_id,template_name,  categories.hc_id as category_id, categories.hc_activate as hc_activate,".
                " categories.hc_name as category_name ".
                " FROM ( SELECT t1.host_id,t1.host_name,templates.host_id as template_id,templates.host_name as template_name ".
                " FROM host t1, host_template_relation t2, host templates ".
                " WHERE t1.host_id = t2.host_host_id AND t2.host_tpl_id = templates.host_id AND t1.host_activate ='1' AND t1.host_id = ".$host_id." ) r1 ".
                " LEFT JOIN hostcategories_relation t3 ON t3.host_host_id = r1.template_id LEFT JOIN hostcategories categories ON t3.hostcategories_hc_id = categories.hc_id ";
	
	
	my @hostCategoriesAllowed = split /,/, $etlProperties->{'dimension.hostcategories'};

	my $sth = $centreon->query($query);
	while (my $row = $sth->fetchrow_hashref()) {
		my @tab = ();
		my $new_entry;
		my $categoryId = $row->{"category_id"};
		my $categoryName = $row->{"category_name"};
		my $categoryActivate = $row->{"hc_activate"};
		
		#If current category is in allowed categories in ETL configuration
		#add it to the categories link to the host, 
		#Then check for templates categories recursively
		if(defined($categoryId) && defined($categoryName) && $categoryActivate=='1'){
		  if ((grep {$_ eq $categoryId} @hostCategoriesAllowed) || (defined($etlProperties->{'dimension.all.hostcategories'}) && $etlProperties->{'dimension.all.hostcategories'} ne '')){
			  $new_entry = $categoryId.";".$categoryName;
  			  #If no hostcat has been found for the host, create the line
			  if (!scalar(@$ref_hostCat)){
			   	  @$ref_hostCat = ($new_entry);
			  }else { #If the tab is not empty, check wether the combination already exists in the tab
				@tab = @$ref_hostCat;
				my $exists = 0;
				foreach(@$ref_hostCat) {
				  if ($_ eq $new_entry) {
					$exists = 1;
					last;
				  }
				}
				#If the host category did not exist, add it to the table @$ref_hostCat
				  if (!$exists) {
					push @$ref_hostCat, $new_entry;
				  }
			  }
		   }
		}
		$self->getRecursiveCategoriesForOneHost($row->{"template_id"},$ref_hostCat);
	}
	$sth->finish();
}

#Get the link between host and categories using direct link hc <> host
sub getDirectLinkedCategories{
	my $self = shift;
	my $host_id = shift;
	my $ref_hostCat = shift;
	my $centreon = $self->{"centreon"};
	my $etlProperties = $self->{"etlProperties"};
	my @tab = ();

	my $query = "SELECT `host_id`, `host_name`, `hc_id`, `hc_name`".
		" FROM `host`, `hostcategories_relation`, `hostcategories`".
		" WHERE `host_register`='1'".
		" AND `hostcategories_hc_id` = `hc_id`".
		" AND `host_id`= `host_host_id`".
		" AND `host_id`= ".$host_id." ".
		" AND `host_activate` ='1' AND hostcategories.hc_activate = '1' ";
		
	if(!defined($etlProperties->{'dimension.all.hostcategories'}) && $etlProperties->{'dimension.hostcategories'} ne ''){
		$query .= " AND `hc_id` IN (".$etlProperties->{'dimension.hostcategories'}.")"; 
	}

	my $sth = $centreon->query($query);
	while (my $row = $sth->fetchrow_hashref()) {
		my $new_entry = $row->{"hc_id"}.";".$row->{"hc_name"};
		if (!scalar(@$ref_hostCat)){
			@$ref_hostCat = ($new_entry);
		}else {
			@tab = @$ref_hostCat;
			my $exists = 0;
				foreach(@$ref_hostCat) {
					if ($_ eq $new_entry) {
						$exists = 1;
						last;
					}
				}
			if (!$exists) {
				push @$ref_hostCat, $new_entry;
			}
		}
	}
	$sth->finish();
}

#Fill a class Hash table that contains the relation between host_id and table[hc_id,hc_name]
sub getHostCategoriesWithTemplate{
	my $self = shift;
	my $centreon = $self->{"centreon"};
	my $activated = 1;
	
	#Hash : each key of the hash table is a host id
	#each key is linked to a table containing entries like : "hc_id,hc_name"
	my $hostCategoriesWithTemplate = $self->{'hostCategoriesWithTemplates'};
	if (@_) {
		$activated  = 0;
	}

	my $query = "SELECT `host_id`".
				" FROM `host`".
				" WHERE `host_activate` ='1'";

	my $sth = $centreon->query($query);
	while (my $row = $sth->fetchrow_hashref()) {
		my @tab = ();
		my $host_id = $row->{"host_id"};
		$self->getRecursiveCategoriesForOneHost($host_id,\@tab);
		$self->getDirectLinkedCategories($host_id,\@tab);
		$hostCategoriesWithTemplate->{$row->{"host_id"}} = [@tab];
		undef @tab;
	}
	$self->{'hostCategoriesWithTemplates'} = $hostCategoriesWithTemplate;
	$sth->finish();
}

sub getHostGroupAndCategories {
	my $self = shift;
	
	my $hostGroups = $self->getHostGroups();
	$self->getHostCategoriesWithTemplate(); 
	my $hostCategories = $self->{"hostCategoriesWithTemplates"};
    my $hosts = $self->getAllHostsByName;
    my @results;
    
    while (my ($hostId, $groups) = each (%$hostGroups)) {
    	my $categories_ref = $hostCategories->{$hostId};
    	my @categoriesTab = ();
    	if (defined($categories_ref) && scalar(@$categories_ref)) {
    		@categoriesTab = @$categories_ref;
    	}
    	my $hostName = $hosts->{$hostId};
    	foreach(@$groups) {
    		my $group = $_;
    		if (scalar(@categoriesTab)) {
	    		foreach(@categoriesTab) {
	    			push @results, $hostId.";".$hostName.";".$group.";".$_;
	    		}
    		}else {
				#If there is no category
    			push @results, $hostId.";".$hostName.";".$group.";0;NoCategory";
    		}
    	}
    }
    return \@results;
}

1;
