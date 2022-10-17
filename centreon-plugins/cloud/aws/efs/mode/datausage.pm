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

package cloud::aws::efs::mode::datausage;

use base qw(cloud::aws::custom::mode);

use strict;
use warnings;

sub get_metrics_mapping {
    my ($self, %options) = @_;

    my $metrics_mapping = {
        extra_params => {
            message_multiple => 'All FS metrics are ok'
        },
        metrics => {
            DataReadIOByte => {
                output    => 'Data Read IO Bytes',
                label     => 'data-iobytes-read',
                nlabel    => {
                    absolute => 'efs.data.iobytes.read.bytes',
                    per_second => 'efs.data.iobytes.read.bytespersecond'
                },
                unit => 'B'
            },
            DataWriteIOBytes => {
                output    => 'Data Write IO Bytes',
                label     => 'data-iobytes-write',
                nlabel    => {
                    absolute      => 'efs.data.iobytes.write.bytes',
                    per_second    => 'efs.data.iobytes.write.bytespersecond'
                },
                unit => 'B'
            },
            MetaDataIOBytes => {
                output    => 'MetaData IO Bytes',
                label     => 'metadata-iobytes',
                nlabel    => {
                    absolute   => 'efs.metadata.iobytes.bytes',
                    per_second => 'efs.metadata.iobytes.bytespersecond'
                },
                unit => 'B'
            },
            TotalIOBytes => {
                output => 'Total IO Bytes',
                label => 'total-iobytes',
                nlabel => {
                    absolute      => 'efs.total.iobytes.bytes',
                    per_second    => 'efs.total.iobytes.bytespersecond'
                },
                unit => 'B'
            },
            BurstCreditBalance => {
                output => 'Burst Credit Balance Bytes',
                label => 'burst-bytes',
                nlabel => {
                    absolute      => 'efs.creditbalance.burst.bytes',
                    per_second    => 'efs.creditbalance.burst.bytespersecond'
                },
                unit => 'B'
            }
        }
    };

    return $metrics_mapping;
}

sub long_output {
    my ($self, %options) = @_;

    return "EFS FileSystemId '" . $options{instance_value}->{display} . "' ";
}

sub new {
    my ($class, %options) = @_;
    my $self = $class->SUPER::new(package => __PACKAGE__, %options, force_new_perfdata => 1);
    bless $self, $class;

    $options{options}->add_options(arguments => {
        'name:s@' => { name => 'name' }
    });
    
    return $self;
}

sub check_options {
    my ($self, %options) = @_;
    $self->SUPER::check_options(%options);

    if (!defined($self->{option_results}->{name}) || $self->{option_results}->{name} eq '') {
        $self->{output}->add_option_msg(short_msg => "Need to specify --name option.");
        $self->{output}->option_exit();
    }

    foreach my $instance (@{$self->{option_results}->{name}}) {
        if ($instance ne '') {
            push @{$self->{aws_instance}}, $instance;
        }
    }
}

sub manage_selection {
    my ($self, %options) = @_;

    my %metric_results;
    foreach my $instance (@{$self->{aws_instance}}) {
        $metric_results{$instance} = $options{custom}->cloudwatch_get_metrics(
            namespace => 'AWS/EFS',
            dimensions => [ { Name => "FileSystemId", Value => $instance } ],
            metrics => $self->{aws_metrics},
            statistics => $self->{aws_statistics},
            timeframe => $self->{aws_timeframe},
            period => $self->{aws_period}
        );

        foreach my $metric (@{$self->{aws_metrics}}) {
            foreach my $statistic (@{$self->{aws_statistics}}) {
                next if (!defined($metric_results{$instance}->{$metric}->{lc($statistic)}) &&
                    !defined($self->{option_results}->{zeroed}));

                $self->{metrics}->{$instance}->{display} = $instance;
                $self->{metrics}->{$instance}->{statistics}->{lc($statistic)}->{display} = $statistic;
                $self->{metrics}->{$instance}->{statistics}->{lc($statistic)}->{timeframe} = $self->{aws_timeframe};
                $self->{metrics}->{$instance}->{statistics}->{lc($statistic)}->{$metric} = 
                    defined($metric_results{$instance}->{$metric}->{lc($statistic)}) ? 
                    $metric_results{$instance}->{$metric}->{lc($statistic)} : 0;
            }
        }
    }

    if (scalar(keys %{$self->{metrics}}) <= 0) {
        $self->{output}->add_option_msg(short_msg => 'No metrics. Check your options or use --zeroed option to set 0 on undefined values');
        $self->{output}->option_exit();
    }
}

1;

__END__

=head1 MODE

Check EFS FileSystem Data IO metrics.

Example:
perl centreon_plugins.pl --plugin=cloud::aws::efs::plugin --custommode=paws --mode=datausage --region='eu-west-1'
--name='fs-1234abcd' --filter-metric='DataReadIOBytes' --warning-data-iobytes-read='5' --critical-data-iobytes-read='10' --verbose

See 'https://docs.aws.amazon.com/efs/latest/ug/monitoring-cloudwatch.html' for more information.


=over 8

=item B<--name>

Set the instance name (Required) (Can be multiple).

=item B<--filter-metric>

Filter on a specific metric 
Can be: DataReadIOBytes, DataWriteIOBytes, MetaDataIOBytes, TotalIOBytes, BurstCreditBalance

=item B<--statistic>

Set the metric calculation method (Default: Average)
Can be 'minimum', 'maximum', 'average', 'sum'

=item B<--warning-$metric$>

Thresholds warning ($metric$ can be: 'data-iobytes-read', 'data-iobytes-write', 'metadata-iobytes', 'total-iobytes', 'burst-bytes').

=item B<--critical-$metric$>

Thresholds critical ($metric$ can be: 'data-iobytes-read', 'data-iobytes-write', 'metadata-iobytes', 'total-iobytes', 'burst-bytes').

=back

=cut
