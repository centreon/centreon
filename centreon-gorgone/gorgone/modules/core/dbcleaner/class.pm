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

package gorgone::modules::core::dbcleaner::class;

use base qw(gorgone::class::module);

use strict;
use warnings;
use gorgone::class::db;
use gorgone::standard::library;
use gorgone::standard::constants qw(:all);
use JSON::XS;
use EV;

my %handlers = (TERM => {}, HUP => {}, DIE => {});
my ($connector);

sub new {
    my ($class, %options) = @_;
    $connector = $class->SUPER::new(%options);
    bless $connector, $class;

    $connector->{purge_timer} = time();

    $connector->set_signal_handlers();
    return $connector;
}

sub set_signal_handlers {
    my $self = shift;

    $SIG{TERM} = \&class_handle_TERM;
    $handlers{TERM}->{$self} = sub { $self->handle_TERM() };
    $SIG{HUP} = \&class_handle_HUP;
    $handlers{HUP}->{$self} = sub { $self->handle_HUP() };
    $SIG{__DIE__} = \&class_handle_DIE;
    $handlers{DIE}->{$self} = sub { $self->handle_DIE($_[0]) };
}

sub handle_HUP {
    my $self = shift;
    $self->{reload} = 0;
}

sub handle_TERM {
    my $self = shift;
    $self->{logger}->writeLogDebug("[dbcleaner] $$ Receiving order to stop...");
    $self->{stop} = 1;
}

sub handle_DIE {
    my $self = shift;
    my $msg = shift;

    $self->{logger}->writeLogError("[dbcleaner] Receiving DIE: $msg");
    $self->exit_process();
}

sub class_handle_DIE {
    my ($msg) = @_;

    foreach (keys %{$handlers{DIE}}) {
        &{$handlers{DIE}->{$_}}($msg);
    }
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

sub exit_process {
    my ($self, %options) = @_;

    $self->{logger}->writeLogInfo("[dbcleaner] $$ has quit");
    exit(0);
}

sub action_dbclean {
    my ($self, %options) = @_;

    $options{token} = $self->generate_token() if (!defined($options{token}));

    if (defined($options{cycle})) {
        return 0 if ((time() - $self->{purge_timer}) < 3600);
    }

    $self->send_log(
        code => GORGONE_ACTION_BEGIN,
        token => $options{token},
        data => {
            message => 'action dbclean proceed'
        }
    ) if (!defined($options{cycle}));

    $self->{logger}->writeLogDebug("[dbcleaner] Purge database in progress...");
    my ($status) = $self->{db_gorgone}->query({
        query => 'DELETE FROM gorgone_identity WHERE `mtime` < ?',
        bind_values => [time() - $self->{config}->{purge_sessions_time}]
    });
    my ($status2) = $self->{db_gorgone}->query({
        query => "DELETE FROM gorgone_history WHERE (instant = 1 AND `ctime` <  " . (time() - 86400) . ") OR `ctime` < ?",
        bind_values => [time() - $self->{config}->{purge_history_time}]
    });
    $self->{purge_timer} = time();

    $self->{logger}->writeLogDebug("[dbcleaner] Purge finished");

    if ($status == -1 || $status2 == -1) {
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => {
                message => 'action dbclean finished'
            }
        ) if (!defined($options{cycle}));
        return 0;
    }

    $self->send_log(
        code => GORGONE_ACTION_FINISH_OK,
        token => $options{token},
        data => {
            message => 'action dbclean finished'
        }
    ) if (!defined($options{cycle}));
    return 0;
}

sub event {
    while (1) {
        my ($message) = $connector->read_message();
        last if (!defined($message));

        $connector->{logger}->writeLogDebug("[dbcleaner] Event: $message");
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
        $connector->exit_process();
    }

    $connector->action_dbclean(cycle => 1);
}

sub run {
    my ($self, %options) = @_;

    $self->{internal_socket} = gorgone::standard::library::connect_com(
        context => $self->{zmq_context},
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgone-dbcleaner',
        logger => $self->{logger},
        type => $self->get_core_config(name => 'internal_com_type'),
        path => $self->get_core_config(name => 'internal_com_path')
    );
    $self->send_internal_action({
        action => 'DBCLEANERREADY',
        data => {}
    });

    $self->{db_gorgone} = gorgone::class::db->new(
        type => $self->get_core_config(name => 'gorgone_db_type'),
        db => $self->get_core_config(name => 'gorgone_db_name'),
        host => $self->get_core_config(name => 'gorgone_db_host'),
        port => $self->get_core_config(name => 'gorgone_db_port'),
        user => $self->get_core_config(name => 'gorgone_db_user'),
        password => $self->get_core_config(name => 'gorgone_db_password'),
        force => 2,
        logger => $self->{logger}
    );

    my $w1 = EV::timer(5, 2, \&periodic_exec);
    my $w2 = EV::io($connector->{internal_socket}->get_fd(), EV::READ|EV::WRITE, \&event);
    EV::run();
}

1;
