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

use strict;
use warnings;
use gorgone::standard::library;
use ZMQ::LibZMQ4;
use ZMQ::Constants qw(:all);
use HTTP::Daemon;
use HTTP::Daemon::SSL;
use HTTP::Status;
use MIME::Base64;
use JSON::XS;

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
    $connector->{modules_events} = $options{modules_events};
    
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
    $self->{logger}->writeLogInfo("[httpserver] -class- $$ Receiving order to stop...");
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
        
        $connector->{logger}->writeLogDebug("[httpserver] -class- Event: $message");
        
        last unless (gorgone::standard::library::zmq_still_read(socket => $connector->{internal_socket}));
    }
}

sub init_dispatch {
    my ($self, $config_dispatch) = @_;

    $self->{dispatch} = { %{$self->{config}->{dispatch}} }
        if (defined($self->{config}->{dispatch}) && $self->{config}->{dispatch} ne '');
}

sub run {
    my ($self, %options) = @_;

    # Connect internal
    $connector->{internal_socket} = gorgone::standard::library::connect_com(
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgonehttpserver',
        logger => $self->{logger},
        type => $self->{config_core}->{internal_com_type},
        path => $self->{config_core}->{internal_com_path}
    );
    gorgone::standard::library::zmq_send_message(
        socket => $connector->{internal_socket},
        action => 'HTTPSERVERREADY',
        data => { },
        json_encode => 1
    );
    $self->{poll} = [
        {
            socket  => $connector->{internal_socket},
            events  => ZMQ_POLLIN,
            callback => \&event,
        }
    ];

    my $rev = zmq_poll($self->{poll}, 4000);

    $self->init_dispatch;

    # HTTP daemon
    my $daemon;
    if ($self->{config}->{ssl} eq 'false') {
        $daemon = HTTP::Daemon->new(
            LocalAddr => $self->{config}->{address} . ':' . $self->{config}->{port},
            ReusePort => 1
        );
    } elsif ($self->{config}->{ssl} eq 'true') {
        $daemon = HTTP::Daemon::SSL->new(
            LocalAddr => $self->{config}->{address} . ':' . $self->{config}->{port},
            SSL_cert_file => $self->{config}->{ssl_cert_file},
            SSL_key_file => $self->{config}->{ssl_key_file},
            SSL_error_trap => \&ssl_error,
            ReusePort => 1
        );
    }
    
    if (defined($daemon)) {
        while (my ($connection, $peer_addr) = $daemon->accept) {
            while (my $request = $connection->get_request) {
                $connector->{logger}->writeLogInfo("[httpserver] -class- " . $request->method . " '" . $request->uri->path . "'");

                if ($self->authentication($request->header('Authorization'))) { # Check Basic authentication
                    my ($root) = ($request->uri->path =~ /^(\/\w+)/);

                    if ($request->method eq 'GET' && $root eq "/status") { # Server status
                        $self->send_response(connection => $connection, response => $self->server_status);
                    } elsif ($root eq "/api") { # API
                        $self->send_response(connection => $connection, response => $self->api_call($request));
                    } elsif (defined($self->{dispatch}->{$root})) { # Other dispatch definition
                        $self->send_response(connection => $connection, response => $self->dispatch_call(root => $root, request => $request));
                    } else { # Forbidden
                        $connection->send_error(RC_FORBIDDEN)
                    }
                } else { # Authen error
                    $connection->send_error(RC_UNAUTHORIZED);
                }
            }
            $connection->close;
            undef($connection);
        }
    }
}

sub ssl_error {
    my ($self, $error) = @_;
    
    ${*$self}{'httpd_client_proto'} = 1000;
    ${*$self}{'httpd_daemon'} = new HTTP::Daemon::SSL::DummyDaemon;
    $self->send_error(RC_BAD_REQUEST);
    $self->close;
}

sub authentication {
    my ($self, $header) = @_;
    return 0 if (!defined($header) || $header eq '');
        
    ($header =~ /Basic\s(.*)$/);
    my ($user, $password) = split(/:/, MIME::Base64::decode($1), 2);
    return 1 if ($user eq $self->{config}->{auth}->{user} && $password eq $self->{config}->{auth}->{password});

    return 0;
}

sub send_response {
    my ($self, %options) = @_;

    my $response = HTTP::Response->new(200);
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

    require 'centreon/gorgone/api.pm';
    my %parameters = $request->uri->query_form;
    my $response = gorgone::standard::api::root(
        method => $request->method,
        uri => $request->uri->path,
        parameters => \%parameters,
        content => $content,
        socket => $connector->{internal_socket},
        logger => $self->{logger},
        modules_events => $self->{modules_events}
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

sub server_status {
    my ($self, %options) = @_;
    
    my %data = (
        starttime => $time,
        dispatch => $self->{dispatch},
        modules_events => $self->{modules_events}
    );

    my $encoded_data;
    eval {
        $encoded_data = JSON::XS->new->utf8->encode(\%data);
    };
    if ($@) {
        $encoded_data = '{"error":"encode_error","message":"Cannot encode response into JSON format"}';
    } 
    
    return $encoded_data;
}

1;
