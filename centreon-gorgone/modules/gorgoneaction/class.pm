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

package modules::gorgoneaction::class;

use strict;
use warnings;
use centreon::gorgone::common;
use centreon::misc::misc;
use ZMQ::LibZMQ4;
use ZMQ::Constants qw(:all);

my %handlers = (TERM => {}, HUP => {});
my ($connector, $socket);

sub new {
    my ($class, %options) = @_;
    $connector  = {};
    $connector->{logger} = $options{logger};
    $connector->{config} = $options{config};
    $connector->{config_core} = $options{config_core};
    $connector->{stop} = 0;
    
    $connector->{enginecommand_timeout} = defined($connector->{config}{enginecommand_timeout}) ? $connector->{config}{enginecommand_timeout} : 30;
    $connector->{command_timeout} = defined($connector->{config}{command_timeout}) ? $connector->{config}{command_timeout} : 30;
    
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
    $self->{logger}->writeLogInfo("gorgone-action $$ Receiving order to stop...");
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

sub action_command {
    my ($self, %options) = @_;
    
    if (!defined($options{data}->{command}) || $options{data}->{command} eq '') {
        centreon::gorgone::common::zmq_send_message(
            socket => $options{socket_log},
            action => 'PUTLOG',
            data => { code => 35, etime => time(), token => $options{token}, data => { message => "need command argument" } },
            json_encode => 1
        );
        return -1;
    }
    
    my ($error, $stdout, $return_code) = centreon::misc::misc::backtick(
        command => $options{data}->{command},
        #arguments => [@$args, $sub_cmd],
        timeout => $self->{command_timeout},
        wait_exit => 1,
        redirect_stderr => 1,
        logger => $self->{logger}
    );
    if ($error <= -1000) {
        centreon::gorgone::common::zmq_send_message(
            socket => $options{socket_log},
            action => 'PUTLOG',
            data => { code => 35, etime => time(), token => $options{token}, data => { message => "command '$options{data}->{command}' execution issue: $stdout" } },
            json_encode => 1
        );
        return -1;
    }
    
    centreon::gorgone::common::zmq_send_message(
        socket => $options{socket_log},
        action => 'PUTLOG',
        data => { code => 36, etime => time(), token => $options{token}, data => { message => "command '$options{data}->{command}' had finished", stdout => $stdout, exit_code => $return_code } },
        json_encode => 1
    );
    return 0;
}

sub action_enginecommand {
    my ($self, %options) = @_;
    
    if (!defined($options{data}->{engine_pipe}) || $options{data}->{engine_pipe} eq '') {
        centreon::gorgone::common::zmq_send_message(
            socket => $options{socket_log},
            action => 'PUTLOG',
            data => { code => 35, etime => time(), token => $options{token}, data => { message => "need engine_pipe argument" } },
            json_encode => 1
        );
        return -1;
    }    
    if (! -e $options{data}->{engine_pipe}) {
        centreon::gorgone::common::zmq_send_message(
            socket => $options{socket_log},
            action => 'PUTLOG',
            data => { code => 35, etime => time(), token => $options{token}, data => { message => "command '$options{data}->{command}' - engine_pipe '$options{data}->{engine_pipe}' must exist" } },
            json_encode => 1
        );
        return -1;
    }
    if (! -p $options{data}->{engine_pipe}) {
        centreon::gorgone::common::zmq_send_message(
            socket => $options{socket_log},
            action => 'PUTLOG', data => { code => 35, etime => time(), token => $options{token}, data => { message => "command '$options{data}->{command}' - engine_pipe '$options{data}->{engine_pipe}' must be a pipe file" } },
            json_encode => 1
        );
        return -1;
    }
    if (! -w $options{data}->{engine_pipe}) {
        centreon::gorgone::common::zmq_send_message(
            socket => $options{socket_log},
            action => 'PUTLOG', data => { code => 35, etime => time(), token => $options{token}, data => { message => "command '$options{data}->{command}' - engine_pipe '$options{data}->{engine_pipe}' must be writeable" } },
            json_encode => 1
        );
        return -1;
    }

    $self->{logger}->writeLogDebug("gorgone-action: class: submit engine command '$options{data}->{command}'");
    my $fh;
    eval {
        local $SIG{ALRM} = sub { die "Timeout command\n" };
        alarm $self->{enginecommand_timeout};
        open($fh, ">", $options{data}->{engine_pipe}) or die "cannot open '$options{data}->{engine_pipe}': $!";
        print $fh $options{data}->{command} . "\n";
        close $fh;
        alarm 0;
    };
    if ($@) {
        close $fh if (defined($fh));
        $self->{logger}->writeLogError("gorgone-action: class: submit engine command '$options{data}->{command}' issue: $@");
        centreon::gorgone::common::zmq_send_message(
            socket => $options{socket_log},
            action => 'PUTLOG',
            data => { code => 35, etime => time(), token => $options{token}, data => { message => "submit command issue '$options{data}->{command}': $@" } },
            json_encode => 1
        );
        return undef;
    }
    
    centreon::gorgone::common::zmq_send_message(
        socket => $options{socket_log},
        action => 'PUTLOG',
        data => { code => 36, etime => time(), token => $options{token}, data => { message => "command '$options{data}->{command}' had been submitted" } },
        json_encode => 1
    );
    return 0;
}

sub action_run {
    my ($self, %options) = @_;
    
    my $socket_log = centreon::gorgone::common::connect_com(
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgoneaction-'. $$,
        logger => $self->{logger},
        linger => 5000,
        type => $self->{config_core}{internal_com_type},
        path => $self->{config_core}{internal_com_path}
    );
    if ($options{action} eq 'COMMAND') {
        $self->action_command(%options, socket_log => $socket_log);
    } elsif ($options{action} eq 'ENGINECOMMAND') {
        $self->action_enginecommand(%options, socket_log => $socket_log);
    }
    
    centreon::gorgone::common::zmq_send_message(
        socket => $socket_log,
        action => 'PUTLOG',
        data => { code => 32, etime => time(), token => $options{token}, data => { message => "proceed action end" } },
        json_encode => 1
    );
    zmq_close($socket_log);
}

sub create_child {
    my ($self, %options) = @_;
    
    $self->{logger}->writeLogInfo("Create gorgoneaction sub-process");
    $options{message} =~ /^\[(.*?)\]\s+\[(.*?)\]\s+\[.*?\]\s+(.*)$/m;
    
    my ($action, $token) = ($1, $2);
    my $data = JSON->new->utf8->decode($3);
    
    my $child_pid = fork();
    if (!defined($child_pid)) {
        centreon::gorgone::common::zmq_send_message(
            socket => $socket,
            action => 'PUTLOG',
            data => { code => 30, etime => time(), token => $token, data => { message => "cannot fork: $!" } },
            json_encode => 1
        );
        return undef;
    }
    
    if ($child_pid == 0) {
        $self->action_run(action => $action, token => $token, data => $data);
        exit(0);
    } else {
        centreon::gorgone::common::zmq_send_message(
            socket => $socket,
            action => 'PUTLOG',
            data => { code => 31, etime => time(), token => $token, data => { message => "proceed action" } },
            json_encode => 1
        );
    }
}

sub event {
    while (1) {
        my $message = centreon::gorgone::common::zmq_dealer_read_message(socket => $socket);
        
        $connector->{logger}->writeLogDebug("gorgoneaction: class: $message");
        
        if ($message !~ /^\[ACK\]/) {
            $connector->create_child(message => $message);
        }
        
        last unless (centreon::gorgone::common::zmq_still_read(socket => $socket));
    }
}

sub run {
    my ($self, %options) = @_;

    # Connect internal
    $socket = centreon::gorgone::common::connect_com(
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgoneaction',
        logger => $self->{logger},
        type => $self->{config_core}{internal_com_type},
        path => $self->{config_core}{internal_com_path}
    );
    centreon::gorgone::common::zmq_send_message(
        socket => $socket,
        action => 'ACTIONREADY', data => { },
        json_encode => 1
    );
    $self->{poll} = [
        {
            socket  => $socket,
            events  => ZMQ_POLLIN,
            callback => \&event,
        }
    ];
    while (1) {
        # we try to do all we can
        my $rev = zmq_poll($self->{poll}, 5000);
        if ($rev == 0 && $self->{stop} == 1) {
            $self->{logger}->writeLogInfo("gorgone-action $$ has quit");
            zmq_close($socket);
            exit(0);
        }
    }
}

1;
