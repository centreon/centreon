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

package gorgone::modules::core::pull::class;

use base qw(gorgone::class::module);

use strict;
use warnings;
use gorgone::class::db;
use gorgone::standard::library;
use gorgone::standard::constants qw(:all);
use gorgone::class::clientzmq;
use ZMQ::LibZMQ4;
use ZMQ::Constants qw(:all);
use JSON::XS;

my %handlers = (TERM => {}, HUP => {});
my ($connector);

sub new {
    my ($class, %options) = @_;
    $connector = $class->SUPER::new(%options);
    bless $connector, $class;

    $connector->{ping_timer} = time();

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
    $self->{logger}->writeLogDebug("[pipeline] -class- $$ Receiving order to stop...");
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

sub exit_process {
    my ($self, %options) = @_;

    $self->{logger}->writeLogInfo("[pull] $$ has quit");

    $self->{client}->send_message(
        action => 'UNREGISTERNODES',
        data => { nodes => [ { id => $self->get_core_config(name => 'id') } ] }, 
        json_encode => 1
    );
    $self->{client}->close();

    zmq_close($self->{internal_socket});
    exit(0);
}

sub ping {
    my ($self, %options) = @_;

    return if ((time() - $self->{ping_timer}) < 60);

    $self->{ping_timer} = time();

    $self->{client}->ping(
        poll => $self->{poll},
        action => 'REGISTERNODES',
        data => { nodes => [ { id => $self->get_core_config(name => 'id'), type => 'pull', identity => $self->{client}->get_connect_identity() } ] },
        json_encode => 1
    );
}

sub transmit_back {
    my (%options) = @_;

    return undef if (!defined($options{message}));

    if ($options{message} =~ /^\[ACK\]\s+\[(.*?)\]\s+(.*)/m) {
        my $data;
        eval {
            $data = JSON::XS->new->decode($2);
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

sub read_message_client {
    my (%options) = @_;

    # We skip. Dont need to send it in gorgone-core
    if ($options{data} =~ /^\[ACK\]/) {
        return undef;
    }

    $connector->{logger}->writeLogDebug("[pull] read message from external: $options{data}");
    $connector->send_internal_action(message => $options{data});
}

sub event {
    while (1) {
        my $message = transmit_back(message => $connector->read_message());
        last if (!defined($message));

        # Only send back SETLOGS and PONG
        $connector->{logger}->writeLogDebug("[pull] read message from internal: $message");
        $connector->{client}->send_message(message => $message);
    }
}

sub run {
    my ($self, %options) = @_;

    # Connect internal
    $self->{internal_socket} = gorgone::standard::library::connect_com(
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgone-pull',
        logger => $self->{logger},
        type => $self->get_core_config(name => 'internal_com_type'),
        path => $self->get_core_config(name => 'internal_com_path')
    );
    $self->send_internal_action(
        action => 'PULLREADY',
        data => {}
    );

    $self->{client} = gorgone::class::clientzmq->new(
        identity => 'gorgone-' . $self->get_core_config(name => 'id'), 
        cipher => $self->{config}->{cipher}, 
        vector => $self->{config}->{vector},
        client_pubkey => 
            defined($self->{config}->{client_pubkey}) && $self->{config}->{client_pubkey} ne '' ?
                $self->{config}->{client_pubkey} : $self->get_core_config(name => 'pubkey'),
        client_privkey =>
            defined($self->{config}->{client_privkey}) && $self->{config}->{client_privkey} ne '' ?
                $self->{config}->{client_privkey} : $self->get_core_config(name => 'privkey'),
        target_type => $self->{config}->{target_type},
        target_path => $self->{config}->{target_path},
        config_core => $self->get_core_config(),
        logger => $self->{logger},
        ping => $self->{config}->{ping},
        ping_timeout => $self->{config}->{ping_timeout}
    );
    $self->{client}->init(callback => \&read_message_client);

    $self->{client}->send_message(
        action => 'REGISTERNODES',
        data => { nodes => [ { id => $self->get_core_config(name => 'id'), type => 'pull', identity => $self->{client}->get_connect_identity() } ] },
        json_encode => 1
    );

    $self->{poll} = [
        {
            socket  => $self->{internal_socket},
            events  => ZMQ_POLLIN,
            callback => \&event
        },
        $self->{client}->get_poll()
    ];

    while (1) {
        my $rv = scalar(zmq_poll($self->{poll}, 5000));
        if ($rv == 0 && $self->{stop} == 1) {
            $self->exit_process();
        }

        $self->ping();
    }
}

1;
