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

package gorgone::modules::centreon::judge::type::distribute;

use strict;
use warnings;

sub check_config {
    my (%options) = @_;

    my $config = $options{config};
    my $sync = defined($config->{sync}) && $config->{sync} =~ /(\d+)/ ? $1 : 3600;
    $config->{sync} = $sync;
    $config->{sync_last} = -1;

    if (!defined($config->{sync}) || $config->{hcategory} eq '') {
        $options{logger}->writeLogError("[judge] -class- please set hcategory for cluster '" . $config->{name} . "'");
        return undef;
    }

    if (!defined($config->{nodes}) || scalar(@{$config->{nodes}}) <= 0) {
        $options{logger}->writeLogError("[judge] -class- please set nodes for cluster '" . $config->{name} . "'");
        return undef;
    }
    
    return $config;
}

sub least_poller_hosts {
    my (%options) = @_;

    my $poller_id;
    my $lowest_hosts;
    my $current_time = time();
    foreach (keys %{$options{module}->{nodes}}) {
        next if (!defined($options{module}->{nodes}->{$_}->{running}) || $options{module}->{nodes}->{$_}->{running} == 0);
        next if (($current_time - 300) > $options{module}->{nodes}->{$_}->{last_alive});

        if (!defined($lowest_hosts) || $options{module}->{nodes}->{$_}->{count_hosts} < $lowest_hosts) {
            $lowest_hosts = $options{module}->{nodes}->{$_}->{count_hosts};
            $poller_id = $_;
        }
    }

    if (defined($poller_id)) {
        $options{module}->{nodes}->{$_}->{count_hosts}++;
    }
    return $poller_id;
}

sub assign {
    my (%options) = @_;

    return {} if (time() - $options{cluster}->{sync} < $options{cluster}->{sync_last});
    $options{cluster}->{sync_last} = time();

    my $request = "
        SELECT nhr.host_host_id
        FROM hostcategories hc, hostcategories_relation hcr, ns_host_relation nhr, nagios_server ns
        WHERE hc.hc_activate = '1' AND hc.hc_name = " . $options{module}->{class_object_centreon}->quote(value => $options{cluster}->{hcategory}) . "
         AND hc.hc_id = hcr.hostcategories_hc_id
         AND hcr.host_host_id = nhr.host_host_id
         AND nhr.nagios_server_id = ns.id
         AND ns.is_default = 1
         AND ns.ns_activate = '0'
    ";
    my ($status, $datas) = $options{module}->{class_object_centreon}->custom_execute(
        request => $request, 
        mode => 2
    );
    if ($status == -1) {
        $options{module}->{logger}->writeLogError("[judge] -class- cluster '" . $options{cluster}->{name} . "': cannot get hosts");
        return {};
    }

    my $pollers_reload = {};
    foreach (@$datas) {
        my $poller_id = least_poller_hosts(module => $options{module});
        if (!defined($poller_id)) {
            $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{cluster}->{name} . "': cannot find poller for host '$_->[0]'");
            next;
        }

        $pollers_reload->{$poller_id} = 1;
        $options{module}->{logger}->writeLogInfo("[judge] -class- cluster '" . $options{cluster}->{name} . "': assign host '$_->[0]' --> poller '$poller_id'");

        ($status) = $options{module}->{class_object_centreon}->custom_execute(
            request => "UPDATE `ns_host_relation` SET `nagios_server_id` = $poller_id WHERE `host_host_id` = $_->[0]"
        );
        if ($status == -1) {
            $options{module}->{logger}->writeLogError("[judge] -class- cluster '" . $options{cluster}->{name} . "': cannot assign host '$_->[0]' --> poller '$poller_id'");
        }
    }

    return $pollers_reload;
}

1;
