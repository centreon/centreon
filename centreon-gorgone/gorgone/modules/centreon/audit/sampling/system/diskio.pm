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

package gorgone::modules::centreon::audit::sampling::system::diskio;

use warnings;
use strict;
use gorgone::standard::misc;

sub sample {
    my (%options) = @_;

    if (!defined($options{sampling}->{diskio})) {
        $options{sampling}->{diskio} = {
            status_code => 0,
            status_message => 'ok',
            partitions => {}
        };
    }

    my $time = time();
    my ($ret, $message, $buffer) = gorgone::standard::misc::slurp(file => '/proc/diskstats');
    if ($ret == 0) {
        $options{sampling}->{diskio}->{status_code} = 1;
        $options{sampling}->{diskio}->{status_message} = $message;
        return ;
    }

    while ($buffer =~ /^\s*\S+\s+\S+\s+(\S+)\s+\d+\s+\d+\s+(\d+)\s+(\d+)\s+\d+\s+\d+\s+(\d+)\s+(\d+)\s+\d+\s+\d+\s+(\d+)/mg) {
        my ($partition_name, $read_sector, $write_sector, $read_ms, $write_ms) = ($1, $2, $4, $3, $5);
        next if ($read_sector == 0 && $write_sector == 0);
        if (!defined($options{sampling}->{diskio}->{partitions}->{$partition_name})) {
            $options{sampling}->{diskio}->{partitions}->{$partition_name} = [];
        }
        unshift @{$options{sampling}->{diskio}->{partitions}->{$partition_name}}, [
            $time,
            $read_sector, $write_sector,
            $read_ms, $write_ms
        ];
        if (scalar(@{$options{sampling}->{diskio}->{partitions}->{$partition_name}}) > 60) {
            pop @{$options{sampling}->{diskio}->{partitions}->{$partition_name}};
        }
    }
}

1;
