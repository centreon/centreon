# 
# Copyright 2019 Centreon (http://www.centreon.com/)
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

package gorgone::modules::centreon::audit::metrics::system::cpu;

use warnings;
use strict;

sub metrics {
    my (%options) = @_;

    my $metrics = {
        status_code => 0,
        status_message => 'ok',
        num_cpu => 0
    };
    if ($options{sampling}->{cpu}->{status_code} != 0) {
        $metrics->{status_code} = $options{sampling}->{cpu}->{status_code};
        $metrics->{status_message} = $options{sampling}->{cpu}->{status_message};
        return $metrics;
    }

    $metrics->{num_cpu} = $options{sampling}->{cpu}->{num_cpu};
    foreach (([1, '1min'], [4, '5min'], [14, '15min'], [59, '60min'])) {
        $metrics->{ 'avg_used_' . $_->[1] } = 'n/a';
        $metrics->{ 'avg_iowait_' . $_->[1] } = 'n/a';
        next if (!defined($options{sampling}->{cpu}->{values}->[ $_->[0] ]));
        $metrics->{ 'avg_used_' . $_->[1] } = sprintf(
            '%.2f',
            100 - (
                100 * ($options{sampling}->{cpu}->{values}->[0]->[1] - $options{sampling}->{cpu}->{values}->[ $_->[0] ]->[1])
                / ($options{sampling}->{cpu}->{values}->[0]->[0] - $options{sampling}->{cpu}->{values}->[ $_->[0] ]->[0])
            )
        );
        $metrics->{ 'avg_iowait_' . $_->[1] } = sprintf(
            '%.2f',
                100 * ($options{sampling}->{cpu}->{values}->[0]->[2] - $options{sampling}->{cpu}->{values}->[ $_->[0] ]->[2])
                / ($options{sampling}->{cpu}->{values}->[0]->[0] - $options{sampling}->{cpu}->{values}->[ $_->[0] ]->[0])
        );
    }

    return $metrics;
}

1;
