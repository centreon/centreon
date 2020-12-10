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

package gorgone::modules::core::action::class;

use base qw(gorgone::class::module);

use strict;
use warnings;
use gorgone::standard::library;
use gorgone::standard::constants qw(:all);
use gorgone::standard::misc;
use ZMQ::LibZMQ4;
use ZMQ::Constants qw(:all);
use JSON::XS;
use File::Basename;
use File::Copy;
use File::Path qw(make_path);
use MIME::Base64;
use Digest::MD5::File qw(file_md5_hex);
use Fcntl;

$Digest::MD5::File::NOFATALS = 1;
my %handlers = (TERM => {}, HUP => {});
my ($connector);

sub new {
    my ($class, %options) = @_;
    $connector = $class->SUPER::new(%options);
    bless $connector, $class;

    $connector->{process_copy_files_error} = {};

    $connector->{command_timeout} = defined($connector->{config}->{command_timeout}) ?
        $connector->{config}->{command_timeout} : 30;
    
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
    $self->{logger}->writeLogInfo("[action] $$ Receiving order to stop...");
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
    
    if (!defined($options{data}->{content}) || ref($options{data}->{content}) ne 'ARRAY') {
        $self->send_log(
            socket => $options{socket_log},
            code => GORGONE_ACTION_FINISH_KO,
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
            $self->send_log(
                socket => $options{socket_log},
                code => GORGONE_ACTION_FINISH_KO,
                token => $options{token},
                data => {
                    message => "need command argument at array index '" . $index . "'",
                }
            );
            return -1;
        }
        $index++;
    }
    
    $self->send_log(
        socket => $options{socket_log},
        code => GORGONE_ACTION_BEGIN,
        token => $options{token},
        data => {
            message => "commands processing has started",
            request_content => $options{data}->{content}
        }
    );

    my $errors = 0;

    foreach my $command (@{$options{data}->{content}}) {
        $self->send_log(
            socket => $options{socket_log},
            code => GORGONE_ACTION_BEGIN,
            token => $options{token},
            data => {
                message => "command has started",
                command => $command->{command},
                metadata => $command->{metadata}
            }
        );
        
        my $start = time();
        my ($error, $stdout, $return_code) = gorgone::standard::misc::backtick(
            command => $command->{command},
            timeout => (defined($command->{timeout})) ? $command->{timeout} : $self->{command_timeout},
            wait_exit => 1,
            redirect_stderr => 1,
            logger => $self->{logger}
        );
        my $end = time();
        if ($error <= -1000) {
            $self->send_log(
                socket => $options{socket_log},
                code => GORGONE_ACTION_FINISH_KO,
                token => $options{token},
                data => {
                    message => "command execution issue",
                    command => $command->{command},
                    metadata => $command->{metadata},
                    result => {
                        exit_code => $return_code,
                        stdout => $stdout
                    },
                    metrics => {
                        start => $start,
                        end => $end,
                        duration => $end - $start
                    }
                }
            );

            if (defined($command->{continue_on_error}) && $command->{continue_on_error} == 0) {
                $self->send_log(
                    socket => $options{socket_log},
                    code => GORGONE_ACTION_FINISH_KO,
                    token => $options{token},
                    data => {
                        message => "commands processing has been interrupted because of error"
                    }
                );
                return -1;
            }

            $errors = 1;
        } else {
            $self->send_log(
                socket => $options{socket_log},
                code => GORGONE_MODULE_ACTION_COMMAND_RESULT,
                token => $options{token},
                instant => $command->{instant},
                data => {
                    message => "command has finished successfully",
                    command => $command->{command},
                    metadata => $command->{metadata},
                    result => {
                        exit_code => $return_code,
                        stdout => $stdout
                    },
                    metrics => {
                        start => $start,
                        end => $end,
                        duration => $end - $start
                    }
                }
            );
        }
    }
    
    if ($errors) {
        $self->send_log(
            socket => $options{socket_log},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => {
                message => "commands processing has finished with errors"
            }
        );
        return -1;
    }

    $self->send_log(
        socket => $options{socket_log},
        code => GORGONE_ACTION_FINISH_OK,
        token => $options{token},
        data => {
            message => "commands processing has finished successfully"
        }
    );

    return 0;
}

sub action_processcopy {
    my ($self, %options) = @_;
    
    if (!defined($options{data}->{content}) || $options{data}->{content} eq '') {
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'no content' }
        );
        return -1;
    }

    my $cache_file = $options{data}->{content}->{cache_dir} . '/copy_' . $options{token};
    if ($options{data}->{content}->{status} eq 'inprogress' && defined($options{data}->{content}->{chunk}->{data})) {        
        my $fh;
        if (!sysopen($fh, $cache_file, O_RDWR|O_APPEND|O_CREAT, 0660)) {
            # no need to insert too many logs
            return -1 if (defined($self->{process_copy_files_error}->{$cache_file}));
            $self->{process_copy_files_error}->{$cache_file} = 1;
            $self->send_log(
                code => GORGONE_ACTION_FINISH_KO,
                token => $options{token},
                data => { message => "file '$cache_file' open failed: $!" }
            );

            $self->{logger}->writeLogError("[action] file '$cache_file' open failed: $!");
            return -1;
        }
        delete $self->{process_copy_files_error}->{$cache_file} if (defined($self->{process_copy_files_error}->{$cache_file}));
        binmode($fh);
        syswrite(
            $fh,
            MIME::Base64::decode_base64($options{data}->{content}->{chunk}->{data}),
            $options{data}->{content}->{chunk}->{size}
        );
        close $fh;

        $self->send_log(
            code => GORGONE_MODULE_ACTION_PROCESSCOPY_INPROGRESS,
            token => $options{token},
            data => {
                message => 'process copy inprogress',
            }
        );
        $self->{logger}->writeLogInfo("[action] Copy processing - Received chunk for '" . $options{data}->{content}->{destination} . "'");
        return 0;
    } elsif ($options{data}->{content}->{status} eq 'end' && defined($options{data}->{content}->{md5})) {
        delete $self->{process_copy_files_error}->{$cache_file} if (defined($self->{process_copy_files_error}->{$cache_file}));
        my $local_md5_hex = file_md5_hex($cache_file);
        if (defined($local_md5_hex) && $options{data}->{content}->{md5} eq $local_md5_hex) {
            if ($options{data}->{content}->{type} eq "archive") {
                if (! -d $options{data}->{content}->{destination}) {
                    make_path($options{data}->{content}->{destination});
                }
                my ($error, $stdout, $exit_code) = gorgone::standard::misc::backtick(
                    command => "tar --no-overwrite-dir -zxf $cache_file -C '" . $options{data}->{content}->{destination} . "' .",
                    timeout => (defined($options{timeout})) ? $options{timeout} : 10,
                    wait_exit => 1,
                    redirect_stderr => 1,
                );
                if ($error <= -1000) {
                    $self->send_log(
                        code => GORGONE_ACTION_FINISH_KO,
                        token => $options{token},
                        data => { message => "untar failed: $stdout" }
                    );
                    $self->{logger}->writeLogError('[action] Copy processing - Untar failed: ' . $stdout);
                    return -1;
                }
                if ($exit_code != 0) {
                    $self->send_log(
                        code => GORGONE_ACTION_FINISH_KO,
                        token => $options{token},
                        data => { message => "untar failed ($exit_code): $stdout" }
                    );
                    $self->{logger}->writeLogError('[action] Copy processing - Untar failed: ' . $stdout);
                    return -1;
                }
            } elsif ($options{data}->{content}->{type} eq 'regular') {
                copy($cache_file, $options{data}->{content}->{destination});
                my $uid = getpwnam($options{data}->{content}->{owner});
                my $gid = getgrnam($options{data}->{content}->{group});
                chown($uid, $gid, $options{data}->{content}->{destination});
            }
        } else {
            $self->send_log(
                code => GORGONE_ACTION_FINISH_KO,
                token => $options{token},
                data => { message => 'md5 does not match' }
            );
            $self->{logger}->writeLogError('[action] Copy processing - MD5 does not match');
            return -1;
        }
    }

    unlink($cache_file);

    $self->send_log(
        code => GORGONE_ACTION_FINISH_OK,
        token => $options{token},
        data => {
            message => "process copy finished successfully",
        }
    );
    $self->{logger}->writeLogInfo("[action] Copy processing - Copy to '" . $options{data}->{content}->{destination} . "' finished successfully");
    return 0;
}

sub action_run {
    my ($self, %options) = @_;
    
    my $socket_log = gorgone::standard::library::connect_com(
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgoneaction-'. $$,
        logger => $self->{logger},
        zmq_linger => 60000,
        type => $self->{config_core}->{internal_com_type},
        path => $self->{config_core}->{internal_com_path}
    );

    if ($options{action} eq 'COMMAND') {
        $self->action_command(%options, socket_log => $socket_log);
    } else {
        $self->send_log(
            socket => $socket_log,
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => "action unknown" }
        );
        return -1;
    }

    zmq_close($socket_log);
}

sub create_child {
    my ($self, %options) = @_;

    if ($options{action} =~ /^BCAST.*/) {
        if ((my $method = $self->can('action_' . lc($options{action})))) {
            $method->($self, token => $options{token}, data => $options{data});
        }
        return undef;
    }

    $self->{logger}->writeLogDebug("[action] Create sub-process");
    my $child_pid = fork();
    if (!defined($child_pid)) {
        $self->{logger}->writeLogError("[action] Cannot fork process: $!");
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => "cannot fork: $!" }
        );
        return undef;
    }
    
    if ($child_pid == 0) {
        $self->action_run(action => $options{action}, token => $options{token}, data => $options{data});
        exit(0);
    }
}

sub event {
    while (1) {
        my $message = gorgone::standard::library::zmq_dealer_read_message(socket => $connector->{internal_socket});
        
        $connector->{logger}->writeLogDebug("[action] Event: $message");
        
        if ($message !~ /^\[ACK\]/) {
            $message =~ /^\[(.*?)\]\s+\[(.*?)\]\s+\[.*?\]\s+(.*)$/m;
            
            my ($action, $token) = ($1, $2);
            my $data = JSON::XS->new->utf8->decode($3);
            if (defined($data->{parameters}->{no_fork})) {
                if ((my $method = $connector->can('action_' . lc($action)))) {
                    $method->($connector, token => $token, data => $data);
                }
            } else{
                $connector->create_child(action => $action, token => $token, data => $data);
            }
        }

        last unless (gorgone::standard::library::zmq_still_read(socket => $connector->{internal_socket}));
    }
}

sub run {
    my ($self, %options) = @_;

    # Connect internal
    $connector->{internal_socket} = gorgone::standard::library::connect_com(
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgoneaction',
        logger => $self->{logger},
        type => $self->{config_core}->{internal_com_type},
        path => $self->{config_core}->{internal_com_path}
    );
    $connector->send_internal_action(
        action => 'ACTIONREADY',
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
            $self->{logger}->writeLogInfo("[action] $$ has quit");
            zmq_close($connector->{internal_socket});
            exit(0);
        }
    }
}

1;
