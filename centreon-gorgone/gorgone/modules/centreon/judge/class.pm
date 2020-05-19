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

package gorgone::modules::centreon::judge::class;

use base qw(gorgone::class::module);

use strict;
use warnings;
use gorgone::class::db;
use gorgone::standard::library;
use gorgone::standard::constants qw(:all);
use ZMQ::LibZMQ4;
use ZMQ::Constants qw(:all);
use JSON::XS;
use gorgone::modules::centreon::judge::type::distribute;
use gorgone::modules::centreon::judge::type::spare;

my %handlers = (TERM => {}, HUP => {});
my ($connector);

sub new {
    my ($class, %options) = @_;
    $connector  = {};
    bless $connector, $class;

    $connector->{internal_socket} = undef;
    $connector->{module_id} = $options{module_id};
    $connector->{logger} = $options{logger};
    $connector->{config} = $options{config};
    $connector->{config_core} = $options{config_core};
    $connector->{stop} = 0;
    $connector->{timeout} = 600;
    $connector->{check_alive_sync} = defined($connector->{config}->{check_alive}) && $connector->{config}->{check_alive} =~ /(\d+)/ ? $1 : 60;
    $connector->{check_alive_last} = -1;
    $connector->{check_alive} = 0;
    
    $connector->{cache_dir} = (defined($connector->{config}->{cache_dir}) && $connector->{config}->{cache_dir} ne '') ?
        $connector->{config}->{cache_dir} : '/var/cache/centreon';

    $connector->check_config();
    $connector->set_signal_handlers();
    return $connector;
}

sub check_config {
    my ($self, %options) = @_;

    $self->{clusters_spare} = {};
    $self->{clusters_distribute} = {};
    $self->{nodes} = {};
    if (defined($self->{config}->{cluster})) {
        foreach (@{$self->{config}->{cluster}}) {
            if (!defined($_->{name}) || $_->{name} eq '') {
                $self->{logger}->writeLogError('[judge] -class- missing name for cluster in config');
                next;
            }

            if (!defined($_->{type}) || $_->{type} !~ /distribute|spare/) {
                $self->{logger}->writeLogError('[judge] -class- missing/unknown type for cluster in config');
                next;
            }

            my $config;
            if ($_->{type} =~ /(distribute)/) {
                $config = gorgone::modules::centreon::judge::type::distribute::check_config(config => $_, logger => $self->{logger});
            } elsif ($_->{type} =~ /(spare)/) {
                $config = gorgone::modules::centreon::judge::type::spare::check_config(config => $_, logger => $self->{logger});
            }

            next if (!defined($config));

            $self->{'clusters_' . $1}->{$_->{name}} = $config;

            foreach (@{$config->{nodes}}) {
                $self->{nodes}->{$_} = {};
            }
            $self->{nodes}->{ $config->{spare} } = {} if (defined($config->{spare}));
        }
    }
}

sub set_signal_handlers {
    my $self = shift;

    $SIG{TERM} = \&class_handle_TERM;
    $handlers{TERM}->{$self} = sub { $self->handle_TERM() };
    $SIG{HUP} = \&class_handle_HUP;
    $handlers{HUP}->{$self} = sub { $self->handle_HUP() };
}

sub handle_HUP {
    my $self = shift;
    $self->{reload} = 0;
}

sub handle_TERM {
    my $self = shift;
    $self->{logger}->writeLogDebug("[judge] -class- $$ Receiving order to stop...");
    $self->{stop} = 1;
}

sub class_handle_TERM {
    foreach (keys %{$handlers{TERM}}) {
        &{$handlers{TERM}->{$_}}();
    }
}

sub class_handle_HUP {
    foreach (keys %{$handlers{HUP}}) {
        &{$handlers{HUP}->{$_}}();
    }
}

sub get_pollers_config {
    my ($self, %options) = @_;

    $self->{pollers_config} = {};
    my ($status, $datas) = $self->{class_object_centreon}->custom_execute(
        request => 'SELECT nagios_server_id, command_file, cfg_dir, centreonbroker_cfg_path, snmp_trapd_path_conf, ' .
            'engine_start_command, engine_stop_command, engine_restart_command, engine_reload_command, ' .
            'broker_reload_command, init_script_centreontrapd ' .
            'FROM cfg_nagios ' .
            'JOIN nagios_server ' .
            'WHERE id = nagios_server_id',
        mode => 1,
        keys => 'nagios_server_id'
    );
    if ($status == -1 || !defined($datas)) {
        $self->{logger}->writeLogError('[judge] -class- cannot get configuration for pollers');
        return -1;
    }

    $self->{pollers_config} = $datas;

    return 0;
}

sub get_clapi_user {
    my ($self, %options) = @_;

    $self->{clapi_user} = undef;
    $self->{clapi_password} = undef;
    my ($status, $datas) = $self->{class_object_centreon}->custom_execute(
        request => "SELECT contact_alias, contact_passwd " .
            "FROM `contact` " .
            "WHERE `contact_admin` = '1' " . 
            "AND `contact_activate` = '1' " .
            "AND `contact_passwd` IS NOT NULL " .
            "LIMIT 1 ",
        mode => 2
    );

    if ($status == -1 || !defined($datas->[0]->[0])) {
        $self->{logger}->writeLogError('[judge] -class- cannot get configuration for CLAPI user');
        return -1;
    }

    my $clapi_user = $datas->[0]->[0];
    my $clapi_password = $datas->[0]->[1];
    if ($clapi_password =~ m/^md5__(.*)/) {
        $clapi_password = $1;
    }

    $self->{clapi_user} = $clapi_user;
    $self->{clapi_password} = $clapi_password;

    return 0;
}

sub event {
    while (1) {
        my $message = gorgone::standard::library::zmq_dealer_read_message(socket => $connector->{internal_socket});
        
        $connector->{logger}->writeLogDebug("[judge] -class- event: $message");
        if ($message =~ /^\[(.*?)\]/) {
            if ((my $method = $connector->can('action_' . lc($1)))) {
                $message =~ /^\[(.*?)\]\s+\[(.*?)\]\s+\[.*?\]\s+(.*)$/m;
                my ($action, $token) = ($1, $2);
                my $data = JSON::XS->new->utf8->decode($3);
                $method->($connector, token => $token, data => $data);
            }
        }

        last unless (gorgone::standard::library::zmq_still_read(socket => $connector->{internal_socket}));
    }
}

sub check_alive {
    my ($self, %options) = @_;

    return if (time() - $self->{check_alive_sync} < $self->{check_alive_last});
    $self->{check_alive_last} = time();
    $self->{check_alive} = 0;

    my $request = q(
        SELECT instances.instance_id, instances.running, instances.last_alive, count(hosts.instance_id)
        FROM instances LEFT JOIN hosts ON hosts.instance_id = instances.instance_id AND hosts.enabled = 1
        GROUP BY instances.instance_id
    );
    my ($status, $datas) = $self->{class_object_centstorage}->custom_execute(
        request => $request, 
        mode => 2
    );    
    if ($status == -1) {
        $self->{logger}->writeLogError('[judge] -class- cannot get pollers status');
        return 1;
    }

    foreach (@$datas) {
        if (defined($self->{nodes}->{ $_->[0] })) {
            $self->{nodes}->{ $_->[0] }->{running} = $_->[1];
            $self->{nodes}->{ $_->[0] }->{last_alive} = $_->[2];
            $self->{nodes}->{ $_->[0] }->{count_hosts} = $_->[3];
        }
    }

    $self->{check_alive} = 1;
}

sub add_pipeline_config_reload_poller {
    my ($self, %options) = @_;

    my $cmd = 'centreon -u ' . $self->{clapi_user} . ' -p ' . $self->{clapi_password} . ' -a POLLERGENERATE -v ' . $options{poller_id};
    $self->send_internal_action(
        action => 'ADDPIPELINE',
        data => [
            {
                action => 'COMMAND', 
                data => {
                    content => [ {
                        command => $cmd 
                    } ] 
                }
            },
            {
                action => 'REMOTECOPY',
                target => $options{poller_id}, 
                data => {
                    content => {
                        source => $self->{cache_dir} . '/config/engine/' . $options{poller_id},
                        destination => $self->{pollers_config}->{ $options{poller_id} }->{cfg_dir} . '/',
                        cache_dir => $self->{cache_dir},
                        owner => 'centreon-engine',
                        group => 'centreon-engine',
                    }
                } 
            },
            {
                action => 'REMOTECOPY',
                target => $options{poller_id}, 
                data => {
                    content => {
                       source => $self->{cache_dir} . '/config/broker/' . $options{poller_id},
                       destination => $self->{pollers_config}->{ $options{poller_id} }->{centreonbroker_cfg_path} . '/',
                       cache_dir => $self->{cache_dir},
                       owner => 'centreon-broker',
                       group => 'centreon-broker',
                    }
                } 
            },
            {
                action => 'COMMAND',
                target => $options{poller_id}, 
                data => {
                    content => [ {
                        command => 'sudo ' . $self->{pollers_config}->{ $options{poller_id} }->{engine_reload_command}
                    } ] 
                }
            }
        ]
    );
}

sub test_types {
    my ($self, %options) = @_;

    # we don't test if we cannot do check_alive
    return if ($self->{check_alive} == 0);

    my $all_pollers = {};
    foreach (values %{$self->{clusters_distribute}}) {
        my $pollers = gorgone::modules::centreon::judge::type::distribute::assign(cluster => $_, module => $self);
        $all_pollers = { %$pollers, %$all_pollers };
    }

    if (scalar(keys %$all_pollers) > 0 &&
        $self->get_clapi_user() == 0 && 
        $self->get_pollers_config() == 0
        ) {
        foreach (keys %$all_pollers) {
            $self->add_pipeline_config_reload_poller(poller_id => $_);
        }
    }
}

sub run {
    my ($self, %options) = @_;

    $connector->{internal_socket} = gorgone::standard::library::connect_com(
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgonejudge',
        logger => $self->{logger},
        type => $self->{config_core}->{internal_com_type},
        path => $self->{config_core}->{internal_com_path}
    );
    $connector->send_internal_action(
        action => 'JUDGEREADY',
        data => {}
    );
    $self->{poll} = [
        {
            socket  => $connector->{internal_socket},
            events  => ZMQ_POLLIN,
            callback => \&event,
        }
    ];

    $self->{db_centstorage} = gorgone::class::db->new(
        dsn => $self->{config_db_centstorage}->{dsn},
        user => $self->{config_db_centstorage}->{username},
        password => $self->{config_db_centstorage}->{password},
        force => 2,
        logger => $self->{logger}
    );
    $self->{db_centreon} = gorgone::class::db->new(
        dsn => $self->{config_db_centreon}->{dsn},
        user => $self->{config_db_centreon}->{username},
        password => $self->{config_db_centreon}->{password},
        force => 2,
        logger => $self->{logger}
    );
    $self->{class_object} = gorgone::class::sqlquery->new(logger => $self->{logger}, db_centreon => $self->{db_centstorage});
    $self->{class_object_centreon} = gorgone::class::sqlquery->new(logger => $self->{logger}, db_centreon => $self->{db_centreon});

    $self->{db_gorgone} = gorgone::class::db->new(
        type => $self->{config_core}->{gorgone_db_type},
        db => $self->{config_core}->{gorgone_db_name},
        host => $self->{config_core}->{gorgone_db_host},
        port => $self->{config_core}->{gorgone_db_port},
        user => $self->{config_core}->{gorgone_db_user},
        password => $self->{config_core}->{gorgone_db_password},
        force => 2,
        logger => $self->{logger}
    );

    while (1) {
        # we try to do all we can
        my $rev = zmq_poll($self->{poll}, 5000);
        if (defined($rev) && $rev == 0 && $self->{stop} == 1) {
            $self->{logger}->writeLogInfo("[judge] -class- $$ has quit");
            zmq_close($connector->{internal_socket});
            exit(0);
        }

        $self->check_alive();
        $self->test_types();
    }
}

1;
