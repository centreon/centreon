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

package gorgone::modules::centreon::audit::metrics::centreon::packages;

use warnings;
use strict;
use gorgone::standard::misc;

sub dpkg_list {
    my (%options) = @_;

    my ($error, $stdout, $return_code) = gorgone::standard::misc::backtick(
        command => "dpkg-query -W -f='\${binary:Package}\\t\${Version}\\n' 'centreon*'",
        timeout => 30,
        wait_exit => 1,
        redirect_stderr => 1,
        logger => $options{logger}
    );
    if ($error != 0 || $return_code != 0) {
        $options{metrics}->{status_code} = 1;
        $options{metrics}->{status_message} = $stdout;
        return ;
    }

    foreach (split(/\n/, $stdout)) {
        my ($name, $version) = split(/\t/);
        push @{$options{metrics}->{list}}, [$name, $version];
    }
}

sub rpm_list {
    my (%options) = @_;

    my ($error, $stdout, $return_code) = gorgone::standard::misc::backtick(
        command => 'rpm -qa --queryformat "%{NAME}\t%{RPMTAG_VERSION}-%{RPMTAG_RELEASE}\n" | grep centreon',
        timeout => 30,
        wait_exit => 1,
        redirect_stderr => 1,
        logger => $options{logger}
    );
    if ($error != 0 || $return_code != 0) {
        $options{metrics}->{status_code} = 1;
        $options{metrics}->{status_message} = $stdout;
        return ;
    }

    foreach (split(/\n/, $stdout)) {
        my ($name, $version) = split(/\t/);
        push @{$options{metrics}->{list}}, [$name, $version];
    }
}

sub metrics {
    my (%options) = @_;

    my $metrics = {
        status_code => 0,
        status_message => 'ok',
        list => []
    };

    if ($options{os} =~ /Debian|Ubuntu/i) {
        dpkg_list(metrics => $metrics);
    } elsif ($options{os} =~ /CentOS|Redhat|rhel|almalinux|rocky/i) {
        rpm_list(metrics => $metrics);
    } elsif ($options{os} eq 'ol' || $options{os} =~ /Oracle Linux/i) {
        rpm_list(metrics => $metrics);
    } else {
        $metrics->{status_code} = 1;
        $metrics->{status_message} = 'unsupported os';
    }

    return $metrics;
}

1;
