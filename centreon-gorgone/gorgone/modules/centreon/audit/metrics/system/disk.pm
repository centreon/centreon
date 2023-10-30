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

package gorgone::modules::centreon::audit::metrics::system::disk;

use warnings;
use strict;
use gorgone::standard::misc;

sub metrics {
    my (%options) = @_;

    my $metrics = {
        status_code => 0,
        status_message => 'ok',
        partitions => {}
    };

    my ($error, $stdout, $return_code) = gorgone::standard::misc::backtick(
        command => 'df -P -k -T',
        timeout => 5,
        wait_exit => 1,
        redirect_stderr => 1,
        logger => $options{logger}
    );
    if ($error != 0) {
        $metrics->{status_code} = 1;
        $metrics->{status_message} = $stdout;
        return $metrics;
    }

    foreach my $line (split(/\n/, $stdout)) {
        next if ($line !~ /^(\S+)\s+(\S+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\S+)\s+(.*)/);
        $metrics->{partitions}->{$7} = {
            mount => $7,
            filesystem => $1,
            type => $2,
            space_size_bytes => $3 * 1024,
            space_size_human => join('', gorgone::standard::misc::scale(value => $3 * 1024, format => '%.2f')),
            space_used_bytes => $4 * 1024,
            space_used_human => join('', gorgone::standard::misc::scale(value => $4 * 1024, format => '%.2f')),
            space_free_bytes => $5 * 1024,
            space_free_human => join('', gorgone::standard::misc::scale(value => $5 * 1024, format => '%.2f')),
            inodes_used_percent => $6
        };
    }

    return $metrics;
}

1;
