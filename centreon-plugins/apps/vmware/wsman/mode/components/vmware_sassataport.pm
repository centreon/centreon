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

package apps::vmware::wsman::mode::components::vmware_sassataport;

use strict;
use warnings;

sub load {}

sub check {
    my ($self) = @_;
    
    my $result = $self->{wsman}->request(uri => 'http://schemas.vmware.com/wbem/wscim/1/cim-schema/2/VMware_SASSATAPort', dont_quit => 1);
    
    $self->{output}->output_add(long_msg => "Checking vmware sas/sata ports");
    $self->{components}->{vmware_sassataport} = {name => 'sas/sata ports', total => 0, skip => 0};
    return if ($self->check_filter(section => 'vmware_sassataport') || !defined($result));

    foreach (@{$result}) {
        my $instance = defined($_->{Name}) && $_->{Name} ne '' ? $_->{Name} : $_->{ElementName};
        
        next if ($self->check_filter(section => 'vmware_sassataport', instance => $instance));
        my $status = $self->get_status(entry => $_);
        if (!defined($status)) {
            $self->{output}->output_add(long_msg => sprintf("skipping sas/sata port '%s' : no status", $_->{ElementName}), debug => 1);
            next;
        }
        
        $self->{components}->{vmware_sassataport}->{total}++;

        $self->{output}->output_add(long_msg => sprintf("Sas/Sata port '%s' status is '%s' [instance: %s].",
                                    $_->{ElementName}, $status,
                                    $instance
                                    ));
        my $exit = $self->get_severity(section => 'vmware_sassataport', label => 'default', value => $status);
        if (!$self->{output}->is_status(value => $exit, compare => 'ok', litteral => 1)) {
            $self->{output}->output_add(severity =>  $exit,
                                        short_msg => sprintf("Sas/Sata port '%s' status is '%s'",
                                                             $_->{ElementName}, $status));
        }
    }
}

1;
