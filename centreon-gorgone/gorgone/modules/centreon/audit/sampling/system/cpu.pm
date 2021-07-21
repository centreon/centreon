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

package gorgone::modules::centreon::audit::sampling::system::cpu;

use warnings;
use strict;
use gorgone::standard::misc;

sub sample {
    my (%options) = @_;

    if (!defined($options{sampling}->{cpu})) {
        $options{sampling}->{cpu} = {
            status_code => 0,
            status_message => 'ok',
            round => 0,
            values => []
        };
    }

    $options{sampling}->{cpu}->{round}++;
    my ($ret, $message, $buffer) = gorgone::standard::misc::slurp(file => '/proc/stat');
    if ($ret == 0) {
        $options{sampling}->{cpu}->{status_code} = 1;
        $options{sampling}->{cpu}->{status_message} = $message;
        return ;
    }

    if ($buffer !~ /^cpu\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/) {
        $options{sampling}->{cpu}->{status_code} = 1;
        $options{sampling}->{cpu}->{status_message} = 'cannot find cpu information';
        return ;
    }

    $options{sampling}->{cpu}->{num_cpu} = 0;
    while ($buffer =~ /^cpu(\d+)/mg) {
        $options{sampling}->{cpu}->{num_cpu}++;
    }

    unshift @{$options{sampling}->{cpu}->{values}}, [
        $1 + $2 + $3 + $4 + $5 + $6 + $7,
        $4,
        $5
    ];
    if (scalar(@{$options{sampling}->{cpu}->{values}}) > 60) {
        pop @{$options{sampling}->{cpu}->{values}};
    }
}

1;
