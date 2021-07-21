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

package gorgone::modules::centreon::audit::metrics::system::os;

use warnings;
use strict;

sub metrics {
    my (%options) = @_;

    my $metrics = {
        kernel => {
            status_code => 0,
            status_message => 'ok',
            value => 'n/a'
        }
    };

    my ($error, $stdout, $return_code) = gorgone::standard::misc::backtick(
        command => 'uname -a',
        timeout => 5,
        wait_exit => 1,
        redirect_stderr => 1,
        logger => $options{logger}
    );
    if ($error != 0) {
        $metrics->{kernel}->{status_code} = 1;
        $metrics->{kernel}->{status_message} = $stdout;
    } else {
        $metrics->{kernel}->{value} = $stdout;
    }

    $metrics->{os}->{value} = $options{os};

    return $metrics;
}

1;
