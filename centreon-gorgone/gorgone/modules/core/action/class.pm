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
use JSON::XS;
use File::Basename;
use File::Copy;
use File::Path qw(make_path);
use POSIX ":sys_wait_h";
use MIME::Base64;
use Digest::MD5::File qw(file_md5_hex);
use Archive::Tar;
use Fcntl;
use Try::Tiny;
use EV;

$Archive::Tar::SAME_PERMISSIONS = 1;
$Archive::Tar::WARN = 0;
$Digest::MD5::File::NOFATALS = 1;
my %handlers = (TERM => {}, HUP => {}, CHLD => {});
my ($connector);

sub new {
    my ($class, %options) = @_;
    $connector = $class->SUPER::new(%options);
    bless $connector, $class;

    $connector->{process_copy_files_error} = {};

    $connector->{command_timeout} = defined($connector->{config}->{command_timeout}) ?
        $connector->{config}->{command_timeout} : 30;
    $connector->{whitelist_cmds} = defined($connector->{config}->{whitelist_cmds}) && $connector->{config}->{whitelist_cmds} =~ /true|1/i ?
        1 : 0;
    $connector->{allowed_cmds} = [];
    $connector->{allowed_cmds} = $connector->{config}->{allowed_cmds}
        if (defined($connector->{config}->{allowed_cmds}) && ref($connector->{config}->{allowed_cmds}) eq 'ARRAY');

    if (defined($connector->{config}->{tar_insecure_extra_mode}) && $connector->{config}->{tar_insecure_extra_mode} =~ /^(?:1|true)$/) {
        $Archive::Tar::INSECURE_EXTRACT_MODE = 1;
    }

    $connector->{paranoid_plugins} = defined($connector->{config}->{paranoid_plugins}) && $connector->{config}->{paranoid_plugins} =~ /true|1/i ?
        1 : 0;

    $connector->{return_childs} = {};
    $connector->{engine_childs} = {};
    $connector->{max_concurrent_engine} = defined($connector->{config}->{max_concurrent_engine}) ?
        $connector->{config}->{max_concurrent_engine} : 3;

    $connector->set_signal_handlers();
    return $connector;
}

sub set_signal_handlers {
    my $self = shift;

    $SIG{TERM} = \&class_handle_TERM;
    $handlers{TERM}->{$self} = sub { $self->handle_TERM() };
    $SIG{HUP} = \&class_handle_HUP;
    $handlers{HUP}->{$self} = sub { $self->handle_HUP() };
    $SIG{CHLD} = \&class_handle_CHLD;
    $handlers{CHLD}->{$self} = sub { $self->handle_CHLD() };
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

sub handle_CHLD {
    my $self = shift;
    my $child_pid;

    while (($child_pid = waitpid(-1, &WNOHANG)) > 0) {
        $self->{logger}->writeLogDebug("[action] Received SIGCLD signal (pid: $child_pid)");
        $self->{return_child}->{$child_pid} = 1;
    }

    $SIG{CHLD} = \&class_handle_CHLD;
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

sub class_handle_CHLD {
    foreach (keys %{$handlers{CHLD}}) {
        &{$handlers{CHLD}->{$_}}();
    }
}

sub check_childs {
    my ($self, %options) = @_;

    foreach (keys %{$self->{return_child}}) {
        delete $self->{engine_childs}->{$_} if (defined($self->{engine_childs}->{$_}));
    }

    $self->{return_child} = {};
}

sub get_package_manager {
    my ($self, %options) = @_;

    my $os = 'unknown';
    my ($rv, $message, $content) = gorgone::standard::misc::slurp(file => '/etc/os-release');
    if ($rv && $content =~ /^ID="(.*?)"/mi) {
        $os = $1;
    } else {
        my ($error, $stdout, $return_code) = gorgone::standard::misc::backtick(
            command => 'lsb_release -a',
            timeout => 5,
            wait_exit => 1,
            redirect_stderr => 1,
            logger => $options{logger}
        );
        if ($error == 0 && $stdout =~ /^Description:\s+(.*)$/mi) {
            $os = $1;
        }
    }

    $self->{package_manager} = 'unknown';
    if ($os =~ /Debian|Ubuntu/i) {
        $self->{package_manager} = 'deb';
    } elsif ($os =~ /CentOS|Redhat|rhel|almalinux|rocky/i) {
        $self->{package_manager} = 'rpm';
    } elsif ($os eq 'ol' || $os =~ /Oracle Linux/i) {
        $self->{package_manager} = 'rpm';
    }
}

sub check_plugins_rpm {
    my ($self, %options) = @_;

    #rpm -q centreon-plugin-Network-Microsens-G6-Snmp test centreon-plugin-Network-Generic-Bluecoat-Snmp
    #centreon-plugin-Network-Microsens-G6-Snmp-20211228-150846.el7.centos.noarch
    #package test is not installed
    #centreon-plugin-Network-Generic-Bluecoat-Snmp-20211102-130335.el7.centos.noarch
    my ($error, $stdout, $return_code) = gorgone::standard::misc::backtick(
        command => 'rpm',
        arguments => ['-q', keys %{$options{plugins}}],
        timeout => 60,
        wait_exit => 1,
        redirect_stderr => 1,
        logger => $self->{logger}
    );
    if ($error != 0) {
        return (-1, 'check rpm plugins command issue: ' . $stdout);
    }

    my $installed = [];
    foreach my $package_name (keys %{$options{plugins}}) {
        if ($stdout =~ /^$package_name-(\d+)-/m) {
            my $current_version = $1;
            if ($current_version < $options{plugins}->{$package_name}) {
                push @$installed, $package_name . '-' . $options{plugins}->{$package_name};
            }
        } else {
            push @$installed, $package_name . '-' . $options{plugins}->{$package_name};
        }
    }

    if (scalar(@$installed) > 0) {
        return (1, 'install', $installed);
    }

    $self->{logger}->writeLogInfo("[action] validate plugins - nothing to install");
    return 0;
}

sub check_plugins_deb {
    my ($self, %options) = @_;

    #dpkg -l centreon-plugin-*
    my ($error, $stdout, $return_code) = gorgone::standard::misc::backtick(
        command => 'dpkg',
        arguments => ['-l', 'centreon-plugin-*'],
        timeout => 60,
        wait_exit => 1,
        redirect_stderr => 1,
        logger => $self->{logger}
    );

    my $installed = [];
    foreach my $package_name (keys %{$options{plugins}}) {
        if ($stdout =~ /\s+$package_name\s+(\d+)-/m) {
            my $current_version = $1;
            if ($current_version < $options{plugins}->{$package_name}) {
                push @$installed, $package_name . '=' . $options{plugins}->{$package_name};
            }
        } else {
            push @$installed, $package_name . '=' . $options{plugins}->{$package_name};
        }
    }

    if (scalar(@$installed) > 0) {
        return (1, 'install', $installed);
    }

    $self->{logger}->writeLogInfo("[action] validate plugins - nothing to install");
    return 0;
}

sub install_plugins {
    my ($self, %options) = @_;

    $self->{logger}->writeLogInfo("[action] validate plugins - install " . join(' ', @{$options{installed}}));
    my ($error, $stdout, $return_code) = gorgone::standard::misc::backtick(
        command => 'sudo',
        arguments => ['/usr/local/bin/gorgone_install_plugins.pl', '--type=' . $options{type}, @{$options{installed}}],
        timeout => 300,
        wait_exit => 1,
        redirect_stderr => 1,
        logger => $self->{logger}
    );
    $self->{logger}->writeLogDebug("[action] install plugins. Command output: [\"$stdout\"]");
    if ($error != 0) {
        return (-1, 'install plugins command issue: ' . $stdout);
    }

    return 0;
}

sub validate_plugins_rpm {
    my ($self, %options) = @_;

    my ($rv, $message, $installed) = $self->check_plugins_rpm(%options);
    return (1, $message) if ($rv == -1);
    return 0 if ($rv == 0);

    if ($rv == 1) {
        ($rv, $message) = $self->install_plugins(type => 'rpm', installed => $installed);
        return (1, $message) if ($rv == -1);
    }

    ($rv, $message, $installed) = $self->check_plugins_rpm(%options);
    return (1, $message) if ($rv == -1);
    if ($rv == 1) {
        $message = 'validate plugins - still some to install: ' . join(' ', @$installed);
        $self->{logger}->writeLogError("[action] $message");
        return (1, $message);
    }

    return 0;
}

sub validate_plugins_deb {
    my ($self, %options) = @_;

    my $plugins = {};
    foreach (keys %{$options{plugins}}) {
        $plugins->{ lc($_) } = $options{plugins}->{$_};
    }

    my ($rv, $message, $installed) = $self->check_plugins_deb(plugins => $plugins);
    return (1, $message) if ($rv == -1);
    return 0 if ($rv == 0);

    if ($rv == 1) {
        ($rv, $message) = $self->install_plugins(type => 'deb', installed => $installed);
        return (1, $message) if ($rv == -1);
    }

    ($rv, $message, $installed) = $self->check_plugins_deb(plugins => $plugins);
    return (1, $message) if ($rv == -1);
    if ($rv == 1) {
        $message = 'validate plugins - still some to install: ' . join(' ', @$installed);
        $self->{logger}->writeLogError("[action] $message");
        return (1, $message);
    }

    return 0;
}

sub validate_plugins {
    my ($self, %options) = @_;

    my ($rv, $message, $content);
    my $plugins = $options{plugins};
    if (!defined($plugins)) {
        ($rv, $message, $content) = gorgone::standard::misc::slurp(file => $options{file});
        return (1, $message) if (!$rv);

        try {
            $plugins = JSON::XS->new->decode($content);
        } catch {
            return (1, 'cannot decode json');
        };
    }

    # nothing to validate. so it's ok, show must go on!! :)
    if (ref($plugins) ne 'HASH' || scalar(keys %$plugins) <= 0) {
        return 0;
    }

    if ($self->{package_manager} eq 'rpm') {
        ($rv, $message) = $self->validate_plugins_rpm(plugins => $plugins);
    } elsif ($self->{package_manager} eq 'deb') {
        ($rv, $message) = $self->validate_plugins_deb(plugins => $plugins);
    } else {
        ($rv, $message) = (1, 'validate plugins - unsupported operating system');
    }

    return ($rv, $message);
}

sub is_command_authorized {
    my ($self, %options) = @_;

    return 0 if ($self->{whitelist_cmds} == 0);

    foreach my $regexp (@{$self->{allowed_cmds}}) {
        return 0 if ($options{command} =~ /$regexp/);
    }

    return 1;
}

sub action_command {
    my ($self, %options) = @_;
    
    if (!defined($options{data}->{content}) || ref($options{data}->{content}) ne 'ARRAY') {
        $self->send_log(
            socket => $options{socket_log},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            logging => $options{data}->{logging},
            data => {
                message => "expected array, found '" . ref($options{data}->{content}) . "'"
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
                logging => $options{data}->{logging},
                data => {
                    message => "need command argument at array index '" . $index . "'"
                }
            );
            return -1;
        }

        if ($self->is_command_authorized(command => $command->{command})) {
            $self->{logger}->writeLogInfo("[action] command not allowed (whitelist): " . $command->{command});
            $self->send_log(
                socket => $options{socket_log},
                code => GORGONE_ACTION_FINISH_KO,
                token => $options{token},
                logging => $options{data}->{logging},
                data => {
                    message => "command not allowed (whitelist) at array index '" . $index . "'"
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
        logging => $options{data}->{logging},
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
            logging => $options{data}->{logging},
            data => {
                message => "command has started",
                command => $command->{command},
                metadata => $command->{metadata}
            }
        );

        # check install pkg
        if (defined($command->{metadata}) && defined($command->{metadata}->{pkg_install})) {
            my ($rv, $message) = $self->validate_plugins(plugins => $command->{metadata}->{pkg_install});
            if ($rv && $self->{paranoid_plugins} == 1) {
                $self->{logger}->writeLogError("[action] $message");
                $self->send_log(
                    socket => $options{socket_log},
                    code => GORGONE_ACTION_FINISH_KO,
                    token => $options{token},
                    logging => $options{data}->{logging},
                    data => {
                        message => "command execution issue",
                        command => $command->{command},
                        metadata => $command->{metadata},
                        result => {
                            exit_code => $rv,
                            stdout => $message
                        }
                    }
                );
                next;
            }
        }

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
                logging => $options{data}->{logging},
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
                    logging => $options{data}->{logging},
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
                logging => $options{data}->{logging},
                instant => $options{data}->{instant},
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
            logging => $options{data}->{logging},
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
        logging => $options{data}->{logging},
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
            logging => $options{data}->{logging},
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
                logging => $options{data}->{logging},
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
            logging => $options{data}->{logging},
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

                my $tar = Archive::Tar->new();
                $tar->setcwd($options{data}->{content}->{destination});
                unless ($tar->read($cache_file, undef, { extract => 1 })) {
                    my $tar_error = $tar->error();
                    $self->send_log(
                        code => GORGONE_ACTION_FINISH_KO,
                        token => $options{token},
                        logging => $options{data}->{logging},
                        data => { message => "untar failed: $tar_error" }
                    );
                    $self->{logger}->writeLogError("[action] Copy processing - Untar failed: $tar_error");
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
                logging => $options{data}->{logging},
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
        logging => $options{data}->{logging},
        data => {
            message => "process copy finished successfully",
        }
    );
    $self->{logger}->writeLogInfo("[action] Copy processing - Copy to '" . $options{data}->{content}->{destination} . "' finished successfully");
    return 0;
}

sub action_actionengine {
    my ($self, %options) = @_;

    if (!defined($options{data}->{content}) || $options{data}->{content} eq '') {
        $self->send_log(
            socket => $options{socket_log},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            logging => $options{data}->{logging},
            data => { message => 'no content' }
        );
        return -1;
    }

    if (!defined($options{data}->{content}->{command})) {
        $self->send_log(
            socket => $options{socket_log},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            logging => $options{data}->{logging},
            data => {
                message => "need valid command argument"
            }
        );
        return -1;
    }

    if ($self->is_command_authorized(command => $options{data}->{content}->{command})) {
        $self->{logger}->writeLogInfo("[action] command not allowed (whitelist): " . $options{data}->{content}->{command});
        $self->send_log(
            socket => $options{socket_log},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            logging => $options{data}->{logging},
            data => {
                message => 'command not allowed (whitelist)'
            }
        );
        return -1;
    }

    if (defined($options{data}->{content}->{plugins}) && $options{data}->{content}->{plugins} ne '') {
        my ($rv, $message) = $self->validate_plugins(file => $options{data}->{content}->{plugins});
        if ($rv && $self->{paranoid_plugins} == 1) {
            $self->{logger}->writeLogError("[action] $message");
            $self->send_log(
                socket => $options{socket_log},
                code => GORGONE_ACTION_FINISH_KO,
                token => $options{token},
                logging => $options{data}->{logging},
                data => {
                    message => $message
                }
            );
            return -1;
        }
    }

    my $start = time();
    my ($error, $stdout, $return_code) = gorgone::standard::misc::backtick(
        command => $options{data}->{content}->{command},
        timeout => $self->{command_timeout},
        wait_exit => 1,
        redirect_stderr => 1,
        logger => $self->{logger}
    );
    my $end = time();
    if ($error != 0) {
        $self->send_log(
            socket => $options{socket_log},
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            logging => $options{data}->{logging},
            data => {
                message => "command execution issue",
                command => $options{data}->{content}->{command},
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
        return -1;
    }

    $self->send_log(
        socket => $options{socket_log},
        code => GORGONE_ACTION_FINISH_OK,
        token => $options{token},
        logging => $options{data}->{logging},
        data => {
            message => 'actionengine has finished successfully'
        }
    );

    return 0;
}

sub action_run {
    my ($self, %options) = @_;

    my $context;
    {
        local $SIG{__DIE__};
        $context = ZMQ::FFI->new();
    }

    my $socket_log = gorgone::standard::library::connect_com(
        context => $context,
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgone-action-'. $$,
        logger => $self->{logger},
        zmq_linger => 60000,
        type => $self->get_core_config(name => 'internal_com_type'),
        path => $self->get_core_config(name => 'internal_com_path')
    );

    if ($options{action} eq 'COMMAND') {
        $self->action_command(%options, socket_log => $socket_log);
    } elsif ($options{action} eq 'ACTIONENGINE') {
        $self->action_actionengine(%options, socket_log => $socket_log);
    } else {
        $self->send_log(
            socket => $socket_log,
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            logging => $options{data}->{logging},
            data => { message => "action unknown" }
        );
        return -1;
    }
}

sub create_child {
    my ($self, %options) = @_;

    if ($options{action} =~ /^BCAST.*/) {
        if ((my $method = $self->can('action_' . lc($options{action})))) {
            $method->($self, token => $options{token}, data => $options{data});
        }
        return undef;
    }

    if ($options{action} eq 'ACTIONENGINE') {
        my $num = scalar(keys %{$self->{engine_childs}});
        if ($num > $self->{max_concurrent_engine}) {
            $self->{logger}->writeLogInfo("[action] max_concurrent_engine limit reached ($num/$self->{max_concurrent_engine})");
            $self->send_log(
                code => GORGONE_ACTION_FINISH_KO,
                token => $options{token},
                data => { message => "max_concurrent_engine limit reached ($num/$self->{max_concurrent_engine})" }
            );
            return undef;
        }
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
        $self->set_fork();
        $self->action_run(action => $options{action}, token => $options{token}, data => $options{data});
        exit(0);
    } else {
        if ($options{action} eq 'ACTIONENGINE') {
            $self->{engine_childs}->{$child_pid} = 1;
        }
    }
}

sub event {
    my ($self, %options) = @_;

    while ($self->{internal_socket}->has_pollin()) {
        my ($message) = $self->read_message();
        next if (!defined($message));

        $self->{logger}->writeLogDebug("[action] Event: $message");
        
        if ($message !~ /^\[ACK\]/) {
            $message =~ /^\[(.*?)\]\s+\[(.*?)\]\s+\[.*?\]\s+(.*)$/m;
            
            my ($action, $token) = ($1, $2);
            my ($rv, $data) = $self->json_decode(argument => $3, token => $token);
            next if ($rv);

            if (defined($data->{parameters}->{no_fork})) {
                if ((my $method = $self->can('action_' . lc($action)))) {
                    $method->($self, token => $token, data => $data);
                }
            } else {
                $self->create_child(action => $action, token => $token, data => $data);
            }
        }
    }
}

sub periodic_exec {
    $connector->check_childs();
    if ($connector->{stop} == 1) {
        $connector->{logger}->writeLogInfo("[action] $$ has quit");
        exit(0);
    }
}

sub run {
    my ($self, %options) = @_;

    $self->{internal_socket} = gorgone::standard::library::connect_com(
        context => $self->{zmq_context},
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgone-action',
        logger => $self->{logger},
        type => $self->get_core_config(name => 'internal_com_type'),
        path => $self->get_core_config(name => 'internal_com_path')
    );
    $self->send_internal_action({
        action => 'ACTIONREADY',
        data => {}
    });

    $self->get_package_manager();

    my $watcher_timer = $self->{loop}->timer(5, 5, \&periodic_exec);
    my $watcher_io = $self->{loop}->io($connector->{internal_socket}->get_fd(), EV::READ, sub { $connector->event() } );
    $self->{loop}->run();
}

1;
