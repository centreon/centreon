#
# Copyright 2022 Centreon (http://www.centreon.com/)
#
# Centreon is a full-fledged industry-strength solution that meets
# the needs in IT infrastructure and alarm monitoring for
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

package apps::monitoring::netdata::restapi::mode::load;

use base qw(centreon::plugins::templates::counter);

use strict;
use warnings;

sub custom_load_output {
    my ($self, %options) = @_;

    my $msg = sprintf('%.2f', $self->{result_values}->{ $self->{key_values}->[0]->{name} });
    if ($self->{result_values}->{cpu} > 0) {
        $msg .= sprintf(
            ' [%.2f/%s CPUs]',
            $self->{result_values}->{ $self->{key_values}->[1]->{name} },
            $self->{result_values}->{cpu}
        );
    }

    $self->{key_values}->[0]->{name} =~ /load(\d+)/;
    $msg .= ' (' . $1 .'m)';
    return $msg;
}

sub prefix_load_output {
    my ($self, %options) = @_;

    return 'Load average ';
}

sub set_counters {
    my ($self, %options) = @_;

    $self->{maps_counters_type} = [
        { name => 'loadaverage', type => 0, skipped_code => { -10 => 1 }, cb_prefix_output => 'prefix_load_output' }
    ];

    $self->{maps_counters}->{loadaverage} = [
        { label => 'load1', nlabel => 'system.loadaverage.1m.count', set => {
                key_values => [ { name => 'load1' }, { name => 'load1abs' }, { name => 'cpu' } ],
                closure_custom_output => $self->can('custom_load_output'),
                output_template => '%.2f (1m)',
                perfdatas => [
                    { template => '%.2f', min => 0 }
                ]
            }
        },
        { label => 'load5', nlabel => 'system.loadaverage.5m.count', set => {
                key_values => [ { name => 'load5' }, { name => 'load5abs' }, { name => 'cpu' } ],
                closure_custom_output => $self->can('custom_load_output'),
                output_template => '%.2f (5m)',
                perfdatas => [
                    { template => '%.2f', min => 0 }
                ]
            }
        },
        { label => 'load15', nlabel => 'system.loadaverage.15m.count', set => {
                key_values => [ { name => 'load15' }, { name => 'load15abs' }, { name => 'cpu' } ],
                closure_custom_output => $self->can('custom_load_output'),
                output_template => '%.2f (15m)',
                perfdatas => [
                    { template => '%.2f', min => 0 }
                ]
            }
        }
    ];
}

sub new {
    my ($class, %options) = @_;
    my $self = $class->SUPER::new(package => __PACKAGE__, %options, force_new_perfdata => 1);
    bless $self, $class;

    $options{options}->add_options(arguments => {
        'chart-period:s'     => { name => 'chart_period', default => '300' },
        'chart-statistics:s' => { name => 'chart_statistics', default => 'average' },
        'average'            => { name => 'average' }
    });

    return $self;
}

sub manage_selection {
    my ($self, %options) = @_;

    my $result = $options{custom}->get_data(
        chart => 'system.load',
        points => $self->{option_results}->{chart_point},
        after_period => $self->{option_results}->{chart_period},
        group => $self->{option_results}->{chart_statistics}
    );
    foreach my $load_value (@{$result->{data}}) {
        foreach my $load_label (@{$result->{labels}}) {
            $self->{load_data}->{$load_label} = shift @{$load_value};
        }
    }

    $self->{loadaverage} = {
        load1  => $self->{load_data}->{load1},
        load5  => $self->{load_data}->{load5},
        load15 => $self->{load_data}->{load15},
        load1abs => $self->{load_data}->{load1},
        load5abs => $self->{load_data}->{load5},
        load15abs => $self->{load_data}->{load15},
        cpu => 0
    };

    if (defined($self->{option_results}->{average})) {
        my $cpu_number = $options{custom}->get_info(filter_info => 'cores_total');
        $self->{loadaverage}->{load1} = $self->{load_data}->{load1} / $cpu_number;
        $self->{loadaverage}->{load5} = $self->{load_data}->{load5} / $cpu_number;
        $self->{loadaverage}->{load15} = $self->{load_data}->{load15} / $cpu_number;
        $self->{loadaverage}->{cpu} = $cpu_number;
    }
};

1;

__END__

=head1 MODE

Check the average load of *nix based servers using the Netdata agent RestAPI.

Example:
perl centreon-plugins/centreon_plugins.pl --plugin=apps::monitoring::netdata::restapi::plugin
--mode=load --hostname=10.0.0.1 --chart-period=300 --warning-load15='4' --critical-load15='5' --verbose

More information on 'https://learn.netdata.cloud/docs/agent/web/api'.

=over 8

=item B<--chart-period>

The period in seconds on which the values are calculated.
Default: 300

=item B<--chart-statistic>

The statistic calculation method used to parse the collected data.
Can be : average, sum, min, max.
Default: average

=item B<--average>

Load average for the number of CPUs.

=item B<--warning-*> B<--critical-*>

Threshold where '*' can be: load1, load5, load15

=back

=cut
