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
use gorgone::standard::constants qw(:all);
use gorgone::standard::misc;
use ZMQ::FFI qw(ZMQ_POLLIN);
use EV;

my %handlers = (TERM => {}, HUP => {});
my ($connector);

sub new {
    my ($class, %options) = @_;
    $connector = $class->SUPER::new(%options);
    bless $connector, $class;
    
    $connector->{timeout} = defined($connector->{config}->{timeout}) ? $connector->{config}->{timeout} : 5;
    
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
    $self->{logger}->writeLogInfo("[engine] $$ Receiving order to stop...");
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

    my $command_file = '';
    if (defined($options{data}->{content}->{command_file}) && $options{data}->{content}->{command_file} ne '') {
        $command_file = $options{data}->{content}->{command_file};
    } elsif (defined($self->{config}->{command_file}) && $self->{config}->{command_file} ne '') {
        $command_file = $self->{config}->{command_file};
    }

    $self->send_log(
        socket => $options{socket_log},
        code => GORGONE_ACTION_BEGIN,
        token => $options{token},
        logging => $options{data}->{logging},
        data => {
            message => "commands processing has started",
            request_content => $options{data}->{content}
        }
    );

    if (!defined($command_file) || $command_file eq '') {
        $self->{logger}->writeLogError("[engine] Need command_file (config or call) argument");
        $self->send_log(
            socket => $options{socket_log},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            logging => $options{data}->{logging},
            data => {
                message => "need command_file (config or call) argument"
            }
        );
        return -1;
    }    
    if (! -e $command_file) {
        $self->{logger}->writeLogError("[engine] Command file '$command_file' must exist");
        $self->send_log(
            socket => $options{socket_log},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            logging => $options{data}->{logging},
            data => {
                message => "command file '$command_file' must exist"
            }
        );
        return -1;
    }
    if (! -p $command_file) {
        $self->{logger}->writeLogError("[engine] Command file '$command_file' must be a pipe file");
        $self->send_log(
            socket => $options{socket_log},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            logging => $options{data}->{logging},
            data => {
                message => "command file '$command_file' must be a pipe file"
            }
        );
        return -1;
    }
    if (! -w $command_file) {
        $self->{logger}->writeLogError("[engine] Command file '$command_file' must be writeable");
        $self->send_log(
            socket => $options{socket_log},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            logging => $options{data}->{logging},
            data => {
                message => "command file '$command_file' must be writeable"
            }
        );
        return -1;
    }

    my $fh;
    eval {
        local $SIG{ALRM} = sub { die 'Timeout command' };
        alarm $self->{timeout};
        open($fh, ">", $command_file) or die "cannot open '$command_file': $!";
        
        foreach my $command (@{$options{data}->{content}->{commands}}) {
            $self->{logger}->writeLogInfo("[engine] Processing external command '" . $command . "'");
            print $fh $command . "\n";
            $self->send_log(
                socket => $options{socket_log},
                code => GORGONE_ACTION_FINISH_OK,
                token => $options{token},
                logging => $options{data}->{logging},
                data => {
                    message => "command has been submitted",
                    command => $command
                }
            );
        }

        close $fh;
        alarm 0;
    };
    if ($@) {
        close $fh if (defined($fh));
        $self->{logger}->writeLogError("[engine] Submit engine command issue: $@");
        $self->send_log(
            socket => $options{socket_log},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            logging => $options{data}->{logging},
            data => {
                message => "submit engine command issue: $@"
            }
        );
        return -1
    }
    
    $self->send_log(
        socket => $options{socket_log},
        code => GORGONE_ACTION_FINISH_OK,
        token => $options{token},
        logging => $options{data}->{logging},
        data => {
            message => "commands processing has finished"
        }
    );

    return 0;
}

sub action_run {
    my ($self, %options) = @_;
    
    my $socket_log = gorgone::standard::library::connect_com(
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgone-engine-'. $$,
        logger => $self->{logger},
        zmq_linger => 60000,
        type => $self->get_core_config(name => 'internal_com_type'),
        path => $self->get_core_config(name => 'internal_com_path')
    );

    if ($options{action} eq 'ENGINECOMMAND') {
        $self->action_enginecommand(%options, socket_log => $socket_log);
    } else {
        $self->send_log(
            socket => $socket_log,
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'action unknown' }
        );
        return -1;
    }

    $socket_log->close();
}

sub create_child {
    my ($self, %options) = @_;
    
    $options{message} =~ /^\[(.*?)\]\s+\[(.*?)\]\s+\[.*?\]\s+(.*)$/m;
    
    my ($action, $token) = ($1, $2);
    my ($rv, $data) = $self->json_decode(argument => $3, token => $token);
    return undef if ($rv);
    
    if ($action =~ /^BCAST.*/) {
        if ((my $method = $self->can('action_' . lc($action)))) {
            $method->($self, token => $token, data => $data);
        }
        return undef;
    }

    $self->{logger}->writeLogDebug('[engine] Create sub-process');
    my $child_pid = fork();
    if (!defined($child_pid)) {
        $self->{logger}->writeLogError("[engine] Cannot fork process: $!");
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $token,
            data => { message => "cannot fork: $!" }
        );
        return undef;
    }
    
    if ($child_pid == 0) {
        $self->set_fork();
        $self->action_run(action => $action, token => $token, data => $data);
        exit(0);
    }
}

sub event {
    my ($self, %options) = @_;

    while (my $events = gorgone::standard::library::zmq_events(socket => $self->{internal_socket})) {
        if ($events & ZMQ_POLLIN) {
            my ($message) = $self->read_message();
            next if (!defined($message));

            $self->{logger}->writeLogDebug("[engine] Event: $message");

            if ($message !~ /^\[ACK\]/) {
                $self->create_child(message => $message);
            }        
        } else {
            last;
        }
    }
}

sub periodic_exec {
    if ($connector->{stop} == 1) {
        $connector->{logger}->writeLogInfo("[engine] $$ has quit");
        exit(0);
    }
}

sub run {
    my ($self, %options) = @_;

    $self->{internal_socket} = gorgone::standard::library::connect_com(
        context => $self->{zmq_context},
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgone-engine',
        logger => $self->{logger},
        type => $self->get_core_config(name => 'internal_com_type'),
        path => $self->get_core_config(name => 'internal_com_path')
    );
    $self->send_internal_action({
        action => 'ENGINEREADY',
        data => {}
    });

    my $watcher_timer = $self->{loop}->timer(5, 5, \&periodic_exec);
    my $watcher_io = $self->{loop}->io($self->{internal_socket}->get_fd(), EV::READ, sub { $connector->event() } );
    $self->{loop}->run();
}

1;
