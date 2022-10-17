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

package hardware::server::dell::omem::snmp::mode::components::temperature;

use strict;
use warnings;

my $mapping = {
    dmmChassisFrontPanelAmbientTemperature => { oid => '.1.3.6.1.4.1.674.10892.6.3.1.8', instance => 'chassis', descr => 'chassis ambient temperature' }
};
my $oid_dmmStatusNowGroup = '.1.3.6.1.4.1.674.10892.6.3.1';

sub load {}

sub check {
    my ($self) = @_;

    $self->{output}->output_add(long_msg => "Checking temperatures");
    $self->{components}->{temperature} = { name => 'temperatures', total => 0, skip => 0 };
    return if ($self->check_filter(section => 'temperature'));

    my $result = $self->{snmp}->map_instance(mapping => $mapping, results => $self->{results}->{$oid_dmmStatusNowGroup}, instance => 0);
    foreach my $probe (keys %$mapping) {
        next if (!defined($result->{$probe}));

        next if ($self->check_filter(section => 'temperature', instance => $mapping->{$probe}->{instance}));
        $self->{components}->{temperature}->{total}++;

        $self->{output}->output_add(
            long_msg => sprintf(
                "%s is %dC [instance: %s]",
                $mapping->{$probe}->{descr}, $result->{$probe},
                $mapping->{$probe}->{instance}
            )
        );
        my ($exit, $warn, $crit, $checked) = $self->get_severity_numeric(section => 'temperature', instance => $mapping->{$probe}->{instance}, value => $result->{$probe});
        if (!$self->{output}->is_status(value => $exit, compare => 'ok', litteral => 1)) {
            $self->{output}->output_add(
                severity => $exit,
                short_msg => sprintf("%s is %sC",
                    $mapping->{$probe}->{descr},
                    $result->{$probe}
                )
            );
        }
        $self->{output}->perfdata_add(
            nlabel => 'hardware.temperature.celsius',
            unit => 'C',
            instances => $mapping->{$probe}->{instance},
            value => $result->{$probe},
            warning => $warn,
            critical => $crit
        );
    }
}

1;
