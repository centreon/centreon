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

package gorgone::modules::core::proxy::class;

use base qw(gorgone::class::module);

use strict;
use warnings;
use gorgone::standard::library;
use gorgone::standard::constants qw(:all);
use gorgone::class::clientzmq;
use gorgone::modules::core::proxy::sshclient;
use JSON::XS;
use ZMQ::FFI qw(ZMQ_POLLIN);
use EV;

my %handlers = (TERM => {}, HUP => {});
my ($connector);

sub new {
    my ($class, %options) = @_;
    $connector = $class->SUPER::new(%options);
    bless $connector, $class;

    $connector->{pool_id} = $options{pool_id};
    $connector->{clients} = {};
    $connector->{internal_channels} = {};

    $connector->set_signal_handlers();
    return $connector;
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
    $self->{logger}->writeLogInfo("[proxy] $$ Receiving order to stop...");
    $self->{stop} = 1;
    $self->{stop_time} = time();
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

sub exit_process {
    my ($self, %options) = @_;

    $self->{logger}->writeLogInfo("[proxy] $$ has quit");
    $self->close_connections();
    foreach (keys %{$self->{internal_channels}}) {
        $self->{logger}->writeLogInfo("[proxy] Close internal connection for $_");
        $self->{internal_channels}->{$_}->close();
    }
    $self->{logger}->writeLogInfo("[proxy] Close control connection");
    $self->{internal_socket}->close();
    exit(0);
}

sub read_message_client {
    my (%options) = @_;

    return undef if (!defined($options{identity}) || $options{identity} !~ /^gorgone-proxy-(.*?)-(.*?)$/);

    my ($client_identity) = ($2);
    if ($options{data} =~ /^\[PONG\]/) {
        if ($options{data} !~ /^\[(.+?)\]\s+\[(.*?)\]\s+\[.*?\]\s+(.*)/m) {
            return undef;
        }
        my ($action, $token) = ($1, $2);
        my ($code, $data) = $connector->json_decode(argument => $3);
        return undef if ($code == 1);

        $data->{data}->{id} = $client_identity;

        # if we get a pong response, we can open the internal com read
        $connector->{clients}->{ $client_identity }->{com_read_internal} = 1;
        $connector->send_internal_action({
            action => 'PONG',
            data => $data,
            token => $token,
            target => ''
        });
    } elsif ($options{data} =~ /^\[(?:REGISTERNODES|UNREGISTERNODES|SYNCLOGS)\]/) {
        if ($options{data} !~ /^\[(.+?)\]\s+\[(.*?)\]\s+\[.*?\]\s+(.*)/ms) {
            return undef;
        }
        my ($action, $token, $data)  = ($1, $2, $3);

        $connector->send_internal_action({
            action => $action,
            data => $data,
            data_noencode => 1,
            token => $token,
            target => ''
        });
    } elsif ($options{data} =~ /^\[ACK\]\s+\[(.*?)\]\s+(.*)/ms) {
        my ($code, $data) = $connector->json_decode(argument => $2);
        return undef if ($code == 1);

        # we set the id (distant node can not have id in configuration)
        $data->{data}->{id} = $client_identity;
        if (defined($data->{data}->{action}) && $data->{data}->{action} eq 'getlog') {
            $connector->send_internal_action({
                action => 'SETLOGS',
                data => $data,
                token => $1,
                target => ''
            });
        }
    }
}

sub connect {
    my ($self, %options) = @_;

    if ($self->{clients}->{$options{id}}->{type} eq 'push_zmq') {
        $self->{clients}->{$options{id}}->{class} = gorgone::class::clientzmq->new(
            context => $self->{zmq_context},
            loop => $self->{loop},
            identity => 'gorgone-proxy-' . $self->{core_id} . '-' . $options{id}, 
            cipher => $self->{clients}->{ $options{id} }->{cipher}, 
            vector => $self->{clients}->{ $options{id} }->{vector},
            client_pubkey => 
                defined($self->{clients}->{ $options{id} }->{client_pubkey}) && $self->{clients}->{ $options{id} }->{client_pubkey} ne ''
                    ? $self->{clients}->{ $options{id} }->{client_pubkey} : $self->get_core_config(name => 'pubkey'),
            client_privkey =>
                defined($self->{clients}->{ $options{id} }->{client_privkey}) && $self->{clients}->{ $options{id} }->{client_privkey} ne ''
                    ? $self->{clients}->{ $options{id} }->{client_privkey} : $self->get_core_config(name => 'privkey'),
            target_type => defined($self->{clients}->{ $options{id} }->{target_type}) ?
                $self->{clients}->{ $options{id} }->{target_type} :
                'tcp',
            target_path => defined($self->{clients}->{ $options{id} }->{target_path}) ?
                $self->{clients}->{ $options{id} }->{target_path} :
                $self->{clients}->{ $options{id} }->{address} . ':' . $self->{clients}->{ $options{id} }->{port},
            config_core =>  $self->get_core_config(),
            logger => $self->{logger}
        );
        $self->{clients}->{ $options{id} }->{class}->init(callback => \&read_message_client);
        $self->{clients}->{ $options{id} }->{class}->add_watcher();
    } elsif ($self->{clients}->{ $options{id} }->{type} eq 'push_ssh') {
        $self->{clients}->{$options{id}}->{class} = gorgone::modules::core::proxy::sshclient->new(logger => $self->{logger});
        my $code = $self->{clients}->{$options{id}}->{class}->open_session(
            ssh_host => $self->{clients}->{$options{id}}->{address},
            ssh_port => $self->{clients}->{$options{id}}->{ssh_port},
            ssh_username => $self->{clients}->{$options{id}}->{ssh_username},
            ssh_password => $self->{clients}->{$options{id}}->{ssh_password},
            ssh_directory => $self->{clients}->{$options{id}}->{ssh_directory},
            ssh_known_hosts => $self->{clients}->{$options{id}}->{ssh_known_hosts},
            ssh_identity => $self->{clients}->{$options{id}}->{ssh_identity},
            strict_serverkey_check => $self->{clients}->{$options{id}}->{strict_serverkey_check},
            ssh_connect_timeout => $self->{clients}->{$options{id}}->{ssh_connect_timeout}
        );
        if ($code != 0) {
            $self->{clients}->{ $options{id} }->{delete} = 1;
            return -1;
        }
    }

    return 0;
}

sub action_proxyaddnode {
    my ($self, %options) = @_;

    my ($code, $data) = $self->json_decode(argument => $options{data});
    return if ($code == 1);

    if (defined($self->{clients}->{ $data->{id} }->{class})) {
        # test if a connection parameter changed
        my $changed = 0;
        foreach (keys %$data) {
            if (ref($data->{$_}) eq '' && (!defined($self->{clients}->{ $data->{id} }->{$_}) || $data->{$_} ne $self->{clients}->{ $data->{id} }->{$_})) {
                $changed = 1;
                last;
            }
        }

        if ($changed == 0) {
            $self->{logger}->writeLogInfo("[proxy] Session not changed $data->{id}");
            return ;
        }

        $self->{logger}->writeLogInfo("[proxy] Recreate session for $data->{id}");
        # we send a pong reset. because the ping can be lost
        $self->send_internal_action({
            action => 'PONGRESET',
            data => '{ "data": { "id": ' . $data->{id} . ' } }',
            data_noencode => 1,
            token => $self->generate_token(),
            target => ''
        });

        $self->{clients}->{ $data->{id} }->{class}->close();
    } else {
        $self->{internal_channels}->{ $data->{id} } = gorgone::standard::library::connect_com(
            context => $self->{zmq_context},
            zmq_type => 'ZMQ_DEALER',
            name => 'gorgone-proxy-channel-' . $data->{id},
            logger => $self->{logger},
            type => $self->get_core_config(name => 'internal_com_type'),
            path => $self->get_core_config(name => 'internal_com_path')
        );
        $self->send_internal_action({
            action => 'PROXYREADY',
            data => {
                node_id => $data->{id}
            }
        });
    }

    $self->{clients}->{ $data->{id} } = $data;
    $self->{clients}->{ $data->{id} }->{delete} = 0;
    $self->{clients}->{ $data->{id} }->{class} = undef;
    $self->{clients}->{ $data->{id} }->{com_read_internal} = 1;

    $self->{clients}->{ $data->{id} }->{watcher} = $self->{loop}->io(
        $self->{internal_channels}->{ $data->{id} }->get_fd(),
        EV::READ,
        sub {
            $connector->event(channel => $data->{id});
        }
    );
}

sub action_proxydelnode {
    my ($self, %options) = @_;

    my ($code, $data) = $self->json_decode(argument => $options{data});
    return if ($code == 1);

    if (defined($self->{clients}->{$data->{id}})) {
        $self->{clients}->{ $data->{id} }->{delete} = 1;
    }
}

sub action_proxycloseconnection {
    my ($self, %options) = @_;

    my ($code, $data) = $self->json_decode(argument => $options{data});
    return if ($code == 1);

    return if (!defined($self->{clients}->{ $data->{id} }));

    $self->{logger}->writeLogInfo("[proxy] Close connectionn for $data->{id}");

    $self->{clients}->{ $data->{id} }->{class}->close();
    $self->{clients}->{ $data->{id} }->{delete} = 0;
    $self->{clients}->{ $data->{id} }->{class} = undef;
}

sub action_proxystopreadchannel {
    my ($self, %options) = @_;

    my ($code, $data) = $self->json_decode(argument => $options{data});
    return if ($code == 1);

    return if (!defined($self->{clients}->{ $data->{id} }));

    $self->{logger}->writeLogInfo("[proxy] Stop read channel for $data->{id}");

    $self->{clients}->{ $data->{id} }->{com_read_internal} = 0;
}

sub close_connections {
    my ($self, %options) = @_;

    foreach (keys %{$self->{clients}}) {
        if (defined($self->{clients}->{$_}->{class}) && $self->{clients}->{$_}->{type} eq 'push_zmq') {
            $self->{logger}->writeLogInfo("[proxy] Close connection for $_");
            $self->{clients}->{$_}->{class}->close();
        }
    }
}

sub proxy_ssh {
    my ($self, %options) = @_;

    my ($code, $decoded_data) = $self->json_decode(argument => $options{data});
    return if ($code == 1);

    if ($options{action} eq 'PING') {
        if ($self->{clients}->{ $options{target_client} }->{class}->ping() == -1) {
            $self->{clients}->{ $options{target_client} }->{delete} = 1;
        } else {
            $self->{clients}->{ $options{target_client} }->{com_read_internal} = 1;
            $self->send_internal_action({
                action => 'PONG',
                data => { data => { id => $options{target_client} } },
                token => $options{token},
                target => ''
            });
        }
        return ;
    }

    my $retry = 1; # manage server disconnected
    while ($retry >= 0) {
        my ($status, $data_ret) = $self->{clients}->{ $options{target_client} }->{class}->action(
            action => $options{action},
            data => $decoded_data,
            target_direct => $options{target_direct},
            target => $options{target},
            token => $options{token}
        );

        if (ref($data_ret) eq 'ARRAY') {
            foreach (@{$data_ret}) {
                $self->send_log(
                    code => $_->{code},
                    token => $options{token},
                    logging => $decoded_data->{logging},
                    instant => $decoded_data->{instant},
                    data => $_->{data}
                );
            }
            last;
        }

        $self->{logger}->writeLogDebug("[proxy] Sshclient return: [message = $data_ret->{message}]");
        if ($status == 0) {
            $self->send_log(
                code => GORGONE_ACTION_FINISH_OK,
                token => $options{token},
                logging => $decoded_data->{logging},
                data => $data_ret
            );
            last;
        }

        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            logging => $decoded_data->{logging},
            data => $data_ret
        );

        # quit because it's not a ssh connection issue
        last if ($self->{clients}->{ $options{target_client} }->{class}->is_connected() != 0);
        $retry--;
    }
}

sub proxy {
    my (%options) = @_;
    
    if ($options{message} !~ /^\[(.+?)\]\s+\[(.*?)\]\s+\[(.*?)\]\s+(.*)$/m) {
        return undef;
    }
    my ($action, $token, $target_complete, $data) = ($1, $2, $3, $4);
    $connector->{logger}->writeLogDebug(
        "[proxy] Send message: [channel = $options{channel}] [action = $action] [token = $token] [target = $target_complete] [data = $data]"
    );

    if ($action eq 'PROXYADDNODE') {
        $connector->action_proxyaddnode(data => $data);
        return ;
    } elsif ($action eq 'PROXYDELNODE') {
        $connector->action_proxydelnode(data => $data);
        return ;
    } elsif ($action eq 'BCASTLOGGER' && $target_complete eq '') {
        (undef, $data) = $connector->json_decode(argument => $data);
        $connector->action_bcastlogger(data => $data);
        return ;
    } elsif ($action eq 'BCASTCOREKEY' && $target_complete eq '') {
        (undef, $data) = $connector->json_decode(argument => $data);
        $connector->action_bcastcorekey(data => $data);
        return ;
    } elsif ($action eq 'PROXYCLOSECONNECTION') {
        $connector->action_proxycloseconnection(data => $data);
        return ;
    } elsif ($action eq 'PROXYSTOPREADCHANNEL') {
        $connector->action_proxystopreadchannel(data => $data);
        return ;
    }

    if ($target_complete !~ /^(.+)~~(.+)$/) {
        $connector->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $token,
            data => {
                message => "unknown target format '$target_complete'"
            }
        );
        return ;
    }

    my ($target_client, $target, $target_direct) = ($1, $2, 1);
    if ($target_client ne $target) {
        $target_direct = 0;
    }
    if (!defined($connector->{clients}->{$target_client}->{class})) {
        if ($connector->connect(id => $target_client) != 0) {
            $connector->send_log(
                code => GORGONE_ACTION_FINISH_KO,
                token => $token,
                data => {
                    message => "cannot connect on target node '$target_client'"
                }
            );
            return ;
        }
    }

    if ($connector->{clients}->{$target_client}->{type} eq 'push_zmq') {
        my ($status, $msg) = $connector->{clients}->{$target_client}->{class}->send_message(
            action => $action,
            token => $token,
            target => $target_direct == 0 ? $target : undef,
            data => $data
        );
        if ($status != 0) {
            $connector->send_log(
                code => GORGONE_ACTION_FINISH_KO,
                token => $token,
                data => {
                    message => "Send message problem for '$target': $msg"
                }
            );
            $connector->{logger}->writeLogError("[proxy] Send message problem for '$target': $msg");
            $connector->{clients}->{$target_client}->{delete} = 1;
        }
    } elsif ($connector->{clients}->{$target_client}->{type} eq 'push_ssh') {
        $connector->proxy_ssh(
            action => $action,
            data => $data,
            target_client => $target_client,
            target => $target,
            target_direct => $target_direct,
            token => $token
        );
    }
}

sub event {
    my ($self, %options) = @_;

    my $socket;
    if (defined($options{channel})) {
        return if (
            defined($self->{clients}->{ $options{channel} }) && 
            ($self->{clients}->{ $options{channel} }->{com_read_internal} == 0 || $self->{clients}->{ $options{channel} }->{delete} == 1)
        );

        $socket = $options{channel} eq 'control' ? $self->{internal_socket} : $self->{internal_channels}->{ $options{channel} };
    } else {
        $socket = $options{socket};
        $options{channel} = 'control';
    }

    while (my $events = gorgone::standard::library::zmq_events(socket => $socket)) {
        if ($events & ZMQ_POLLIN) {
            my ($message) = $self->read_message(socket => $socket);
            next if (!defined($message));

            proxy(message => $message, channel => $options{channel});
            if ($self->{stop} == 1 && (time() - $self->{exit_timeout}) > $self->{stop_time}) {
                $self->exit_process();
            }
            return if (
                defined($self->{clients}->{ $options{channel} }) && 
                ($self->{clients}->{ $options{channel} }->{com_read_internal} == 0 || $self->{clients}->{ $options{channel} }->{delete} == 1)
            );
        } else {
            last;
        }
    }
}

sub periodic_exec {
    foreach (keys %{$connector->{clients}}) {
        if (defined($connector->{clients}->{$_}->{delete}) && $connector->{clients}->{$_}->{delete} == 1) {
            $connector->send_internal_action({
                action => 'PONGRESET',
                data => '{ "data": { "id": ' . $_ . ' } }',
                data_noencode => 1,
                token => $connector->generate_token(),
                target => ''
            });
            $connector->{clients}->{$_}->{class}->close() if (defined($connector->{clients}->{$_}->{class}));
            $connector->{clients}->{$_}->{class} = undef;
            $connector->{clients}->{$_}->{delete} = 0;
            $connector->{clients}->{$_}->{com_read_internal} = 0;
            next;
        }
    }

    if ($connector->{stop} == 1) {
        $connector->exit_process();
    }
}

sub run {
    my ($self, %options) = @_;

    $self->{internal_socket} = gorgone::standard::library::connect_com(
        context => $self->{zmq_context},
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgone-proxy-' . $self->{pool_id},
        logger => $self->{logger},
        type => $self->get_core_config(name => 'internal_com_type'),
        path => $self->get_core_config(name => 'internal_com_path')
    );
    $self->send_internal_action({
        action => 'PROXYREADY',
        data => {
            pool_id => $self->{pool_id}
        }
    });

    my $watcher_timer = $self->{loop}->timer(5, 5, \&periodic_exec);
    my $watcher_io = $self->{loop}->io(
        $self->{internal_socket}->get_fd(),
        EV::READ,
        sub {
            $connector->event(channel => 'control');
        }
    );

    $self->{loop}->run();
}

1;
