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

package hardware::server::dell::openmanage::snmp::mode::components::fan;

use strict;
use warnings;

my %map_status = (
    1 => 'other',
    2 => 'unknown',
    3 => 'ok',
    4 => 'nonCriticalUpper',
    5 => 'criticalUpper',
    6 => 'nonRecoverableUpper',
    7 => 'nonCriticalLower',
    8 => 'criticalLower',
    9 => 'nonRecoverableLower',
    10 => 'failed',
);

# In MIB '10892.mib'
my $mapping = {
    coolingDeviceStatus => { oid => '.1.3.6.1.4.1.674.10892.1.700.12.1.5', map => \%map_status },
    coolingDeviceReading => { oid => '.1.3.6.1.4.1.674.10892.1.700.12.1.6' },
};
my $mapping2 = {
    coolingDeviceLocationName => { oid => '.1.3.6.1.4.1.674.10892.1.700.12.1.8' },
};
my $oid_coolingDeviceTableEntry = '.1.3.6.1.4.1.674.10892.1.700.12.1';

sub load {
    my ($self) = @_;
    
    push @{$self->{request}}, { oid => $oid_coolingDeviceTableEntry, start => $mapping->{coolingDeviceStatus}->{oid}, end => $mapping->{coolingDeviceReading}->{oid} },
        { oid => $mapping2->{coolingDeviceLocationName}->{oid} };
}

sub check {
    my ($self) = @_;

    $self->{output}->output_add(long_msg => "Checking fans");
    $self->{components}->{fan} = {name => 'fans', total => 0, skip => 0};
    return if ($self->check_filter(section => 'fan'));

    foreach my $oid ($self->{snmp}->oid_lex_sort(keys %{$self->{results}->{$oid_coolingDeviceTableEntry}})) {
        next if ($oid !~ /^$mapping->{coolingDeviceStatus}->{oid}\.(.*)$/);
        my $instance = $1;
        my $result = $self->{snmp}->map_instance(mapping => $mapping, results => $self->{results}->{$oid_coolingDeviceTableEntry}, instance => $instance);
        my $result2 = $self->{snmp}->map_instance(mapping => $mapping2, results => $self->{results}->{$mapping2->{coolingDeviceLocationName}->{oid}}, instance => $instance);

        next if ($self->check_filter(section => 'fan', instance => $instance));
        
        $self->{components}->{fan}->{total}++;

        $self->{output}->output_add(long_msg => sprintf("Fan '%s' status is '%s' [instance: %s, Location: %s, reading: %s]",
                                    $instance, $result->{coolingDeviceStatus}, $instance, 
                                    $result2->{coolingDeviceLocationName}, $result->{coolingDeviceReading}
                                    ));
        my $exit = $self->get_severity(label => 'default', section => 'fan', value => $result->{coolingDeviceStatus});
        if (!$self->{output}->is_status(value => $exit, compare => 'ok', litteral => 1)) {
            $self->{output}->output_add(severity => $exit,
                                        short_msg => sprintf("Fan '%s' status is '%s'",
                                           $instance, $result->{coolingDeviceStatus}));
        }
        
        if (defined($result->{coolingDeviceReading}) && $result->{coolingDeviceReading} =~ /[0-9]/) {
            my ($exit, $warn, $crit, $checked) = $self->get_severity_numeric(section => 'fan', instance => $instance, value => $result->{coolingDeviceReading});
            
            if (!$self->{output}->is_status(value => $exit, compare => 'ok', litteral => 1)) {
                $self->{output}->output_add(severity => $exit,
                                            short_msg => sprintf("Fan '%s' speed is %s rpm", $instance, $result->{coolingDeviceReading}));
            }
            $self->{output}->perfdata_add(
                label => 'fan', unit => 'rpm',
                nlabel => 'hardware.fan.speed.rpm',
                instances => $instance,
                value => $result->{coolingDeviceReading},
                warning => $warn,
                critical => $crit,
                min => 0
            );
        }
    }
}

1;
