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

package gorgone::modules::centreon::engine::class;

use base qw(gorgone::class::module);

use strict;
use warnings;
use JSON::XS;
use gorgone::standard::library;
use gorgone::standard::misc;
use ZMQ::LibZMQ4;
use ZMQ::Constants qw(:all);

my %handlers = (TERM => {}, HUP => {});
my ($connector);

sub new {
    my ($class, %options) = @_;
    $connector  = {};
    $connector->{internal_socket} = undef;
    $connector->{logger} = $options{logger};
    $connector->{config} = $options{config};
    $connector->{config_core} = $options{config_core};
    $connector->{stop} = 0;
    
    $connector->{timeout} = defined($connector->{config}->{timeout}) ? $connector->{config}->{timeout} : 5;
    
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
    $self->{logger}->writeLogInfo("[engine] -class- $$ Receiving order to stop...");
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

sub action_enginecommand {
    my ($self, %options) = @_;
    
    if (!defined($options{data}->{content}) || ref($options{data}->{content}) ne 'ARRAY') {
        $self->send_log(
            socket => $options{socket_log},
            code => $self->ACTION_FINISH_KO,
            token => $options{token},
            data => {
                message => "expected array, found '" . ref($options{data}->{content}) . "'",
            }
        );
        return -1;
    }

    my $index = 0;
    foreach my $command (@{$options{data}->{content}}) {
        if (!defined($command->{command}) || $command->{command} eq '') {
            $self->{logger}->writeLogError("[engine] -class- action_enginecommand: need command argument at array index '" . $index . "'");
            $self->send_log(
                socket => $options{socket_log},
                code => $self->ACTION_FINISH_KO,
                token => $options{token},
                data => {
                    message => "need command argument at array index '" . $index . "'",
                }
            );
            return -1;
        }
        $index++;
    }

    foreach my $command (@{$options{data}->{content}}) {
        my $command_file = '';
        if (defined($command->{command_file}) && $command->{command_file} ne '') {
            $command_file = $command->{command_file};
        } elsif (defined($self->{config}->{command_file}) && $self->{config}->{command_file} ne '') {
            $command_file = $self->{config}->{command_file};
        }

        if (!defined($command_file) || $command_file eq '') {
            $self->{logger}->writeLogError("[engine] -class- need command_file (config or call) argument");
            $self->send_log(
                socket => $options{socket_log},
                code => $self->ACTION_FINISH_KO,
                token => $options{token},
                data => {
                    message => "need command_file (config or call) argument",
                    command => $command->{command}
                }
            );
            return -1;
        }    
        if (! -e $command_file) {
            $self->{logger}->writeLogError("[engine] -class- command '$command->{command}' - command_file '$command_file' must exist");
            $self->send_log(
                socket => $options{socket_log},
                code => $self->ACTION_FINISH_KO,
                token => $options{token},
                data => {
                    message => "command_file '$command_file' must exist",
                    command => $command->{command}
                }
            );
            return -1;
        }
        if (! -p $command_file) {
            $self->{logger}->writeLogError("[engine] -class- command '$command->{command}' - command_file '$command_file' must be a pipe file");
            $self->send_log(
                socket => $options{socket_log},
                code => $self->ACTION_FINISH_KO,
                token => $options{token},
                data => {
                    message => "command_file '$command_file' must be a pipe file",
                    command => $command->{command}
                }
            );
            return -1;
        }
        if (! -w $command_file) {
            $self->{logger}->writeLogError("[engine] -class- command '$command->{command}' - command_file '$command_file' must be writeable");
            $self->send_log(
                socket => $options{socket_log},
                code => $self->ACTION_FINISH_KO,
                token => $options{token},
                data => {
                    message => "command_file '$command_file' must be writeable",
                    command => $command->{command}
                }
            );
            return -1;
        }

        my $fh;
        eval {
            local $SIG{ALRM} = sub { die 'Timeout command' };
            alarm $self->{timeout};
            open($fh, ">", $command_file) or die "cannot open '$command_file': $!";
            print $fh $command->{command} . "\n";
            close $fh;
            alarm 0;
        };
        if ($@) {
            close $fh if (defined($fh));
            $self->{logger}->writeLogError("[engine] -class- submit engine command '$command->{command}' issue: $@");
            $self->send_log(
                socket => $options{socket_log},
                code => $self->ACTION_FINISH_KO,
                token => $options{token},
                data => {
                    message => "submit engine command issue: $@",
                    command => $command->{command}
                }
            );
            return undef;
        }
        
        $self->send_log(
            socket => $options{socket_log},
            code => $self->ACTION_FINISH_OK,
            token => $options{token},
            data => {
                message => "command had been submitted",
                command => $command->{command}
            }
        );
    }

    return 0;
}

sub action_run {
    my ($self, %options) = @_;
    
    my $socket_log = gorgone::standard::library::connect_com(
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgoneengine-'. $$,
        logger => $self->{logger},
        linger => 5000,
        type => $self->{config_core}->{internal_com_type},
        path => $self->{config_core}->{internal_com_path}
    );

    if ($options{action} eq 'ENGINECOMMAND') {
        $self->action_enginecommand(%options, socket_log => $socket_log);
    } else {
        $self->send_log(
            socket => $socket_log,
            code => $self->ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'action unknown' }
        );
        return -1;
    }

    zmq_close($socket_log);
}

sub create_child {
    my ($self, %options) = @_;
    
    $self->{logger}->writeLogInfo('[engine] -class- create sub-process');
    $options{message} =~ /^\[(.*?)\]\s+\[(.*?)\]\s+\[.*?\]\s+(.*)$/m;
    
    my ($action, $token) = ($1, $2);
    my $data = JSON::XS->new->utf8->decode($3);
    
    my $child_pid = fork();
    if (!defined($child_pid)) {
        $self->send_log(
            code => $self->ACTION_FINISH_KO,
            token => $token,
            data => { message => "cannot fork: $!" }
        );
        return undef;
    }
    
    if ($child_pid == 0) {
        $self->action_run(action => $action, token => $token, data => $data);
        exit(0);
    } else {
        $self->send_log(
            code => $self->ACTION_BEGIN,
            token => $token,
            data => { message => 'proceed action' }
        );
    }
}

sub event {
    while (1) {
        my $message = gorgone::standard::library::zmq_dealer_read_message(socket => $connector->{internal_socket});
        
        $connector->{logger}->writeLogDebug("[engine] -class- Event: $message");
        
        if ($message !~ /^\[ACK\]/) {
            $connector->create_child(message => $message);
        }
        
        last unless (gorgone::standard::library::zmq_still_read(socket => $connector->{internal_socket}));
    }
}

sub run {
    my ($self, %options) = @_;

    # Connect internal
    $connector->{internal_socket} = gorgone::standard::library::connect_com(
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgoneengine',
        logger => $self->{logger},
        type => $self->{config_core}->{internal_com_type},
        path => $self->{config_core}->{internal_com_path}
    );
    $connector->send_internal_action(
        action => 'ENGINEREADY',
        data => {}
    );
    $self->{poll} = [
        {
            socket  => $connector->{internal_socket},
            events  => ZMQ_POLLIN,
            callback => \&event,
        }
    ];
    while (1) {
        # we try to do all we can
        my $rev = zmq_poll($self->{poll}, 5000);
        if ($rev == 0 && $self->{stop} == 1) {
            $self->{logger}->writeLogInfo("[engine] -class- $$ has quit");
            zmq_close($connector->{internal_socket});
            exit(0);
        }
    }
}

1;
