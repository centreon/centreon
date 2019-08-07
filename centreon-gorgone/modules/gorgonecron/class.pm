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

package modules::gorgonecron::class;

use strict;
use warnings;
use centreon::gorgone::common;
use ZMQ::LibZMQ4;
use ZMQ::Constants qw(:all);
use Schedule::Cron;

my %handlers = (TERM => {}, HUP => {});
my ($connector, $socket);

sub new {
    my ($class, %options) = @_;
    $connector  = {};
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
    $self->{logger}->writeLogInfo("gorgone-action $$ Receiving order to stop...");
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

sub event {
    while (1) {
        my $message = centreon::gorgone::common::zmq_dealer_read_message(socket => $socket);
        
        $connector->{logger}->writeLogDebug("gorgonecron: class: $message");
        
        last unless (centreon::gorgone::common::zmq_still_read(socket => $socket));
    }
}

sub cron_sleep {
    my $rev = zmq_poll($connector->{poll}, 1000);
    if ($rev == 0 && $connector->{stop} == 1) {
        $connector->{logger}->writeLogInfo("gorgone-cron $$ has quit");
        zmq_close($socket);
        exit(0);
    }
}

sub dispatcher {
    my ($options) = @_;

    $options->{logger}->writeLogInfo('gorgone-cron Job is starting');    
}

sub run {
    my ($self, %options) = @_;

    # Connect internal
    $socket = centreon::gorgone::common::connect_com(
        zmq_type => 'ZMQ_DEALER', name => 'gorgonecron',
        logger => $self->{logger},
        type => $self->{config_core}->{internal_com_type},
        path => $self->{config_core}->{internal_com_path}
    );
    centreon::gorgone::common::zmq_send_message(
        socket => $socket,
        action => 'CRONREADY', data => { },
        json_encode => 1
    );
    $self->{poll} = [
        {
            socket  => $socket,
            events  => ZMQ_POLLIN,
            callback => \&event,
        }
    ];
    my $cron = new Schedule::Cron(\&dispatcher, nostatus => 1, nofork => 1);
    $cron->add_entry('* * * * *', \&dispatcher, { logger => $self->{logger}, plop => 1 });
    
    # Each cron should have an ID in centreon DB. And like that, you can delete some not here.
    #print Data::Dumper::Dumper($cron->list_entries());
    
    $cron->run(sleep => \&cron_sleep);
    zmq_close($socket);
    exit(0);
}

1;
