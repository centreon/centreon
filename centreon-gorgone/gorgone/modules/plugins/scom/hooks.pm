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

package gorgone::modules::plugins::scom::hooks;

use warnings;
use strict;
use JSON::XS;
use gorgone::class::core;
use gorgone::modules::plugins::scom::class;
use gorgone::standard::constants qw(:all);

use constant NAMESPACE => 'plugins';
use constant NAME => 'scom';
use constant EVENTS => [
    { event => 'SCOMREADY' },
    { event => 'SCOMRESYNC', uri => '/resync', method => 'GET' },
];

my ($config_core, $config);
my $config_db_centstorage;
my $last_containers = {}; # Last values from config ini
my $containers = {};
my $containers_pid = {};
my $stop = 0;
my $timer_check = time();
my $config_check_containers_time;

sub register {
    my (%options) = @_;
    
    $config = $options{config};
    $config_core = $options{config_core};
    $config_db_centstorage = $options{config_db_centstorage};
    $config_check_containers_time = defined($config->{check_containers_time}) ? $config->{check_containers_time} : 3600;
    return (1, NAMESPACE, NAME, EVENTS);
}

sub init {
    my (%options) = @_;

    $last_containers = get_containers(logger => $options{logger});
    foreach my $container_id (keys %$last_containers) {
        create_child(container_id => $container_id, logger => $options{logger});
    }
}

sub routing {
    my (%options) = @_;
    
    my $data;
    eval {
        $data = JSON::XS->new->decode($options{data});
    };
    if ($@) {
        $options{logger}->writeLogError("[scom] Cannot decode json data: $@");
        gorgone::standard::library::add_history(
            dbh => $options{dbh},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'gorgone-scom: cannot decode json' },
            json_encode => 1
        );
        return undef;
    }
    
    if ($options{action} eq 'SCOMREADY') {
        $containers->{$data->{container_id}}->{ready} = 1;
        return undef;
    }
    
    if (!defined($data->{container_id}) || !defined($last_containers->{$data->{container_id}})) {
        gorgone::standard::library::add_history(
            dbh => $options{dbh},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'gorgone-scom: need a valid container id' },
            json_encode => 1
        );
        return undef;
    }
    
    if (gorgone::class::core::waiting_ready(ready => \$containers->{$data->{container_id}}->{ready}) == 0) {
        gorgone::standard::library::add_history(
            dbh => $options{dbh},
             code => GORGONE_ACTION_FINISH_KO,
             token => $options{token},
             data => { message => 'gorgone-scom: still no ready' },
             json_encode => 1
        );
        return undef;
    }
    
    $options{gorgone}->send_internal_message(
        identity => 'gorgone-scom-' . $data->{container_id},
        action => $options{action},
        data => $options{data},
        token => $options{token}
    );
}

sub gently {
    my (%options) = @_;

    $stop = 1;
    foreach my $container_id (keys %$containers) {
        if (defined($containers->{$container_id}->{running}) && $containers->{$container_id}->{running} == 1) {
            $options{logger}->writeLogInfo("[scom] Send TERM signal for container '" . $container_id . "'");
            CORE::kill('TERM', $containers->{$container_id}->{pid});
        }
    }
}

sub kill_internal {
    my (%options) = @_;

    foreach (keys %$containers) {
        if ($containers->{$_}->{running} == 1) {
            $options{logger}->writeLogInfo("[scom] Send KILL signal for container '" . $_ . "'");
            CORE::kill('KILL', $containers->{$_}->{pid});
        }
    }
}

sub kill {
    my (%options) = @_;

}

sub check {
    my (%options) = @_;

    if ($timer_check - time() > $config_check_containers_time) {
        sync_container_childs(logger => $options{logger});
        $timer_check = time();
    }
    
    my $count = 0;
    foreach my $pid (keys %{$options{dead_childs}}) {
        # Not me
        next if (!defined($containers_pid->{$pid}));
        
        # If someone dead, we recreate
        delete $containers->{$containers_pid->{$pid}};
        delete $containers_pid->{$pid};
        delete $options{dead_childs}->{$pid};
        if ($stop == 0) {
            # Need to check if we need to recreate (can be a container destruction)!!!
            sync_container_childs(logger => $options{logger});
        }
    }
    
    return $count;
}

sub broadcast {
    my (%options) = @_;

    foreach my $container_id (keys %$containers) {
        next if ($containers->{$container_id}->{ready} != 1);

        $options{gorgone}->send_internal_message(
            identity => 'gorgone-scom-' . $container_id,
            action => $options{action},
            data => $options{data},
            token => $options{token}
        );
    }
}

# Specific functions
sub get_containers {
    my (%options) = @_;

    my $containers = {};
    return $containers if (!defined($config->{containers}));
    foreach (@{$config->{containers}}) {
        next if (!defined($_->{name}) || $_->{name} eq '');

        if (!defined($_->{url}) || $_->{url} eq '') {
            $options{logger}->writeLogError("[scom] Cannot load container '" . $_->{name} . "' - please set url option");
            next;
        }
        if (!defined($_->{dsmhost}) || $_->{dsmhost} eq '') {
            $options{logger}->writeLogError("[scom] Cannot load container '" . $_->{name} . "' - please set dsmhost option");
            next;
        }
        if (!defined($_->{dsmslot}) || $_->{dsmslot} eq '') {
            $options{logger}->writeLogError("[scom] Cannot load container '" . $_->{name} . "' - please set dsmslot option");
            next;
        }

        $containers->{$_->{name}} = {
            url => $_->{url},
            username => $_->{username},
            password => $_->{password},
            httpauth => defined($_->{httpauth}) && $_->{httpauth} =~ /(basic|ntlmv2)/ ? $_->{httpauth} : 'basic',
            resync_time => 
                (defined($_->{resync_time}) && $_->{resync_time} =~ /(\d+)/) ? $1 : 300,
            api_version => (defined($_->{api_version}) && $_->{api_version} =~ /(2012|2016|1801)/) ? $1 : '2016',
            dsmhost => $_->{dsmhost},
            dsmslot => $_->{dsmslot},
            dsmmacro => defined($_->{dsmmacro}) ? $_->{dsmmacro} : 'ALARM_ID',
            dsmalertmessage => defined($_->{dsmalertmessage}) ? $_->{dsmalertmessage} : '%{monitoringobjectdisplayname} %{name}',
            dsmrecoverymessage => defined($_->{dsmrecoverymessage}) ? $_->{dsmrecoverymessage} : 'slot ok',
            curlopts => $_->{curlopts},
        };
    }

    return $containers;
}

sub sync_container_childs {
    my (%options) = @_;
    
    $last_containers = get_containers(logger => $options{logger});
    foreach my $container_id (keys %$last_containers) {
        if (!defined($containers->{$container_id})) {
            create_child(container_id => $container_id, logger => $options{logger});
        }
    }

    # Check if need to delete on containers
    foreach my $container_id (keys %$containers) {
        next if (defined($last_containers->{$container_id}));

        if ($containers->{$container_id}->{running} == 1) {
            $options{logger}->writeLogDebug("[scom] Send KILL signal for container '" . $container_id . "'");
            CORE::kill('KILL', $containers->{$container_id}->{pid});
        }
        
        delete $containers_pid->{ $containers->{$container_id}->{pid} };
        delete $containers->{$container_id};
    }
}

sub create_child {
    my (%options) = @_;
    
    $options{logger}->writeLogInfo("[scom] Create 'gorgone-scom' process for container '" . $options{container_id} . "'");
    my $child_pid = fork();
    if ($child_pid == 0) {
        $0 = 'gorgone-scom ' . $options{container_id};
        my $module = gorgone::modules::plugins::scom::class->new(
            logger => $options{logger},
            module_id => NAME,
            config_core => $config_core,
            config => $config,
            config_db_centstorage => $config_db_centstorage,
            config_scom => $last_containers->{$options{container_id}},
            container_id => $options{container_id},
        );
        $module->run();
        exit(0);
    }
    $options{logger}->writeLogDebug("[scom] PID $child_pid (gorgone-scom) for container '" . $options{container_id} . "'");
    $containers->{$options{container_id}} = { pid => $child_pid, ready => 0, running => 1 };
    $containers_pid->{$child_pid} = $options{container_id};
}

1;
