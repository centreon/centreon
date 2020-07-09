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

package gorgone::modules::core::pull::hooks;

use warnings;
use strict;
use gorgone::class::clientzmq;
use JSON::XS;

use constant NAMESPACE => 'core';
use constant NAME => 'pull';
use constant EVENTS => [];

my $config_core;
my $config;
my $stop = 0;
my $client;
my $socket_to_internal;
my $logger;

sub register {
    my (%options) = @_;
    
    $config = $options{config};
    $config_core = $options{config_core};
    return (1, NAMESPACE, NAME, EVENTS);
}

sub init {
    my (%options) = @_;

    $logger = $options{logger};
    # Connect internal
    $socket_to_internal = gorgone::standard::library::connect_com(
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgonepull',
        logger => $options{logger},
        type => $config_core->{internal_com_type},
        path => $config_core->{internal_com_path},
        linger => $config->{linger}
    );
    $client = gorgone::class::clientzmq->new(
        identity => $config_core->{id}, 
        cipher => $config->{cipher}, 
        vector => $config->{vector},
        client_pubkey => 
            defined($config->{client_pubkey}) && $config->{client_pubkey} ne '' ?
                $config->{client_pubkey} : $config_core->{pubkey},
        client_privkey =>
            defined($config->{client_privkey}) && $config->{client_privkey} ne '' ?
                $config->{client_privkey} : $config_core->{privkey},
        target_type => $config->{target_type},
        target_path => $config->{target_path},
        config_core => $config_core,
        logger => $options{logger},
        ping => $config->{ping},
        ping_timeout => $config->{ping_timeout}
    );
    $client->init(callback => \&read_message);
    
    $client->send_message(
        action => 'REGISTERNODES',
        data => { nodes => [ { id => $config_core->{id}, type => 'pull', identity => $client->get_connect_identity() } ] },
        json_encode => 1
    );
    gorgone::standard::library::add_zmq_pollin(
        socket => $socket_to_internal,
        callback => \&from_router,
        poll => $options{poll}
    );
}

sub routing {
    my (%options) = @_;

}

sub gently {
    my (%options) = @_;

    $stop = 1;
    $client->send_message(
        action => 'UNREGISTERNODES',
        data => { nodes => [ { id => $config_core->{id} } ] }, 
        json_encode => 1
    );
    $client->close();
    return 0;
}

sub kill {
    my (%options) = @_;

    return 0;
}

sub kill_internal {
    my (%options) = @_;

    return 0;
}

sub check {
    my (%options) = @_;

    if ($stop == 0) {
        # If distant server restart, it's a not problem. It save the key. 
        # But i don't have the registernode anymore. The ping is the 'registernode' for pull mode.
        $client->ping(
            poll => $options{poll},
            action => 'REGISTERNODES',
            data => { nodes => [ { id => $config_core->{id}, type => 'pull' } ] },
            json_encode => 1
        );
    }

    return (0, 1);
}

sub broadcast {}

####### specific

sub transmit_back {
    my (%options) = @_;

    if ($options{message} =~ /^\[ACK\]\s+\[(.*?)\]\s+(.*)/m) {
        my $data;
        eval {
            $data = JSON::XS->new->utf8->decode($2);
        };
        if ($@) {
            return $options{message};
        }
        
        if (defined($data->{data}->{action}) && $data->{data}->{action} eq 'getlog') {
            return '[SETLOGS] [' . $1 . '] [] ' . $2;
        }
        return undef;
    } elsif ($options{message} =~ /^\[(PONG|SYNCLOGS)\]/) {
        return $options{message};
    } 
    return undef;
}

sub from_router {
    while (1) {        
        my $message = transmit_back(message => gorgone::standard::library::zmq_dealer_read_message(socket => $socket_to_internal));
        # Only send back SETLOGS and PONG
        if (defined($message)) {
            $logger->writeLogDebug("[pull] Read message from internal: $message");
            $client->send_message(message => $message);
        }
        last unless (gorgone::standard::library::zmq_still_read(socket => $socket_to_internal));
    }
}

sub read_message {
    my (%options) = @_;

    # We skip. Dont need to send it in gorgone-core
    if ($options{data} =~ /^\[ACK\]/) {
        return undef;
    }

    $logger->writeLogDebug("[pull] Read message from external: $options{data}");
    gorgone::standard::library::zmq_send_message(
        socket => $socket_to_internal,
        message => $options{data}
    );
}


1;
