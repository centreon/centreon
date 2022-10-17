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

package network::alcatel::omniswitch::snmp::mode::components::unknown;

use strict;
use warnings;
use network::alcatel::omniswitch::snmp::mode::components::resources qw(%oids $mapping);

sub load {}

sub check {
    my ($self) = @_;

    $self->{output}->output_add(long_msg => "Checking unknown");
    $self->{components}->{unknown} = {name => 'unknowns', total => 0, skip => 0};
    return if ($self->check_filter(section => 'unknown'));
    
    my @instances = ();
    foreach my $key (keys %{$self->{results}->{$oids{common}->{entPhysicalClass}}}) {
        if ($self->{results}->{$oids{common}->{entPhysicalClass}}->{$key} == 2) {
            next if ($key !~ /^$oids{common}->{entPhysicalClass}\.(.*)$/);
            push @instances, $1;
        }
    }
    
    foreach my $instance (@instances) {
        next if (!defined($self->{results}->{entity}->{$oids{$self->{type}}->{chasEntPhysAdminStatus} . '.' . $instance}));
        
        my $result = $self->{snmp}->map_instance(mapping => $mapping->{$self->{type}}, results => $self->{results}->{entity}, instance => $instance);
        
        next if ($self->check_filter(section => 'unknown', instance => $instance));
        $self->{components}->{unknown}->{total}++;
        
        $self->{output}->output_add(
            long_msg => sprintf(
                "unknown '%s/%s' [instance: %s, admin status: %s] operationnal status is %s.",
                $result->{entPhysicalName}, $result->{entPhysicalDescr}, $instance, 
                $result->{chasEntPhysAdminStatus}, $result->{chasEntPhysOperStatus}
            )
        );

        if ($result->{chasEntPhysPower} > 0) {
            $self->{output}->perfdata_add(
                nlabel => [$result->{entPhysicalName}, $result->{entPhysicalDescr}, $instance],
                unit => 'W',
                instances => $instance,
                value => $result->{chasEntPhysPower},
                min => 0
            );
        }
        
        my $exit = $self->get_severity(label => 'admin', section => 'unknown.admin', value => $result->{chasEntPhysAdminStatus});
        if (!$self->{output}->is_status(value => $exit, compare => 'ok', litteral => 1)) {
            $self->{output}->output_add(
                severity => $exit,
                short_msg => sprintf(
                    "unknown '%s/%s/%s' admin status is %s",
                    $result->{entPhysicalName}, $result->{entPhysicalDescr}, $instance, 
                    $result->{chasEntPhysAdminStatus}
                )
            );
            next;
        }

        $exit = $self->get_severity(label => 'oper', section => 'unknown.oper', value => $result->{chasEntPhysOperStatus});
        if (!$self->{output}->is_status(value => $exit, compare => 'ok', litteral => 1)) {
            $self->{output}->output_add(
                severity => $exit,
                short_msg => sprintf(
                    "unknown '%s/%s/%s' operational status is %s",
                    $result->{entPhysicalName}, $result->{entPhysicalDescr}, $instance, 
                    $result->{chasEntPhysOperStatus}
                )
            );
        }
    }
}

1;
