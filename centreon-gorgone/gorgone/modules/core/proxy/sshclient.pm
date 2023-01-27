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

package gorgone::modules::core::proxy::sshclient;

use base qw(Libssh::Session);

use strict;
use warnings;
use Libssh::Sftp qw(:all);
use POSIX;
use gorgone::standard::misc;
use File::Basename;
use Time::HiRes;
use gorgone::standard::constants qw(:all);
use MIME::Base64;

sub new {
    my ($class, %options) = @_;
    my $self = $class->SUPER::new(%options);
    bless $self, $class;

    $self->{save_options} = {};
    $self->{logger} = $options{logger};
    $self->{sftp} = undef;
    return $self;
}

sub open_session {
    my ($self, %options) = @_;

    $self->{save_options} = { %options };
    my $timeout = defined($options{ssh_connect_timeout}) && $options{ssh_connect_timeout} =~ /^\d+$/ ? $options{ssh_connect_timeout} : 5;
    if ($self->options(
            host => $options{ssh_host},
            port => $options{ssh_port},
            user => $options{ssh_username},
            sshdir => $options{ssh_directory},
            knownhosts => $options{ssh_known_hosts},
            identity => $options{ssh_identity},
            timeout => $timeout
        ) != Libssh::Session::SSH_OK) {
        $self->{logger}->writeLogError('[sshclient] Options method: ' . $self->error());
        return -1;
    }

    if ($self->connect(SkipKeyProblem => $options{strict_serverkey_check}) != Libssh::Session::SSH_OK) {
        $self->{logger}->writeLogError('[sshclient] Connect method: ' . $self->error());
        return -1;
    }

    if ($self->auth_publickey_auto() != Libssh::Session::SSH_AUTH_SUCCESS) {
        $self->{logger}->writeLogInfo('[sshclient] Authentication publickey auto failure: ' . $self->error(GetErrorSession => 1));
        if (!defined($options{ssh_password}) || $options{ssh_password} eq '') {
            $self->{logger}->writeLogError('[sshclient] Authentication issue: no password');
            return -1;
        }
        if ($self->auth_password(password => $options{ssh_password}) != Libssh::Session::SSH_AUTH_SUCCESS) {
            $self->{logger}->writeLogError('[sshclient] Authentication issue: ' . $self->error(GetErrorSession => 1));
            return -1;
        }
    }

    $self->{logger}->writeLogInfo(
        "[sshclient] Client authenticated successfully to 'ssh://" . $options{ssh_host} . ":" . $options{ssh_port} . "'"
    );

    $self->{sftp} = Libssh::Sftp->new(session => $self);
    if (!defined($self->{sftp})) {
        $self->{logger}->writeLogError('[sshclient] Cannot init sftp: ' . Libssh::Sftp::error());
        $self->disconnect();
        return -1;
    }

    return 0;
}

sub local_command {
    my ($self, %options) = @_;

    my ($error, $stdout, $exit_code) = gorgone::standard::misc::backtick(
        command => $options{command},
        timeout => (defined($options{timeout})) ? $options{timeout} : 120,
        wait_exit => 1,
        redirect_stderr => 1,
        logger => $self->{logger}
    );
    if ($error <= -1000) {
        return (-1, { message => "command '$options{command}' execution issue: $stdout" });
    }
    if ($exit_code != 0) {
        return (-1, { message => "command '$options{command}' execution issue ($exit_code): $stdout" });
    }
    return 0;
}

sub ping {
    my ($self, %options) = @_;

    my $ret = $self->execute_simple(
        cmd => 'hostname',
        timeout => 5,
        timeout_nodata => 5
    );
    if ($ret->{exit} == Libssh::Session::SSH_OK) {
        return 0;
    }

    return -1;
}

sub action_centcore {
    my ($self, %options) = @_;

    if (!defined($options{data}->{content}->{command}) || $options{data}->{content}->{command} eq '') {
        $self->{logger}->writeLogError('[sshclient] Action centcore - Need command');
        return (-1, { message => 'please set command' });
    }
    if (!defined($options{data}->{content}->{target}) || $options{data}->{content}->{target} eq '') {
        $self->{logger}->writeLogError('[sshclient] Action centcore - Need target');
        return (-1, { message => 'please set target' });
    }

    my $centcore_cmd = defined($options{data}->{content}->{centcore_cmd}) ? $options{data}->{content}->{centcore_dir} : '/var/lib/centreon/centcore/';
    my $time = Time::HiRes::time();
    $time =~ s/\.//g;
    $centcore_cmd .= $time . '.cmd';
    
    my $data = $options{data}->{content}->{command} . ':' . $options{data}->{content}->{target};
    $data .= ':' . $options{data}->{content}->{param} if (defined($options{data}->{content}->{param}) && $options{data}->{content}->{param} ne '');
    chomp $data;

    my $file = $self->{sftp}->open(file => $centcore_cmd, accesstype => O_WRONLY|O_CREAT|O_TRUNC, mode => 0660);
    if (!defined($file)) {
        return (-1, { message => "cannot open stat file '$centcore_cmd': " . $self->{sftp}->error() });
    }
    if ($self->{sftp}->write(handle_file => $file, data => $data . "\n") != Libssh::Session::SSH_OK) {
        return (-1, { message => "cannot write stat file '$centcore_cmd': " . $self->{sftp}->error() });
    }

    $self->{logger}->writeLogDebug("[sshclient] Action centcore - '" . $centcore_cmd . "' succeeded");
    return (0, { message => 'send action_centcore succeeded' });
}

sub action_actionengine {
    my ($self, %options) = @_;

    # validate plugins unsupported with ssh
    $self->action_command(
        data => {
            logging => $options{data}->{logging},
            content => [
                $options{data}->{content}
            ]
        },
        target_direct => $options{target_direct},
        target => $options{target},
        token => $options{token}
    );
}

sub action_command {
    my ($self, %options) = @_;

    if (!defined($options{data}->{content}) || ref($options{data}->{content}) ne 'ARRAY') {
        return (-1, { message => "expected array, found '" . ref($options{data}->{content}) . "'" });
    }

    my $index = 0;
    foreach my $command (@{$options{data}->{content}}) {
        if (!defined($command->{command}) || $command->{command} eq '') {
            return (-1, { message => "need command argument at array index '" . $index . "'" });
        }
        $index++;
    }

    my $errors = 0;
    my $results;
    
    push @{$results}, {
        code => GORGONE_ACTION_BEGIN,
        data => {
            message => "commands processing has started",
            request_content => $options{data}->{content}
        }
    };

    foreach my $command (@{$options{data}->{content}}) {
        my ($code, $data) = (0, {});

        push @{$results}, {
            code => GORGONE_ACTION_BEGIN,
            data => {
                message => "command has started",
                command => $command->{command},
                metadata => $command->{metadata}
            }
        };

        if (defined($command->{metadata}->{centcore_proxy}) && $options{target_direct} == 0) {
            ($code, $data->{data}) = $self->action_centcore(
                data => {
                    content => {
                        command => $command->{metadata}->{centcore_cmd},
                        target => $options{target},
                    }
                }
            );
            $data->{code} = ($code < 0) ? GORGONE_ACTION_FINISH_KO : GORGONE_ACTION_FINISH_OK;
        } else {
            my $timeout = defined($command->{timeout}) && $command->{timeout} =~ /(\d+)/ ? $1 : 60;
            my $timeout_nodata = defined($command->{timeout_nodata}) && $command->{timeout_nodata} =~ /(\d+)/ ? $1 : 30;
            
            my $start = time();
            my $ret = $self->execute_simple(
                cmd => $command->{command},
                timeout => $timeout,
                timeout_nodata => $timeout_nodata
            );
            my $end = time();

            $data = {
                data => {
                    command => $command->{command},
                    metadata => $command->{metadata},
                    result => {
                        exit_code => $ret->{exit_code},
                        stdout => $ret->{stdout},
                        stderr => $ret->{stderr},
                    },
                    metrics => {
                        start => $start,
                        end => $end,
                        duration => $end - $start
                    }
                }
            };

            if ($ret->{exit} == Libssh::Session::SSH_OK) {
                $data->{data}->{message} = "command has finished successfully";
                $data->{code} = GORGONE_MODULE_ACTION_COMMAND_RESULT;
            } elsif ($ret->{exit} == Libssh::Session::SSH_AGAIN) { # AGAIN means timeout
                $code = -1;
                $data->{data}->{message} = "command has timed out";
                $data->{code} = GORGONE_ACTION_FINISH_KO;
            } else {
                $code = -1;
                $data->{data}->{message} = $self->error(GetErrorSession => 1);
                $data->{code} = GORGONE_ACTION_FINISH_KO;
            }
        }

        push @{$results}, $data;

        if ($code < 0) {
            if (defined($command->{continue_on_error}) && $command->{continue_on_error} == 0) {
                push @{$results}, {
                    code => 1,
                    data => {
                        message => "commands processing has been interrupted because of error"
                    }
                };
                return (-1, $results);
            }

            $errors = 1;
        }
    }

    if ($errors) {
        push @{$results}, {
            code => GORGONE_ACTION_FINISH_KO,
            data => {
                message => "commands processing has finished with errors"
            }
        };
        return (-1, $results);
    }

    push @{$results}, {
        code => GORGONE_ACTION_FINISH_OK,
        data => {
            message => "commands processing has finished successfully"
        }
    };

    return (0, $results);
}

sub action_enginecommand {
    my ($self, %options) = @_;

    my $results;

    if ($options{target_direct} == 0) {
        foreach my $command (@{$options{data}->{content}->{commands}}) {
            chomp $command;
            my $msg = "[sshclient] Handling command 'EXTERNALCMD'";
            $msg .= ", Target: '" . $options{target} . "'" if (defined($options{target}));
            $msg .= ", Parameters: '" . $command . "'" if (defined($command));
            $self->{logger}->writeLogInfo($msg);
            my ($code, $data) = $self->action_centcore(
                data => {
                    content => {
                        command => 'EXTERNALCMD',
                        target => $options{target},
                        param => $command,
                    }
                }
            );
        }
    } else {
        if (!defined($options{data}->{content}->{command_file}) || $options{data}->{content}->{command_file} eq '') {
            $self->{logger}->writeLogError("[sshclient] Need command_file argument");
            return (-1, { message => "need command_file argument" });
        }

        my $command_file = $options{data}->{content}->{command_file};

        my $ret = $self->{sftp}->stat_file(file => $command_file);
        if (!defined($ret)) {
            $self->{logger}->writeLogError("[sshclient] Command file '$command_file' must exist");
            return (-1, { message => "command file '$command_file' must exist", error => $self->{sftp}->get_msg_error() });
        }

        if ($ret->{type} != SSH_FILEXFER_TYPE_SPECIAL) {
            $self->{logger}->writeLogError("[sshclient] Command file '$command_file' must be a pipe file");
            return (-1, { message => "command file '$command_file' must be a pipe file" });
        }

        my $file = $self->{sftp}->open(file => $command_file, accesstype => O_WRONLY|O_APPEND, mode => 0660);
        if (!defined($file)) {
            $self->{logger}->writeLogError("[sshclient] Cannot open command file '$command_file'");
            return (-1, { message => "cannot open command file '$command_file'", error => $self->{sftp}->error() });
        }
    
        push @{$results}, {
            code => GORGONE_ACTION_BEGIN,
            data => {
                message => "commands processing has started",
                request_content => $options{data}->{content}
            }
        };

        foreach my $command (@{$options{data}->{content}->{commands}}) {
            $self->{logger}->writeLogInfo("[sshclient] Processing external command '" . $command . "'");
            if ($self->{sftp}->write(handle_file => $file, data => $command . "\n") != Libssh::Session::SSH_OK) {
                $self->{logger}->writeLogError("[sshclient] Command file '$command_file' must be writeable");
                push @{$results}, {
                    code => GORGONE_ACTION_FINISH_KO,
                    data => {
                        message => "command file '$command_file' must be writeable",
                        error => $self->{sftp}->error()
                    }
                };

                return (-1, $results);
            }

            push @{$results}, {
                code => GORGONE_ACTION_FINISH_OK,
                data => {
                    message => "command has been submitted",
                    command => $command
                }
            };
        }
    }

    push @{$results}, {
        code => GORGONE_ACTION_FINISH_OK,
        data => {
            message => "commands processing has finished"
        }
    };

    return (0, $results);
}

sub action_processcopy {
    my ($self, %options) = @_;

    if (!defined($options{data}->{content}->{status}) || $options{data}->{content}->{status} !~ /^(?:inprogress|end)$/) {
        $self->{logger}->writeLogError('[sshclient] Action process copy - need status');
        return (-1, { message => 'please set status' });
    }
    if (!defined($options{data}->{content}->{type}) || $options{data}->{content}->{type} !~ /^(?:archive|regular)$/) {
        $self->{logger}->writeLogError('[sshclient] Action process copy - need type');
        return (-1, { message => 'please set type' });
    }
    if (!defined($options{data}->{content}->{cache_dir}) || $options{data}->{content}->{cache_dir} eq '') {
        $self->{logger}->writeLogError('[sshclient] Action process copy - need cache_dir');
        return (-1, { message => 'please set cache_dir' });
    }
    if ($options{data}->{content}->{status} eq 'end' &&
        (!defined($options{data}->{content}->{destination}) || $options{data}->{content}->{destination} eq '')) {
        $self->{logger}->writeLogError('[sshclient] Action process copy - need destination');
        return (-1, { message => 'please set destination' });
    }

    my $copy_local_file = $options{data}->{content}->{cache_dir} . '/copy_local_' . $options{token};
    if ($options{data}->{content}->{status} eq 'inprogress') {
        my $fh;
        if (!sysopen($fh, $copy_local_file, O_RDWR|O_APPEND|O_CREAT, 0660)) {
            return (-1, { message => "file '$copy_local_file' open failed: $!" });
        }
        binmode($fh);
        syswrite(
            $fh,
            MIME::Base64::decode_base64($options{data}->{content}->{chunk}->{data}),
            $options{data}->{content}->{chunk}->{size}
        );
        close $fh;

        return (0, [{
            code => GORGONE_MODULE_ACTION_PROCESSCOPY_INPROGRESS,
            data => {
                message => 'process copy inprogress'
            }
        }]);        
    }
    if ($options{data}->{content}->{status} eq 'end') {
        my $copy_file = $options{data}->{content}->{cache_dir} . '/copy_' . $options{token};
        my $code = $self->{sftp}->copy_file(src => $copy_local_file, dst => $copy_file);
        unlink($copy_local_file);
        if ($code == -1) {
            return (-1, { message => "cannot sftp copy file : " . $self->{sftp}->error() });
        }

        if ($options{data}->{content}->{type} eq 'archive') {
            return $self->action_command(
                data => {
                    content => [ { command => "tar zxf $copy_file -C '" . $options{data}->{content}->{destination}  .  "' ." } ]
                }
            );
        }
        if ($options{data}->{content}->{type} eq 'regular') {
            return $self->action_command(
                data => {
                    content => [ { command => "cp -f $copy_file '$options{data}->{content}->{destination}'" } ]
                }
            );
        }
    }

    return (-1, { message => 'process copy unknown error' });
}

sub action_remotecopy {
    my ($self, %options) = @_;
    
    if (!defined($options{data}->{content}->{source}) || $options{data}->{content}->{source} eq '') {
        $self->{logger}->writeLogError('[sshclient] Action remote copy - need source');
        return (-1, { message => 'please set source' });
    }
    if (!defined($options{data}->{content}->{destination}) || $options{data}->{content}->{destination} eq '') {
        $self->{logger}->writeLogError('[sshclient] Action remote copy - need destination');
        return (-1, { message => 'please set destination' });
    }

    my ($code, $message, $data);

    my $srcname;
    my $localsrc = $options{data}->{content}->{source};
    my $src = $options{data}->{content}->{source};
    my ($dst, $dst_sftp) = ($options{data}->{content}->{destination}, $options{data}->{content}->{destination});
    if ($options{target_direct} == 0) {
        $dst = $src;
        $dst_sftp = $src;
    }    

    if (-f $options{data}->{content}->{source}) {
        $localsrc = $src;
        $srcname = File::Basename::basename($src);
        $dst_sftp .= $srcname if ($dst =~ /\/$/);
    } elsif (-d $options{data}->{content}->{source}) {
        $srcname = (defined($options{data}->{content}->{type}) ? $options{data}->{content}->{type} : 'tmp') . '-' . $options{target} . '.tar.gz';
        $localsrc = $options{data}->{content}->{cache_dir} . '/' . $srcname; 
        $dst_sftp = $options{data}->{content}->{cache_dir} . '/' . $srcname;

        ($code, $message) = $self->local_command(command => "tar czf $localsrc -C '" . $src . "' .");
        return ($code, $message) if ($code == -1);
    } else {
        return (-1, { message => 'unknown source' });
    }

    if (($code = $self->{sftp}->copy_file(src => $localsrc, dst => $dst_sftp)) == -1) {
        return (-1, { message => "cannot sftp copy file : " . $self->{sftp}->error() });
    }

    if (-d $options{data}->{content}->{source}) {
        ($code, $data) = $self->action_command(
            data => {
                content => [ { command => "tar zxf $dst_sftp -C '" . $dst  .  "' ." } ]
            }
        );
        return ($code, $data) if ($code == -1);
    }

    if (defined($options{data}->{content}->{metadata}->{centcore_proxy}) && $options{target_direct} == 0) {
        $self->action_centcore(
            data => {
                content => {
                    command => $options{data}->{content}->{metadata}->{centcore_cmd},
                    target => $options{target},
                }
            }
        );
    }

    return (0, { message => 'send remotecopy succeeded' });
}

sub action {
    my ($self, %options) = @_;

    my $func = $self->can('action_' . lc($options{action}));
    if (defined($func)) {
        return $func->(
            $self,
            data => $options{data},
            target_direct => $options{target_direct},
            target => $options{target},
            token => $options{token}
        );
    }

    $self->{logger}->writeLogError("[sshclient] Unsupported action '" . $options{action} . "'");
    return (-1, { message => 'unsupported action' });
}

sub close {
    my ($self, %options) = @_;
    
    $self->disconnect();
}

1;
