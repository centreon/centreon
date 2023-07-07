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
	$self->{logger}	= shift;
	$self->{centreon} = shift;
	$self->{etlProperties} = undef;

	if (@_) {
		$self->{centstorage}  = shift;
	}
	bless $self, $class;
	return $self;
}

#Set the etl properties as a variable of the class
sub setEtlProperties{
	my $self = shift;
	$self->{etlProperties} = shift;
}

# returns two references to two hash tables => hosts indexed by id and hosts indexed by name
sub getAllHosts {
	my $self = shift;
	my $centreon = $self->{centreon};
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
	my $sth = $centreon->query({ query => $query });
	while (my $row = $sth->fetchrow_hashref()) {
		$host_ids{ $row->{host_name} } = $row->{host_id};
		$host_names{ $row->{host_id} } = $row->{host_name};
	}
	return (\%host_ids, \%host_names);
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

sub loadAllCategories {
    my $self = shift;

    $self->{hc} = {};
    $self->{host_hc_relations} = {};
    my $query = "SELECT hc.hc_id as category_id, hc.hc_name as category_name, host_host_id
        FROM hostcategories hc, hostcategories_relation hr
        WHERE hc.hc_activate = '1' AND hc.hc_id = hr.hostcategories_hc_id";
    my $sth = $self->{centreon}->query({ query => $query });
    while (my $row = $sth->fetchrow_hashref()) {
        $self->{hc}->{ $row->{category_id} } = $row->{category_name} if (!defined($self->{hc}->{ $row->{category_id} }));
        $self->{host_hc_relations}->{ $row->{host_host_id} } = [] if (!defined($self->{host_hc_relations}->{ $row->{host_host_id} }));
        push @{$self->{host_hc_relations}->{ $row->{host_host_id} }}, $row->{category_id};
    }
}

sub loadAllHosts {
    my $self = shift;

    $self->{hosts} = {};
    $self->{host_htpl_relations} = {};
    my $query = "SELECT h.host_id, h.host_name, host_tpl_id
        FROM host h, host_template_relation htr
        WHERE h.host_activate = '1' AND h.host_id = htr.host_host_id";
    my $sth = $self->{centreon}->query({ query => $query });
    while (my $row = $sth->fetchrow_hashref()) {
        $self->{hosts}->{ $row->{host_id} } = $row->{host_name} if (!defined($self->{hosts}->{ $row->{host_id} }));
        $self->{host_htpl_relations}->{ $row->{host_id} } = [] if (!defined($self->{host_htpl_relations}->{ $row->{host_id} }));
        push @{$self->{host_htpl_relations}->{ $row->{host_id} }}, $row->{host_tpl_id};
    }
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
    if (!defined($etlProperties->{'dimension.all.hostgroups'}) && $etlProperties->{'dimension.hostgroups'} ne '') {
        $query .= " AND `hg_id` IN (".$etlProperties->{'dimension.hostgroups'}.")"; 
    }
	my $sth = $centreon->query({ query => $query });
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

#Fill a class Hash table that contains the relation between host_id and table[hc_id,hc_name]
sub getHostCategoriesWithTemplate {
	my $self = shift;

    my @hostCategoriesAllowed = split(/,/, $self->{etlProperties}->{'dimension.hostcategories'});

    my %loop = ();
    my $hcResult = {};
    foreach my $host_id (keys %{$self->{hosts}}) {
        my $stack = [$host_id];
        my $hcAdd = {};
        my $hc = [];
        foreach (my $id = shift(@$stack)) {
            next if (defined($loop{$id}));
            $loop{$id} = 1;

            if (defined($self->{host_hc_relations}->{$id})) {
                foreach my $category_id (@{$self->{host_hc_relations}->{$id}}) {
                    next if (defined($hcAdd->{$category_id}));
                    if ((grep {$_ eq $category_id} @hostCategoriesAllowed) ||
                        (defined($self->{etlProperties}->{'dimension.all.hostcategories'}) && $self->{etlProperties}->{'dimension.all.hostcategories'} ne '')) {
                        $hcAdd->{$category_id} = 1;
                        push @$hc, $category_id . ';' . $self->{hc}->{$category_id};
                    }
                }
            }

            unshift(@$stack, @{$self->{host_htpl_relations}->{id}}) if (defined($self->{host_htpl_relations}->{id}));
        }

        $hcResult->{$host_id} = $hc;
    }

    return $hcResult;
}

sub getHostGroupAndCategories {
    my $self = shift;
	
    my $hostGroups = $self->getHostGroups();

    $self->loadAllCategories();
    $self->loadAllHosts();
    my $hostCategories = $self->getHostCategoriesWithTemplate();
    my @results;

    while (my ($hostId, $groups) = each (%$hostGroups)) {
        my $categories_ref = $hostCategories->{$hostId};
        my @categoriesTab = ();
        if (defined($categories_ref) && scalar(@$categories_ref)) {
            @categoriesTab = @$categories_ref;
        }
        my $hostName = $self->{hosts}->{$hostId};
        foreach (@$groups) {
            my $group = $_;
            if (scalar(@categoriesTab)) {
                foreach(@categoriesTab) {
                    push @results, $hostId . ';' .$hostName . ';' . $group . ';' . $_;
                }
    		} else {
                #If there is no category
                push @results, $hostId . ";" . $hostName . ";" .  $group . ";0;NoCategory";
            }
        }
    }

    return \@results;
}

1;
