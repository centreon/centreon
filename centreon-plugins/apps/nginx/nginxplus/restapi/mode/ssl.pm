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

package apps::nginx::nginxplus::restapi::mode::ssl;

use base qw(centreon::plugins::templates::counter);

use strict;
use warnings;
use Digest::MD5 qw(md5_hex);

sub prefix_ssl_output {
    my ($self, %options) = @_;
    
    return 'Ssl ';
}

sub set_counters {
    my ($self, %options) = @_;

    $self->{maps_counters_type} = [
        { name => 'ssl', type => 0, cb_prefix_output => 'prefix_ssl_output' }
    ];

    $self->{maps_counters}->{ssl} = [
        { label => 'handshakes-succeeded', nlabel => 'ssl.handshakes.succeeded.count', set => {
                key_values => [ { name => 'handshakes', diff => 1 } ],
                output_template => 'handshakes succeeded: %s',
                perfdatas => [
                    { template => '%s', min => 0 }
                ]
            }
        },
        { label => 'handshakes-failed', nlabel => 'ssl.handshakes.failed.count', set => {
                key_values => [ { name => 'handshakes_failed', diff => 1 } ],
                output_template => 'handshakes failed: %s',
                perfdatas => [
                    { template => '%s', min => 0 }
                ]
            }
        },
        { label => 'sessions-reuses', nlabel => 'ssl.sessions.reuses.count', set => {
                key_values => [ { name => 'session_reuses', diff => 1 } ],
                output_template => 'session reuses: %s',
                perfdatas => [
                    { template => '%s', min => 0 }
                ]
            }
        }
    ];
}

sub new {
    my ($class, %options) = @_;
    my $self = $class->SUPER::new(package => __PACKAGE__, %options, statefile => 1, force_new_perfdata => 1);
    bless $self, $class;

    $options{options}->add_options(arguments => {
    });

    return $self;
}

sub manage_selection {
    my ($self, %options) = @_;

    my $result = $options{custom}->request_api(
        endpoint => '/ssl'
    );

    $self->{ssl} = {};
    foreach (keys %$result) {
        $self->{ssl}->{$_} = $result->{$_};
    }

    $self->{cache_name} = 'nginx_nginxplus_' . $options{custom}->get_hostname()  . '_' . $self->{mode} . '_' .
        (defined($self->{option_results}->{filter_counters}) ? md5_hex($self->{option_results}->{filter_counters}) : md5_hex('all'));
}

1;

__END__

=head1 MODE

Check ssl statistics.

=over 8

=item B<--filter-counters>

Only display some counters (regexp can be used).
Example: --filter-counters='failed'

=item B<--warning-*> B<--critical-*>

Thresholds.
Can be: 'handshakes-succeeded', 'handshakes-failed', 'sessions-reuses'.

=back

=cut
