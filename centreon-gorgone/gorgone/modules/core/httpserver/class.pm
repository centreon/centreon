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

package gorgone::modules::core::httpserver::class;

use base qw(gorgone::class::module);

use strict;
use warnings;
use gorgone::standard::library;
use gorgone::standard::misc;
use gorgone::standard::api;
use ZMQ::LibZMQ4;
use ZMQ::Constants qw(:all);
use HTTP::Daemon;
use HTTP::Status;
use MIME::Base64;
use JSON::XS;
use Socket;

my $time = time();

my %handlers = (TERM => {}, HUP => {});
my ($connector);

my %dispatch;

sub new {
    my ($class, %options) = @_;
    $connector  = {};
    $connector->{internal_socket} = undef;
    $connector->{logger} = $options{logger};
    $connector->{config} = $options{config};
    $connector->{config_core} = $options{config_core};
    $connector->{stop} = 0;
    $connector->{api_endpoints} = $options{api_endpoints};

    if ($connector->{config}->{ssl} eq 'true') {
        exit(1) if (gorgone::standard::misc::mymodule_load(
            logger => $connector->{logger},
            module => 'HTTP::Daemon::SSL',
            error_msg => "[httpserver] -class- cannot load module 'HTTP::Daemon::SSL'")
        );
    }

    $connector->{auth_enabled} = (defined($connector->{config}->{auth}->{enabled}) && $connector->{config}->{auth}->{enabled} eq 'true') ? 1 : 0;

    $connector->{allowed_hosts_enabled} = (defined($connector->{config}->{allowed_hosts}->{enabled}) && $connector->{config}->{allowed_hosts}->{enabled} eq 'true') ? 1 : 0;
    if (gorgone::standard::misc::mymodule_load(
            logger => $connector->{logger},
            module => 'NetAddr::IP',
            error_msg => "[httpserver] -class- cannot load module 'NetAddr::IP'. Cannot use allowed_hosts configuration.")
    ) {
        $connector->{allowed_hosts_enabled} = 0;
    }

    bless $connector, $class;
    $connector->set_signal_handlers;
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
    $self->{logger}->writeLogDebug("[httpserver] $$ Receiving order to stop...");
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

sub event {
    while (1) {
        my $message = gorgone::standard::library::zmq_dealer_read_message(socket => $connector->{internal_socket});

        $connector->{logger}->writeLogDebug("[httpserver] Event: $message");

        last unless (gorgone::standard::library::zmq_still_read(socket => $connector->{internal_socket}));
    }
}

sub init_dispatch {
    my ($self, $config_dispatch) = @_;

    $self->{dispatch} = { %{$self->{config}->{dispatch}} }
        if (defined($self->{config}->{dispatch}) && $self->{config}->{dispatch} ne '');
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

    return if ($connector->{allowed_hosts_enabled} == 0);

    $connector->{peer_subnets} = [];
    return if (!defined($connector->{config}->{allowed_hosts}->{subnets}));

    foreach (@{$connector->{config}->{allowed_hosts}->{subnets}}) {
        my $subnet = NetAddr::IP->new($_);
        if (!defined($subnet)) {
            $self->{logger}->writeLogError("[httpserver] Cannot load subnet: $_");
            next;
        }

        push @{$connector->{peer_subnets}}, $subnet;
    }
}

sub run {
    my ($self, %options) = @_;

    $self->load_peer_subnets();

    # Connect internal
    $connector->{internal_socket} = gorgone::standard::library::connect_com(
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgonehttpserver',
        logger => $self->{logger},
        type => $self->{config_core}->{internal_com_type},
        path => $self->{config_core}->{internal_com_path}
    );
    $connector->send_internal_action(
        action => 'HTTPSERVERREADY',
        data => {}
    );

    $self->{poll} = [
        {
            socket  => $connector->{internal_socket},
            events  => ZMQ_POLLIN,
            callback => \&event,
        }
    ];

    my $rev = zmq_poll($self->{poll}, 4000);

    $self->init_dispatch();

    # HTTP daemon
    my $daemon;
    if ($self->{config}->{ssl} eq 'false') {
        $daemon = HTTP::Daemon->new(
            LocalAddr => $self->{config}->{address} . ':' . $self->{config}->{port},
            ReusePort => 1,
            Timeout => 5
        );
    } elsif ($self->{config}->{ssl} eq 'true') {
        $daemon = HTTP::Daemon::SSL->new(
            LocalAddr => $self->{config}->{address} . ':' . $self->{config}->{port},
            SSL_cert_file => $self->{config}->{ssl_cert_file},
            SSL_key_file => $self->{config}->{ssl_key_file},
            SSL_error_trap => \&ssl_error,
            ReusePort => 1,
            Timeout => 5
        );
    }

    if (defined($daemon)) {
        while (1) {
            my ($connection) = $daemon->accept();

            if ($self->{stop} == 1) {
                $self->{logger}->writeLogInfo("[httpserver] $$ has quit");
                $connection->close() if (defined($connection));
                zmq_close($connector->{internal_socket});
                exit(0);
            }

            next if (!defined($connection));

            while (my $request = $connection->get_request) {
                $connector->{logger}->writeLogInfo("[httpserver] " . $connection->peerhost() . " " . $request->method . " '" . $request->uri->path . "' '" . $request->header("User-Agent") . "'");

                if ($connector->{allowed_hosts_enabled} == 1) {
                    if ($connector->check_allowed_host(peer_addr => inet_ntoa($connection->peeraddr())) == 0) {
                        $connector->{logger}->writeLogError("[httpserver] " . $connection->peerhost() . " Unauthorized");
                        $self->send_error(
                            connection => $connection,
                            code => "401",
                            response => '{"error":"http_error_401","message":"unauthorized"}'
                        );
                        next;
                    }
                }

                if ($self->authentication($request->header('Authorization'))) { # Check Basic authentication
                    my ($root) = ($request->uri->path =~ /^(\/\w+)/);

                    if ($root eq "/api") { # API
                        $self->send_response(connection => $connection, response => $self->api_call($request));
                    } elsif (defined($self->{dispatch}->{$root})) { # Other dispatch definition
                        $self->send_response(connection => $connection, response => $self->dispatch_call(root => $root, request => $request));
                    } else { # Forbidden
                        $connector->{logger}->writeLogError("[httpserver] " . $connection->peerhost() . " '" . $request->uri->path . "' Forbidden");
                        $self->send_error(
                            connection => $connection,
                            code => "403",
                            response => '{"error":"http_error_403","message":"forbidden"}'
                        );
                    }
                } else { # Authen error
                    $connector->{logger}->writeLogError("[httpserver] " . $connection->peerhost() . " Unauthorized");
                    $self->send_error(
                        connection => $connection,
                        code => "401",
                        response => '{"error":"http_error_401","message":"unauthorized"}'
                    );
                }
                $connection->force_last_request;
            }
            $connection->close;
            undef($connection);
        }
    }
}

sub ssl_error {
    my ($self, $error) = @_;

    ${*$self}{httpd_client_proto} = 1000;
    ${*$self}{httpd_daemon} = HTTP::Daemon::SSL::DummyDaemon->new();
    $self->send_error(RC_BAD_REQUEST);
    $self->close;
}

sub authentication {
    my ($self, $header) = @_;

    return 1 if ($self->{auth_enabled} == 0);

    return 0 if (!defined($header) || $header eq '');

    ($header =~ /Basic\s(.*)$/);
    my ($user, $password) = split(/:/, MIME::Base64::decode($1), 2);
    return 1 if (defined($self->{config}->{auth}->{user}) && $user eq $self->{config}->{auth}->{user} && 
        defined($self->{config}->{auth}->{password}) && $password eq $self->{config}->{auth}->{password});

    return 0;
}

sub send_response {
    my ($self, %options) = @_;

    if (defined($options{response}) && $options{response} ne '') {
        my $response = HTTP::Response->new(200);
        $response->header('Content-Type' => 'application/json'); 
        $response->content($options{response} . "\n");
        $options{connection}->send_response($response);
    } else {
        my $response = HTTP::Response->new(204);
        $options{connection}->send_response($response);
    }
}

sub send_error {
    my ($self, %options) = @_;

    my $response = HTTP::Response->new($options{code});
    $response->header('Content-Type' => 'application/json'); 
    $response->content($options{response} . "\n");
    $options{connection}->send_response($response);
}

sub api_call {
    my ($self, $request) = @_;

    my $content;
    eval {
        $content = JSON::XS->new->utf8->decode($request->content)
            if ($request->method =~ /POST|PATCH/ && defined($request->content));
    };
    if ($@) {
        return '{"error":"decode_error","message":"POST content must be JSON-formated"}';;
    }

    my %parameters = $request->uri->query_form;
    my $response = gorgone::standard::api::root(
        method => $request->method,
        uri => $request->uri->path,
        parameters => \%parameters,
        content => $content,
        socket => $connector->{internal_socket},
        logger => $self->{logger},
        api_endpoints => $self->{api_endpoints}
    );

    return $response;
}

sub dispatch_call {
    my ($self, %options) = @_;

    my $class = $self->{dispatch}->{$options{root}}->{class};
    my $method = $self->{dispatch}->{$options{root}}->{method};
    my $response;
    eval {
        (my $file = "$class.pm") =~ s|::|/|g;
        require $file;
        $response = $class->$method(request => $options{request});
    };
    if ($@) {
        $response = $@;
    };

    return $response;
}

1;
