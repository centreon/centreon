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
use ZMQ::LibZMQ4;
use ZMQ::Constants qw(:all);
use JSON::XS;

my %handlers = (TERM => {}, HUP => {});
my ($connector);

sub new {
    my ($class, %options) = @_;

    $connector  = {};
    $connector->{module_id} = $options{module_id};
    $connector->{internal_socket} = undef;
    $connector->{logger} = $options{logger};
    $connector->{core_id} = $options{core_id};
    $connector->{pool_id} = $options{pool_id};
    $connector->{config} = $options{config};
    $connector->{config_core} = $options{config_core};
    $connector->{stop} = 0;
    $connector->{clients} = {};
    
    bless $connector, $class;
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

sub read_message {
    my (%options) = @_;

    return undef if (!defined($options{identity}) || $options{identity} !~ /^proxy-(.*?)-(.*?)$/);

    my ($client_identity) = ($2);
    if ($options{data} =~ /^\[PONG\]/) {
        if ($options{data} !~ /^\[(.+?)\]\s+\[(.*?)\]\s+\[.*?\]\s+(.*)/m) {
            return undef;
        }
        my ($action, $token, $data) = ($1, $2, $3);

        $connector->send_internal_action(
            action => 'PONG',
            data => $data,
            data_noencode => 1,
            token => $token,
            target => ''
        );
    } elsif ($options{data} =~ /^\[(?:REGISTERNODES|UNREGISTERNODES|SYNCLOGS)\]/) {
        if ($options{data} !~ /^\[(.+?)\]\s+\[(.*?)\]\s+\[.*?\]\s+(.*)/ms) {
            return undef;
        }
        my ($action, $token, $data)  = ($1, $2, $3);

        $connector->send_internal_action(
            action => $action,
            data => $data,
            data_noencode => 1,
            token => $token,
            target => ''
        );
    } elsif ($options{data} =~ /^\[ACK\]\s+\[(.*?)\]\s+(.*)/ms) {
        my $data;
        eval {
            $data = JSON::XS->new->utf8->decode($2);
        };
        if ($@) {
            return undef;
        }
        
        if (defined($data->{data}->{action}) && $data->{data}->{action} eq 'getlog') {
            $connector->send_internal_action(
                action => 'SETLOGS',
                data => $2,
                data_noencode => 1,
                token => $1,
                target => ''
            );
        }
    }
}

sub connect {
    my ($self, %options) = @_;

    if ($self->{clients}->{$options{id}}->{type} eq 'push_zmq') {
        $self->{clients}->{$options{id}}->{class} = gorgone::class::clientzmq->new(
            identity => 'proxy-' . $self->{core_id} . '-' . $options{id}, 
            cipher => $self->{clients}->{$options{id}}->{cipher}, 
            vector => $self->{clients}->{$options{id}}->{vector},
            client_pubkey => 
                defined($self->{clients}->{$options{id}}->{client_pubkey}) && $self->{clients}->{$options{id}}->{client_pubkey} ne ''
                    ? $self->{clients}->{$options{id}}->{client_pubkey} : $self->{config_core}->{pubkey},
            client_privkey =>
                defined($self->{clients}->{$options{id}}->{client_privkey}) && $self->{clients}->{$options{id}}->{client_privkey} ne ''
                    ? $self->{clients}->{$options{id}}->{client_privkey} : $self->{config_core}->{privkey},
            target_type => defined($self->{clients}->{$options{id}}->{target_type}) ?
                $self->{clients}->{$options{id}}->{target_type} :
                'tcp',
            target_path => defined($self->{clients}->{$options{id}}->{target_path}) ?
                $self->{clients}->{$options{id}}->{target_path} :
                $self->{clients}->{$options{id}}->{address} . ':' . $self->{clients}->{$options{id}}->{port},
            config_core => $self->{config_core},
            logger => $self->{logger}
        );
        $self->{clients}->{$options{id}}->{class}->init(callback => \&read_message);
    } elsif ($self->{clients}->{$options{id}}->{type} eq 'push_ssh') {
        $self->{clients}->{$options{id}}->{class} = gorgone::modules::core::proxy::sshclient->new(logger => $self->{logger});
        my $code = $self->{clients}->{$options{id}}->{class}->open_session(
            ssh_host => $self->{clients}->{$options{id}}->{address},
            ssh_port => $self->{clients}->{$options{id}}->{ssh_port},
            ssh_username => $self->{clients}->{$options{id}}->{ssh_username},
            ssh_password => $self->{clients}->{$options{id}}->{ssh_password},
            ssh_directory => $self->{clients}->{$options{id}}->{ssh_directory},
            ssh_identity => $self->{clients}->{$options{id}}->{ssh_identity},
            strict_serverkey_check => $self->{clients}->{$options{id}}->{strict_serverkey_check},
            ssh_connect_timeout => $self->{clients}->{$options{id}}->{ssh_connect_timeout}
        );
        if ($code != 0) {
            $self->{clients}->{$options{id}}->{delete} = 1;
            return -1;
        }
    }

    return 0;
}

sub action_proxyaddnode {
    my ($self, %options) = @_;

    my ($code, $data) = $self->json_decode(argument => $options{data});
    return if ($code == 1);

    if (defined($self->{clients}->{$data->{id}}->{class})) {
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
        $self->send_internal_action(
            action => 'PONGRESET',
            data => '{ "data": { "id": ' . $data->{id} . ' } }',
            data_noencode => 1,
            token => $self->generate_token(),
            target => ''
        );

        $self->{clients}->{$data->{id}}->{class}->close();
    }

    $self->{clients}->{$data->{id}} = $data;
    $self->{clients}->{$data->{id}}->{delete} = 0;
    $self->{clients}->{$data->{id}}->{class} = undef;
}

sub action_proxydelnode {
    my ($self, %options) = @_;

    my ($code, $data) = $self->json_decode(argument => $options{data});
    return if ($code == 1);

    if (defined($self->{clients}->{$data->{id}})) {
        $self->{clients}->{$data->{id}}->{delete} = 1;
    }
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
        my $action = 'PONG';
        if ($self->{clients}->{ $options{target_client} }->{class}->ping() == -1) {
            $action = 'PONGRESET';
            $self->{clients}->{ $options{target_client} }->{class}->close();
            $self->{clients}->{ $options{target_client} }->{class} = undef;
            $self->{clients}->{ $options{target_client} }->{delete} = 0;
        }
        $self->send_internal_action(
            action => $action,
            data => { data => { id => $options{target_client} } },
            token => $options{token},
            target => ''
        );
        return ;
    }

    my $retry = 1; # manage server disconnected
    while ($retry >= 0) {
        my ($status, $data_ret) = $self->{clients}->{ $options{target_client} }->{class}->action(
            action => $options{action},
            data => $decoded_data,
            target_direct => $options{target_direct},
            target => $options{target}
        );

        if (ref($data_ret) eq 'ARRAY') {
            foreach (@{$data_ret}) {
                $self->send_log(
                    code => $_->{code},
                    token => $options{token},
                    instant => $_->{instant},
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
                data => $data_ret
            );
            last;
        }

        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
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
        "[proxy] Send message: [action = $action] [token = $token] [target = $target_complete] [data = $data]"
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
            target => $target,
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

sub event_internal {
    while (1) {
        my $message = gorgone::standard::library::zmq_dealer_read_message(socket => $connector->{internal_socket});

        proxy(message => $message);        
        last unless (gorgone::standard::library::zmq_still_read(socket => $connector->{internal_socket}));
    }
}

sub run {
    my ($self, %options) = @_;

    # Connect internal
    $self->{internal_socket} = gorgone::standard::library::connect_com(
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgoneproxy-' . $self->{pool_id},
        logger => $self->{logger},
        type => $self->{config_core}->{internal_com_type},
        path => $self->{config_core}->{internal_com_path}
    );
    $self->send_internal_action(
        action => 'PROXYREADY',
        data => {
            pool_id => $self->{pool_id}
        },
    );
    my $poll = {
        socket  => $self->{internal_socket},
        events  => ZMQ_POLLIN,
        callback => \&event_internal,
    };
    while (1) {
        my $polls = [$poll];
        foreach (keys %{$self->{clients}}) {
            if (defined($self->{clients}->{$_}->{delete}) && $self->{clients}->{$_}->{delete} == 1) {
                $self->send_internal_action(
                    action => 'PONGRESET',
                    data => '{ "data": { "id": ' . $_ . ' } }',
                    data_noencode => 1,
                    token => $self->generate_token(),
                    target => ''
                );
                $self->{clients}->{$_}->{class}->close() if (defined($self->{clients}->{$_}->{class}));
                $self->{clients}->{$_}->{class} = undef;
                $self->{clients}->{$_}->{delete} = 0;
                next;
            }

            if (defined($self->{clients}->{$_}->{class}) && $self->{clients}->{$_}->{type} eq 'push_zmq') {
                push @$polls, $self->{clients}->{$_}->{class}->get_poll();
            }
        }

        # we try to do all we can
        my $rev = zmq_poll($polls, 5000);
        
        # Sometimes (with big message) we have a undef ??!!!
        next if (!defined($rev));
        
        if ($rev == 0 && $self->{stop} == 1) {
            $self->{logger}->writeLogInfo("[proxy] $$ has quit");
            $self->close_connections();
            zmq_close($self->{internal_socket});
            exit(0);
        }
    }
}

1;
