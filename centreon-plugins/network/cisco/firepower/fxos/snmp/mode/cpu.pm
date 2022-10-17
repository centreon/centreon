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

package network::cisco::firepower::fxos::snmp::mode::cpu;

use base qw(centreon::plugins::templates::counter);

use strict;
use warnings;

sub set_counters {
    my ($self, %options) = @_;

    $self->{maps_counters_type} = [
        { name => 'cpu', type => 1, cb_prefix_output => 'prefix_message_output', message_multiple => 'All CPU usages are ok' }
    ];

    $self->{maps_counters}->{cpu} = [
        { label => 'average-1m', nlabel => 'cpu.utilization.1m.percentage', set => {
                key_values => [ { name => 'average_1m' } ],
                output_template => '%.2f %% (1m)',
                perfdatas => [
                    { template => '%.2f', min => 0, max => 100, unit => '%', label_extra_instance => 1 }
                ]
            }
        },
        { label => 'average-5m', nlabel => 'cpu.utilization.5m.percentage', set => {
                key_values => [ { name => 'average_5m' } ],
                output_template => '%.2f %% (5m)',
                perfdatas => [
                    { template => '%.2f', min => 0, max => 100, unit => '%', label_extra_instance => 1 }
                ]
            }
        },
        { label => 'average-15m', nlabel => 'cpu.utilization.15m.percentage', set => {
                key_values => [ { name => 'average_15m' } ],
                output_template => '%.2f %% (15m)',
                perfdatas => [
                    { template => '%.2f', min => 0, max => 100, unit => '%', label_extra_instance => 1 }
                ]
            }
        }
    ];
}

sub prefix_message_output {
    my ($self, %options) = @_;

    return "Security module '" . $options{instance_value}->{display} . "' CPU average usage: ";
}

sub new {
    my ($class, %options) = @_;
    my $self = $class->SUPER::new(package => __PACKAGE__, %options, force_new_perfdata => 1);
    bless $self, $class;

    $options{options}->add_options(arguments => {
        'filter-security-module:s' => { name => 'filter_security_module' }
    });

    return $self;
}

my $mapping = {
    display     => { oid => '.1.3.6.1.4.1.9.9.826.1.71.20.1.2' }, # cfprSmMonitorDn
    average_15m => { oid => '.1.3.6.1.4.1.9.9.826.1.71.20.1.5' }, # cfprSmMonitorCpuTotalLoadAvg15min
    average_1m  => { oid => '.1.3.6.1.4.1.9.9.826.1.71.20.1.6' }, # cfprSmMonitorCpuTotalLoadAvg1min
    average_5m  => { oid => '.1.3.6.1.4.1.9.9.826.1.71.20.1.7' }  # cfprSmMonitorCpuTotalLoadAvg5min
};
my $oid_cfprSmMonitorEntry = '.1.3.6.1.4.1.9.9.826.1.71.20.1';

sub manage_selection {
    my ($self, %options) = @_;

    my $snmp_result = $options{snmp}->get_multiple_table(
        oids => [
            { oid => $mapping->{display}->{oid} },
            { oid => $oid_cfprSmMonitorEntry, start => $mapping->{average_15m}->{oid}, end => $mapping->{average_5m}->{oid} }
        ],
        return_type => 1,
        nothing_quit => 1
    );

    $self->{cpu} = {};
    foreach my $oid (keys %$snmp_result) {
        next if ($oid !~ /^$mapping->{display}->{oid}\.(.*)$/);
        my $instance = $1;
        my $result = $options{snmp}->map_instance(mapping => $mapping, results => $snmp_result, instance => $instance);

        # remove 'monitor': sec-svc/slot-1/monitor
        $result->{display} =~ s/\/([^\/]*?)$//;
        if (defined($self->{option_results}->{filter_security_module}) && $self->{option_results}->{filter_security_module} ne '' &&
            $result->{display} !~ /$self->{option_results}->{filter_security_module}/) {
            $self->{output}->output_add(long_msg => "skipping '" . $result->{display} . "': no matching filter.", debug => 1);
            next;
        }

        $self->{cpu}->{ $result->{display} } = $result;
    }
}

1;

__END__

=head1 MODE

Check CPU usage.

=over 8

=item B<--filter-security-module>

Filter security module name.

=item B<--warning-*> B<--critical-*>

Thresholds.
Can be: 'average-1m' (%), 'average-5m' (%), 'average-15m' (%).

=back

=cut
