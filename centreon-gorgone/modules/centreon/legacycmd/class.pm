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
    $connector->{config_core} = $options{config_core};
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
    if (-e $self->{config}->{cmd_file} . '_read') {
        if (!open($handle, '+<', $self->{config}->{cmd_file} . '_read')) {
            $self->{logger}->writeLogError("[legacycmd] -class- cannot open file '" . $self->{config}->{cmd_file} . "_read': $!");
            return -1;
        }
        
        return (0, $handle);
    }

    return -1 if (! -e $connector->{config}->{cmd_file});

    if (!File::Copy::move($self->{config}->{cmd_file}, $self->{config}->{cmd_file} . '_read')) {
        $self->{logger}->writeLogError("[legacycmd] -class- cannot move file '" . $connector->{config}->{cmd_file} . "': $!");
        return -1;
    }

    if (!open($handle, '+<', $self->{config}->{cmd_file} . '_read')) {
        $self->{logger}->writeLogError("[legacycmd] -class- cannot open file '" . $self->{config}->{cmd_file} . "_read': $!");
        return -1;
    }

    return (0, $handle);
}

#sub getNagiosConfigurationField($$){
#    my $self = shift;

#    my ($status, $sth) = $self->{centreon_dbc}->query("SELECT " . $_[1] . " FROM `cfg_nagios` WHERE `nagios_server_id` = '" . $_[0] . "' AND nagios_activate = '1'");
#    if ($status == -1) {
#        $self->{logger}->writeLogError("Error when getting server properties");
#        return undef;
#    }
#    my $data = $sth->fetchrow_hashref();
#    return $data->{$_[1]};
#}

sub execute_cmd {
    my ($self, %options) = @_;

    if ($options{cmd} eq 'EXTERNALCMD') {
        $self->send_internal_action(
            action => 'ENGINECOMMAND',
            target => $options{target},
            token => $self->generate_token(),
            data => { content => { command => $options{param}, engine_pipe => '/var/lib/centreon-engine/rw/centengine.cmd' } },
        );
    }
}

sub handle_cmd_file {
    my ($self, %options) = @_;
    require bytes;

    my ($code, $handle) = $self->move_cmd_file();
    return if ($code == -1);

    while (my $line = <$handle>) {
        if ($self->{stop} == 1) {
            close($handle);
            return ;
        }

        if ($line =~ /^(.*?):(.*?):(.*)/) {
            $self->execute_cmd(cmd => $1, target => $2, param => $3);
            my $current_pos = tell($handle);
            seek($handle, $current_pos - bytes::length($line), 0);
            syswrite($handle, '-');
            # line is useless
            $line = <$handle>;
        }
    }

    $self->{logger}->writeLogDebug("[legacycmd] -class- process file '" . $connector->{config}->{cmd_file} . "_read'");
    close($handle);
    unlink($self->{config}->{cmd_file} . '_read');
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

    $self->{poll} = [
        {
            socket  => $connector->{internal_socket},
            events  => ZMQ_POLLIN,
            callback => \&event,
        }
    ];
    while (1) {
        # we try to do all we can
        my $rev = zmq_poll($self->{poll}, 1000);
        if ($rev == 0 && $self->{stop} == 1) {
            $self->{logger}->writeLogInfo("[legacycmd] -class- $$ has quit");
            zmq_close($connector->{internal_socket});
            exit(0);
        }

        $self->handle_cmd_file();
    }
}

1;
