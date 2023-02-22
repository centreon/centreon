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
use JSON::XS;
use gorgone::modules::centreon::judge::type::distribute;
use gorgone::modules::centreon::judge::type::spare;
use EV;

my %handlers = (TERM => {}, HUP => {});
my ($connector);

sub new {
    my ($class, %options) = @_;
    $connector = $class->SUPER::new(%options);
    bless $connector, $class;

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

    $self->{clapi_user} = $self->{config}->{clapi_user};
    $self->{clapi_password} = $self->{config}->{clapi_password};

    if (!defined($self->{clapi_password})) {
        $self->{logger}->writeLogError('[judge] -class- cannot get configuration for CLAPI user');
        return -1;
    }

    return 0;

=pod
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
=cut

    return 0;
}

sub action_judgemove {
    my ($self, %options) = @_;

    $options{token} = $self->generate_token() if (!defined($options{token}));
    # { content => { cluster_name => 'moncluster', node_move => 2 } }

    return -1 if (!defined($options{data}->{content}->{cluster_name}) || $options{data}->{content}->{cluster_name} eq '');
    return -1 if (!defined($options{data}->{content}->{node_move}) || $options{data}->{content}->{node_move} eq '');

    $self->send_log(
        code => GORGONE_ACTION_BEGIN,
        token => $options{token},
        data => { message => 'failover start' }
    );

    if (!defined($self->{clusters_spare}->{ $options{data}->{content}->{cluster_name} })) {
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => "unknown cluster_name '" . $options{data}->{content}->{cluster_name} . "' in config" }
        );
        return -1;
    }
    
    my $node_configured = 0;
    foreach (@{$self->{clusters_spare}->{ $options{data}->{content}->{cluster_name} }->{nodes}}) {
        if ($_ eq $options{data}->{content}->{node_move}) {
            $node_configured = 1;
            last;
        }
    }
    if ($node_configured == 0) {
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => "unknown node '" . $options{data}->{content}->{node_move} . "' in cluster config" }
        );
        return -1;
    }

    $self->check_alive();
    if ($self->{check_alive} == 0) {
         $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'cannot check cluster nodes status' }
        );
        return -1;
    }

    if (!gorgone::modules::centreon::judge::type::spare::is_ready_status(status => $self->{clusters_spare}->{ $options{data}->{content}->{cluster_name} }->{live}->{status})) {
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'cluster status not ready to move' }
        );
        return -1;
    }
    if (!gorgone::modules::centreon::judge::type::spare::is_spare_ready(module => $self, ctime => time(), cluster => $self->{clusters_spare}->{ $options{data}->{content}->{cluster_name} })) {
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'cluster spare not ready' }
        );
        return -1;
    }    

    gorgone::modules::centreon::judge::type::spare::migrate_steps_1_2_3(
        token => $options{token},
        module => $self,
        node_src => $options{data}->{content}->{node_move},
        cluster => $options{data}->{content}->{cluster_name},
        clusters => $self->{clusters_spare},
        no_update_running_failed => 1
    );

    return 0;
}

sub action_judgefailback {
    my ($self, %options) = @_;

    $options{token} = $self->generate_token() if (!defined($options{token}));
    # { content => { cluster_name => 'moncluster' } }

    return -1 if (!defined($options{data}->{content}->{cluster_name}) || $options{data}->{content}->{cluster_name} eq '');

    $self->send_log(
        code => GORGONE_ACTION_BEGIN,
        token => $options{token},
        data => { message => 'failback start' }
    );

    if (!defined($self->{clusters_spare}->{ $options{data}->{content}->{cluster_name} })) {
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => "unknown cluster_name '" . $options{data}->{content}->{cluster_name} . "' in config" }
        );
        return -1;
    }

    $self->check_alive();
    if ($self->{check_alive} == 0) {
         $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'cannot check cluster nodes status' }
        );
        return -1;
    }

    if ($self->get_clapi_user() != 0 ||
        $self->get_pollers_config() != 0) {
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'cannot get clapi user informations and/or poller config' }
        );
        return -1;
    }

    if (!gorgone::modules::centreon::judge::type::spare::is_failover_status(status => $self->{clusters_spare}->{ $options{data}->{content}->{cluster_name} }->{live}->{status})) {
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'cluster status not ready to failback' }
        );
        return -1;
    }

    gorgone::modules::centreon::judge::type::spare::failback_start(
        token => $options{token},
        module => $self,
        cluster => $options{data}->{content}->{cluster_name},
        clusters => $self->{clusters_spare}
    );

    return 0;
}

sub action_judgeclean {
    my ($self, %options) = @_;

    $options{token} = $self->generate_token() if (!defined($options{token}));
    # { content => { cluster_name => 'moncluster' } }

    return -1 if (!defined($options{data}->{content}->{cluster_name}) || $options{data}->{content}->{cluster_name} eq '');

    $self->send_log(
        code => GORGONE_ACTION_BEGIN,
        token => $options{token},
        data => { message => 'clean start' }
    );

    if (!defined($self->{clusters_spare}->{ $options{data}->{content}->{cluster_name} })) {
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => "unknown cluster_name '" . $options{data}->{content}->{cluster_name} . "' in config" }
        );
        return -1;
    }

    gorgone::modules::centreon::judge::type::spare::clean(
        token => $options{token},
        module => $self,
        cluster => $options{data}->{content}->{cluster_name},
        clusters => $self->{clusters_spare}
    );

    return 0;
}

sub action_judgelistener {
    my ($self, %options) = @_;

    return 0 if (!defined($options{token}));

    if ($options{token} =~ /^judge-spare##(.*?)##(\d+)##/) {
        gorgone::modules::centreon::judge::type::spare::migrate_steps_listener_response(
            token => $options{token},
            cluster => $1,
            state => $2,
            clusters => $self->{clusters_spare},
            module => $self,
            code => $options{data}->{code}
        );
    }

    return 1;
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

    my $actions = [
        {
            action => 'REMOTECOPY',
            target => $options{poller_id},
            timeout => 120,
            log_pace => 5,
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
            timeout => 120,
            log_pace => 5,
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
            timeout => 60,
            data => {
                content => [ {
                    command => 'sudo ' . $self->{pollers_config}->{ $options{poller_id} }->{engine_reload_command}
                } ] 
            }
        }
    ];

    if (!defined($options{no_generate_config})) {
        my $cmd = 'centreon -u ' . $self->{clapi_user} . ' -p ' . $self->{clapi_password} . ' -a POLLERGENERATE -v ' . $options{poller_id};
        unshift @$actions, {
            action => 'COMMAND', 
            data => {
                content => [ {
                    command => $cmd 
                } ] 
            }
        };
    }

    $self->send_internal_action({
        action => 'ADDPIPELINE',
        token => $options{token},
        timeout => $options{pipeline_timeout},
        data => $actions
    });
}

sub test_types {
    my ($self, %options) = @_;

    # we don't test if we cannot do check_alive
    return if ($self->{check_alive} == 0);

    # distribute clusters
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

    # spare clusters
    gorgone::modules::centreon::judge::type::spare::init(
        clusters => $self->{clusters_spare},
        module => $self
    );
    gorgone::modules::centreon::judge::type::spare::check_migrate(
        clusters => $self->{clusters_spare},
        module => $self
    );
}

sub periodic_exec {
    if ($connector->{stop} == 1) {
        $connector->{logger}->writeLogInfo("[judge] -class- $$ has quit");
        exit(0);
    }

    $connector->check_alive();
    $connector->test_types();
}

sub run {
    my ($self, %options) = @_;

    $self->{internal_socket} = gorgone::standard::library::connect_com(
        context => $self->{zmq_context},
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgone-judge',
        logger => $self->{logger},
        type => $self->get_core_config(name => 'internal_com_type'),
        path => $self->get_core_config(name => 'internal_com_path')
    );
    $connector->send_internal_action({
        action => 'JUDGEREADY',
        data => {}
    });

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
    $self->{class_object_centstorage} = gorgone::class::sqlquery->new(logger => $self->{logger}, db_centreon => $self->{db_centstorage});
    $self->{class_object_centreon} = gorgone::class::sqlquery->new(logger => $self->{logger}, db_centreon => $self->{db_centreon});

    $self->{db_gorgone} = gorgone::class::db->new(
        type => $self->get_core_config(name => 'gorgone_db_type'),
        db => $self->get_core_config(name => 'gorgone_db_name'),
        host => $self->get_core_config(name => 'gorgone_db_host'),
        port => $self->get_core_config(name => 'gorgone_db_port'),
        user => $self->get_core_config(name => 'gorgone_db_user'),
        password => $self->get_core_config(name => 'gorgone_db_password'),
        force => 2,
        logger => $self->{logger}
    );

    my $w1 = EV::timer(5, 2, \&periodic_exec);
    my $w2 = EV::io($connector->{internal_socket}->get_fd(), EV::READ, sub { $connector->event() } );
    EV::run();
}

1;
