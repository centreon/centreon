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

package gorgone::modules::centreon::legacycmd::class;

use base qw(gorgone::class::module);

use strict;
use warnings;
use gorgone::standard::library;
use gorgone::standard::constants qw(:all);
use gorgone::standard::misc;
use gorgone::class::sqlquery;
use gorgone::class::tpapi::clapi;
use File::Copy;
use EV;

my %handlers = (TERM => {}, HUP => {});
my ($connector);

sub new {
    my ($class, %options) = @_;
    $connector = $class->SUPER::new(%options);
    bless $connector, $class;

    $connector->{tpapi_clapi_name} = defined($options{config}->{tpapi_clapi}) && $options{config}->{tpapi_clapi} ne ''
        ? $options{config}->{tpapi_clapi}
        : 'clapi';
    if (!defined($connector->{config}->{cmd_file}) || $connector->{config}->{cmd_file} eq '') {
        $connector->{config}->{cmd_file} = '/var/lib/centreon/centcore.cmd';
    }
    if (!defined($connector->{config}->{cmd_dir}) || $connector->{config}->{cmd_dir} eq '') {
        $connector->{config}->{cmd_dir} = '/var/lib/centreon/centcore/';
    }
    $connector->{config}->{bulk_external_cmd} =
        defined($connector->{config}->{bulk_external_cmd}) && $connector->{config}->{bulk_external_cmd} =~ /(\d+)/ ? $1 : 50;
    $connector->{config}->{bulk_external_cmd_sequential} =
        defined($connector->{config}->{bulk_external_cmd_sequential}) && $connector->{config}->{bulk_external_cmd_sequential} =~ /^False|0$/i ? 0 : 1;
    $connector->{config}->{dirty_mode} = defined($connector->{config}->{dirty_mode}) ? $connector->{config}->{dirty_mode} : 1;
    $connector->{gorgone_illegal_characters} = '`';
    $connector->{cache_refresh_interval} = 60;
    $connector->{cache_refresh_last} = -1;
    $connector->{bulk_commands} = {};

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
    $self->{logger}->writeLogDebug("[legacycmd] $$ Receiving order to stop...");
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

sub cache_refresh {
    my ($self, %options) = @_;

    return if ((time() - $self->{cache_refresh_interval}) < $self->{cache_refresh_last});
    $self->{cache_refresh_last} = time();

    # get pollers config
    $self->{pollers} = undef;
    my ($status, $datas) = $self->{class_object_centreon}->custom_execute(
        request => 'SELECT nagios_server_id, command_file, cfg_dir, centreonbroker_cfg_path, snmp_trapd_path_conf, ' .
            'engine_start_command, engine_stop_command, engine_restart_command, engine_reload_command, ' .
            'broker_reload_command, init_script_centreontrapd ' .
            'FROM cfg_nagios, nagios_server ' .
            "WHERE nagios_server.id = cfg_nagios.nagios_server_id AND cfg_nagios.nagios_activate = '1'",
        mode => 1,
        keys => 'nagios_server_id'
    );
    if ($status == -1 || !defined($datas)) {
        $self->{logger}->writeLogError('[legacycmd] Cannot get configuration for pollers');
        return ;
    }

    $self->{pollers} = $datas;

    # check illegal characters
    ($status, $datas) = $self->{class_object_centreon}->custom_execute(
        request => "SELECT `value` FROM options WHERE `key` = 'gorgone_illegal_characters'",
        mode => 2
    );
    if ($status == -1) {
        $self->{logger}->writeLogError('[legacycmd] Cannot get illegal characters');
        return ;
    }

    if (defined($datas->[0]->[0])) {
        $self->{gorgone_illegal_characters} = $datas->[0]->[0];
    }
}

sub check_pollers_config {
    my ($self, %options) = @_;

    return defined($self->{pollers}) ? 1 : 0;
}

sub send_external_commands {
    my ($self, %options) = @_;
    my $token = $options{token};
    $token = $self->generate_token() if (!defined($token));

    my $targets = [];
    $targets = [$options{target}] if (defined($options{target}));
    if (scalar(@$targets) <= 0) {
        $targets = [keys %{$self->{bulk_commands}}];
    }

    foreach my $target (@$targets) {
        next if (!defined($self->{bulk_commands}->{$target}) || scalar(@{$self->{bulk_commands}->{$target}}) <= 0);
        $self->send_internal_action({
            action => 'ENGINECOMMAND',
            target => $target,
            token => $token,
            data => {
                logging => $options{logging},
                content => {
                    command_file => $self->{pollers}->{$target}->{command_file},
                    commands => [
                        join("\n", @{$self->{bulk_commands}->{$target}})
                    ]
                }
            }
        });

        $self->{logger}->writeLogDebug("[legacycmd] send external commands for '$target'");
        $self->{bulk_commands}->{$target} = [];
    }
}

sub add_external_command {
    my ($self, %options) = @_;

    $options{param} =~ s/[\Q$self->{gorgone_illegal_characters}\E]//g
        if (defined($self->{gorgone_illegal_characters}) && $self->{gorgone_illegal_characters} ne '');
    if ($options{action} == 1) {
        $self->send_internal_action({
            action => 'ENGINECOMMAND',
            target => $options{target},
            token => $options{token},
            data => {
                logging => $options{logging},
                content => {
                    command_file => $self->{pollers}->{ $options{target} }->{command_file},
                    commands => [
                        $options{param}
                    ]
                }
            }
        });
    } else {
        $self->{bulk_commands}->{ $options{target} } = [] if (!defined($self->{bulk_commands}->{ $options{target} }));
        push @{$self->{bulk_commands}->{ $options{target} }}, $options{param};
        if (scalar(@{$self->{bulk_commands}->{ $options{target} }}) > $self->{config}->{bulk_external_cmd}) {
            $self->send_external_commands(%options);
        }
    }
}

sub execute_cmd {
    my ($self, %options) = @_;

    chomp $options{target};
    chomp $options{param} if (defined($options{param}));
    my $token = $options{token};
    $token = $self->generate_token() if (!defined($token));

    my $msg = "[legacycmd] Handling command '" . $options{cmd} . "'";
    $msg .= ", Target: '" . $options{target} . "'" if (defined($options{target}));
    $msg .= ", Parameters: '" . $options{param} . "'" if (defined($options{param}));
    $self->{logger}->writeLogInfo($msg);

    if ($options{cmd} eq 'EXTERNALCMD') {
        $self->add_external_command(
            action => $options{action},
            param => $options{param},
            target => $options{target},
            token => $options{token},
            logging => $options{logging}
        );
        return 0;
    }

    $self->send_external_commands(target => $options{target})
        if (defined($options{target}) && $self->{config}->{bulk_external_cmd_sequential} == 1);

    if ($options{cmd} eq 'SENDCFGFILE') {
        my $cache_dir = (defined($connector->{config}->{cache_dir}) && $connector->{config}->{cache_dir} ne '') ?
            $connector->{config}->{cache_dir} : '/var/cache/centreon';
        # engine
        $self->send_internal_action({
            action => 'REMOTECOPY',
            target => $options{target},
            token => $token,
            data => {
                logging => $options{logging},
                content => {
                    source => $cache_dir . '/config/engine/' . $options{target},
                    destination => $self->{pollers}->{$options{target}}->{cfg_dir} . '/',
                    cache_dir => $cache_dir,
                    owner => 'centreon-engine',
                    group => 'centreon-engine',
                    metadata => {
                        centcore_proxy => 1,
                        centcore_cmd => 'SENDCFGFILE'
                    }
                }
            }
        });
        # broker
        $self->send_internal_action({
            action => 'REMOTECOPY',
            target => $options{target},
            token => $token,
            data => {
                logging => $options{logging},
                content => {
                    source => $cache_dir . '/config/broker/' . $options{target},
                    destination => $self->{pollers}->{$options{target}}->{centreonbroker_cfg_path} . '/',
                    cache_dir => $cache_dir,
                    owner => 'centreon-broker',
                    group => 'centreon-broker',
                    metadata => {
                        centcore_proxy => 1,
                        centcore_cmd => 'SENDCFGFILE'
                    }
                }
            }
        });
    } elsif ($options{cmd} eq 'SENDEXPORTFILE') {
        if (!defined($self->{clapi_password})) {
            return (-1, 'need centreon clapi password to execute SENDEXPORTFILE command');
        }

        my $cache_dir = (defined($connector->{config}->{cache_dir}) && $connector->{config}->{cache_dir} ne '') ?
            $connector->{config}->{cache_dir} : '/var/cache/centreon';
        my $remote_dir = (defined($connector->{config}->{remote_dir})) ?
            $connector->{config}->{remote_dir} : '/var/cache/centreon/config/remote-data/';
        # remote server
        $self->send_internal_action({
            action => 'REMOTECOPY',
            target => $options{target},
            token => $token,
            data => {
                logging => $options{logging},
                content => {
                    source => $cache_dir . '/config/export/' . $options{target},
                    destination => $remote_dir,
                    cache_dir => $cache_dir,
                    owner => 'centreon',
                    group => 'centreon',
                    metadata => {
                        centcore_cmd => 'SENDEXPORTFILE'
                    }
                }
            }
        });

        # Forward data use to be done by createRemoteTask as well as task_id in a gorgone command
        # Command name: AddImportTaskWithParent
        # Data: ['parent_id' => $task->getId()]
        $self->send_internal_action({
            action => 'ADDIMPORTTASKWITHPARENT',
            token => $options{token},
            target => $options{target},
            data => {
                logging => $options{logging},
                content => {
                    parent_id => $options{param},
                    cbd_reload => 'sudo ' . $self->{pollers}->{ $options{target} }->{broker_reload_command}
                }
            }
        });
    } elsif ($options{cmd} eq 'SYNCTRAP') {
        my $cache_dir = (defined($connector->{config}->{cache_dir}) && $connector->{config}->{cache_dir} ne '') ?
            $connector->{config}->{cache_dir} : '/var/cache/centreon';
        my $cache_dir_trap = (defined($connector->{config}->{cache_dir_trap}) && $connector->{config}->{cache_dir_trap} ne '') ?
            $connector->{config}->{cache_dir_trap} : '/etc/snmp/centreon_traps/';
        # centreontrapd
        $self->send_internal_action({
            action => 'REMOTECOPY',
            target => $options{target},
            token => $token,
            data => {
                logging => $options{logging},
                content => {
                    source => $cache_dir_trap . '/' . $options{target} . '/centreontrapd.sdb',
                    destination => $self->{pollers}->{$options{target}}->{snmp_trapd_path_conf} . '/',
                    cache_dir => $cache_dir,
                    owner => 'centreon',
                    group => 'centreon',
                    metadata => {
                        centcore_proxy => 1,
                        centcore_cmd => 'SYNCTRAP'
                    }
                }
            }
        });
    } elsif ($options{cmd} eq 'ENGINERESTART') {
        my $cmd = $self->{pollers}->{$options{target}}->{engine_restart_command};
        $self->send_internal_action({
            action => 'ACTIONENGINE',
            target => $options{target},
            token => $token,
            data => {
                logging => $options{logging},
                content => {
                    command => 'sudo ' . $cmd,
                    plugins => $self->{pollers}->{ $options{target} }->{cfg_dir} . '/plugins.json',
                    metadata => {
                        centcore_proxy => 1,
                        centcore_cmd => 'ENGINERESTART'
                    }
                }
            }
        });
    } elsif ($options{cmd} eq 'RESTART') {
        my $cmd = $self->{pollers}->{$options{target}}->{engine_restart_command};
        $self->send_internal_action({
            action => 'COMMAND',
            target => $options{target},
            token => $token,
            data => {
                logging => $options{logging},
                content => [
                    {
                        command => 'sudo ' . $cmd,
                        metadata => {
                            centcore_proxy => 1,
                            centcore_cmd => 'RESTART'
                        }
                    }
                ]
            }
        });
    } elsif ($options{cmd} eq 'ENGINERELOAD') {
        my $cmd = $self->{pollers}->{ $options{target} }->{engine_reload_command};
        $self->send_internal_action({
            action => 'ACTIONENGINE',
            target => $options{target},
            token => $token,
            data => {
                logging => $options{logging},
                content => {
                    command => 'sudo ' . $cmd,
                    plugins => $self->{pollers}->{ $options{target} }->{cfg_dir} . '/plugins.json',
                    metadata => {
                        centcore_proxy => 1,
                        centcore_cmd => 'ENGINERELOAD'
                    }
                }
            }
        });
    } elsif ($options{cmd} eq 'RELOAD') {
        my $cmd = $self->{pollers}->{$options{target}}->{engine_reload_command};
        $self->send_internal_action({
            action => 'COMMAND',
            target => $options{target},
            token => $token,
            data => {
                logging => $options{logging},
                content => [
                    {
                        command => 'sudo ' . $cmd,
                        metadata => {
                            centcore_proxy => 1,
                            centcore_cmd => 'RELOAD'
                        }
                    }
                ]
            }
        });
    } elsif ($options{cmd} eq 'START') {
        my $cmd = $self->{pollers}->{$options{target}}->{engine_start_command};
        $self->send_internal_action({
            action => 'COMMAND',
            target => $options{target},
            token => $token,
            data => {
                logging => $options{logging},
                content => [
                    {
                        command => 'sudo ' . $cmd,
                        metadata => {
                            centcore_proxy => 1,
                            centcore_cmd => 'START'
                        }
                    }
                ]
            }
        });
    } elsif ($options{cmd} eq 'STOP') {
        my $cmd = $self->{pollers}->{$options{target}}->{engine_stop_command};
        $self->send_internal_action({
            action => 'COMMAND',
            target => $options{target},
            token => $token,
            data => {
                logging => $options{logging},
                content => [
                    {
                        command => 'sudo ' . $cmd,
                        metadata => {
                            centcore_proxy => 1,
                            centcore_cmd => 'STOP'
                        }
                    }
                ]
            }
        });
    } elsif ($options{cmd} eq 'RELOADBROKER') {
        my $cmd = $self->{pollers}->{$options{target}}->{broker_reload_command};
        $self->send_internal_action({
            action => 'COMMAND',
            target => $options{target},
            token => $token,
            data => {
                logging => $options{logging},
                content => [
                    {
                        command => 'sudo ' . $cmd,
                        metadata => {
                            centcore_proxy => 1,
                            centcore_cmd => 'RELOADBROKER'
                        }
                    }
                ]
            }
        });
    } elsif ($options{cmd} eq 'RESTARTCENTREONTRAPD') {
        my $cmd = $self->{pollers}->{$options{target}}->{init_script_centreontrapd};
        $self->send_internal_action({
            action => 'COMMAND',
            target => $options{target},
            token => $token,
            data => {
                logging => $options{logging},
                content => [
                    {
                        command => 'sudo service ' . $cmd . ' restart',
                        metadata => {
                            centcore_proxy => 1,
                            centcore_cmd => 'RESTARTCENTREONTRAPD'
                        }
                    }
                ]
            }
        });
    } elsif ($options{cmd} eq 'RELOADCENTREONTRAPD') {
        my $cmd = $self->{pollers}->{$options{target}}->{init_script_centreontrapd};
        $self->send_internal_action({
            action => 'COMMAND',
            target => $options{target},
            token => $token,
            data => {
                logging => $options{logging},
                content => [
                    {
                        command => 'sudo service ' . $cmd . ' reload',
                        metadata => {
                            centcore_proxy => 1,
                            centcore_cmd => 'RELOADCENTREONTRAPD'
                        }
                    }
                ]
            }
        });
    } elsif ($options{cmd} eq 'STARTWORKER') {
        if (!defined($self->{clapi_password})) {
            return (-1, 'need centreon clapi password to execute STARTWORKER command');
        }
        my $centreon_dir = (defined($connector->{config}->{centreon_dir})) ?
            $connector->{config}->{centreon_dir} : '/usr/share/centreon';
        my $cmd = $centreon_dir . '/bin/centreon -u "' . $self->{clapi_user} . '" -p "' .
            $self->{clapi_password} . '" -w -o CentreonWorker -a processQueue';
        $self->send_internal_action({
            action => 'COMMAND',
            target => undef,
            token => $token,
            data => {
                logging => $options{logging},
                content => [
                    {
                        command => $cmd,
                        metadata => {
                            centcore_cmd => 'STARTWORKER'
                        }
                    }
                ]
            }
        });
    }

    return 0;
}

sub action_addimporttaskwithparent {
    my ($self, %options) = @_;

    if (!defined($options{data}->{content}->{parent_id})) {
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            logging => $options{data}->{logging},
            data => {
                message => "expected parent_id task ID, found '" . $options{data}->{content}->{parent_id} . "'",
            }
        );
        return -1;
    }

    my ($status, $datas) = $self->{class_object_centreon}->custom_execute(
        request => "INSERT INTO task (`type`, `status`, `parent_id`) VALUES ('import', 'pending', '" . $options{data}->{content}->{parent_id} . "')"
    );
    if ($status == -1) {
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            logging => $options{data}->{logging},
            data => {
                message => "Cannot add import task on Remote Server.",
            }
        );
        return -1;
    }

    my $centreon_dir = (defined($connector->{config}->{centreon_dir})) ?
        $connector->{config}->{centreon_dir} : '/usr/share/centreon';
    my $cmd = $centreon_dir . '/bin/centreon -u "' . $self->{clapi_user} . '" -p "' .
        $self->{clapi_password} . '" -w -o CentreonWorker -a processQueue';
    $self->send_internal_action({
        action => 'COMMAND',
        token => $options{token},
        data => {
            logging => $options{data}->{logging},
            content => [
                {
                    command => $cmd
                }
            ],
            parameters => { no_fork => 1 }
        }
    });
    $self->send_internal_action({
        action => 'COMMAND',
        token => $options{token},
        data => {
            logging => $options{data}->{logging},
            content => [
                {
                    command => $options{data}->{content}->{cbd_reload}
                }
            ]
        }
    });

    $self->send_log(
        code => GORGONE_ACTION_FINISH_OK,
        token => $options{token},
        logging => $options{data}->{logging},
        data => {
            message => 'Task inserted on Remote Server',
        }
    );

    return 0;
}

sub move_cmd_file {
    my ($self, %options) = @_;

    my $operator = '+<';
    if ($self->{config}->{dirty_mode} == 1) {
        $operator = '<';
    }
    my $handle;
    if (-e $options{dst}) {
        if (!open($handle, $operator, $options{dst})) {
            $self->{logger}->writeLogError("[legacycmd] Cannot open file '" . $options{dst} . "': $!");
            return -1;
        }
        
        return (0, $handle);
    }

    return -1 if (!defined($options{src}));
    return -1 if (! -e $options{src});

    if (!File::Copy::move($options{src}, $options{dst})) {
        $self->{logger}->writeLogError("[legacycmd] Cannot move file '" . $options{src} . "': $!");
        return -1;
    }

    if (!open($handle, $operator, $options{dst})) {
        $self->{logger}->writeLogError("[legacycmd] Cannot open file '" . $options{dst} . "': $!");
        return -1;
    }

    return (0, $handle);
}

sub handle_file {
    my ($self, %options) = @_;
    require bytes;

    $self->{logger}->writeLogDebug("[legacycmd] Processing file '" . $options{file} . "'");
    my $handle = $options{handle};
    while (my $line = <$handle>) {
        if ($self->{stop} == 1) {
            close($handle);
            return -1;
        }

        if ($line =~ /^(.*?):([^:]*)(?::(.*)){0,1}/) {
            $self->execute_cmd(action => 0, cmd => $1, target => $2, param => $3, logging => 0);
            if ($self->{config}->{dirty_mode} != 1) {
                my $current_pos = tell($handle);
                seek($handle, $current_pos - bytes::length($line), 0);
                syswrite($handle, '-');
                # line is useless
                $line = <$handle>;
            }
        }
    }

    close($handle);
    unlink($options{file});
    return 0;
}

sub handle_centcore_cmd {
    my ($self, %options) = @_;

    my ($code, $handle) = $self->move_cmd_file(
        src => $self->{config}->{cmd_file},
        dst => $self->{config}->{cmd_file} . '_read',
    );
    return if ($code == -1);
    $self->handle_file(handle => $handle, file => $self->{config}->{cmd_file} . '_read');
}

sub handle_centcore_dir {
    my ($self, %options) = @_;
    
    my ($dh, @files);
    if (!opendir($dh, $self->{config}->{cmd_dir})) {
        $self->{logger}->writeLogError("[legacycmd] Cannot open directory '" . $self->{config}->{cmd_dir} . "': $!");
        return ;
    }
    @files = sort {
        (stat($self->{config}->{cmd_dir} . '/' . $a))[10] <=> (stat($self->{config}->{cmd_dir} . '/' . $b))[10]
    } (readdir($dh));
    closedir($dh);

    my ($code, $handle);
    foreach (@files) {
        next if ($_ =~ /^\./);
        my $file = $self->{config}->{cmd_dir} . '/' . $_;
        if ($file =~ /_read$/) {
            ($code, $handle) = $self->move_cmd_file(
                dst => $file,
            );
        } else {
            ($code, $handle) = $self->move_cmd_file(
                src => $file,
                dst => $file . '_read',
            );
            $file .= '_read';
        }
        return if ($code == -1);
        if ($self->handle_file(handle => $handle, file => $file) == -1) {
            return ;
        }
    }
}

sub handle_cmd_files {
    my ($self, %options) = @_;

    return if ($self->check_pollers_config() == 0);
    $self->handle_centcore_cmd();
    $self->handle_centcore_dir();
    $self->send_external_commands(logging => 0);
}

sub action_centreoncommand {
    my ($self, %options) = @_;

    $self->{logger}->writeLogDebug('[legacycmd] -class- start centreoncommand');
    $options{token} = $self->generate_token() if (!defined($options{token}));
    $self->send_log(code => GORGONE_ACTION_BEGIN, token => $options{token}, data => { message => 'action centreoncommand proceed' });

    if (!defined($options{data}->{content}) || ref($options{data}->{content}) ne 'ARRAY') {
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => {
                message => "expected array, found '" . ref($options{data}->{content}) . "'",
            }
        );
        return -1;
    }

    if ($self->check_pollers_config() == 0) {
        $self->{logger}->writeLogError('[legacycmd] cannot get centreon database configuration');
        $self->send_log(code => GORGONE_ACTION_FINISH_KO, token => $options{token}, data => { message => 'cannot get centreon database configuration' });
        return 1;
    }

    foreach my $command (@{$options{data}->{content}}) {
        my ($code, $message) = $self->execute_cmd(
            action => 1,
            token => $options{token},
            target => $command->{target},
            cmd => $command->{command},
            param => $command->{param},
            logging => 1
        );

        if ($code == -1) {
            $self->{logger}->writeLogError('[legacycmd] -class- ' . $message);
            $self->send_log(code => GORGONE_ACTION_FINISH_KO, token => $options{token}, data => { message => $message });
            return 1;
        }
    }

    $self->{logger}->writeLogDebug('[legacycmd] -class- finish centreoncommand');
    return 0;
}

sub event {
    while (1) {
        my ($message) = $connector->read_message();
        last if (!defined($message));

        $connector->{logger}->writeLogDebug("[legacycmd] Event: $message");
        if ($message =~ /^\[(.*?)\]/) {
            if ((my $method = $connector->can('action_' . lc($1)))) {
                $message =~ /^\[(.*?)\]\s+\[(.*?)\]\s+\[.*?\]\s+(.*)$/m;
                my ($action, $token) = ($1, $2);
                my ($rv, $data) = $connector->json_decode(argument => $3, token => $token);
                next if ($rv);

                $method->($connector, token => $token, data => $data);
            }
        }
    }
}

sub periodic_exec {
    if ($connector->{stop} == 1) {
        $connector->{logger}->writeLogInfo("[legacycmd] $$ has quit");
        exit(0);
    }

    $connector->cache_refresh();
    $connector->handle_cmd_files();
}

sub run {
    my ($self, %options) = @_;

    $self->{tpapi_clapi} = gorgone::class::tpapi::clapi->new();
    $self->{tpapi_clapi}->set_configuration(
        config => $self->{tpapi}->get_configuration(name => $self->{tpapi_clapi_name})
    );

    $self->{clapi_user} = $self->{tpapi_clapi}->get_username();
    $self->{clapi_password} = $self->{tpapi_clapi}->get_password(protected => 1);

    # Connect internal
    $self->{internal_socket} = gorgone::standard::library::connect_com(
        context => $self->{zmq_context},
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgone-legacycmd',
        logger => $self->{logger},
        type => $self->get_core_config(name => 'internal_com_type'),
        path => $self->get_core_config(name => 'internal_com_path')
    );
    $self->send_internal_action({
        action => 'LEGACYCMDREADY',
        data => {}
    });

    $self->{db_centreon} = gorgone::class::db->new(
        dsn => $self->{config_db_centreon}->{dsn},
        user => $self->{config_db_centreon}->{username},
        password => $self->{config_db_centreon}->{password},
        force => 2,
        logger => $self->{logger}
    );
    $self->{class_object_centreon} = gorgone::class::sqlquery->new(
        logger => $self->{logger},
        db_centreon => $self->{db_centreon}
    );

    my $w1 = EV::timer(5, 2, \&periodic_exec);
    my $w2 = EV::io($connector->{internal_socket}->get_fd(), EV::READ|EV::WRITE, \&event);
    EV::run();
}

1;
