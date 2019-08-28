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
use ZMQ::LibZMQ4;
use ZMQ::Constants qw(:all);
use JSON::XS;

my %handlers = (TERM => {}, HUP => {});
my ($connector);

sub new {
    my ($class, %options) = @_;

    $connector  = {};
    $connector->{internal_socket} = undef;
    $connector->{logger} = $options{logger};
    $connector->{core_id} = $options{core_id};
    $connector->{pool_id} = $options{pool_id};
    $connector->{config} = $options{config};
    $connector->{config_core} = $options{config_core};
    $connector->{stop} = 0;
    $connector->{clients} = {};
    
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

sub get_client_information {
    my ($self, %options) = @_;
    
    # TODO DATABASE or file maybe. hardcoded right now
    my $result = {
        type => 1,
        target_type => 'tcp',
        target_path => 'localhost:5556',
        server_pubkey => 'keys/poller/pubkey.crt', 
        client_pubkey => 'keys/central/pubkey.crt',
        client_privkey => 'keys/central/privkey.pem',
        cipher => 'Cipher::AES',
        keysize => '32',
        vector => '0123456789012345',
        class => undef,
        delete => 0
    };
    return $result;
}

sub read_message {
    my (%options) = @_;
    
    return undef if (!defined($options{identity}) || $options{identity} !~ /^proxy-(.*?)-(.*?)$/);
    
    my ($client_identity) = ($2);
    if ($options{data} =~ /^\[PONG\]/) {
        if ($options{data} !~ /^\[(.+?)\]\s+\[(.*?)\]\s+\[(.*?)\]/m) {
            return undef;
        }
        my ($action, $token) = ($1, $2);
        
        centreon::gorgone::common::zmq_send_message(
            socket => $connector->{internal_socket},
            action => 'PONG',
            token => $token,
            target => '',
            data => { code => 0, data => { message => 'ping ok', action => 'ping', id => $client_identity } },
            json_encode => 1
        );
    }
    elsif ($options{data} =~ /^\[ACK\]\s+\[(.*?)\]\s+(.*)/m) {
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
        return undef;
    }
}

sub connect {
    my ($self, %options) = @_;

    if ($options{entry}->{type} == 1) {
        $options{entry}->{class} = centreon::gorgone::clientzmq->new(
            identity => 'proxy-' . $self->{core_id} . '-' . $options{id}, 
            cipher => $options{entry}->{cipher}, 
            vector => $options{entry}->{vector},
            server_pubkey => $options{entry}->{server_pubkey},
            client_pubkey => $options{entry}->{client_pubkey},
            client_privkey => $options{entry}->{client_privkey},            
            target_type => $options{entry}->{target_type},
            target_path => $options{entry}->{target_path},
            logger => $self->{logger}
        );
        $options{entry}->{class}->init(callback => \&read_message);
    }
}

sub proxy {
    my (%options) = @_;
    
    if ($options{message} !~ /^\[(.+?)\]\s+\[(.*?)\]\s+\[(.*?)\]\s+(.*)$/m) {
        return undef;
    }
    my ($action, $token, $target, $data) = ($1, $2, $3, $4);
    
    my $entry;
    if (!defined($connector->{clients}->{$target})) {
        $entry = $connector->get_client_information(id => $target);
        return if (!defined($entry));
        
        $connector->connect(id => $target, entry => $entry);
        $connector->{clients}->{$target} = $entry;
    } else {
        $entry = $connector->{clients}->{$target};
    }

    # TODO we need to manage type SSH with libssh 
    # type 1 = ZMQ.
    # type 2 = SSH
    if ($entry->{type} == 1) {
        my ($status, $msg) = $entry->{class}->send_message(
            action => $action,
            token => $token,
            target => '', # TODO: don't set to null if we need to chain it!!!
            data => $data
        );
        if ($status != 0) {
            # error we put log and we close (TODO the log)
            $connector->{logger}->writeLogError("[proxy] -class- Send message problem for '$target': $msg");
            $connector->{clients}->{$target}->{delete} = 1;
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
    $connector->{internal_socket} = centreon::gorgone::common::connect_com(
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgoneproxy-' . $self->{pool_id},
        logger => $self->{logger},
        type => $self->{config_core}{internal_com_type},
        path => $self->{config_core}{internal_com_path}
    );
    centreon::gorgone::common::zmq_send_message(
        socket => $connector->{internal_socket},
        action => 'PROXYREADY',
        data => { pool_id => $self->{pool_id} },
        json_encode => 1
    );
    my $poll = {
            socket  => $connector->{internal_socket},
            events  => ZMQ_POLLIN,
            callback => \&event_internal,
    };
    while (1) {
        my $polls = [$poll];
        foreach (keys %{$self->{clients}}) {
            if ($self->{clients}->{$_}->{delete} == 1) {
                $self->{clients}->{$_}->{class}->close();
                delete $self->{clients}->{$_};
                next;
            }
            if ($self->{clients}->{$_}->{type} == 1) {
                push @{$polls}, $self->{clients}->{$_}->{class}->get_poll();
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
