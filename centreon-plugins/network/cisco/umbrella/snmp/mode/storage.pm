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

package network::cisco::umbrella::snmp::mode::storage;

use base qw(centreon::plugins::templates::counter);

use strict;
use warnings;

sub set_counters {
    my ($self, %options) = @_;

    $self->{maps_counters_type} = [
        { name => 'global', type => 0, skipped_code => { -10 => 1 } },
    ];
    $self->{maps_counters}->{global} = [
        { label => 'usage', nlabel => 'storage.utilization.percentage', set => {
                key_values => [ { name => 'usage' } ],
                output_template => 'Storage usage: %.2f %%',
                perfdatas => [
                    { template => '%.2f', min => 0, max => 100, unit => '%' }
                ]
            }
        },
    ];
}

sub new {
    my ($class, %options) = @_;
    my $self = $class->SUPER::new(package => __PACKAGE__, %options, force_new_perfdata => 1);
    bless $self, $class;

    $options{options}->add_options(arguments => { });

    return $self;
}

sub manage_selection {
    my ($self, %options) = @_;

    my $oid_storageUsage = '.1.3.6.1.4.1.8072.1.3.2.4.1.2.9.100.105.115.107.117.115.97.103.101.1';
    my $snmp_results = $options{snmp}->get_leef(
        oids => [ $oid_storageUsage ],
        nothing_quit => 1
    );

    $self->{global} = { usage => $snmp_results->{$oid_storageUsage} };
}

1;

__END__

=head1 MODE

Check system storage usage in percent.

=over 8

=item B<--warning-usage>

Warning threshold for disk usage in percent.

=item B<--critical-usage>

Critical threshold for disk usage in percent.

=back

=cut
