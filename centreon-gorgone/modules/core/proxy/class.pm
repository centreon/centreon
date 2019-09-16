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

package modules::core::proxy::class;

use base qw(centreon::gorgone::module);

use strict;
use warnings;
use centreon::gorgone::common;
use centreon::gorgone::clientzmq;
use modules::core::proxy::sshclient;
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
    $connector->{subnodes} = {};
    
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
    $self->{logger}->writeLogInfo("[proxy] -class- $$ Receiving order to stop...");
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

        centreon::gorgone::common::zmq_send_message(
            socket => $connector->{internal_socket},
            action => 'PONG',
            token => $token,
            target => '',
            data => $data,
        );
    } elsif ($options{data} =~ /^\[REGISTERNODES|UNREGISTERNODES|SYNCLOGS\]/) {
        if ($options{data} !~ /^\[(.+?)\]\s+\[(.*?)\]\s+\[.*?\]\s+(.*)/m) {
            return undef;
        }
        my ($action, $token, $data)  = ($1, $2, $3);
        
        centreon::gorgone::common::zmq_send_message(
            socket => $connector->{internal_socket},
            action => $action,
            token => $token,
            target => '',
            data => $data
        );
    } elsif ($options{data} =~ /^\[ACK\]\s+\[(.*?)\]\s+(.*)/m) {
        my $data;
        eval {
            $data = JSON::XS->new->utf8->decode($2);
        };
        if ($@) {
            return undef;
        }
        
        if (defined($data->{data}->{action}) && $data->{data}->{action} eq 'getlog') {
            centreon::gorgone::common::zmq_send_message(
                socket => $connector->{internal_socket},
                action => 'SETLOGS',
                token => $1,
                target => '',
                data => $2
            );
        }
    }
}

sub connect {
    my ($self, %options) = @_;

    if ($self->{clients}->{$options{id}}->{type} eq 'push_zmq') {
        $self->{clients}->{$options{id}}->{class} = centreon::gorgone::clientzmq->new(
            identity => 'proxy-' . $self->{core_id} . '-' . $options{id}, 
            cipher => $self->{clients}->{$options{id}}->{cipher}, 
            vector => $self->{clients}->{$options{id}}->{vector},
            server_pubkey => $self->{clients}->{$options{id}}->{server_pubkey},
            client_pubkey => $self->{clients}->{$options{id}}->{client_pubkey},
            client_privkey => $self->{clients}->{$options{id}}->{client_privkey},
            target_type =>
                defined($self->{clients}->{$options{id}}->{target_type}) ? $self->{clients}->{$options{id}}->{target_type} : 'tcp',
            target_path =>
                defined($self->{clients}->{$options{id}}->{target_path}) ? $self->{clients}->{$options{id}}->{target_path} : $self->{clients}->{$options{id}}->{address} . ':' . $self->{clients}->{$options{id}}->{port},
            logger => $self->{logger},
        );
        $self->{clients}->{$options{id}}->{class}->init(callback => \&read_message);
    } elsif ($self->{clients}->{$options{id}}->{type} eq 'push_ssh') {
        $self->{clients}->{$options{id}}->{class} = modules::core::proxy::sshclient->new(logger => $self->{logger});
        my $code = $self->{clients}->{$options{id}}->{class}->open_session(
            ssh_host => $self->{clients}->{$options{id}}->{address},
            ssh_port => $self->{clients}->{$options{id}}->{ssh_port},
            ssh_username => $self->{clients}->{$options{id}}->{ssh_username},
            ssh_password => $self->{clients}->{$options{id}}->{ssh_password},
            strict_serverkey_check => $self->{clients}->{$options{id}}->{strict_serverkey_check},
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

    $self->{clients}->{$data->{id}} = $data;
    $self->{clients}->{$data->{id}}->{delete} = 0;
    $self->{clients}->{$data->{id}}->{class} = undef;

    my $temp = {};
    foreach (@{$data->{nodes}}) {
        $temp->{$_} = 1;
        $self->{subnodes}->{$_} = $data->{id};
    }
    foreach (keys %{$self->{subnodes}}) {
        delete $self->{subnodes}->{$_}
            if ($self->{subnodes}->{$_} eq $data->{id} && !defined($temp->{$_}));
    }
}

sub action_proxydelnode {
    my ($self, %options) = @_;

    my ($code, $data) = $self->json_decode(argument => $options{data});
    return if ($code == 1);

    if (defined($self->{clients}->{$data->{id}})) {
        $self->{clients}->{$data->{id}}->{delete} = 1;
    }

    foreach (keys %{$self->{subnodes}}) {
        delete $self->{subnodes}->{$_}
            if ($self->{subnodes}->{$_} eq $data->{id});
    }
}

sub action_proxyaddsubnode {
    my ($self, %options) = @_;

    my ($code, $data) = $self->json_decode(argument => $options{data});
    return if ($code == 1);

    foreach (keys %{$self->{subnodes}}) {
        delete $self->{subnodes}->{$_} if ($self->{subnodes}->{$_} eq $data->{id});
    }
    foreach (keys %{$data->{nodes}}) {
        $self->{subnodes}->{$_} = $data->{id};
    }
}

sub proxy {
    my (%options) = @_;
    
    if ($options{message} !~ /^\[(.+?)\]\s+\[(.*?)\]\s+\[(.*?)\]\s+(.*)$/m) {
        return undef;
    }
    my ($action, $token, $target, $data) = ($1, $2, $3, $4);
    if ($action eq 'PROXYADDNODE') {
        action_proxyaddnode($connector, data => $data);
        return ;
    } elsif ($action eq 'PROXYADDSUBNODE') {
        action_proxyaddsubnode($connector, data => $data);
        return ;
    } elsif ($action eq 'PROXYDELNODE') {
        action_proxydelnode($connector, data => $data);
        return ;
    }

    my ($target_client, $target_direct) = ($target, 1);
    if (!defined($connector->{clients}->{$target})) {
        $target_client = $connector->{subnodes}->{$target};
        $target_direct = 0;
    }
    if (!defined($connector->{clients}->{$target_client}->{class})) {
        if ($connector->connect(id => $target_client) != 0) {
            $connector->send_log(code => centreon::gorgone::module::ACTION_FINISH_KO, token => $token, data => { message => "cannot connect on target node '$target_client'" });
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
            $connector->send_log(code => centreon::gorgone::module::ACTION_FINISH_KO, token => $token, data => { message => "Send message problem for '$target': $msg" });
            $connector->{logger}->writeLogError("[proxy] -class- Send message problem for '$target': $msg");
            $connector->{clients}->{$target}->{delete} = 1;
        }
    } elsif ($connector->{clients}->{$target_client}->{type} eq 'push_ssh') {
        my ($code, $decoded_data) = $connector->json_decode(argument => $data);
        return if ($code == 1);
        
        my ($status, $data_ret) = $connector->{clients}->{$target_client}->{class}->action(
            action => $action,
            data => $decoded_data,
            target_direct => $target_direct
        );
        if ($status == 0) {
            $connector->send_log(code => centreon::gorgone::module::ACTION_FINISH_OK, token => $token, data => $data_ret);
        } else {
            $connector->send_log(code => centreon::gorgone::module::ACTION_FINISH_KO, token => $token, data => $data_ret);
        } 
    }

    $connector->{logger}->writeLogDebug("[proxy] -class- Send message: [action = $action] [token = $token] [target = $target] [data = $data]");
}

sub event_internal {
    while (1) {
        my $message = centreon::gorgone::common::zmq_dealer_read_message(socket => $connector->{internal_socket});

        proxy(message => $message);        
        last unless (centreon::gorgone::common::zmq_still_read(socket => $connector->{internal_socket}));
    }
}

sub run {
    my ($self, %options) = @_;

    # Connect internal
    $self->{internal_socket} = centreon::gorgone::common::connect_com(
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgoneproxy-' . $self->{pool_id},
        logger => $self->{logger},
        type => $self->{config_core}{internal_com_type},
        path => $self->{config_core}{internal_com_path}
    );
    centreon::gorgone::common::zmq_send_message(
        socket => $self->{internal_socket},
        action => 'PROXYREADY',
        data => { pool_id => $self->{pool_id} },
        json_encode => 1
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
                if ($self->{clients}->{$_}->{type} eq 'push_zmq') {
                    centreon::gorgone::common::zmq_send_message(
                        socket => $self->{internal_socket},
                        action => 'PONGRESET',
                        token => $self->generate_token(),
                        target => '',
                        data => '{ "id": ' . $_ . '}' 
                    );
                }
                $self->{clients}->{$_}->{class}->close();
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
            $self->{logger}->writeLogInfo("[proxy] -class- $$ has quit");
            zmq_close($connector->{internal_socket});
            exit(0);
        }
    }
}

1;
