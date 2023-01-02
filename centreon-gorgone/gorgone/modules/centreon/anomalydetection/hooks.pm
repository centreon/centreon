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

package gorgone::modules::centreon::anomalydetection::hooks;

use warnings;
use strict;
use gorgone::class::core;
use gorgone::modules::centreon::anomalydetection::class;
use gorgone::standard::constants qw(:all);

use constant NAMESPACE => 'centreon';
use constant NAME => 'anomalydetection';
use constant EVENTS => [
    { event => 'CENTREONADREADY' }
];

my $config_core;
my $config;
my ($config_db_centreon, $config_db_centstorage);
my $process = {};
my $stop = 0;

sub register {
    my (%options) = @_;

    $config = $options{config};
    $config_core = $options{config_core};
    $config_db_centreon = $options{config_db_centreon};
    $config_db_centstorage = $options{config_db_centstorage};
    $config->{resync_time} = defined($config->{resync_time}) && $config->{resync_time} =~ /(\d+)/ ? $1 : 600;
    return (1, NAMESPACE, NAME, EVENTS);
}

sub init {
    my (%options) = @_;

    create_child(logger => $options{logger});
}

sub routing {
    my (%options) = @_;
    
    if ($options{action} eq 'CENTREONADREADY') {
        $process->{ready} = 1;
        return undef;
    }
    
    if (gorgone::class::core::waiting_ready(ready => \$process->{ready}) == 0) {
        gorgone::standard::library::add_history(
            dbh => $options{dbh},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'gorgone-anomalydetection: still no ready' },
            json_encode => 1
        );
        return undef;
    }
    
    $options{gorgone}->send_internal_message(
        identity => 'gorgone-anomalydetection',
        action => $options{action},
        data => $options{data},
        token => $options{token}
    );
}

sub gently {
    my (%options) = @_;

    $stop = 1;
    if (defined($process->{running}) && $process->{running} == 1) {
        $options{logger}->writeLogDebug("[anomalydetection] Send TERM signal $process->{pid}");
        CORE::kill('TERM', $process->{pid});
    }
}

sub kill {
    my (%options) = @_;

    if ($process->{running} == 1) {
        $options{logger}->writeLogDebug("[anomalydetection] Send KILL signal for pool");
        CORE::kill('KILL', $process->{pid});
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
        next if (!defined($process->{pid}) || $process->{pid} != $pid);
        
        $process = {};
        delete $options{dead_childs}->{$pid};
        if ($stop == 0) {
            create_child(logger => $options{logger});
        }
    }
    
    $count++ if (defined($process->{running}) && $process->{running} == 1);
    
    return $count;
}

sub broadcast {
    my (%options) = @_;

    routing(%options);
}

# Specific functions
sub create_child {
    my (%options) = @_;
    
    $options{logger}->writeLogInfo("[anomalydetection] Create module 'anomalydetection' process");
    my $child_pid = fork();
    if ($child_pid == 0) {
        $0 = 'gorgone-anomalydetection';
        my $module = gorgone::modules::centreon::anomalydetection::class->new(
            logger => $options{logger},
            module_id => NAME,
            config_core => $config_core,
            config => $config,
            config_db_centreon => $config_db_centreon,
            config_db_centstorage => $config_db_centstorage
        );
        $module->run();
        exit(0);
    }
    $options{logger}->writeLogDebug("[anomalydetection] PID $child_pid (gorgone-anomalydetection)");
    $process = { pid => $child_pid, ready => 0, running => 1 };
}

1;
