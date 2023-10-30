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

package gorgone::modules::core::proxy::httpserver;

use base qw(gorgone::class::module);

use strict;
use warnings;
use gorgone::standard::library;
use gorgone::standard::constants qw(:all);
use gorgone::standard::misc;
use Mojolicious::Lite;
use Mojo::Server::Daemon;
use IO::Socket::SSL;
use IO::Handle;
use JSON::XS;
use IO::Poll qw(POLLIN POLLPRI);
use EV;

my %handlers = (TERM => {}, HUP => {});
my ($connector);

websocket '/' => sub {
    my $mojo = shift;

    $connector->{logger}->writeLogDebug('[proxy] httpserver websocket client connected: ' . $mojo->tx->connection);

    $connector->{ws_clients}->{ $mojo->tx->connection } = {
        tx => $mojo->tx,
        logged => 0,
        last_update => time(),
        authorization => $mojo->tx->req->headers->header('authorization')
    };

    $mojo->on(message => sub {
        my ($mojo, $msg) = @_;

        $connector->{ws_clients}->{ $mojo->tx->connection }->{last_update} = time();

        $connector->{logger}->writeLogDebug("[proxy] httpserver receiving message: " . $msg);

        my $rv = $connector->is_logged_websocket(ws_id => $mojo->tx->connection, data => $msg);
        return if ($rv == 0);

        read_message_client(data => $msg);
    });

    $mojo->on(finish => sub {
        my ($mojo, $code, $reason) = @_;

        $connector->{logger}->writeLogDebug('[proxy] httpserver websocket client disconnected: ' . $mojo->tx->connection);
        $connector->clean_websocket(ws_id => $mojo->tx->connection, finish => 1);
    });
};

sub construct {
    my ($class, %options) = @_;
    $connector = $class->SUPER::new(%options);
    bless $connector, $class;

    $connector->{ws_clients} = {};
    $connector->{identities} = {};

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
    $self->{logger}->writeLogDebug("[proxy] $$ Receiving order to stop...");
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

sub run {
    my ($self, %options) = @_;

    my $listen = 'reuse=1';
    if ($self->{config}->{httpserver}->{ssl} eq 'true') {
        if (!defined($self->{config}->{httpserver}->{ssl_cert_file}) || $self->{config}->{httpserver}->{ssl_cert_file} eq '' ||
            ! -r "$self->{config}->{httpserver}->{ssl_cert_file}") {
            $connector->{logger}->writeLogError("[proxy] httpserver cannot read/find ssl-cert-file");
            exit(1);
        }
        if (!defined($self->{config}->{httpserver}->{ssl_key_file}) || $self->{config}->{httpserver}->{ssl_key_file} eq '' ||
            ! -r "$self->{config}->{httpserver}->{ssl_key_file}") {
            $connector->{logger}->writeLogError("[proxy] httpserver cannot read/find ssl-key-file");
            exit(1);
        }
        $listen .= '&cert=' . $self->{config}->{httpserver}->{ssl_cert_file} . '&key=' . $self->{config}->{httpserver}->{ssl_key_file};
    }
    my $proto = 'http';
    if ($self->{config}->{httpserver}->{ssl} eq 'true') {
        $proto = 'https';
        if (defined($self->{config}->{httpserver}->{passphrase}) && $self->{config}->{httpserver}->{passphrase} ne '') {
            IO::Socket::SSL::set_defaults(SSL_passwd_cb => sub { return $connector->{config}->{httpserver}->{passphrase} } );
        }
    }

    $self->{internal_socket} = gorgone::standard::library::connect_com(
        context => $self->{zmq_context},
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgone-proxy-httpserver',
        logger => $self->{logger},
        type => $self->get_core_config(name => 'internal_com_type'),
        path => $self->get_core_config(name => 'internal_com_path')
    );
    $self->send_internal_action({
        action => 'PROXYREADY',
        data => {
            httpserver => 1
        }
    });
    $self->read_zmq_events();

    my $type = ref(Mojo::IOLoop->singleton->reactor);
    my $watcher_io;
    if ($type eq 'Mojo::Reactor::Poll') {
        Mojo::IOLoop->singleton->reactor->{io}{ $self->{internal_socket}->get_fd()} = {
            cb => sub { $connector->read_zmq_events(); },
            mode => POLLIN | POLLPRI
        };
    }  else {
        # need EV version 4.32
        $watcher_io = EV::io(
            $self->{internal_socket}->get_fd(),
            EV::READ,
            sub {
                $connector->read_zmq_events();
            }
        );
    }

    #my $socket_fd = $self->{internal_socket}->get_fd();
    #my $socket = IO::Handle->new_from_fd($socket_fd, 'r');
    #Mojo::IOLoop->singleton->reactor->io($socket => sub {
    #    $connector->read_zmq_events();
    #});
    #Mojo::IOLoop->singleton->reactor->watch($socket, 1, 0);

    Mojo::IOLoop->singleton->recurring(60 => sub {
        $connector->{logger}->writeLogDebug('[proxy] httpserver recurring timeout loop');
        my $ctime = time();
        foreach my $ws_id (keys %{$connector->{ws_clients}}) {
            if (($ctime - $connector->{ws_clients}->{$ws_id}->{last_update}) > 300) {
                $connector->{logger}->writeLogDebug('[proxy] httpserver websocket client timeout reached: ' . $ws_id);
                $connector->close_websocket(
                    code => 500,
                    message  => 'timeout reached',
                    ws_id => $ws_id
                );
            }
        }
    });

    app->mode('production');
    my $daemon = Mojo::Server::Daemon->new(
        app    => app,
        listen => [$proto . '://' . $self->{config}->{httpserver}->{address} . ':' . $self->{config}->{httpserver}->{port} . '?' . $listen]
    );
    $daemon->inactivity_timeout(180);

    $daemon->run();

    exit(0);
}

sub read_message_client {
    my (%options) = @_;

    if ($options{data} =~ /^\[PONG\]/) {
        return undef if ($options{data} !~ /^\[(.+?)\]\s+\[(.*?)\]\s+\[.*?\]\s+(.*)/m);

        my ($action, $token) = ($1, $2);
        my ($rv, $data) = $connector->json_decode(argument => $3);
        return undef if ($rv == 1);

        $connector->send_internal_action({
            action => 'PONG',
            data => $data,
            token => $token,
            target => ''
        });
        $connector->read_zmq_events();
    } elsif ($options{data} =~ /^\[(?:REGISTERNODES|UNREGISTERNODES|SYNCLOGS|SETLOGS)\]/) {
        return undef if ($options{data} !~ /^\[(.+?)\]\s+\[(.*?)\]\s+\[.*?\]\s+(.*)/ms);

        my ($action, $token, $data)  = ($1, $2, $3);

        $connector->send_internal_action({
            action => $action,
            data => $data,
            data_noencode => 1,
            token => $token,
            target => ''
        });
        $connector->read_zmq_events();
    }
}

sub proxy {
    my (%options) = @_;
    
    return undef if ($options{message} !~ /^\[(.+?)\]\s+\[(.*?)\]\s+\[(.*?)\]\s+(.*)$/m);

    my ($action, $token, $target_complete, $data) = ($1, $2, $3, $4);
    $connector->{logger}->writeLogDebug(
        "[proxy] httpserver send message: [action = $action] [token = $token] [target = $target_complete] [data = $data]"
    );

    if ($action eq 'BCASTLOGGER' && $target_complete eq '') {
        (undef, $data) = $connector->json_decode(argument => $data);
        $connector->action_bcastlogger(data => $data);
        return ;
    } elsif ($action eq 'BCASTCOREKEY' && $target_complete eq '') {
        (undef, $data) = $connector->json_decode(argument => $data);
        $connector->action_bcastcorekey(data => $data);
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
        $connector->read_zmq_events();
        return ;
    }

    my ($target_client, $target, $target_direct) = ($1, $2, 1);
    if ($target_client ne $target) {
        $target_direct = 0;
    }

    if (!defined($connector->{identities}->{$target_client})) {
        $connector->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $token,
            data => {
                message => "cannot get connection from target node '$target_client'"
            }
        );
        $connector->read_zmq_events();
        return ;
    }

    my $message = gorgone::standard::library::build_protocol(
        action => $action,
        token => $token,
        target => $target_direct == 0 ? $target : undef,
        data => $data
    );

    $connector->{ws_clients}->{ $connector->{identities}->{$target_client} }->{tx}->send({text => $message});
}

sub read_zmq_events {
    my ($self, %options) = @_;

    while ($self->{internal_socket}->has_pollin()) {
        my ($message) = $connector->read_message();
        proxy(message => $message);
    }
}

sub is_logged_websocket {
    my ($self, %options) = @_;

    return 1 if ($self->{ws_clients}->{ $options{ws_id} }->{logged} == 1);

    if (!defined($self->{ws_clients}->{ $options{ws_id} }->{authorization}) || 
        $self->{ws_clients}->{ $options{ws_id} }->{authorization} !~ /^\s*Bearer\s+$self->{config}->{httpserver}->{token}\s*$/) {
        $self->close_websocket(
            code => 500,
            message  => 'token authorization unallowed',
            ws_id => $options{ws_id}
        );
        return 0;
    }

    if ($options{data} !~ /^\[REGISTERNODES\]\s+\[(?:.*?)\]\s+\[.*?\]\s+(.*)/ms) {
        $self->close_websocket(
            code => 500,
            message  => 'please registernodes',
            ws_id => $options{ws_id}
        );
        return 0;
    }

    my $content;
    eval {
        $content = JSON::XS->new->decode($1);
    };
    if ($@) {
        $self->close_websocket(
            code => 500,
            message  => 'decode error: unsupported format',
            ws_id => $options{ws_id}
        );
        return 0;
    }

    $self->{logger}->writeLogDebug("[proxy] httpserver client " . $content->{nodes}->[0]->{id} . " is logged");

    $self->{ws_clients}->{ $options{ws_id} }->{identity} = $content->{nodes}->[0]->{id};
    $self->{identities}->{ $content->{nodes}->[0]->{id} } = $options{ws_id};
    $self->{ws_clients}->{ $options{ws_id} }->{logged} = 1;
    return 2;
}

sub clean_websocket {
    my ($self, %options) = @_;

    return if (!defined($self->{ws_clients}->{ $options{ws_id} }));

    $self->{ws_clients}->{ $options{ws_id} }->{tx}->finish() if (!defined($options{finish}));
    delete $self->{identities}->{ $self->{ws_clients}->{ $options{ws_id} }->{identity} } 
        if (defined($self->{ws_clients}->{ $options{ws_id} }->{identity}));
    delete $self->{ws_clients}->{ $options{ws_id} };
}

sub close_websocket {
    my ($self, %options) = @_;

    $self->{ws_clients}->{ $options{ws_id} }->{tx}->send({json => {
        code => $options{code},
        message  => $options{message}
    }});
    $self->clean_websocket(ws_id => $options{ws_id});
}

1;
