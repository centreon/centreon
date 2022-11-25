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

package gorgone::modules::centreon::audit::hooks;

use warnings;
use strict;
use gorgone::class::core;
use gorgone::modules::centreon::audit::class;
use gorgone::standard::constants qw(:all);

use constant NAMESPACE => 'centreon';
use constant NAME => 'audit';
use constant EVENTS => [
    { event => 'CENTREONAUDITSCHEDULE', uri => '/schedule', method => 'POST' },
    { event => 'CENTREONAUDITNODE', uri => '/node', method => 'POST' },
    { event => 'CENTREONAUDITNODELISTENER' },
    { event => 'CENTREONAUDITREADY' }
];

my $config_core;
my $config;
my $audit = {};
my $stop = 0;
my ($config_db_centreon, $config_db_centstorage);

sub register {
    my (%options) = @_;
    
    $config = $options{config};
    $config_core = $options{config_core};
    $config_db_centstorage = $options{config_db_centstorage};
    $config_db_centreon = $options{config_db_centreon};
    return (1, NAMESPACE, NAME, EVENTS);
}

sub init {
    my (%options) = @_;

    create_child(logger => $options{logger});
}

sub routing {
    my (%options) = @_;
    
    if ($options{action} eq 'CENTREONAUDITREADY') {
        $audit->{ready} = 1;
        return undef;
    }
    
    if (gorgone::class::core::waiting_ready(ready => \$audit->{ready}) == 0) {
        gorgone::standard::library::add_history({
            dbh => $options{dbh},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'gorgone-audit: still no ready' },
            json_encode => 1
        });
        return undef;
    }
    
    $options{gorgone}->send_internal_message(
        identity => 'gorgone-audit',
        action => $options{action},
        raw_data_ref => $options{frame}->getRawData(),
        token => $options{token}
    );
}

sub gently {
    my (%options) = @_;

    $stop = 1;
    if (defined($audit->{running}) && $audit->{running} == 1) {
        $options{logger}->writeLogDebug("[audit] Send TERM signal $audit->{pid}");
        CORE::kill('TERM', $audit->{pid});
    }
}

sub kill {
    my (%options) = @_;

    if ($audit->{running} == 1) {
        $options{logger}->writeLogDebug("[audit] Send KILL signal for child");
        CORE::kill('KILL', $audit->{pid});
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
        next if (!defined($audit->{pid}) || $audit->{pid} != $pid);
        
        $audit = {};
        delete $options{dead_childs}->{$pid};
        if ($stop == 0) {
            create_child(logger => $options{logger});
        }
    }

    $count++  if (defined($audit->{running}) && $audit->{running} == 1);

    return $count;
}

sub broadcast {
    my (%options) = @_;

    routing(%options);
}

# Specific functions
sub create_child {
    my (%options) = @_;
    
    $options{logger}->writeLogInfo("[audit] Create module 'audit' process");

    my $child_pid = fork();
    if ($child_pid == 0) {
        $0 = 'gorgone-audit';
        my $module = gorgone::modules::centreon::audit::class->new(
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
    $options{logger}->writeLogDebug("[audit] PID $child_pid (gorgone-audit)");
    $audit = { pid => $child_pid, ready => 0, running => 1 };
}

1;
