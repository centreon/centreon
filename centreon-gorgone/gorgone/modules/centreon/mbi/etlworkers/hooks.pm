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

package gorgone::modules::centreon::mbi::etlworkers::hooks;

use warnings;
use strict;
use JSON::XS;
use gorgone::class::core;
use gorgone::standard::library;
use gorgone::standard::constants qw(:all);
use gorgone::modules::centreon::mbi::etlworkers::class;

use constant NAMESPACE => 'centreon';
use constant NAME => 'mbi-etlworkers';
use constant EVENTS => [
    { event => 'CENTREONMBIETLWORKERSIMPORT' },
    { event => 'CENTREONMBIETLWORKERSDIMENSIONS' },
    { event => 'CENTREONMBIETLWORKERSEVENT' },
    { event => 'CENTREONMBIETLWORKERSPERFDATA' },
    { event => 'CENTREONMBIETLWORKERSREADY' }
];

my $config_core;
my $config;

my $pools = {};
my $pools_pid = {};
my $rr_current = 0;
my $stop = 0;

sub register {
    my (%options) = @_;

    $config = $options{config};
    $config_core = $options{config_core};

    $config->{pool} = defined($config->{pool}) && $config->{pool} =~ /(\d+)/ ? $1 : 8;
    return (1, NAMESPACE, NAME, EVENTS);
}

sub init {
    my (%options) = @_;

    for my $pool_id (1..$config->{pool}) {
        create_child(dbh => $options{dbh}, pool_id => $pool_id, logger => $options{logger});
    }
}

sub routing {
    my (%options) = @_;

    my $data;
    eval {
        $data = JSON::XS->new->decode($options{data});
    };
    if ($@) {
        $options{logger}->writeLogError("[proxy] Cannot decode json data: $@");
        gorgone::standard::library::add_history(
            dbh => $options{dbh},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => NAME . ' - cannot decode json' },
            json_encode => 1
        );
        return undef;
    }

    if ($options{action} eq 'CENTREONMBIETLWORKERSREADY') {
        if (defined($data->{pool_id})) {
            $pools->{ $data->{pool_id} }->{ready} = 1;
        }
        return undef;
    }

    my $pool_id = rr_pool();
    if (!defined($pool_id)) {
        gorgone::standard::library::add_history(
            dbh => $options{dbh},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => NAME . ' - no pool ready' },
            json_encode => 1
        );
        return undef;
    }

    my $identity = 'gorgone-' . NAME . '-' . $pool_id;

    $options{gorgone}->send_internal_message(
        identity => $identity,
        action => $options{action},
        data => $options{data},
        token => $options{token}
    );
}

sub gently {
    my (%options) = @_;

    $stop = 1;
    foreach my $pool_id (keys %$pools) {
        if (defined($pools->{$pool_id}->{running}) && $pools->{$pool_id}->{running} == 1) {
            $options{logger}->writeLogDebug("[" . NAME . "] Send TERM signal for pool '" . $pool_id . "'");
            CORE::kill('TERM', $pools->{$pool_id}->{pid});
        }
    }
}

sub kill {
    my (%options) = @_;

    foreach (keys %$pools) {
        if ($pools->{$_}->{running} == 1) {
            $options{logger}->writeLogDebug("[" . NAME . "] Send KILL signal for pool '" . $_ . "'");
            CORE::kill('KILL', $pools->{$_}->{pid});
        }
    }
}

sub kill_internal {
    my (%options) = @_;

}

sub check_create_child {
    my (%options) = @_;

    return if ($stop == 1);

    # Check if we need to create a child
    for my $pool_id (1..$config->{pool}) {
        if (!defined($pools->{$pool_id})) {
            create_child(dbh => $options{dbh}, pool_id => $pool_id, logger => $options{logger});
        }
    }
}

sub check {
    my (%options) = @_;

    my $count = 0;
    foreach my $pid (keys %{$options{dead_childs}}) {
        # Not me
        next if (!defined($pools_pid->{$pid}));
        
        # If someone dead, we recreate
        my $pool_id = $pools_pid->{$pid};
        delete $pools->{$pools_pid->{$pid}};
        delete $pools_pid->{$pid};
        delete $options{dead_childs}->{$pid};
        if ($stop == 0) {
            create_child(dbh => $options{dbh}, pool_id => $pool_id, logger => $options{logger});
        }
    }

    check_create_child(dbh => $options{dbh}, logger => $options{logger});

    foreach (keys %$pools) {
        $count++  if ($pools->{$_}->{running} == 1);
    }

    return ($count, 1);
}

sub broadcast {
    my (%options) = @_;

    foreach my $pool_id (keys %$pools) {
        next if ($pools->{$pool_id}->{ready} != 1);

        $options{gorgone}->send_internal_message(
            identity => 'gorgone-' . NAME .  '-' . $pool_id,
            action => $options{action},
            data => $options{data},
            token => $options{token}
        );
    }
}

# Specific functions
sub rr_pool {
    my (%options) = @_;

    my ($loop, $i) = ($config->{pool}, 0);
    while ($i <= $loop) {
        $rr_current = $rr_current % $config->{pool};
        if ($pools->{$rr_current + 1}->{ready} == 1) {
            $rr_current++;
            return $rr_current;
        }
        $rr_current++;
        $i++;
    }

    return undef;
}

sub create_child {
    my (%options) = @_;

    $options{logger}->writeLogInfo("[" . NAME . "] Create module '" . NAME . "' child process for pool id '" . $options{pool_id} . "'");
    my $child_pid = fork();
    if ($child_pid == 0) {
        $0 = 'gorgone-' . NAME;
        my $module = gorgone::modules::centreon::mbi::etlworkers::class->new(
            logger => $options{logger},
            module_id => NAME,
            config_core => $config_core,
            config => $config,
            pool_id => $options{pool_id},
            container_id => $options{pool_id}
        );
        $module->run();
        exit(0);
    }
    $options{logger}->writeLogDebug("[" . NAME . "] PID $child_pid (gorgone-" . NAME . ") for pool id '" . $options{pool_id} . "'");
    $pools->{$options{pool_id}} = { pid => $child_pid, ready => 0, running => 1 };
    $pools_pid->{$child_pid} = $options{pool_id};
}

1;
