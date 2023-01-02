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

package gorgone::modules::centreon::audit::metrics::system::diskio;

use warnings;
use strict;

sub metrics {
    my (%options) = @_;

    my $metrics = {
        status_code => 0,
        status_message => 'ok',
        partitions => {}
    };
    if ($options{sampling}->{diskio}->{status_code} != 0) {
        $metrics->{status_code} = $options{sampling}->{diskio}->{status_code};
        $metrics->{status_message} = $options{sampling}->{diskio}->{status_message};
        return $metrics;
    }

    foreach my $partname (keys %{$options{sampling}->{diskio}->{partitions}}) {
        $metrics->{partitions}->{$partname} = {};
        foreach (([1, '1min'], [4, '5min'], [14, '15min'], [59, '60min'])) {
            $metrics->{partitions}->{$partname}->{ 'read_iops_' . $_->[1] . '_bytes' } = 'n/a';
            $metrics->{partitions}->{$partname}->{ 'write_iops_' . $_->[1] . '_bytes' } = 'n/a';
            $metrics->{partitions}->{$partname}->{ 'read_time_' . $_->[1] . '_ms' } = 'n/a';
            $metrics->{partitions}->{$partname}->{ 'write_time_' . $_->[1] . '_ms' } = 'n/a';
            next if (!defined($options{sampling}->{diskio}->{partitions}->{$partname}->[ $_->[0] ]));

            $metrics->{partitions}->{$partname}->{ 'read_iops_' . $_->[1] . '_bytes' } = sprintf(
                '%.2f',
                    ($options{sampling}->{diskio}->{partitions}->{$partname}->[0]->[1] - $options{sampling}->{diskio}->{partitions}->{$partname}->[ $_->[0] ]->[1])
                    / ($options{sampling}->{diskio}->{partitions}->{$partname}->[0]->[0] - $options{sampling}->{diskio}->{partitions}->{$partname}->[ $_->[0] ]->[0])
            );
            $metrics->{partitions}->{$partname}->{ 'read_iops_' . $_->[1] . '_human' } = join('', gorgone::standard::misc::scale(value => $metrics->{partitions}->{$partname}->{ 'read_iops_' . $_->[1] . '_bytes' }, format => '%.2f'));

            $metrics->{partitions}->{$partname}->{ 'write_iops_' . $_->[1] . '_bytes' } = sprintf(
                '%.2f',
                    ($options{sampling}->{diskio}->{partitions}->{$partname}->[0]->[2] - $options{sampling}->{diskio}->{partitions}->{$partname}->[ $_->[0] ]->[2])
                    / ($options{sampling}->{diskio}->{partitions}->{$partname}->[0]->[0] - $options{sampling}->{diskio}->{partitions}->{$partname}->[ $_->[0] ]->[0])
            );
            $metrics->{partitions}->{$partname}->{ 'write_iops_' . $_->[1] . '_human' } = join('', gorgone::standard::misc::scale(value => $metrics->{partitions}->{$partname}->{ 'write_iops_' . $_->[1] . '_bytes' }, format => '%.2f'));

            $metrics->{partitions}->{$partname}->{ 'read_time_' . $_->[1] . '_ms' } = sprintf(
                '%s', ($options{sampling}->{diskio}->{partitions}->{$partname}->[0]->[3] - $options{sampling}->{diskio}->{partitions}->{$partname}->[ $_->[0] ]->[3])
            );
            $metrics->{partitions}->{$partname}->{ 'write_time_' . $_->[1] . '_ms' } = sprintf(
                '%s', ($options{sampling}->{diskio}->{partitions}->{$partname}->[0]->[4] - $options{sampling}->{diskio}->{partitions}->{$partname}->[ $_->[0] ]->[4])
            );
        }
    }

    return $metrics;
}

1;
