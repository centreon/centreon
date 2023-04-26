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

package gorgone::modules::centreon::audit::metrics::centreon::rrd;

use warnings;
use strict;
use gorgone::standard::misc;

sub metrics {
    my (%options) = @_;

    return undef if (!defined($options{params}->{rrd_metrics_path}));
    return undef if (! -d $options{params}->{rrd_metrics_path});

    my $metrics = {
        status_code => 0,
        status_message => 'ok',
        rrd_metrics_count => 0,
        rrd_status_count => 0,
        rrd_metrics_bytes => 0,
        rrd_status_bytes => 0,
        rrd_metrics_outdated => 0,
        rrd_status_outdated => 0
    };

    my $outdated_time = time() - (180 * 86400);
    my $dh;
    foreach my $type (('metrics', 'status')) {
        if (!opendir($dh, $options{params}->{'rrd_' . $type . '_path'})) {
            $metrics->{status_code} = 1;
            $metrics->{status_message} = "Could not open directoy for reading: $!";
            next;
        }
        while (my $file = readdir($dh)) {
            next if ($file !~ /\.rrd/);
            $metrics->{'rrd_' . $type . '_count'}++;
            my @attrs = stat($options{params}->{'rrd_' . $type . '_path'} . '/' . $file);
            $metrics->{'rrd_' . $type . '_bytes'} += $attrs[7] if (defined($attrs[7]));
            $metrics->{'rrd_' . $type . '_outdated'}++ if ($attrs[9] < $outdated_time);
        }
        closedir($dh);
    }

    $metrics->{rrd_metrics_human} = join('', gorgone::standard::misc::scale(value => $metrics->{rrd_metrics_bytes}, format => '%.2f'));
    $metrics->{rrd_status_human} = join('', gorgone::standard::misc::scale(value => $metrics->{rrd_status_bytes}, format => '%.2f'));

    return $metrics;
}

1;
