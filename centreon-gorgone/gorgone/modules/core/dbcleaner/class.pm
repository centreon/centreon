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
use ZMQ::LibZMQ4;
use ZMQ::Constants qw(:all);
use JSON::XS;

my %handlers = (TERM => {}, HUP => {});
my ($connector);

sub new {
    my ($class, %options) = @_;

    $connector  = {};
    $connector->{internal_socket} = undef;
    $connector->{module_id} = $options{module_id};
    $connector->{logger} = $options{logger};
    $connector->{config} = $options{config};
    $connector->{config_core} = $options{config_core};
    $connector->{stop} = 0;
    $connector->{purge_timer} = time();

    bless $connector, $class;
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
    $self->{logger}->writeLogInfo("[dbcleaner] -class- $$ Receiving order to stop...");
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

sub action_dbclean {
    my ($self, %options) = @_;

    $options{token} = $self->generate_token() if (!defined($options{token}));

    if (defined($options{cycle})) {
        return 0 if ($self->{purge_timer} - time() < 3600);
    }

    $self->send_log(
        code => gorgone::class::module::ACTION_BEGIN,
        token => $options{token},
        data => {
            message => 'action dbclean proceed'
        }
    ) if (!defined($options{cycle}));

    $self->{logger}->writeLogInfo("[dbcleaner] -class- purge db in progress...");
    my ($status) = $self->{db_gorgone}->query("DELETE FROM gorgone_identity WHERE `ctime` <  " . $self->{db_gorgone}->quote(time() - $self->{config}->{purge_sessions_time}));
    my ($status2) = $self->{db_gorgone}->query("DELETE FROM gorgone_history WHERE `ctime` <  " . $self->{db_gorgone}->quote(time() - $self->{config}->{purge_history_time}));
    $self->{purge_timer} = time();

    $self->{logger}->writeLogDebug("[dbcleaner] -class- finish dbclean");

    if ($status == -1 || $status2 == -1) {
        $self->send_log(
            code => $self->ACTION_FINISH_KO,
            token => $options{token},
            data => {
                message => 'action dbclean finished'
            }
        ) if (!defined($options{cycle}));
        return 0;
    }

    $self->send_log(
        code => $self->ACTION_FINISH_OK,
        token => $options{token},
        data => {
            message => 'action dbclean finished'
        }
    ) if (!defined($options{cycle}));
    return 0;
}

sub event {
    while (1) {
        my $message = gorgone::standard::library::zmq_dealer_read_message(socket => $connector->{internal_socket});
        
        $connector->{logger}->writeLogDebug("[dbcleaner] -class- Event: $message");
        if ($message =~ /^\[(.*?)\]/) {
            if ((my $method = $connector->can('action_' . lc($1)))) {
                $message =~ /^\[(.*?)\]\s+\[(.*?)\]\s+\[.*?\]\s+(.*)$/m;
                my ($action, $token) = ($1, $2);
                my $data = JSON::XS->new->utf8->decode($3);
                $method->($connector, token => $token, data => $data);
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
        name => 'gorgonedbcleaner',
        logger => $self->{logger},
        type => $self->{config_core}->{internal_com_type},
        path => $self->{config_core}->{internal_com_path}
    );
    gorgone::standard::library::zmq_send_message(
        socket => $connector->{internal_socket},
        action => 'DBCLEANERREADY',
        data => {},
        json_encode => 1
    );
    $self->{poll} = [
        {
            socket  => $connector->{internal_socket},
            events  => ZMQ_POLLIN,
            callback => \&event,
        }
    ];

    $self->{db_gorgone} = gorgone::class::db->new(
        type => $self->{config_core}->{gorgone_db_type},
        db => $self->{config_core}->{gorgone_db_name},
        host => $self->{config_core}->{gorgone_db_host},
        port => $self->{config_core}->{gorgone_db_port},
        user => $self->{config_core}->{gorgone_db_user},
        password => $self->{config_core}->{gorgone_db_password},
        force => 2,
        logger => $self->{logger}
    );

    while (1) {
        # we try to do all we can
        my $rev = zmq_poll($self->{poll}, 5000);
        if (defined($rev) && $rev == 0 && $self->{stop} == 1) {
            $self->{logger}->writeLogInfo("[dbcleaner] -class- $$ has quit");
            zmq_close($connector->{internal_socket});
            exit(0);
        }

        $self->action_dbclean(cycle => 1);
    }
}

1;
