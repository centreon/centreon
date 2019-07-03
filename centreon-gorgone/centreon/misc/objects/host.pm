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
package centreon::misc::objects::host;

use strict;
use warnings;

use base qw(centreon::misc::objects::object);

sub new {
    my ($class, %options) = @_;
    my $self = $class->SUPER::new(%options);
    
    bless $self, $class;
    return $self;
}

# special options: 
#   'organization_name' or 'organization_id'
#   'with_services'
sub get_hosts_by_organization {
    my ($self, %options) = @_;

    my %defaults = (request => 'SELECT', tables => ['cfg_hosts'], fields => ['*']);
    if (defined($options{organization_name})) {
        $defaults{tables} = ['cfg_hosts', 'cfg_organizations'];
        $defaults{where} = 'cfg_organizations.name = ' . $self->{db_centreon}->quote($options{organization_name});
    } elsif (defined($options{organization_id})) {
        $defaults{where} = 'cfg_hosts.organization_id = ' . $self->{db_centreon}->quote($options{organization_id});
    } else {
        $self->{logger}->writeLogError("Please specify 'organization_name' or 'organization_id' parameter.");
        return (-1, undef);
    }
    if (defined($options{with_services})) {
        push @{$defaults{tables}}, 'cfg_hosts_services_relations', 'cfg_services';
        $defaults{where} .= ' AND cfg_hosts.host_id = cfg_hosts_services_relations.host_host_id AND cfg_hosts_services_relations.service_service_id = cfg_services.service_id';
    }

    my $options_builder = {%defaults, %options};
    return $self->execute(%$options_builder);
}

1;
