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

package gorgone::modules::centreon::statistics::hooks;

use warnings;
use strict;
use gorgone::class::core;
use gorgone::standard::constants qw(:all);
use gorgone::modules::centreon::statistics::class;

use constant NAMESPACE => 'centreon';
use constant NAME => 'statistics';
use constant EVENTS => [
    { event => 'STATISTICSREADY' },
    { event => 'STATISTICSLISTENER' },
    { event => 'BROKERSTATS', uri => '/broker', method => 'GET' },
    { event => 'ENGINESTATS', uri => '/engine', method => 'GET' }
];

my $config_core;
my $config;
my $config_db_centreon;
my $config_db_centstorage;
my $statistics = {};
my $stop = 0;

sub register {
    my (%options) = @_;
    
    $config = $options{config};
    $config_core = $options{config_core};
    $config_db_centreon = $options{config_db_centreon};
    $config_db_centstorage = $options{config_db_centstorage};
    $config->{broker_cache_dir} = defined($config->{broker_cache_dir}) ?
        $config->{broker_cache_dir} : '/var/cache/centreon/broker-stats/';
    $config->{engine_stats_dir} = defined($config->{config}->{engine_stats_dir}) ?
        $config->{config}->{engine_stats_dir} : "/var/lib/centreon/nagios-perf/";
    
    $config->{interval} = defined($config->{interval}) ? $config->{interval} : 300;
    $config->{length} = defined($config->{length}) ? $config->{length} : 365;
    $config->{number} = $config->{length} * 24 * 60 * 60 / $config->{interval};
    $config->{heartbeat_factor} = defined($config->{heartbeat_factor}) ? $config->{heartbeat_factor} : 10;
    $config->{heartbeat} = $config->{interval} * $config->{heartbeat_factor};

    return (1, NAMESPACE, NAME, EVENTS);
}

sub init {
    my (%options) = @_;

    create_child(logger => $options{logger});
}

sub routing {
    my (%options) = @_;

    if ($options{action} eq 'STATISTICSREADY') {
        $statistics->{ready} = 1;
        return undef;
    }
    
    if (gorgone::class::core::waiting_ready(ready => \$statistics->{ready}) == 0) {
        gorgone::standard::library::add_history(
            dbh => $options{dbh},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { msg => 'gorgonestatistics: still no ready' },
            json_encode => 1
        );
        return undef;
    }
    
    $options{gorgone}->send_internal_message(
        identity => 'gorgone-statistics',
        action => $options{action},
        data => $options{data},
        token => $options{token}
    );
}

sub gently {
    my (%options) = @_;

    $stop = 1;
    if (defined($statistics->{running}) && $statistics->{running} == 1) {
        $options{logger}->writeLogDebug("[statistics] Send TERM signal $statistics->{pid}");
        CORE::kill('TERM', $statistics->{pid});
    }
}

sub kill {
    my (%options) = @_;

    if ($statistics->{running} == 1) {
        $options{logger}->writeLogDebug("[statistics] Send KILL signal for pool");
        CORE::kill('KILL', $statistics->{pid});
    }
}

sub kill_internal {
    my (%options) = @_;

}

sub check {
    my (%options) = @_;

    my $count = 0;
    foreach my $pid (keys %{$options{dead_childs}}) {
        # Not me
        next if (!defined($statistics->{pid}) || $statistics->{pid} != $pid);
        
        $statistics = {};
        delete $options{dead_childs}->{$pid};
        if ($stop == 0) {
            create_child(logger => $options{logger});
        }
    }
    
    $count++  if (defined($statistics->{running}) && $statistics->{running} == 1);
    
    return $count;
}

sub broadcast {
    my (%options) = @_;

    routing(%options);
}

# Specific functions
sub create_child {
    my (%options) = @_;
    
    $options{logger}->writeLogInfo("[statistics] Create module 'statistics' process");
    my $child_pid = fork();
    if ($child_pid == 0) {
        $0 = 'gorgone-statistics';
        my $module = gorgone::modules::centreon::statistics::class->new(
            logger => $options{logger},
            module_id => NAME,
            config_core => $config_core,
            config => $config,
            config_db_centreon => $config_db_centreon,
            config_db_centstorage => $config_db_centstorage,
        );
        $module->run();
        exit(0);
    }
    $options{logger}->writeLogDebug("[statistics] PID $child_pid (gorgone-statistics)");
    $statistics = { pid => $child_pid, ready => 0, running => 1 };
}

1;
