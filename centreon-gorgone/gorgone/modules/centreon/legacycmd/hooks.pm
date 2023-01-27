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

package gorgone::modules::centreon::legacycmd::hooks;

use warnings;
use strict;
use gorgone::class::core;
use gorgone::modules::centreon::legacycmd::class;
use gorgone::standard::constants qw(:all);

use constant NAMESPACE => 'centreon';
use constant NAME => 'legacycmd';
use constant EVENTS => [
    { event => 'CENTREONCOMMAND', uri => '/command', method => 'POST' },
    { event => 'LEGACYCMDREADY' },
    { event => 'ADDIMPORTTASKWITHPARENT' }
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
    $config->{cmd_file} = defined($config->{cmd_file}) ? $config->{cmd_file} : '/var/lib/centreon/centcore.cmd';
    $config->{cache_dir} = defined($config->{cache_dir}) ? $config->{cache_dir} : '/var/cache/centreon/';
    $config->{cache_dir_trap} = defined($config->{cache_dir_trap}) ? $config->{cache_dir_trap} : '/etc/snmp/centreon_traps/';
    $config->{remote_dir} = defined($config->{remote_dir}) ? $config->{remote_dir} : '/var/lib/centreon/remote-data/';
    return (1, NAMESPACE, NAME, EVENTS);
}

sub init {
    my (%options) = @_;

    create_child(logger => $options{logger});
}

sub routing {
    my (%options) = @_;

    if ($options{action} eq 'LEGACYCMDREADY') {
        $legacycmd->{ready} = 1;
        return undef;
    }
    
    if (gorgone::class::core::waiting_ready(ready => \$legacycmd->{ready}) == 0) {
        gorgone::standard::library::add_history(
            dbh => $options{dbh},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'gorgone-legacycmd: still no ready' },
            json_encode => 1
        );
        return undef;
    }
    
    $options{gorgone}->send_internal_message(
        identity => 'gorgone-legacycmd',
        action => $options{action},
        data => $options{data},
        token => $options{token}
    );
}

sub gently {
    my (%options) = @_;

    $stop = 1;
    if (defined($legacycmd->{running}) && $legacycmd->{running} == 1) {
        $options{logger}->writeLogDebug("[legacycmd] Send TERM signal $legacycmd->{running}");
        CORE::kill('TERM', $legacycmd->{pid});
    }
}

sub kill {
    my (%options) = @_;

    if ($legacycmd->{running} == 1) {
        $options{logger}->writeLogDebug("[legacycmd] Send KILL signal for pool");
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
        next if (!defined($legacycmd->{pid}) || $legacycmd->{pid} != $pid);
        
        $legacycmd = {};
        delete $options{dead_childs}->{$pid};
        if ($stop == 0) {
            create_child(logger => $options{logger});
        }
    }
    
    $count++  if (defined($legacycmd->{running}) && $legacycmd->{running} == 1);
    
    return $count;
}

sub broadcast {
    my (%options) = @_;

    routing(%options);
}

# Specific functions
sub create_child {
    my (%options) = @_;
    
    $options{logger}->writeLogInfo("[legacycmd] Create module 'legacycmd' process");

    my $child_pid = fork();
    if ($child_pid == 0) {
        $0 = 'gorgone-legacycmd';
        my $module = gorgone::modules::centreon::legacycmd::class->new(
            logger => $options{logger},
            module_id => NAME,
            config_core => $config_core,
            config => $config,
            config_db_centreon => $config_db_centreon,
        );
        $module->run();
        exit(0);
    }
    $options{logger}->writeLogDebug("[legacycmd] PID $child_pid (gorgone-legacycmd)");
    $legacycmd = { pid => $child_pid, ready => 0, running => 1 };
}

1;
