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

package modules::centreon::engine::class;

use base qw(centreon::gorgone::module);

use strict;
use warnings;
use centreon::gorgone::common;
use centreon::misc::misc;
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
    
    if (!defined($self->{config}->{command_file}) || $self->{config}->{command_file} eq '') {
        $self->send_log(
            socket => $options{socket_log},
            code => $self->ACTION_FINISH_KO,
            token => $options{token},
            data => { message => "need command_file argument" }
        );
        return -1;
    }    
    if (! -e $self->{config}->{command_file}) {
        $self->send_log(
            socket => $options{socket_log},
            code => $self->ACTION_FINISH_KO,
            token => $options{token},
            data => { message => "command '$options{data}->{content}->{command}' - engine_pipe '$self->{config}->{command_file}' must exist" }
        );
        return -1;
    }
    if (! -p $self->{config}->{command_file}) {
        $self->send_log(
            socket => $options{socket_log},
            code => $self->ACTION_FINISH_KO,
            token => $options{token},
            data => { message => "command '$options{data}->{content}->{command}' - engine_pipe '$self->{config}->{command_file}' must be a pipe file" }
        );
        return -1;
    }
    if (! -w $self->{config}->{command_file}) {
        $self->send_log(
            socket => $options{socket_log},
            code => $self->ACTION_FINISH_KO,
            token => $options{token},
            data => { message => "command '$options{data}->{content}->{command}' - engine_pipe '$self->{config}->{command_file}' must be writeable" }
        );
        return -1;
    }

    my $fh;
    eval {
        local $SIG{ALRM} = sub { die "Timeout command\n" };
        alarm $self->{timeout};
        open($fh, ">", $self->{config}->{command_file}) or die "cannot open '$self->{config}->{command_file}': $!";
        print $fh $options{data}->{content}->{command} . "\n";
        close $fh;
        alarm 0;
    };
    if ($@) {
        close $fh if (defined($fh));
        $self->{logger}->writeLogError("[action] -class- Submit engine command '$options{data}->{content}->{command}' issue: $@");
        $self->send_log(
            socket => $options{socket_log},
            code => $self->ACTION_FINISH_KO,
            token => $options{token},
            data => { message => "submit command issue '$options{data}->{content}->{command}': $@" }
        );
        return undef;
    }
    
    $self->send_log(
        socket => $options{socket_log},
        code => $self->ACTION_FINISH_OK,
        token => $options{token},
        data => { message => "command '$options{data}->{content}->{command}' had been submitted': $@" }
    );

    return 0;
}

sub action_run {
    my ($self, %options) = @_;
    
    my $socket_log = centreon::gorgone::common::connect_com(
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
            data => { message => "action unknown" }
        );
        return -1;
    }

    zmq_close($socket_log);
}

sub create_child {
    my ($self, %options) = @_;
    
    $self->{logger}->writeLogInfo("[engine] -class- create sub-process");
    $options{message} =~ /^\[(.*?)\]\s+\[(.*?)\]\s+\[.*?\]\s+(.*)$/m;
    
    my ($action, $token) = ($1, $2);
    my $data = JSON->new->utf8->decode($3);
    
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
            data => { message => "proceed action" }
        );
    }
}

sub event {
    while (1) {
        my $message = centreon::gorgone::common::zmq_dealer_read_message(socket => $connector->{internal_socket});
        
        $connector->{logger}->writeLogDebug("[engine] -class- Event: $message");
        
        if ($message !~ /^\[ACK\]/) {
            $connector->create_child(message => $message);
        }
        
        last unless (centreon::gorgone::common::zmq_still_read(socket => $connector->{internal_socket}));
    }
}

sub run {
    my ($self, %options) = @_;

    # Connect internal
    $connector->{internal_socket} = centreon::gorgone::common::connect_com(
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgoneengine',
        logger => $self->{logger},
        type => $self->{config_core}->{internal_com_type},
        path => $self->{config_core}->{internal_com_path}
    );
    centreon::gorgone::common::zmq_send_message(
        socket => $connector->{internal_socket},
        action => 'ENGINEREADY', data => { },
        json_encode => 1
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
