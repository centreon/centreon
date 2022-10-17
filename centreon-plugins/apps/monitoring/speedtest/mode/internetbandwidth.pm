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

package apps::monitoring::speedtest::mode::internetbandwidth;

use base qw(centreon::plugins::templates::counter);

use strict;
use warnings;
use JSON::XS;

sub prefix_global_output {
    my ($self, %options) = @_;

    return 'speedtest ';
}

sub set_counters {
    my ($self, %options) = @_;

    $self->{maps_counters_type} = [
        { name => 'global', type => 0, cb_prefix_output => 'prefix_global_output', skipped_code => { -10 => 1 } }
    ];

    $self->{maps_counters}->{global} = [
        { label => 'ping-time', nlabel => 'ping.time.milliseconds', set => {
                key_values => [ { name => 'ping' } ],
                output_template => 'ping time: %d ms',
                perfdatas => [
                    { template => '%.3f', unit => 'ms', min => 0 }
                ]
            }
        },
        { label => 'bandwidth-download', nlabel => 'internet.bandwidth.download.bitspersecond', set => {
                key_values => [ { name => 'download' } ],
                output_template => 'download: %s %s/s',
                output_change_bytes => 2,
                perfdatas => [
                    { template => '%d', unit => 'b/s', min => 0 }
                ]
            }
        },
        { label => 'bandwidth-upload', nlabel => 'internet.bandwidth.upload.bitspersecond', set => {
                key_values => [ { name => 'upload' } ],
                output_template => 'upload: %s %s/s',
                output_change_bytes => 2,
                perfdatas => [
                    { template => '%d', unit => 'b/s', min => 0 }
                ]
            }
        }
    ];
}

sub new {
    my ($class, %options) = @_;
    my $self = $class->SUPER::new(package => __PACKAGE__, %options, force_new_perfdata => 1);
    bless $self, $class;

    $options{options}->add_options(arguments => {});

    return $self;
}

sub manage_selection {
    my ($self, %options) = @_;

    my ($output) = $options{custom}->execute_command(
        command => 'speedtest-cli',
        command_options => '--json'
    );

    my $decoded;
    eval {
        $decoded = JSON::XS->new->utf8->decode($output);
    };
    if ($@) {
        $self->{output}->add_option_msg(short_msg => 'Cannot decode response');
        $self->{output}->option_exit();
    }
    $self->{global} = {
        ping => $decoded->{ping},
        download => $decoded->{download},
        upload => $decoded->{upload}
    };
}

1;

__END__

=head1 MODE

Check internet bandwidth. 

Command used: speedtest-cli --json

=over 8

=item B<--warning-*> B<--critical-*>

Thresholds.
Can be: 'ping-time', 'bandwidth-download', 'bandwidth-upload'.

=back

=cut
