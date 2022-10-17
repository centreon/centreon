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

package storage::ibm::fs900::snmp::mode::hardware;

use base qw(centreon::plugins::templates::hardware);

use strict;
use warnings;

sub set_system {
    my ($self, %options) = @_;
    
    $self->{regexp_threshold_numeric_check_section_option} = '^(battery.capacity|battery.current|battery.voltage|fan.pwm|fan.temperature|fan.speed|flashcard.health|flashcard.power|flashcard.temperature|psu.fan)$';

    $self->{cb_hook2} = 'snmp_execute';
    
    $self->{thresholds} = {
        psu => [
            ['success', 'OK'],
            ['error', 'CRITICAL'],
        ],
        fibrechannel => [
            ['None', 'UNKNOWN'],
            ['No Light', 'OK'],
            ['Offline', 'CRITICAL'],
            ['Online', 'OK'],
            ['Disabled', 'OK'],
        ],
    };
    
    $self->{components_path} = 'storage::ibm::fs900::snmp::mode::components';
    $self->{components_module} = ['battery', 'fan', 'fibrechannel', 'flashcard', 'psu'];
}

sub snmp_execute {
    my ($self, %options) = @_;
    
    $self->{snmp} = $options{snmp};
    $self->{results} = $self->{snmp}->get_multiple_table(oids => $self->{request});
}

sub new {
    my ($class, %options) = @_;
    my $self = $class->SUPER::new(package => __PACKAGE__, %options);
    bless $self, $class;

    $options{options}->add_options(arguments => {});

    return $self;
}

1;

__END__

=head1 MODE

Check hardware (batteries, fan modules, fibre channels, flashcards, power supplies).

=over 8

=item B<--component>

Which component to check (Default: 'all').
Can be: 'battery', 'fan', 'fibrechannel', 'flashcard', 'psu'.

=item B<--filter>

Exclude some parts (comma seperated list) (Example: --filter=fan --filter=psu)
Can also exclude specific instance: --filter=fan,1

=item B<--absent-problem>

Return an error if an entity is not 'notAvailable' (default is skipping) (comma seperated list)
Can be specific or global: --absent-problem=fan,2

=item B<--no-component>

Return an error if no compenents are checked.
If total (with skipped) is 0. (Default: 'critical' returns).

=item B<--threshold-overload>

Set to overload default threshold values (syntax: section,[instance,]status,regexp)
It used before default thresholds (order stays).
Example: --threshold-overload='psu,OK,error'

=item B<--warning>

Set warning threshold (syntax: type,instance,threshold)
Example: --warning='battery.capacity,0,10' 

=item B<--critical>

Set critical threshold (syntax: type,instance,threshold)
Example: --critical='battery.voltage,1,1000'

=back

=cut
