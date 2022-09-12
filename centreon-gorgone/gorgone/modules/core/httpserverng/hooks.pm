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

package gorgone::modules::core::httpserverng::hooks;

use warnings;
use strict;
use gorgone::class::core;
use gorgone::modules::core::httpserverng::class;
use gorgone::standard::constants qw(:all);

use constant NAMESPACE => 'core';
use constant NAME => 'httpserverng';
use constant EVENTS => [
    { event => 'HTTPSERVERNGLISTENER' },
    { event => 'HTTPSERVERNGREADY' }
];

my $config_core;
my $config;
my $httpserverng = {};
my $stop = 0;

sub register {
    my (%options) = @_;

    my $loaded = 1;
    $config = $options{config};
    $config_core = $options{config_core};
    $config->{address} = defined($config->{address}) && $config->{address} ne '' ? $config->{address} : '0.0.0.0';
    $config->{port} = defined($config->{port}) && $config->{port} =~ /(\d+)/ ? $1 : 8080;
    if (defined($config->{auth}->{enabled}) && $config->{auth}->{enabled} eq 'true') {
        if (!defined($config->{auth}->{user}) || $config->{auth}->{user} =~ /^\s*$/) {
            $options{logger}->writeLogError('[httpserverng] User option mandatory if authentication is enabled');
            $loaded = 0;
        }
        if (!defined($config->{auth}->{password}) || $config->{auth}->{password} =~ /^\s*$/) {
            $options{logger}->writeLogError('[httpserverng] Password option mandatory if authentication is enabled');
            $loaded = 0;
        }
    }

    return ($loaded, NAMESPACE, NAME, EVENTS);
}

sub init {
    my (%options) = @_;

    create_child(logger => $options{logger}, api_endpoints => $options{api_endpoints});
}

sub routing {
    my (%options) = @_;
    
    if ($options{action} eq 'HTTPSERVERNGREADY') {
        $httpserverng->{ready} = 1;
        return undef;
    }
    
    if (gorgone::class::core::waiting_ready(ready => \$httpserverng->{ready}) == 0) {
        gorgone::standard::library::add_history({
            dbh => $options{dbh},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'gorgone-httpserverng: still no ready' },
            json_encode => 1
        });
        return undef;
    }

    $options{gorgone}->send_internal_message(
        identity => 'gorgone-httpserverng',
        action => $options{action},
        raw_data_ref => $options{frame}->getRawData(),
        token => $options{token}
    );
}

sub gently {
    my (%options) = @_;

    $stop = 1;
    if (defined($httpserverng->{running}) && $httpserverng->{running} == 1) {
        $options{logger}->writeLogDebug("[httpserverng] Send TERM signal $httpserverng->{pid}");
        CORE::kill('TERM', $httpserverng->{pid});
    }
}

sub kill {
    my (%options) = @_;

    if ($httpserverng->{running} == 1) {
        $options{logger}->writeLogDebug("[httpserverng] Send KILL signal for pool");
        CORE::kill('KILL', $httpserverng->{pid});
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
        next if (!defined($httpserverng->{pid}) || $httpserverng->{pid} != $pid);

        $httpserverng = {};
        delete $options{dead_childs}->{$pid};
        if ($stop == 0) {
            create_child(logger => $options{logger}, api_endpoints => $options{api_endpoints});
        }

        last;
    }

    $count++  if (defined($httpserverng->{running}) && $httpserverng->{running} == 1);

    return $count;
}

sub broadcast {
    my (%options) = @_;

    routing(%options);
}

# Specific functions
sub create_child {
    my (%options) = @_;
    
    $options{logger}->writeLogInfo("[httpserverng] Create module 'httpserverng' process");
    my $child_pid = fork();
    if ($child_pid == 0) {
        $0 = 'gorgone-httpserverng';
        my $module = gorgone::modules::core::httpserverng::class->construct(
            logger => $options{logger},
            module_id => NAME,
            config_core => $config_core,
            config => $config,
            api_endpoints => $options{api_endpoints}
        );
        $module->run();
        exit(0);
    }
    $options{logger}->writeLogDebug("[httpserverng] PID $child_pid (gorgone-httpserverng)");
    $httpserverng = { pid => $child_pid, ready => 0, running => 1 };
}

1;
