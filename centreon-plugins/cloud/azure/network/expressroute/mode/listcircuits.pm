#
# Copyright 2022 Centreon (http://www.centreon.com/)
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

package cloud::azure::network::expressroute::mode::listcircuits;

use base qw(centreon::plugins::mode);

use strict;
use warnings;

sub new {
    my ($class, %options) = @_;
    my $self = $class->SUPER::new(package => __PACKAGE__, %options);
    bless $self, $class;
    
    $options{options}->add_options(arguments =>
                                {
                                    "resource-group:s"      => { name => 'resource_group' },
                                    "location:s"            => { name => 'location' },
                                    "filter-name:s"         => { name => 'filter_name' },
                                });

    return $self;
}

sub check_options {
    my ($self, %options) = @_;
    $self->SUPER::init(%options);
}

sub manage_selection {
    my ($self, %options) = @_;

    $self->{circuits} = $options{custom}->azure_list_expressroute_circuits(
        resource_group => $self->{option_results}->{resource_group}
    );
}

sub run {
    my ($self, %options) = @_;

    $self->manage_selection(%options);
    foreach my $circuit (@{$self->{circuits}}) {
        next if (defined($self->{option_results}->{filter_name}) && $self->{option_results}->{filter_name} ne ''
            && $circuit->{name} !~ /$self->{option_results}->{filter_name}/);
        next if (defined($self->{option_results}->{location}) && $self->{option_results}->{location} ne ''
            && $circuit->{location} !~ /$self->{option_results}->{location}/);
        my $resource_group = '-';
        $resource_group = $circuit->{resourceGroup} if (defined($circuit->{resourceGroup}));
        $resource_group = $1 if ($resource_group eq '-' && defined($circuit->{id}) && $circuit->{id} =~ /resourceGroups\/(.*)\/providers/);
        
        my @tags;
        foreach my $tag (keys %{$circuit->{tags}}) {
            push @tags, $tag . ':' . $circuit->{tags}->{$tag};
        }

        $self->{output}->output_add(long_msg => sprintf("[name = %s][resourcegroup = %s][location = %s][id = %s][tags = %s]",
            $circuit->{name},
            $resource_group,
            $circuit->{location},
            $circuit->{id},
            join(',', @tags),
        ));
    }
    
    $self->{output}->output_add(severity => 'OK',
                                short_msg => 'List ExpressRoute circuits:');
    $self->{output}->display(nolabel => 1, force_ignore_perfdata => 1, force_long_output => 1);
    $self->{output}->exit();
}

sub disco_format {
    my ($self, %options) = @_;
    
    $self->{output}->add_disco_format(elements => ['name', 'resourcegroup', 'location', 'id', 'tags']);
}

sub disco_show {
    my ($self, %options) = @_;

    $self->manage_selection(%options);
    foreach my $circuit (@{$self->{circuits}}) {
        my $resource_group = '-';
        $resource_group = $circuit->{resourceGroup} if (defined($circuit->{resourceGroup}));
        $resource_group = $1 if ($resource_group eq '-' && defined($circuit->{id}) && $circuit->{id} =~ /resourceGroups\/(.*)\/providers/);
        
        my @tags;
        foreach my $tag (keys %{$circuit->{tags}}) {
            push @tags, $tag . ':' . $circuit->{tags}->{$tag};
        }

        $self->{output}->add_disco_entry(
            name => $circuit->{name},
            resourcegroup => $resource_group,
            location => $circuit->{location},
            id => $circuit->{id},
            tags => join(',', @tags),
        );
    }
}

1;

__END__

=head1 MODE

List ExpressRoute circuits.

=over 8

=item B<--resource-group>

Set resource group.

=item B<--location>

Set resource location.

=item B<--filter-name>

Filter resource name (Can be a regexp).

=back

=cut
