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

package gorgone::modules::core::httpserverng::class;

use base qw(gorgone::class::module);

use strict;
use warnings;
use gorgone::standard::library;
use gorgone::standard::constants qw(:all);
use gorgone::standard::misc;
use Mojolicious::Lite;
use Mojo::Server::Daemon;
use Authen::Simple::Password;
use IO::Socket::SSL;
use IO::Handle;
use JSON::XS;
use IO::Poll qw(POLLIN POLLPRI);
use EV;

my %handlers = (TERM => {}, HUP => {});
my ($connector);

websocket '/' => sub {
    my $mojo = shift;

    $connector->{logger}->writeLogDebug('[httpserverng] websocket client connected: ' . $mojo->tx->connection);

    if ($connector->{allowed_hosts_enabled} == 1) {
        if ($connector->check_allowed_host(peer_addr => $mojo->tx->remote_address) == 0) {
            $connector->{logger}->writeLogError("[httpserverng] " . $mojo->tx->remote_address . " Unauthorized");
            $mojo->tx->send({json => {
                code => 401,
                message  => 'unauthorized',
            }});
            return ;
        }
    }

    $connector->{ws_clients}->{ $mojo->tx->connection } = {
        tx => $mojo->tx,
        logged => 0,
        last_update => time(),
        tokens => {}
    };

    $mojo->on(message => sub {
        my ($mojo, $msg) = @_;

        $connector->{ws_clients}->{ $mojo->tx->connection }->{last_update} = time();

        my $content;
        eval {
            $content = JSON::XS->new->decode($msg);
        };
        if ($@) {
            $connector->close_websocket(
                code => 500,
                message  => 'decode error: unsupported format',
                ws_id => $mojo->tx->connection
            );
            return ;
        }

        my $rv = $connector->is_logged_websocket(ws_id => $mojo->tx->connection, content => $content);
        return if ($rv != 1);

        $connector->api_root_ws(ws_id => $mojo->tx->connection, content => $content);
    });

    $mojo->on(finish => sub {
        my ($mojo, $code, $reason) = @_;

        $connector->{logger}->writeLogDebug('[httpserverng] websocket client disconnected: ' . $mojo->tx->connection);
        $connector->clean_websocket(ws_id => $mojo->tx->connection, finish => 1);
    });
};

patch '/*' => sub {
    my $mojo = shift;

    $connector->api_call(
        mojo => $mojo,
        method => 'PATCH'
    );
};

post '/*' => sub {
    my $mojo = shift;

    $connector->api_call(
        mojo => $mojo,
        method => 'POST'
    );
};

get '/*' => sub { 
    my $mojo = shift;

    $connector->api_call(
        mojo => $mojo,
        method => 'GET'
    );
};

sub construct {
    my ($class, %options) = @_;
    $connector = $class->SUPER::new(%options);
    bless $connector, $class;

    $connector->{api_endpoints} = $options{api_endpoints};
    $connector->{auth_enabled} = (defined($connector->{config}->{auth}->{enabled}) && $connector->{config}->{auth}->{enabled} eq 'true') ? 1 : 0;
    $connector->{allowed_hosts_enabled} = (defined($connector->{config}->{allowed_hosts}->{enabled}) && $connector->{config}->{allowed_hosts}->{enabled} eq 'true') ? 1 : 0;
    $connector->{clients} = {};
    $connector->{token_watch} = {};
    $connector->{ws_clients} = {};

    if (gorgone::standard::misc::mymodule_load(
            logger => $connector->{logger},
            module => 'NetAddr::IP',
            error_msg => "[httpserverng] -class- cannot load module 'NetAddr::IP'. Cannot use allowed_hosts configuration.")
    ) {
        $connector->{allowed_hosts_enabled} = 0;
    }

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
    $self->{logger}->writeLogDebug("[httpserverng] $$ Receiving order to stop...");
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

sub check_allowed_host {
    my ($self, %options) = @_;

    my $subnet = NetAddr::IP->new($options{peer_addr} . '/32');
    foreach (@{$self->{peer_subnets}}) {
        return 1 if ($_->contains($subnet));
    }

    return 0;
}

sub load_peer_subnets {
    my ($self, %options) = @_;

    return if ($self->{allowed_hosts_enabled} == 0);

    $self->{peer_subnets} = [];
    return if (!defined($connector->{config}->{allowed_hosts}->{subnets}));

    foreach (@{$self->{config}->{allowed_hosts}->{subnets}}) {
        my $subnet = NetAddr::IP->new($_);
        if (!defined($subnet)) {
            $self->{logger}->writeLogError("[httpserverng] Cannot load subnet: $_");
            next;
        }

        push @{$self->{peer_subnets}}, $subnet;
    }
}

sub run {
    my ($self, %options) = @_;

    $self->load_peer_subnets();

    my $listen = 'reuse=1';
    if ($self->{config}->{ssl} eq 'true') {
        if (!defined($self->{config}->{ssl_cert_file}) || $self->{config}->{ssl_cert_file} eq '' ||
            ! -r "$self->{config}->{ssl_cert_file}") {
            $connector->{logger}->writeLogError("[httpserverng] cannot read/find ssl-cert-file");
            exit(1);
        }
        if (!defined($self->{config}->{ssl_key_file}) || $self->{config}->{ssl_key_file} eq '' ||
            ! -r "$self->{config}->{ssl_key_file}") {
            $connector->{logger}->writeLogError("[httpserverng] cannot read/find ssl-key-file");
            exit(1);
        }
        $listen .= '&cert=' . $self->{config}->{ssl_cert_file} . '&key=' . $self->{config}->{ssl_key_file};
    }
    my $proto = 'http';
    if ($self->{config}->{ssl} eq 'true') {
        $proto = 'https';
        if (defined($self->{config}->{passphrase}) && $self->{config}->{passphrase} ne '') {
            IO::Socket::SSL::set_defaults(SSL_passwd_cb => sub { return $connector->{config}->{passphrase} } );
        }
    }

    # Connect internal
    $self->{internal_socket} = gorgone::standard::library::connect_com(
        context => $connector->{zmq_context},
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgone-httpserverng',
        logger => $self->{logger},
        type => $self->get_core_config(name => 'internal_com_type'),
        path => $self->get_core_config(name => 'internal_com_path')
    );
    $self->send_internal_action({
        action => 'HTTPSERVERNGREADY',
        data => {}
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

    #my $socket_fd = gorgone::standard::library::zmq_getfd(socket => $self->{internal_socket});
    #my $socket = IO::Handle->new_from_fd($socket_fd, 'r');
    #Mojo::IOLoop->singleton->reactor->io($socket => sub {
    #    $connector->read_zmq_events();
    #});
    #Mojo::IOLoop->singleton->reactor->watch($socket, 1, 0);

    Mojo::IOLoop->singleton->recurring(60 => sub {
        $connector->{logger}->writeLogDebug('[httpserverng] recurring timeout loop');
        my $ctime = time();
        foreach my $ws_id (keys %{$connector->{ws_clients}}) {
            if (scalar(keys %{$connector->{ws_clients}->{$ws_id}->{tokens}}) <= 0 && ($ctime - $connector->{ws_clients}->{$ws_id}->{last_update}) > 300) {
                $connector->{logger}->writeLogDebug('[httpserverng] websocket client timeout reached: ' . $ws_id);
                $connector->close_websocket(
                    code => 500,
                    message  => 'timeout reached',
                    ws_id => $ws_id
                );
            }
        }
    });

    $self->{basic_auth_plus} = 1;
    eval {
        local $SIG{__DIE__} = 'IGNORE';

        app->plugin('basic_auth_plus');
    };
    if ($@) {
        $self->{basic_auth_plus} = 0;
    }
    if ($self->{auth_enabled} == 1 && $self->{basic_auth_plus} == 0 && $self->{allowed_hosts_enabled} == 0) {
        $connector->{logger}->writeLogError("[httpserverng] need to install the module basic_auth_plus");
        exit(1);
    }

    app->mode('production');
    my $daemon = Mojo::Server::Daemon->new(
        app    => app,
        listen => [$proto . '://' . $self->{config}->{address} . ':' . $self->{config}->{port} . '?' . $listen]
    );
    # more than 2 minutes, need to use async system
    $daemon->inactivity_timeout(120);

    #my $loop = Mojo::IOLoop->new();
    #my $reactor = Mojo::Reactor::EV->new();
    #$reactor->io($socket => sub {
    #    my $message = gorgone::standard::library::zmq_dealer_read_message(socket => $connector->{internal_socket}); 
    #});
    #$reactor->watch($socket, 1, 0);
    #$loop->reactor($reactor);
    #$daemon->ioloop($loop);

    $daemon->run();

    exit(0);
}

sub read_log_event {
    my ($self, %options) = @_;

    my $token = $options{token};
    $token =~ s/-log$//;
    my $response = { error => 'no_log', message => 'No log found for token', data => [], token => $token };
    if (defined($options{data})) {
        my $content;
        eval {
            $content = JSON::XS->new->decode($options{data});
        };
        if ($@) {
            $response = { error => 'decode_error', message => 'Cannot decode response' };
        } elsif (defined($content->{data}->{result}) && scalar(@{$content->{data}->{result}}) > 0) {
            $response = {
                message => 'Logs found',
                token => $token,
                data => $content->{data}->{result}
            };
        }
    }

    if (defined($self->{token_watch}->{ $options{token} }->{ws_id})) {
        $response->{userdata} = $self->{token_watch}->{ $options{token} }->{userdata};
        $self->{ws_clients}->{ $self->{token_watch}->{ $options{token} }->{ws_id} }->{last_update} = time();
        $self->{ws_clients}->{ $self->{token_watch}->{ $options{token} }->{ws_id} }->{tx}->send({json => $response });
        delete $self->{ws_clients}->{ $self->{token_watch}->{ $options{token} }->{ws_id} }->{tokens}->{ $options{token} };
    } else {
        $self->{token_watch}->{ $options{token} }->{mojo}->render(json => $response);
    }
    delete $self->{token_watch}->{ $options{token} };
}

sub read_listener {
    my ($self, %options) = @_;

    my $content;
    eval {
        $content = JSON::XS->new->decode($options{data});
    };
    if ($@) {
        $self->{token_watch}->{ $options{token} }->{mojo}->render(json => { error => 'decode_error', message => 'Cannot decode response' });
        delete $self->{token_watch}->{ $options{token} };
        return ;
    }

    push @{$self->{token_watch}->{ $options{token} }->{results}}, $content;
    if (defined($self->{token_watch}->{ $options{token} }->{ws_id})) {
        $self->{ws_clients}->{ $self->{token_watch}->{ $options{token} }->{ws_id} }->{last_update} = time();
    }

    if ($content->{code} == GORGONE_ACTION_FINISH_KO || $content->{code} == GORGONE_ACTION_FINISH_OK) {
        my $json = { data => $self->{token_watch}->{ $options{token} }->{results} };
        if (defined($self->{token_watch}->{ $options{token} }->{internal}) && $content->{code} == GORGONE_ACTION_FINISH_OK) {
            $json = $content->{data};
        }

        if (defined($self->{token_watch}->{ $options{token} }->{ws_id})) {
            $json->{userdata} = $self->{token_watch}->{ $options{token} }->{userdata};
            $self->{ws_clients}->{ $self->{token_watch}->{ $options{token} }->{ws_id} }->{tx}->send({json => $json });
            delete $self->{ws_clients}->{ $self->{token_watch}->{ $options{token} }->{ws_id} }->{tokens}->{ $options{token} };
        } else {
            $self->{token_watch}->{ $options{token} }->{mojo}->render(json => $json);
        }
        delete $self->{token_watch}->{ $options{token} };
    }
}

sub read_zmq_events {
    my ($self, %options) = @_;

    while ($self->{internal_socket}->has_pollin()) {
        my ($message) = $connector->read_message();
        $connector->{logger}->writeLogDebug('[httpserverng] zmq message received: ' . $message);
        if ($message =~ /^\[(.*?)\]\s+\[(.*?)\]\s+\[.*?\]\s+(.*)$/m || 
            $message =~ /^\[(.*?)\]\s+\[(.*?)\]\s+(.*)$/m) {
            my ($action, $token, $data) = ($1, $2, $3);
            if (defined($connector->{token_watch}->{$token})) {
                if ($action eq 'HTTPSERVERNGLISTENER') {
                    $connector->read_listener(token => $token, data => $data);
                } elsif ($token =~ /-log$/) {
                    $connector->read_log_event(token => $token, data => $data);
                }
            }
            if ((my $method = $connector->can('action_' . lc($action)))) {
                my ($rv, $decoded) = $connector->json_decode(argument => $data, token => $token);
                if (!$rv) {
                    $method->($connector, token => $token, data => $decoded);
                }
            }
        }
    }
}

sub api_call {
    my ($self, %options) = @_;

    if ($self->{allowed_hosts_enabled} == 1) {
        if ($self->check_allowed_host(peer_addr => $options{mojo}->tx->remote_address) == 0) {
            $connector->{logger}->writeLogError("[httpserverng] " . $options{mojo}->tx->remote_address . " Unauthorized");
            return $options{mojo}->render(json => { message => 'unauthorized' }, status => 401);
        }
    }

    if ($self->{auth_enabled} == 1 && $self->{basic_auth_plus} == 1) {
        my ($hash_ref, $auth_ok) = $options{mojo}->basic_auth(
            'Realm Name' => {
                username => $self->{config}->{auth}->{user},
                password => $self->{config}->{auth}->{password}
            }
        );
        if (!$auth_ok) {
            return $options{mojo}->render(json => { message => 'unauthorized' }, status => 401);
        }
    }

    my $path = $options{mojo}->tx->req->url->path;
    my $names = $options{mojo}->req->params->names();
    my $params = {};
    foreach (@$names) {
        $params->{$_} = $options{mojo}->param($_);
    }

    my $content = $options{mojo}->req->json();

    $self->api_root(
        mojo => $options{mojo},
        method => $options{method},
        uri => $path,
        parameters => $params,
        content => $content
    );
}

sub get_log {
    my ($self, %options) = @_;

    if (defined($options{target}) && $options{target} ne '') {
        $self->send_internal_action({
            target => $options{target},
            action => 'GETLOG',
            data => {}
        });
        $self->read_zmq_events();
    }

    my $token_log = $options{token} . '-log';

    if (defined($options{ws_id})) {
        $self->{ws_clients}->{ $options{ws_id} }->{tokens}->{$token_log} = 1;
    }
    $self->{token_watch}->{$token_log} = {
        ws_id => $options{ws_id},
        userdata => $options{userdata},
        mojo => $options{mojo}
    };

    $self->send_internal_action({
        action => 'GETLOG',
        token => $token_log,
        data => {
            token => $options{token},
            %{$options{parameters}}
        }
    });

    $self->read_zmq_events();

    # keep reference tx to avoid "Transaction already destroyed"
    $self->{token_watch}->{$token_log}->{tx} = $options{mojo}->render_later()->tx if (!defined($options{ws_id}));
}

sub call_action {
    my ($self, %options) = @_;

    my $action_token = gorgone::standard::library::generate_token();

    if ($options{async} == 0) {
        if (defined($options{ws_id})) {
            $self->{ws_clients}->{ $options{ws_id} }->{tokens}->{$action_token} = 1;
        }
        $self->{token_watch}->{$action_token} = {
            ws_id => $options{ws_id},
            userdata => $options{userdata},
            mojo => $options{mojo},
            internal => $options{internal},
            results => []
        };

        $self->send_internal_action({
            action => 'ADDLISTENER',
            data => [
                {
                    identity => 'gorgone-httpserverng',
                    event => 'HTTPSERVERNGLISTENER',
                    token => $action_token,
                    target => $options{target},
                    log_pace => 5,
                    timeout => 110
                }
            ]
        });
        $self->read_zmq_events();
    }

    $self->send_internal_action({
        action => $options{action},
        target => $options{target},
        token => $action_token,
        data => $options{data}
    });
    $self->read_zmq_events();

    if ($options{async} == 1) {
        $options{mojo}->render(json => { token => $action_token }, status => 200);
    } else {
        # keep reference tx to avoid "Transaction already destroyed"
        $self->{token_watch}->{$action_token}->{tx} = $options{mojo}->render_later()->tx if (!defined($options{ws_id}));
    }
}

sub is_logged_websocket {
    my ($self, %options) = @_;

    return 1 if ($self->{ws_clients}->{ $options{ws_id} }->{logged} == 1);

    if ($self->{auth_enabled} == 1) {
        if (!defined($options{content}->{username}) || $options{content}->{username} eq '' ||
            !defined($options{content}->{password}) || $options{content}->{password} eq '') {
            $self->close_websocket(
                code => 500,
                message  => 'please set username/password',
                ws_id => $options{ws_id}
            );
            return 0;
        }

        unless ($options{content}->{username} eq $self->{config}->{auth}->{user} &&
            Authen::Simple::Password->check($options{content}->{password}, $self->{config}->{auth}->{password})) {
            $self->close_websocket(
                code => 401,
                message  => 'unauthorized user',
                ws_id => $options{ws_id}
            );
            return 0;
        }
    }

    $self->{ws_clients}->{ $options{ws_id} }->{logged} = 1;
    return 2;
}

sub clean_websocket {
    my ($self, %options) = @_;

    return if (!defined($self->{ws_clients}->{ $options{ws_id} }));

    $self->{ws_clients}->{ $options{ws_id} }->{tx}->finish() if (!defined($options{finish}));
    foreach (keys %{$self->{ws_clients}->{ $options{ws_id} }->{tokens}}) {
        delete $self->{token_watch}->{$_};
    }
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

sub api_root_ws {
    my ($self, %options) = @_;

    if (!defined($options{content}->{method})) {
        $self->{ws_clients}->{ $options{ws_id} }->{tx}->send({json => {
            code => 500,
            message  => 'unknown method',
            userdata => $options{content}->{userdata}
        }});
        return ;
    }
    if (!defined($options{content}->{uri})) {
        $self->{ws_clients}->{ $options{ws_id} }->{tx}->send({json => {
            code => 500,
            message  => 'unknown uri',
            userdata => $options{content}->{userdata}
        }});
        return ;
    }

    $self->{logger}->writeLogInfo("[api] Requesting '" . $options{content}->{uri} . "' [" . $options{content}->{method} . "]");

    if ($options{content}->{method} eq 'GET' && $options{content}->{uri} =~ /^\/api\/log\/?$/) {
        $self->get_log(
            ws_id => $options{ws_id},
            userdata => $options{content}->{userdata},
            target => $options{target},
            token => $options{content}->{token},
            parameters => $options{content}->{parameters}
        );
    } elsif ($options{content}->{uri} =~ /^\/internal\/(\w+)\/?$/
        && defined($self->{api_endpoints}->{ $options{content}->{method} . '_/internal/' . $1 })) {
        $self->call_action(
            ws_id => $options{ws_id},
            userdata => $options{content}->{userdata},
            async => 0,
            action => $self->{api_endpoints}->{ $options{content}->{method} . '_/internal/' . $1 },
            internal => $1,
            target => $options{target},
            data => { 
                content => $options{content}->{data},
                parameters => $options{content}->{parameters},
                variables => $options{content}->{variable}
            }
        );
    } elsif ($options{content}->{uri} =~ /^\/(\w+)\/(\w+)\/(\w+)\/?$/
        && defined($self->{api_endpoints}->{ $options{content}->{method} . '_/' . $1 . '/' . $2 . '/' . $3 })) {
        $self->call_action(
            ws_id => $options{ws_id},
            userdata => $options{content}->{userdata},
            async => 0,
            action => $self->{api_endpoints}->{ $options{content}->{method} . '_/' . $1 . '/' . $2 . '/' . $3 },
            target => $options{target},
            data => { 
                content => $options{content}->{data},
                parameters => $options{content}->{parameters},
                variables => $options{content}->{variable}
            }
        );
    } else {
        $self->{ws_clients}->{ $options{ws_id} }->{tx}->send({json => {
            code => 500,
            message  => 'method not implemented',
            userdata => $options{userdata}
        }});
    }
}

sub api_root {
    my ($self, %options) = @_;

    $self->{logger}->writeLogInfo("[api] Requesting '" . $options{uri} . "' [" . $options{method} . "]");

    my $async = 0;
    $async = 1 if (defined($options{parameters}->{async}) && $options{parameters}->{async} == 1);

    # async mode:
    #   provide the token directly and close the connection. need to call GETLOG on the token
    #   not working with GETLOG 
    
    # listener is used for other case.

    if ($options{method} eq 'GET' && $options{uri} =~ /^\/api\/(nodes\/(\w*)\/)?log\/(.*)$/) {
        $self->get_log(
            mojo => $options{mojo},
            target => $2,
            token => $3,
            parameters => $options{parameters}
        );
    } elsif ($options{uri} =~ /^\/api\/(nodes\/(\w*)\/)?internal\/(\w+)\/?([\w\/]*?)$/
        && defined($self->{api_endpoints}->{ $options{method} . '_/internal/' . $3 })) {
        my @variables = split(/\//, $4);
        $self->call_action(
            mojo => $options{mojo},
            async => $async,
            action => $self->{api_endpoints}->{ $options{method} . '_/internal/' . $3 },
            internal => $3,
            target => $2,
            data => { 
                content => $options{content},
                parameters => $options{parameters},
                variables => \@variables
            }
        );
    } elsif ($options{uri} =~ /^\/api\/(nodes\/(\w*)\/)?(\w+)\/(\w+)\/(\w+)\/?([\w\/]*?)$/
        && defined($self->{api_endpoints}->{ $options{method} . '_/' . $3 . '/' . $4 . '/' . $5 })) {
        my @variables = split(/\//, $6);
        $self->call_action(
            mojo => $options{mojo},
            async => $async,
            action => $self->{api_endpoints}->{ $options{method} . '_/' . $3 . '/' . $4 . '/' . $5 },
            target => $2,
            data => { 
                content => $options{content},
                parameters => $options{parameters},
                variables => \@variables
            }
        );
    } else {
        $options{mojo}->render(json => { error => 'method_unknown', message => 'Method not implemented' }, status => 200);
        return ;
    }
}

1;
