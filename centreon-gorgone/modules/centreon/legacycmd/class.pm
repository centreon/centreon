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

package modules::centreon::legacycmd::class;

use base qw(centreon::gorgone::module);

use strict;
use warnings;
use centreon::gorgone::common;
use centreon::misc::misc;
use centreon::misc::objects::object;
use ZMQ::LibZMQ4;
use ZMQ::Constants qw(:all);
use File::Copy;

my %handlers = (TERM => {}, HUP => {});
my ($connector);

sub new {
    my ($class, %options) = @_;
    $connector  = {};
    $connector->{internal_socket} = undef;
    $connector->{logger} = $options{logger};
    $connector->{config} = $options{config};
    if (!defined($connector->{config}->{cmd_file}) || $connector->{config}->{cmd_file} eq '') {
        $connector->{config}->{cmd_file} = '/var/lib/centreon/centcore.cmd';
    }
    if (!defined($connector->{config}->{cmd_dir}) || $connector->{config}->{cmd_dir} eq '') {
        $connector->{config}->{cmd_dir} = '/var/lib/centreon/centcore/';
    }
    $connector->{config_core} = $options{config_core};
    $connector->{config_db_centreon} = $options{config_db_centreon};
    $connector->{stop} = 0;
    
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
    $self->{logger}->writeLogInfo("[legacycmd] -class- $$ Receiving order to stop...");
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

sub move_cmd_file {
    my ($self, %options) = @_;

    my $handle;
    if (-e $options{dst}) {
        if (!open($handle, '+<', $options{dst})) {
            $self->{logger}->writeLogError("[legacycmd] -class- cannot open file '" . $options{dst} . "': $!");
            return -1;
        }
        
        return (0, $handle);
    }

    return -1 if (!defined($options{src}));
    return -1 if (! -e $options{src});

    if (!File::Copy::move($options{src}, $options{dst})) {
        $self->{logger}->writeLogError("[legacycmd] -class- cannot move file '" . $options{src} . "': $!");
        return -1;
    }

    if (!open($handle, '+<', $options{dst})) {
        $self->{logger}->writeLogError("[legacycmd] -class- cannot open file '" . $options{dst} . "': $!");
        return -1;
    }

    return (0, $handle);
}

sub get_pollers_config {
    my ($self, %options) = @_;

    $self->{pollers} = {};
    my ($status, $datas) = $self->{class_object_centreon}->custom_execute(
        request => 'SELECT nagios_server_id, command_file, cfg_dir, centreonbroker_cfg_path, snmp_trapd_path_conf, ' .
            'engine_start_command, engine_stop_command, engine_restart_command, engine_reload_command, ' .
            'broker_reload_command, init_script_centreontrapd ' .
            'FROM cfg_nagios ' .
            'JOIN nagios_server ' .
            'WHERE id = nagios_server_id',
        mode => 1,
        keys => 'nagios_server_id'
    );
    if ($status == -1 || !defined($datas)) {
        $self->{logger}->writeLogError('[legacycmd] -class- cannot get configuration for pollers');
        return -1;
    }

    $self->{pollers} = $datas;

    return 0;
}

sub get_clapi_user {
    my ($self, %options) = @_;

    my $clapi_user = (defined($connector->{config}->{clapi_user})) ?
        $connector->{config}->{clapi_user} : 'admin';
    my ($status, $datas) = $self->{class_object_centreon}->custom_execute(
        request => "SELECT contact_passwd " .
            "FROM `contact` " .
            "WHERE `contact_activate` = '1' AND `contact_alias` = '" . $clapi_user . "'",
        mode => 2
    );
    if ($status == -1 || !defined($datas->[0])) {
        $self->{logger}->writeLogError('[legacycmd] -class- cannot get configuration for clapi user');
        return -1;
    }
    my $clapi_password = $datas->[0];
    if ($clapi_password =~ m/^md5__(.*)/) {
        $clapi_password = $1;
    }

    $self->{clapi_user} = $clapi_user;
    $self->{clapi_password} = $clapi_password;
    
    return 0;
}

sub execute_cmd {
    my ($self, %options) = @_;

    chomp $options{target};
    chomp $options{param} if (defined($options{param}));
    if ($options{cmd} eq 'EXTERNALCMD') {
        # TODO: need to remove illegal characters!!
        $self->send_internal_action(
            action => 'ENGINECOMMAND',
            target => $options{target},
            token => $self->generate_token(),
            data => {
                content => {
                    command => $options{param},
                    command_file => $self->{pollers}->{$options{target}}->{command_file},
                }
            },
        );
    } elsif ($options{cmd} eq 'SENDCFGFILE') {
        my $cache_dir = (defined($connector->{config}->{cache_dir})) ?
            $connector->{config}->{cache_dir} : '/var/cache/centreon';
        # engine
        $self->send_internal_action(
            action => 'REMOTECOPY',
            target => $options{target},
            token => $self->generate_token(),
            data => {
                content => {
                    source => $cache_dir . '/config/engine/' . $options{target},
                    destination => $self->{pollers}->{$options{target}}->{cfg_dir} . '/',
                    cache_dir => $cache_dir,
                    metadata => {
                        centcore_cmd => 'SENDCFGFILE',
                    }
                }
            },
        );
        # broker
        $self->send_internal_action(
            action => 'REMOTECOPY',
            target => $options{target},
            token => $self->generate_token(),
            data => {
                content => {
                    source => $cache_dir . '/config/broker/' . $options{target},
                    destination => $self->{pollers}->{$options{target}}->{centreonbroker_cfg_path} . '/',
                    cache_dir => $cache_dir,
                    metadata => {
                        centcore_proxy => 1,
                        centcore_cmd => 'SENDCFGFILE',
                    }
                }
            },
        );
    } elsif ($options{cmd} eq 'SENDEXPORTFILE') {
        my $cache_dir = (defined($connector->{config}->{cache_dir})) ?
            $connector->{config}->{cache_dir} : '/var/cache/centreon';
        my $remote_dir = (defined($connector->{config}->{remote_dir})) ?
            $connector->{config}->{remote_dir} : '/var/lib/centreon/remote-data/';
        # remote server
        $self->send_internal_action(
            action => 'REMOTECOPY',
            target => $options{target},
            token => $self->generate_token(),
            data => {
                content => {
                    source => $cache_dir . '/config/export/' . $options{target},
                    destination => $remote_dir,
                    cache_dir => $cache_dir,
                    metadata => {
                        centcore_cmd => 'SENDEXPORTFILE',
                    }
                }
            },
        );
        
        my $centreon_dir = (defined($connector->{config}->{centreon_dir})) ?
            $connector->{config}->{centreon_dir} : '/usr/share/centreon';
        my $task_id = $options{param};
        my $cmd = $centreon_dir . '/bin/centreon -u ' . $self->{clapi_user} . ' -p ' .
            $self->{clapi_password} . ' -w -o CentreonWorker -a createRemoteTask -v ' . $task_id;
        $self->send_internal_action(
            action => 'COMMAND',
            target => undef,
            token => $self->generate_token(),
            data => {
                content => {
                    command => $cmd,
                    metadata => {
                        centcore_cmd => 'SENDEXPORTFILE',
                    }
                }
            },
        );
    } elsif ($options{cmd} eq 'SYNCTRAP') {
        my $cache_dir = (defined($connector->{config}->{cache_dir})) ?
            $connector->{config}->{cache_dir} : '/var/cache/centreon';
        my $cache_dir_trap = (defined($connector->{config}->{cache_dir_trap})) ?
            $connector->{config}->{cache_dir_trap} : '/etc/snmp/centreon_traps/';
        # centreontrapd
        $self->send_internal_action(
            action => 'REMOTECOPY',
            target => $options{target},
            token => $self->generate_token(),
            data => {
                content => {
                    source => $cache_dir_trap . '/' . $options{target} . '/centreontrapd.sdb',
                    destination => $self->{pollers}->{$options{target}}->{snmp_trapd_path_conf} . '/',
                    cache_dir => $cache_dir,
                    metadata => {
                        centcore_proxy => 1,
                        centcore_cmd => 'SYNCTRAP',
                    }
                }
            },
        );
    } elsif ($options{cmd} eq 'RESTART') {
        my $cmd = $self->{pollers}->{$options{target}}->{engine_restart_command};
        $self->send_internal_action(
            action => 'COMMAND',
            target => $options{target},
            token => $self->generate_token(),
            data => {
                content => {
                    command => 'sudo ' . $cmd,
                    metadata => {
                        centcore_proxy => 1,
                        centcore_cmd => 'SYNCTRAP',
                    }
                }
            },
        );
    } elsif ($options{cmd} eq 'RELOAD') {
        my $cmd = $self->{pollers}->{$options{target}}->{engine_reload_command};
        $self->send_internal_action(
            action => 'COMMAND',
            target => $options{target},
            token => $self->generate_token(),
            data => {
                content => {
                    command => 'sudo ' . $cmd,
                    metadata => {
                        centcore_proxy => 1,
                        centcore_cmd => 'RELOAD',
                    }
                }
            },
        );
    } elsif ($options{cmd} eq 'START') {
        my $cmd = $self->{pollers}->{$options{target}}->{engine_start_command};
        $self->send_internal_action(
            action => 'COMMAND',
            target => $options{target},
            token => $self->generate_token(),
            data => {
                content => {
                    command => 'sudo ' . $cmd,
                    metadata => {
                        centcore_proxy => 1,
                        centcore_cmd => 'START',
                    }
                }
            },
        );
    } elsif ($options{cmd} eq 'STOP') {
        my $cmd = $self->{pollers}->{$options{target}}->{engine_stop_command};
        $self->send_internal_action(
            action => 'COMMAND',
            target => $options{target},
            token => $self->generate_token(),
            data => {
                content => {
                    command => 'sudo ' . $cmd,
                    metadata => {
                        centcore_proxy => 1,
                        centcore_cmd => 'STOP',
                    }
                }
            },
        );
    } elsif ($options{cmd} eq 'RESTARTCENTREONTRAPD') {
        my $cmd = $self->{pollers}->{$options{target}}->{init_script_centreontrapd};
        $self->send_internal_action(
            action => 'COMMAND',
            target => $options{target},
            token => $self->generate_token(),
            data => {
                content => {
                    command => 'sudo service ' . $cmd . ' restart',
                    metadata => {
                        centcore_proxy => 1,
                        centcore_cmd => 'RESTARTCENTREONTRAPD',
                    }
                }
            },
        );
    } elsif ($options{cmd} eq 'RELOADCENTREONTRAPD') {
        my $cmd = $self->{pollers}->{$options{target}}->{init_script_centreontrapd};
        $self->send_internal_action(
            action => 'COMMAND',
            target => $options{target},
            token => $self->generate_token(),
            data => {
                content => {
                    command => 'sudo service ' . $cmd . ' reload',
                    metadata => {
                        centcore_proxy => 1,
                        centcore_cmd => 'RELOADCENTREONTRAPD',
                    }
                }
            },
        );
    } elsif ($options{cmd} eq 'STARTWORKER') {
        my $centreon_dir = (defined($connector->{config}->{centreon_dir})) ?
            $connector->{config}->{centreon_dir} : '/usr/share/centreon';
        my $cmd = $centreon_dir . '/bin/centreon -u ' . $self->{clapi_user} . ' -p ' .
            $self->{clapi_password} . ' -w -o CentreonWorker -a processQueue';
        $self->send_internal_action(
            action => 'COMMAND',
            target => undef,
            token => $self->generate_token(),
            data => {
                content => {
                    command => $cmd,
                    metadata => {
                        centcore_cmd => 'STARTWORKER',
                    }
                }
            },
        );
    } elsif ($options{cmd} eq 'CREATEREMOTETASK') {
        my $centreon_dir = (defined($connector->{config}->{centreon_dir})) ?
            $connector->{config}->{centreon_dir} : '/usr/share/centreon';
        my $task_id = $options{target};
        my $cmd = $centreon_dir . '/bin/centreon -u ' . $self->{clapi_user} . ' -p ' .
            $self->{clapi_password} . ' -w -o CentreonWorker -a createRemoteTask -v ' . $task_id;
        $self->send_internal_action(
            action => 'COMMAND',
            target => undef,
            token => $self->generate_token(),
            data => {
                content => {
                    command => $cmd,
                    metadata => {
                        centcore_cmd => 'CREATEREMOTETASK',
                    }
                }
            },
        );
    }
}

sub handle_file {
    my ($self, %options) = @_;
    require bytes;

    my $handle = $options{handle};
    while (my $line = <$handle>) {
        if ($self->{stop} == 1) {
            close($handle);
            return -1;
        }

        if ($line =~ /^(.*?):([^:]*)(?::(.*)){0,1}/) {
            $self->execute_cmd(cmd => $1, target => $2, param => $3);
            my $current_pos = tell($handle);
            seek($handle, $current_pos - bytes::length($line), 0);
            syswrite($handle, '-');
            # line is useless
            $line = <$handle>;
        }
    }

    $self->{logger}->writeLogDebug("[legacycmd] -class- process file '" . $options{file} . "'");
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
        $self->{logger}->writeLogError("[legacycmd] -class- cant opendir '" . $self->{config}->{cmd_dir} . "': $!");
        return ;
    }
    @files = sort {
        (stat($self->{config}->{cmd_dir} . '/' . $a))[10] <=> (stat($self->{config}->{cmd_dir} . '/' . $b))[10]
    } (readdir($dh));
    closedir($dh);
    
    my ($code, $handle);
    foreach (@files) {
        next if ($_ =~ /^\./);
        if ($_ =~ /_read$/) {
            ($code, $handle) = $self->move_cmd_file(
                dst => $self->{config}->{cmd_dir} . '/' . $_,
            );
        } else {
            ($code, $handle) = $self->move_cmd_file(
                src => $self->{config}->{cmd_dir} . '/' . $_,
                dst => $self->{config}->{cmd_dir} . '/' . $_ . '_read',
            );
        }
        return if ($code == -1);
        if ($self->handle_file(handle => $handle, file => $self->{config}->{cmd_dir} . '/' . $_) == -1) {
            return ;
        }
    }
}

sub handle_cmd_files {
    my ($self, %options) = @_;

    return if ($self->get_pollers_config() == -1 || $self->get_clapi_user() == -1);
    $self->handle_centcore_cmd();
    $self->handle_centcore_dir();
}

sub event {
    while (1) {
        my $message = centreon::gorgone::common::zmq_dealer_read_message(socket => $connector->{internal_socket});
        
        $connector->{logger}->writeLogDebug("[legacycmd] -class- Event: $message");
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
        name => 'gorgonelegacycmd',
        logger => $self->{logger},
        type => $self->{config_core}->{internal_com_type},
        path => $self->{config_core}->{internal_com_path}
    );
    centreon::gorgone::common::zmq_send_message(
        socket => $connector->{internal_socket},
        action => 'LEGACYCMDREADY', data => {},
        json_encode => 1
    );

    $self->{db_centreon} = centreon::misc::db->new(
        dsn => $self->{config_db_centreon}->{dsn},
        user => $self->{config_db_centreon}->{username},
        password => $self->{config_db_centreon}->{password},
        force => 2,
        logger => $self->{logger}
    );
    $self->{class_object_centreon} = centreon::misc::objects::object->new(
        logger => $self->{logger},
        db_centreon => $self->{db_centreon}
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
        my $rev = zmq_poll($self->{poll}, 2000);
        if ($rev == 0 && $self->{stop} == 1) {
            $self->{logger}->writeLogInfo("[legacycmd] -class- $$ has quit");
            zmq_close($connector->{internal_socket});
            exit(0);
        }

        $self->handle_cmd_files();
    }
}

1;
