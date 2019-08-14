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

package modules::core::cron::class;

use base qw(centreon::gorgone::module);

use strict;
use warnings;
use centreon::gorgone::common;
use centreon::misc::misc;
use ZMQ::LibZMQ4;
use ZMQ::Constants qw(:all);
use Schedule::Cron;

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
    $self->{logger}->writeLogInfo("[cron] -class- $$ Receiving order to stop...");
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

sub action_listcron {
    my ($self, %options) = @_;
    
    $options{token} = $self->generate_token() if (!defined($options{token}));

    $self->send_log(code => centreon::gorgone::module::ACTION_BEGIN, token => $options{token}, data => { message => 'action listcron proceed' });
    $self->{logger}->writeLogDebug("[cron] -class- Cron list start");

    my @cron_list;
    eval {
        my @results = $self->{cron}->list_entries();
        foreach my $cron (@results) {
            push @cron_list, { %{$cron->{args}[0]->{definition}} };
        }
    };
    if ($@) {        
        $self->{logger}->writeLogDebug("[cron] -class- Cron list failed");
        $self->send_log(code => $self->ACTION_FINISH_KO, token => $options{token}, data => { message => 'action listcron failed' });
        return 1;
    }

    $self->{logger}->writeLogDebug("[cron] -class- Cron list finish");
    $self->send_log(code => $self->ACTION_FINISH_OK, token => $options{token}, data => \@cron_list);
    return 0;
}

sub event {
    while (1) {
        my $message = centreon::gorgone::common::zmq_dealer_read_message(socket => $connector->{internal_socket});
        
        $connector->{logger}->writeLogDebug("[cron] -class- Event: $message");
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

sub cron_sleep {
    my $rev = zmq_poll($connector->{poll}, 1000);
    if ($rev == 0 && $connector->{stop} == 1) {
        $connector->{logger}->writeLogInfo("[cron] -class- $$ has quit");
        zmq_close($connector->{internal_socket});
        exit(0);
    }
}

sub dispatcher {
    my ($options) = @_;

    $options->{logger}->writeLogInfo("[cron] -class- Launching job '" . $options->{definition}->{name} . "'");

    centreon::gorgone::common::zmq_send_message(
        socket => $options->{socket},
        action => 'COMMAND',
        target => $options->{definition}->{target},
        data => { command => $options->{definition}->{command_line} },
        json_encode => 1
    );
 
    my $poll = [
        {
            socket  => $options->{socket},
            events  => ZMQ_POLLIN,
            callback => \&event,
        }
    ];

    my $rev = zmq_poll($poll, 5000);
}

sub run {
    my ($self, %options) = @_;

    # Connect internal
    $connector->{internal_socket} = centreon::gorgone::common::connect_com(
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgonecron',
        logger => $self->{logger},
        type => $self->{config_core}->{internal_com_type},
        path => $self->{config_core}->{internal_com_path}
    );
    centreon::gorgone::common::zmq_send_message(
        socket => $connector->{internal_socket},
        action => 'CRONREADY', data => { },
        json_encode => 1
    );
    $connector->{poll} = [
        {
            socket  => $connector->{internal_socket},
            events  => ZMQ_POLLIN,
            callback => \&event,
        }
    ];

    $self->{cron} = new Schedule::Cron(\&dispatcher, nostatus => 1, nofork => 1);

    foreach my $def (@{$self->{config}->{cron}}) {
        $self->{cron}->add_entry($def->{timespec}, \&dispatcher, { socket => $connector->{internal_socket}, logger => $self->{logger}, definition => $def });
    }
        
    $self->{cron}->run(sleep => \&cron_sleep);

    zmq_close($connector->{internal_socket});
    exit(0);
}

1;
