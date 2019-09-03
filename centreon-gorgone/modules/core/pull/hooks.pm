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

package modules::core::pull::hooks;

use warnings;
use strict;
use centreon::gorgone::clientzmq;
use JSON::XS;

my $NAME = 'pull';
my $EVENTS = [];

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
    return ($NAME, $EVENTS);
}

sub init {
    my (%options) = @_;

    $logger = $options{logger};
    # Connect internal
    $socket_to_internal = centreon::gorgone::common::connect_com(
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgonepull',
        logger => $options{logger},
        type => $config_core->{internal_com_type},
        path => $config_core->{internal_com_path},
        linger => $config->{linger}
    );
    $client = centreon::gorgone::clientzmq->new(
        identity => $config_core->{id}, 
        cipher => $config->{cipher}, 
        vector => $config->{vector},
        server_pubkey => $config->{server_pubkey},
        client_pubkey => $config->{client_pubkey},
        client_privkey => $config->{server_privkey},        
        target_type => $config->{target_type},
        target_path => $config->{target_path},
        logger => $options{logger},
        ping => $config->{ping},
        ping_timeout => $config->{ping_timeout}
    );
    $client->init(callback => \&read_message);
    
    $client->send_message(
        action => 'REGISTERNODES',
        data => { nodes => [ { id => $config_core->{id}, type => 'pull' } ] },
        json_encode => 1
    );
    centreon::gorgone::common::add_zmq_pollin(
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
            data => { nodes => [ { id => $config_core->{id}, type => 'pull' } ] }
            json_encode => 1
        );
    }
    return 0;
}

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
    } elsif ($options{message} =~ /^\[PONG\]/) {
        return $options{message};
    }
    return undef;
}

sub from_router {
    while (1) {        
        my $message = transmit_back(message => centreon::gorgone::common::zmq_dealer_read_message(socket => $socket_to_internal));
        # Only send back SETLOGS and PONG
        if (defined($message)) {
            $logger->writeLogDebug("[pull] -hooks- Read message from internal: $message");
            $client->send_message(message => $message);
        }
        last unless (centreon::gorgone::common::zmq_still_read(socket => $socket_to_internal));
    }
}

sub read_message {
    my (%options) = @_;

    # We skip. Dont need to send it in gorgone-core
    if ($options{data} =~ /^\[ACK\]/) {
        return undef;
    }

    $logger->writeLogDebug("[pull] -hooks- Read message from external: $options{data}");
    centreon::gorgone::common::zmq_send_message(
        socket => $socket_to_internal,
        message => $options{data}
    );
}


1;
