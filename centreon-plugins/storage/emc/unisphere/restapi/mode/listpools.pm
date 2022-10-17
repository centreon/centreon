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

package storage::emc::unisphere::restapi::mode::listpools;

use base qw(centreon::plugins::mode);

use strict;
use warnings;
use storage::emc::unisphere::restapi::mode::components::resources qw($health_status);

sub new {
    my ($class, %options) = @_;
    my $self = $class->SUPER::new(package => __PACKAGE__, %options);
    bless $self, $class;
    
    $options{options}->add_options(arguments => {
        'filter-name:s' => { name => 'filter_name' },
    });

    return $self;
}

sub check_options {
    my ($self, %options) = @_;
    $self->SUPER::init(%options);
}

sub manage_selection {
    my ($self, %options) = @_;
    
    return $options{custom}->request_api(url_path => '/api/types/pool/instances?fields=name,health');
}

sub run {
    my ($self, %options) = @_;

    my $pools = $self->manage_selection(%options);
    foreach (@{$pools->{entries}}) {
        next if (defined($self->{option_results}->{filter_name}) && $self->{option_results}->{filter_name} ne ''
            && $_->{content}->{name} !~ /$self->{option_results}->{filter_name}/);
        
        $self->{output}->output_add(long_msg => sprintf(
            '[name = %s][status = %s]',
            $_->{content}->{name},
            $health_status->{ $_->{content}->{health}->{value} },
        ));
    }

    $self->{output}->output_add(
        severity => 'OK',
        short_msg => 'List pools:'
    );
    $self->{output}->display(nolabel => 1, force_ignore_perfdata => 1, force_long_output => 1);
    $self->{output}->exit();
}

sub disco_format {
    my ($self, %options) = @_;
    
    $self->{output}->add_disco_format(elements => ['name', 'status']);
}

sub disco_show {
    my ($self, %options) = @_;

    my $pools = $self->manage_selection(%options);
    foreach (@{$pools->{entries}}) {
        $self->{output}->add_disco_entry(
            name => $_->{content}->{name},
            status => $health_status->{ $_->{content}->{health}->{value} },
        );
    }
}

1;

__END__

=head1 MODE

List pools.

=over 8

=item B<--filter-name>

Filter pool name (Can be a regexp).

=back

=cut
