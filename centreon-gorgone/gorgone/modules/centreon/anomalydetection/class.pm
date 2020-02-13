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

package gorgone::modules::centreon::anomalydetection::class;

use base qw(gorgone::class::module);

use strict;
use warnings;
use gorgone::standard::library;
use gorgone::class::sqlquery;
use gorgone::class::http::http;
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
    $connector->{config_db_centreon} = $options{config_db_centreon};
    $connector->{config_db_centstorage} = $options{config_db_centstorage};
    $connector->{stop} = 0;

    $connector->{resync_time} = (defined($options{config}->{resync_time}) && $options{config}->{resync_time} =~ /(\d+)/) ? $1 : 600;
    $connector->{last_resync_time} = -1;
    $connector->{authtoken} = undef;
    $connector->{proxyurl} = undef; # format http://[username:password@]server:port

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
    $self->{logger}->writeLogDebug("[anomalydetection] $$ Receiving order to stop...");
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

sub action_adsync {
    my ($self, %options) = @_;

    $options{token} = $self->generate_token() if (!defined($options{token}));
    $self->send_log(code => gorgone::class::module::ACTION_BEGIN, token => $options{token}, data => { message => 'action adsync proceed' });

    # we need to get proxyurl, token options
    # need to check if the module is installed
    # first part: declare in SaaS

    $self->{logger}->writeLogDebug("[anomalydetection] Finish adsync");
    $self->send_log(code => $self->ACTION_FINISH_OK, token => $options{token}, data => { message => 'action adsync finished' });
    return 0;
}

sub event {
    while (1) {
        my $message = gorgone::standard::library::zmq_dealer_read_message(socket => $connector->{internal_socket});

        $connector->{logger}->writeLogDebug("[anomalydetection] Event: $message");
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

    $self->{db_centreon} = gorgone::class::db->new(
        dsn => $self->{config_db_centreon}->{dsn},
        user => $self->{config_db_centreon}->{username},
        password => $self->{config_db_centreon}->{password},
        force => 2,
        logger => $self->{logger}
    );
    $self->{db_centstorage} = gorgone::class::db->new(
        dsn => $self->{config_db_centstorage}->{dsn},
        user => $self->{config_db_centstorage}->{username},
        password => $self->{config_db_centstorage}->{password},
        force => 2,
        logger => $self->{logger}
    );

    ##### Load objects #####
    $self->{class_object_centreon} = gorgone::class::sqlquery->new(logger => $self->{logger}, db_centreon => $self->{db_centreon});
    $self->{class_object_centstorage} = gorgone::class::sqlquery->new(logger => $self->{logger}, db_centreon => $self->{db_centstorage});
    $self->{http} = gorgone::class::http::http->new(logger => $self->{logger});

    # Connect internal
    $connector->{internal_socket} = gorgone::standard::library::connect_com(
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgone-anomalydetection',
        logger => $self->{logger},
        type => $self->{config_core}->{internal_com_type},
        path => $self->{config_core}->{internal_com_path}
    );
    $connector->send_internal_action(
        action => 'CENTREONADREADY',
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
        if (defined($rev) && $rev == 0 && $self->{stop} == 1) {
            $self->{logger}->writeLogInfo("[anomalydetection] -class- $$ has quit");
            zmq_close($connector->{internal_socket});
            exit(0);
        }

        if (time() - $self->{resync_time} > $self->{last_resync_time}) {
            $self->{last_resync_time} = time();
            $self->action_adsync();
        }
    }
}

1;
