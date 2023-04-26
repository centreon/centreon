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

package gorgone::modules::centreon::audit::metrics::system::memory;

use warnings;
use strict;
use gorgone::standard::misc;

sub metrics {
    my (%options) = @_;

    my $metrics = {
        status_code => 0,
        status_message => 'ok',
        ram_total_bytes => 0,
        ram_available_bytes => 0,
        swap_total_bytes => 0,
        swap_free_bytes => 0
    };
    my ($ret, $message, $buffer) = gorgone::standard::misc::slurp(file => '/proc/meminfo');
    if ($ret == 0) {
        $metrics->{status_code} = 1;
        $metrics->{status_message} = $message;
        return $metrics;
    }

    if ($buffer !~ /^MemTotal:\s+(\d+)/mi) {
        $metrics->{status_code} = 1;
        $metrics->{status_message} = 'cannot find memory information';
        return $metrics;
    }

    $metrics->{ram_total_bytes} = $1 * 1024;
    $metrics->{ram_total_human} = join('', gorgone::standard::misc::scale(value => $metrics->{ram_total_bytes}, format => '%.2f'));

    if ($buffer =~ /^MemAvailable:\s+(\d+)/mi) {
        $metrics->{ram_available_bytes} = $1 * 1024;
        $metrics->{ram_available_human} = join('', gorgone::standard::misc::scale(value => $metrics->{ram_available_bytes}, format => '%.2f'));
    }
    if ($buffer =~ /^SwapTotal:\s+(\d+)/mi) {
        $metrics->{swap_total_bytes} = $1 * 1024;
        $metrics->{swap_total_human} = join('', gorgone::standard::misc::scale(value => $metrics->{swap_total_bytes}, format => '%.2f'));
    }
    if ($buffer =~ /^SwapFree:\s+(\d+)/mi) {
        $metrics->{swap_free_bytes} = $1 * 1024;
        $metrics->{swap_free_human} = join('', gorgone::standard::misc::scale(value => $metrics->{swap_free_bytes}, format => '%.2f'));
    }

    return $metrics;
}

1;
