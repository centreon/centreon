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

package storage::huawei::oceanstor::snmp::mode::components::fan;

use strict;
use warnings;
use storage::huawei::oceanstor::snmp::mode::resources qw($health_status $running_status);

my $mapping = {
    id             => { oid => '.1.3.6.1.4.1.34774.4.1.23.5.4.1.1' }, # hwInfoFanID
    location       => { oid => '.1.3.6.1.4.1.34774.4.1.23.5.4.1.2' }, # hwInfoFanLocation
    health_status  => { oid => '.1.3.6.1.4.1.34774.4.1.23.5.4.1.3', map => $health_status }, # hwInfoFanHealthStatus
    running_status => { oid => '.1.3.6.1.4.1.34774.4.1.23.5.4.1.4', map => $running_status } # hwInfoFanRunningStatus
};
my $oid_fan_entry = '.1.3.6.1.4.1.34774.4.1.23.5.4.1'; # hwInfoFanEntry

sub load {
    my ($self) = @_;
    
    push @{$self->{request}}, {
        oid => $oid_fan_entry, 
        end => $mapping->{running_status}->{oid}
    };
}

sub check {
    my ($self) = @_;

    $self->{output}->output_add(long_msg => 'checking fans');
    $self->{components}->{fan} = { name => 'fans', total => 0, skip => 0 };
    return if ($self->check_filter(section => 'fan'));

    foreach my $oid ($self->{snmp}->oid_lex_sort(keys %{$self->{results}->{$oid_fan_entry}})) {
        next if ($oid !~ /^$mapping->{id}->{oid}\.(.*)$/);
        my $result = $self->{snmp}->map_instance(mapping => $mapping, results => $self->{results}->{$oid_fan_entry}, instance => $1);
        my $instance = $result->{id};
        my $name = $result->{location} . ':' . $result->{id};

        next if ($self->check_filter(section => 'fan', instance => $instance, name => $name));
        
        $self->{components}->{fan}->{total}++;
        $self->{output}->output_add(
            long_msg => sprintf(
                "fan '%s' status is '%s' [instance: %s, location: %s, running status: %s]",
                $instance,
                $result->{health_status},
                $instance,
                $result->{location},
                $result->{running_status}
            )
        );
        my $exit = $self->get_severity(label => 'default', section => 'fan', name => $name, value => $result->{health_status});
        if (!$self->{output}->is_status(value => $exit, compare => 'ok', litteral => 1)) {
            $self->{output}->output_add(
                severity => $exit,
                short_msg => sprintf(
                    "Fan '%s' status is '%s'",
                    $instance,
                    $result->{health_status}
                )
            );
        }
    }
}

1;
