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

package modules::core::action::class;

use base qw(centreon::gorgone::module);

use strict;
use warnings;
use centreon::gorgone::common;
use centreon::misc::misc;
use ZMQ::LibZMQ4;
use ZMQ::Constants qw(:all);
use JSON::XS;
use File::Basename;
use File::Copy;
use MIME::Base64;
use Digest::MD5::File qw(file_md5_hex);
use Fcntl;

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
    
    $connector->{command_timeout} = defined($connector->{config}->{command_timeout}) ?
        $connector->{config}->{command_timeout} : 30;
    
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
    $self->{logger}->writeLogInfo("[action] -class- $$ Receiving order to stop...");
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
    
    if (!defined($options{data}->{content}->{command}) || $options{data}->{content}->{command} eq '') {
        $self->send_log(
            socket => $options{socket_log},
            code => $self->ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'need command argument' }
        );
        return -1;
    }
    
    my ($error, $stdout, $return_code) = centreon::misc::misc::backtick(
        command => $options{data}->{content}->{command},
        #arguments => [@$args, $sub_cmd],
        timeout => (defined($options{data}->{content}->{timeout})) ?
            $options{data}->{content}->{timeout} : $self->{command_timeout},
        wait_exit => 1,
        redirect_stderr => 1,
        logger => $self->{logger}
    );
    if ($error <= -1000) {
        $self->send_log(
            socket => $options{socket_log},
            code => $self->ACTION_FINISH_KO,
            token => $options{token},
            data => { message => "command '$options{data}->{content}->{command}' execution issue: $stdout" }
        );
        return -1;
    }
    
    $self->send_log(
        socket => $options{socket_log},
        code => $self->ACTION_FINISH_OK,
        token => $options{token},
        data => {
            message => "command '$options{data}->{content}->{command}' has finished",
            stdout => $stdout,
            exit_code => $return_code
        }
    );

    return 0;
}

sub action_processcopy {
    my ($self, %options) = @_;
    
    if (!defined($options{data}->{content}) || $options{data}->{content} eq '') {
        $self->send_log(
            socket => $options{socket_log},
            code => $self->ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'no content' }
        );
        return -1;
    }

    my $cache_file = $options{data}->{content}->{cache_dir} . '/copy_' . $options{token};
    if ($options{data}->{content}->{status} eq "inprogress" && defined($options{data}->{content}->{chunk}->{data})) {
        sysopen(FH, $cache_file, O_WRONLY|O_APPEND|O_CREAT);
        binmode(FH);
        syswrite(
            FH,
            MIME::Base64::decode_base64($options{data}->{content}->{chunk}->{data}),
            $options{data}->{content}->{chunk}->{size}
        );
        close FH;

        $self->send_log(
            socket => $options{socket_log},
            code => $self->ACTION_FINISH_OK,
            token => $options{token},
            data => {
                message => "process copy inprogress",
            }
        );
        return 0;
    } elsif ($options{data}->{content}->{status} eq "end" && defined($options{data}->{content}->{md5})) {
        if ($options{data}->{content}->{md5} eq file_md5_hex($cache_file)) {
            if ($options{data}->{content}->{type} eq "archive") {
                my ($error, $stdout, $exit_code) = centreon::misc::misc::backtick(
                    command => "tar --no-overwrite-dir -zxf $cache_file -C '" . $options{data}->{content}->{destination} . "' .",
                    timeout => (defined($options{timeout})) ? $options{timeout} : 10,
                    wait_exit => 1,
                    redirect_stderr => 1,
                );
                if ($error <= -1000) {
                    $self->send_log(
                        socket => $options{socket_log},
                        code => $self->ACTION_FINISH_KO,
                        token => $options{token},
                        data => { message => "untar failed: $stdout" }
                    );
                    return -1;
                }
                if ($exit_code != 0) {
                    $self->send_log(
                        socket => $options{socket_log},
                        code => $self->ACTION_FINISH_KO,
                        token => $options{token},
                        data => { message => "untar failed ($exit_code): $stdout" }
                    );
                    return -1;
                }
            } elsif ($options{data}->{content}->{type} eq "regular") {
                copy(
                    $cache_file,
                    $options{data}->{content}->{destination} . '/' . $options{data}->{content}->{filename}
                );
            }
        } else {
            $self->send_log(
                socket => $options{socket_log},
                code => $self->ACTION_FINISH_KO,
                token => $options{token},
                data => { message => 'md5 does not match' }
            );
            return -1;
        }
    }

    # unlink($cache_file);

    $self->send_log(
        socket => $options{socket_log},
        code => $self->ACTION_FINISH_OK,
        token => $options{token},
        data => {
            message => "process copy finished successfully",
        }
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
        type => $self->{config_core}->{internal_com_type},
        path => $self->{config_core}->{internal_com_path}
    );

    if ($options{action} eq 'COMMAND') {
        $self->action_command(%options, socket_log => $socket_log);
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
    
    $self->{logger}->writeLogInfo("[action] -class- create sub-process");
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
            data => { message => "proceed action" }
        );
    }
}

sub event {
    while (1) {
        my $message = centreon::gorgone::common::zmq_dealer_read_message(socket => $connector->{internal_socket});
        
        $connector->{logger}->writeLogDebug("[action] -class- Event: $message");
        
        if ($message !~ /^\[ACK\]/) {
            $message =~ /^\[(.*?)\]\s+\[(.*?)\]\s+\[.*?\]\s+(.*)$/m;
            
            my ($action, $token) = ($1, $2);
            my $data = JSON::XS->new->utf8->decode($3);
            if (defined($data->{parameters}->{no_fork})) {
                if ((my $method = $connector->can('action_' . lc($action)))) {
                    $method->($connector, token => $token, data => $data);
                }
            } else{
                $connector->create_child(message => $message);
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
        name => 'gorgoneaction',
        logger => $self->{logger},
        type => $self->{config_core}->{internal_com_type},
        path => $self->{config_core}->{internal_com_path}
    );
    centreon::gorgone::common::zmq_send_message(
        socket => $connector->{internal_socket},
        action => 'ACTIONREADY', data => { },
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
            $self->{logger}->writeLogInfo("[action] -class- $$ has quit");
            zmq_close($connector->{internal_socket});
            exit(0);
        }
    }
}

1;
