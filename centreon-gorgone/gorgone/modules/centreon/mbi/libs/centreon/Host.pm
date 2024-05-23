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
    $self->{logger}    = shift;
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

    my $query = "SELECT `host_id`, `host_name`" .
                " FROM `host`" .
                " WHERE `host_register`='1'";
    if ($activated == 1) {
        $query .= " AND `host_activate` ='1'";
    }
    my $sth                            = $centreon->query({ query => $query });
    while (my $row                     = $sth->fetchrow_hashref()) {
        $host_ids{ $row->{host_name} } = $row->{host_id};
        $host_names{ $row->{host_id} } = $row->{host_name};
    }
    return (\%host_ids, \%host_names);
}

# Get all hosts, keys are IDs
sub getAllHostsByID {
    my $self                    = shift;
    my ($host_ids, $host_names) = $self->getAllHosts();
    return ($host_ids);
}

# Get all hosts, keys are names
sub getAllHostsByName {
    my $self                    = shift;
    my ($host_ids, $host_names) = $self->getAllHosts();
    return ($host_names);
}

sub loadAllCategories {
    my $self = shift;

    $self->{hc}                = {};
    $self->{host_hc_relations} = {};
    my $query                  = "SELECT hc.hc_id as category_id, hc.hc_name as category_name, host_host_id
        FROM hostcategories hc, hostcategories_relation hr
        WHERE hc.hc_activate = '1' AND hc.hc_id = hr.hostcategories_hc_id";
    my $sth                                                  = $self->{centreon}->query({ query => $query });
    while (my $row                                           = $sth->fetchrow_hashref()) {
        $self->{hc}->{ $row->{category_id} }                 = $row->{category_name} if (!defined($self->{hc}->{ $row
            ->{category_id} }));
        $self->{host_hc_relations}->{ $row->{host_host_id} } = [] if (!defined($self->{host_hc_relations}->{ $row
            ->{host_host_id} }));
        push @{$self->{host_hc_relations}->{ $row->{host_host_id} }}, $row->{category_id};
    }
}

sub loadAllHosts {
    my $self = shift;

    $self->{hosts}               = {};
    $self->{host_htpl_relations} = {};
    my $query                    = "SELECT h.host_id, h.host_name, host_tpl_id
        FROM host h, host_template_relation htr
        WHERE h.host_activate = '1' AND h.host_id = htr.host_host_id";
    my $sth                                               = $self->{centreon}->query({ query => $query });
    while (my $row                                        = $sth->fetchrow_hashref()) {
        $self->{hosts}->{ $row->{host_id} }               = $row->{host_name} if (!defined($self->{hosts}->{ $row
            ->{host_id} }));
        $self->{host_htpl_relations}->{ $row->{host_id} } = [] if (!defined($self->{host_htpl_relations}->{ $row
            ->{host_id} }));
        push @{$self->{host_htpl_relations}->{ $row->{host_id} }}, $row->{host_tpl_id};
    }
}

# returns host groups linked to hosts
# all hosts will be stored in a hash table
# each key of the hash table is a host id
# each key is linked to a table containing entries like : "hostgroup_id;hostgroup_name"
sub getHostGroups {
    my $self          = shift;
    my $centreon      = $self->{"centreon"};
    my $activated     = 1;
    my $etlProperties = $self->{'etlProperties'};
    if (@_) {
        $activated = 0;
    }
    my %result = ();

    my $query = "SELECT `host_id`, `host_name`, `hg_id`, `hg_name`" .
                " FROM `host`, `hostgroup_relation`, `hostgroup`" .
                " WHERE `host_register`='1'" .
                " AND `hostgroup_hg_id` = `hg_id`" .
                " AND `host_id`= `host_host_id`";
    if ($activated == 1) {
        $query .= " AND `host_activate` ='1'";
    }
    if (!defined($etlProperties->{'dimension.all.hostgroups'}) && $etlProperties->{'dimension.hostgroups'} ne '') {
        $query .= " AND `hg_id` IN (" . $etlProperties->{'dimension.hostgroups'} . ")";
    }
    my $sth           = $centreon->query({ query => $query });
    while (my $row    = $sth->fetchrow_hashref()) {
        my $new_entry = $row->{"hg_id"} . ";" . $row->{"hg_name"};
        if (defined($result{$row->{"host_id"}})) {
            my $tab_ref = $result{$row->{"host_id"}};
            my @tab     = @$tab_ref;
            my $exists  = 0;
            foreach (@tab) {
                if ($_ eq $new_entry) {
                    $exists = 1;
                    last;
                }
            }
            if (!$exists) {
                push @tab, $new_entry;
            }
            $result{$row->{"host_id"}} = \@tab;
        } else {
            my @tab                    = ($new_entry);
            $result{$row->{"host_id"}} = \@tab;
        }
    }
    $sth->finish();
    return (\%result);
}

#Fill a class Hash table that contains the relation between host_id and table[hc_id,hc_name]
sub getHostCategoriesWithTemplate {
    my $self      = shift;
    my $centreon  = $self->{"centreon"};
    my $activated = 1;

    #Hash : each key of the hash table is a host id
    #each key is linked to a table containing entries like : "hc_id,hc_name"
    my $hostCategoriesWithTemplate = $self->{'hostCategoriesWithTemplates'};
    if (@_) {
        $activated = 0;
    }

    my $query = "SELECT `host_id` FROM `host` WHERE `host_activate` ='1' AND `host_register` ='1'";

    my $sth         = $centreon->query({ query => $query });
    while (my $row  = $sth->fetchrow_hashref()) {
        my @tab     = ();
        my $host_id = $row->{"host_id"};
        $self->getRecursiveCategoriesForOneHost($host_id, \@tab);
        $self->getDirectLinkedCategories($host_id, \@tab);
        $hostCategoriesWithTemplate->{$row->{"host_id"}} = [@tab];
        undef @tab;
    }
    $self->{'hostCategoriesWithTemplates'} = $hostCategoriesWithTemplate;
    $sth->finish();
}

#Get the link between host and categories using direct link hc <> host
sub getDirectLinkedCategories {
    my $self          = shift;
    my $host_id       = shift;
    my $ref_hostCat   = shift;
    my $centreon      = $self->{"centreon"};
    my $etlProperties = $self->{"etlProperties"};
    my @tab           = ();

    my $query = "SELECT `host_id`, `host_name`, `hc_id`, `hc_name`" .
                " FROM `host`, `hostcategories_relation`, `hostcategories`" .
                " WHERE `host_register`='1'" .
                " AND `hostcategories_hc_id` = `hc_id`" .
                " AND `host_id`= `host_host_id`" .
                " AND `host_id`= " . $host_id . " " .
                " AND `host_activate` ='1' AND hostcategories.hc_activate = '1' ";

    if (!defined($etlProperties->{'dimension.all.hostcategories'}) && $etlProperties->{'dimension.hostcategories'}
                                                                      ne '') {
        $query .= " AND `hc_id` IN (" . $etlProperties->{'dimension.hostcategories'} . ")";
    }

    my $sth           = $centreon->query({ query => $query });
    while (my $row    = $sth->fetchrow_hashref()) {
        my $new_entry = $row->{"hc_id"} . ";" . $row->{"hc_name"};
        if (!scalar(@$ref_hostCat)) {
            @$ref_hostCat = ($new_entry);
        } else {
            @tab       = @$ref_hostCat;
            my $exists = 0;
            foreach (@$ref_hostCat) {
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

sub GetHostTemplateAndCategoryForOneHost {
    my $self = shift;
    my $host_id = shift;

        my $query = << "EOQ";
SELECT
    hhtemplates.host_id,
    hhtemplates.host_name,
    hhtemplates.template_id,
    hhtemplates.template_name,
    categories.hc_id as category_id,
    categories.hc_activate as hc_activate,
    categories.hc_name as category_name
FROM (
    SELECT
        hst.host_id,
        hst.host_name,
        htpls.host_id as template_id,
        htpls.host_name as template_name
    FROM
        host hst
    JOIN
        host_template_relation hst_htpl_rel
        ON
            hst.host_id = hst_htpl_rel.host_host_id
    JOIN
        host htpls
        ON
            hst_htpl_rel.host_tpl_id = htpls.host_id
    WHERE
        hst.host_activate ='1'
        AND hst.host_id = $host_id
) hhtemplates
LEFT JOIN
    hostcategories_relation hcs_rel
    ON
        hcs_rel.host_host_id = hhtemplates.template_id
LEFT JOIN
    hostcategories categories
    ON
        hcs_rel.hostcategories_hc_id = categories.hc_id
EOQ

    return $self->{centreon}->query({ query => $query });

}

#Get the link between host and categories using templates
sub getRecursiveCategoriesForOneHost {
    my $self          = shift;
    my $host_id       = shift;
    my $ref_hostCat   = shift;
    my $etlProperties = $self->{"etlProperties"};

    #Get all categories linked to the templates associated with the host or just template associated with host to be able to call the method recursively
    my $sth = $self->GetHostTemplateAndCategoryForOneHost($host_id);

    my @hostCategoriesAllowed = split /,/, $etlProperties->{'dimension.hostcategories'};
    while (my $row = $sth->fetchrow_hashref()) {
        my $new_entry;
        my @tab              = ();
        my $categoryId       = $row->{"category_id"};
        my $categoryName     = $row->{"category_name"};
        my $categoryActivate = $row->{"hc_activate"};

        #If current category is in allowed categories in ETL configuration
        #add it to the categories link to the host,
        #Then check for templates categories recursively
        if (defined($categoryId) && defined($categoryName) && $categoryActivate == '1') {
            if ((grep {$_ eq $categoryId} @hostCategoriesAllowed)
            || (defined($etlProperties->{'dimension.all.hostcategories'})
            && $etlProperties->{'dimension.all.hostcategories'} ne '')) {
                $new_entry = $categoryId . ";" . $categoryName;
                #If no hostcat has been found for the host, create the line
                if (!scalar(@$ref_hostCat)) {
                    @$ref_hostCat = ($new_entry);
                } else {
                    #If the tab is not empty, check wether the combination already exists in the tab
                    @tab       = @$ref_hostCat;
                    my $exists = 0;
                    foreach (@$ref_hostCat) {
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
        $self->getRecursiveCategoriesForOneHost($row->{"template_id"}, $ref_hostCat);
    }
    $sth->finish();
}

sub getHostGroupAndCategories {
    my $self = shift;

    my $hostGroups = $self->getHostGroups();

    $self->loadAllCategories();
    $self->loadAllHosts();
    $self->getHostCategoriesWithTemplate();
    my $hostCategories = $self->{"hostCategoriesWithTemplates"};
    my @results;

    while (my ($hostId, $groups) = each(%$hostGroups)) {
        my $categories_ref       = $hostCategories->{$hostId};
        my @categoriesTab        = ();
        if (defined($categories_ref) && scalar(@$categories_ref)) {
            @categoriesTab = @$categories_ref;
        }
        my $hostName = $self->{hosts}->{$hostId};
        foreach (@$groups) {
            my $group = $_;
            if (scalar(@categoriesTab)) {
                foreach (@categoriesTab) {
                    push @results, $hostId . ';' . $hostName . ';' . $group . ';' . $_;
                }
            } else {
                #If there is no category
                push @results, $hostId . ";" . $hostName . ";" . $group . ";0;NoCategory";
            }
        }
    }

    return \@results;
}

1;