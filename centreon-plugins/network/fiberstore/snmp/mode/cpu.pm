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

package network::fiberstore::snmp::mode::cpu;

use base qw(centreon::plugins::templates::counter);

use strict;
use warnings;

sub set_counters {
    my ($self, %options) = @_;

    $self->{maps_counters_type} = [
        { name => 'global', type => 0, skipped_code => { -10 => 1 } }
    ];

    $self->{maps_counters}->{global} = [
        { label => 'cpu-utilization', nlabel => 'cpu.utilization.percentage', set => {
                key_values => [ { name => 'cpu_util' } ],
                output_template => 'Cpu utilization: %.2f%%',
                perfdatas => [
                    { template => '%s', min => 0, max => 100, unit => '%' }
                ]
            }
        }
    ];
}

sub new {
    my ($class, %options) = @_;
    my $self = $class->SUPER::new(package => __PACKAGE__, %options, force_new_perfdata => 1);
    bless $self, $class;
    
    return $self;
}

my $mapping = {
    idle_legacy => { oid => '.1.3.6.1.4.1.27975.1.2.11' }, # ssCpuIdle
    idle        => { oid => '.1.3.6.1.4.1.52642.1.1.2.11' }  # ssCpuIdle
};

sub manage_selection {
    my ($self, %options) = @_;

    my $snmp_result = $options{snmp}->get_leef(
        oids => [ map($_->{oid} . '.0', values(%$mapping)) ],
        nothing_quit => 1
    );
    my $result = $options{snmp}->map_instance(mapping => $mapping, results => $snmp_result, instance => 0);
    my $idle = 'idle';
    $idle = 'idle_legacy' if (!defined($result->{idle}));

    $self->{global} = {
        cpu_util => 100 - $result->{$idle}
    };
}

1;

__END__

=head1 MODE

Check cpu.

=over 8

=item B<--warning-*> B<--critical-*>

Thresholds.
Can be: 'cpu-utilization' (%).

=back

=cut
