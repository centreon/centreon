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

package gorgone::modules::centreon::audit::metrics::system::load;

use warnings;
use strict;
use gorgone::standard::misc;

sub metrics {
    my (%options) = @_;

    my $metrics = {
        status_code => 0,
        status_message => 'ok'
    };
    my ($ret, $message, $buffer) = gorgone::standard::misc::slurp(file => '/proc/loadavg');
    if ($ret == 0) {
        $metrics->{status_code} = 1;
        $metrics->{status_message} = $message;
        return $metrics;
    }

    if ($buffer !~ /^([0-9\.]+)\s+([0-9\.]+)\s+([0-9\.]+)/mi) {
        $metrics->{status_code} = 1;
        $metrics->{status_message} = 'cannot find load information';
        return $metrics;
    }

    $metrics->{load1m} = $1;
    $metrics->{load5m} = $2;
    $metrics->{load15m} = $3;
    return $metrics;
}

1;
