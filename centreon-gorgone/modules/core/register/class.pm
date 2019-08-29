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

package modules::core::register::class;

use base qw(centreon::gorgone::module);

use strict;
use warnings;
use centreon::gorgone::common;
use ZMQ::LibZMQ4;
use ZMQ::Constants qw(:all);
use JSON::XS;

my %handlers = (TERM => {}, HUP => {});
my ($connector);

sub new {
    my ($class, %options) = @_;

    $connector  = {};
    $connector->{internal_socket} = undef;
    $connector->{module_id} = $options{module_id};
    $connector->{logger} = $options{logger};
    $connector->{config} = $options{config};
    $connector->{config_core} = $options{config_core};
    $connector->{stop} = 0;
    $connector->{register_nodes} = {};

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
    $self->{logger}->writeLogInfo("[register] -class- $$ Receiving order to stop...");
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

sub action_registerresync {
    my ($self, %options) = @_;

    $options{token} = $self->generate_token() if (!defined($options{token}));

    $self->send_log(code => centreon::gorgone::module::ACTION_BEGIN, token => $options{token}, data => { message => 'action registerresync proceed' });

    my $content = do {
        local $/ = undef;
        if (!open my $fh, "<", $self->{config}->{config_file}) {
            $self->send_log(code => centreon::gorgone::module::ACTION_FINISH_KO, token => $options{token}, data => { message => "Could not open file $self->{config}->{config_file}: $!" });
            $self->{logger}->writeLogError("[register] -class- Could not open file $self->{config}->{config_file}: $!");
            return 1;
        }
        <$fh>;
    };

    my $data;
    eval {
        $data = JSON::XS->new->utf8->decode($content);
    };
    if ($@) {
        $self->send_log(code => centreon::gorgone::module::ACTION_FINISH_KO, token => $options{token}, data => { message => "Cannot decode json file: $@" });
        $self->{logger}->writeLogError("[register] -class- Cannot decode json file: $@");
        return 1;
    }

    my $register_temp = {};
    my $register_nodes = [];
    if (defined($data->{nodes})) {
        foreach (@{$data->{nodes}}) {
            $self->{register_nodes}->{$_->{id}} = 1;
            $register_temp->{$_->{id}} = 1;
            push @{$register_nodes}, { %$_ };
        }
    }

    my $unregister_nodes = [];
    foreach (keys %{$self->{register_nodes}}) {
        if (!defined($register_temp->{$_})) {
            push @{$unregister_nodes}, { id => $_ };
            delete $self->{register_nodes}->{$_};
        }
    }

    $self->send_internal_action(action => 'REGISTERNODES', data => { nodes => $register_nodes } );
    $self->send_internal_action(action => 'UNREGISTERNODES', data => { nodes => $unregister_nodes } );

    $self->{logger}->writeLogDebug("[register] -class- finish resync");
    $self->send_log(code => $self->ACTION_FINISH_OK, token => $options{token}, data => { message => 'action registerresync finished' });
    return 0;
}

sub event {
    while (1) {
        my $message = centreon::gorgone::common::zmq_dealer_read_message(socket => $connector->{internal_socket});
        
        $connector->{logger}->writeLogDebug("[register] -class- Event: $message");
        if ($message =~ /^\[(.*?)\]/) {
            if ((my $method = $connector->can('action_' . lc($1)))) {
                $message =~ /^\[(.*?)\]\s+\[(.*?)\]\s+\[.*?\]\s+(.*)$/m;
                my ($action, $token) = ($1, $2);
                my $data = JSON::XS->new->utf8->decode($3);
                $method->($connector, token => $token, data => $data);
            }
        }

        last unless (centreon::gorgone::common::zmq_still_read(socket => $connector->{internal_socket}));
    }
}

sub run {
    my ($self, %options) = @_;

    # Connect internal
    $connector->{internal_socket} = centreon::gorgone::common::connect_com(
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgoneregister',
        logger => $self->{logger},
        type => $self->{config_core}->{internal_com_type},
        path => $self->{config_core}->{internal_com_path}
    );
    centreon::gorgone::common::zmq_send_message(
        socket => $connector->{internal_socket},
        action => 'REGISTERREADY', data => { },
        json_encode => 1
    );
    $self->{poll} = [
        {
            socket  => $connector->{internal_socket},
            events  => ZMQ_POLLIN,
            callback => \&event,
        }
    ];

    $self->action_registerresync();
    while (1) {
        # we try to do all we can
        my $rev = zmq_poll($self->{poll}, 5000);
        if (defined($rev) && $rev == 0 && $self->{stop} == 1) {
            $self->{logger}->writeLogInfo("[register] -class- $$ has quit");
            zmq_close($connector->{internal_socket});
            exit(0);
        }
    }
}

1;
