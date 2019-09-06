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

package modules::centreon::legacycmd::hooks;

use warnings;
use strict;
use centreon::script::gorgonecore;
use modules::centreon::legacycmd::class;
use JSON::XS;

my $NAME = 'legacycmd';
my $EVENTS = [
    { event => 'LEGACYCMDREADY' },
];

my $config_core;
my $config;
my $legacycmd = {};
my $stop = 0;
my $config_db_centreon;

sub register {
    my (%options) = @_;
    
    $config = $options{config};
    $config_core = $options{config_core};
    $config_db_centreon = $options{config_db_centreon};
    return ($NAME, $EVENTS);
}

sub init {
    my (%options) = @_;

    create_child(logger => $options{logger});
}

sub routing {
    my (%options) = @_;

    my $data;
    eval {
        $data = JSON::XS->new->utf8->decode($options{data});
    };
    if ($@) {
        $options{logger}->writeLogError("[legacycmd] -hooks- Cannot decode json data: $@");
        centreon::gorgone::common::add_history(
            dbh => $options{dbh},
            code => 10, token => $options{token},
            data => { message => 'gorgone-legacycmd: cannot decode json' },
            json_encode => 1
        );
        return undef;
    }
    
    if ($options{action} eq 'LEGACYCMDREADY') {
        $legacycmd->{ready} = 1;
        return undef;
    }
    
    if (centreon::script::gorgonecore::waiting_ready(ready => \$legacycmd->{ready}) == 0) {
        centreon::gorgone::common::add_history(
            dbh => $options{dbh},
            code => 10, token => $options{token},
            data => { message => 'gorgone-legacycmd: still no ready' },
            json_encode => 1
        );
        return undef;
    }
    
    centreon::gorgone::common::zmq_send_message(
        socket => $options{socket},
        identity => 'gorgonelegacycmd',
        action => $options{action},
        data => $options{data},
        token => $options{token},
    );
}

sub gently {
    my (%options) = @_;

    $stop = 1;
    $options{logger}->writeLogInfo("[legacycmd] -hooks- Send TERM signal");
    if ($legacycmd->{running} == 1) {
        CORE::kill('TERM', $legacycmd->{pid});
    }
}

sub kill {
    my (%options) = @_;

    if ($legacycmd->{running} == 1) {
        $options{logger}->writeLogInfo("[legacycmd] -hooks- Send KILL signal for pool");
        CORE::kill('KILL', $legacycmd->{pid});
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
        next if ($legacycmd->{pid} != $pid);
        
        $legacycmd = {};
        delete $options{dead_childs}->{$pid};
        if ($stop == 0) {
            create_child(logger => $options{logger});
        }
    }
    
    $count++  if (defined($legacycmd->{running}) && $legacycmd->{running} == 1);
    
    return $count;
}

# Specific functions
sub create_child {
    my (%options) = @_;
    
    $options{logger}->writeLogInfo("[legacycmd] -hooks- Create module process");

    my $child_pid = fork();
    if ($child_pid == 0) {
        $0 = 'gorgone-legacycmd';
        my $module = modules::centreon::legacycmd::class->new(
            logger => $options{logger},
            config_core => $config_core,
            config => $config,
            config_db_centreon => $config_db_centreon,
        );
        $module->run();
        exit(0);
    }
    $options{logger}->writeLogInfo("[legacycmd] -hooks- PID $child_pid (gorgone-legacycmd)");
    $legacycmd = { pid => $child_pid, ready => 0, running => 1 };
}

1;
