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

package network::colubris::snmp::mode::storage;

use base qw(centreon::plugins::templates::counter);

use strict;
use warnings;

sub set_counters {
    my ($self, %options) = @_;
    
    $self->{maps_counters_type} = [
        { name => 'storage', type => 0 }
    ];
    
    $self->{maps_counters}->{storage} = [
        { label => 'permanent-usage', set => {
                key_values => [ { name => 'perm_used' } ],
                output_template => 'Permanent Storage Used: %.2f%%',
                perfdatas => [
                    { label => 'storage_permanent_used', value => 'perm_used', template => '%.2f',
                      min => 0, max => 100, unit => '%' },
                ],
            }
        },
        { label => 'temporary-usage', set => {
                key_values => [ { name => 'temp_used' } ],
                output_template => 'Temporary Storage Used: %.2f%%',
                perfdatas => [
                    { label => 'storage_temporary_used', value => 'temp_used', template => '%.2f',
                      min => 0, max => 100, unit => '%' },
                ],
            }
        },
    ];
}

sub new {
    my ($class, %options) = @_;
    my $self = $class->SUPER::new(package => __PACKAGE__, %options);
    bless $self, $class;
    
    $options{options}->add_options(arguments => { 
    });

    return $self;
}

sub manage_selection {
    my ($self, %options) = @_;

    my $oid_coUsInfoStorageUsePermanent = '.1.3.6.1.4.1.8744.5.21.1.1.13.0';
    my $oid_coUsInfoStorageUseTemporary = '.1.3.6.1.4.1.8744.5.21.1.1.14.0';
    my $snmp_result = $options{snmp}->get_leef(
        oids => [
            $oid_coUsInfoStorageUsePermanent, $oid_coUsInfoStorageUseTemporary,
        ],
        nothing_quit => 1
    );

    $self->{storage} = {
        perm_used => $snmp_result->{$oid_coUsInfoStorageUsePermanent},
        temp_used => $snmp_result->{$oid_coUsInfoStorageUseTemporary},
    };
}

1;

__END__

=head1 MODE

Check storage usage.

=over 8

=item B<--warning-*>

Threshold warning.
Can be: 'permanent-usage' (%), 'temporary-usage' (%).

=item B<--critical-*>

Threshold critical.
Can be: 'permanent-usage' (%), 'temporary-usage' (%).


=back

=cut
