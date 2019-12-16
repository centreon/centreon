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

package gorgone::modules::centreon::nodes::class;

use base qw(gorgone::class::module);

use strict;
use warnings;
use gorgone::standard::library;
use gorgone::class::sqlquery;
use gorgone::class::http::http;
use ZMQ::LibZMQ4;
use ZMQ::Constants qw(:all);
use MIME::Base64;
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
    $connector->{stop} = 0;
    $connector->{register_nodes} = {}; 

    $connector->{resync_time} = (defined($options{config}->{resync_time}) && $options{config}->{resync_time} =~ /(\d+)/) ? $1 : 600;
    $connector->{last_resync_time} = -1;

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
    $self->{logger}->writeLogInfo("[nodes] -class- $$ Receiving order to stop...");
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

sub action_nodesresync {
    my ($self, %options) = @_;

    $options{token} = $self->generate_token() if (!defined($options{token}));

    $self->send_log(code => gorgone::class::module::ACTION_BEGIN, token => $options{token}, data => { message => 'action nodesresync proceed' });

    my $request = 'SELECT remote_server_id, poller_server_id FROM rs_poller_relation';
    my ($status, $datas) = $self->{class_object}->custom_execute(request => $request, mode => 2);
    if ($status == -1) {
        $self->send_log(code => gorgone::class::module::ACTION_FINISH_KO, token => $options{token}, data => { message => 'cannot find nodes remote configuration' });
        $self->{logger}->writeLogError('[nodes] -class- cannot find nodes remote configuration');
        return 1;
    }

    # we set a pathscore of 100 because it's "slave"
    my $register_subnodes = {};
    foreach (@$datas) {
        $register_subnodes->{$_->[0]} = [] if (!defined($register_subnodes->{$_->[0]}));
        unshift $register_subnodes->{$_->[0]}, { id => $_->[1], pathscore => 100 };
    }

    $request = "
        SELECT id, name, localhost, ns_ip_address, gorgone_port, remote_id, remote_server_use_as_proxy, gorgone_communication_type
        FROM nagios_server
        WHERE ns_activate = '1'
    ";
    ($status, $datas) = $self->{class_object}->custom_execute(request => $request, mode => 2);
    if ($status == -1) {
        $self->send_log(code => gorgone::class::module::ACTION_FINISH_KO, token => $options{token}, data => { message => 'cannot find nodes configuration' });
        $self->{logger}->writeLogError('[nodes] -class- cannot find nodes configuration');
        return 1;
    }

    my $core_id;
    my $register_temp = {};
    my $register_nodes = [];
    foreach (@$datas) {
        if ($_->[2] == 1) {
            $core_id = $_->[0];
            next;
        }

        # remote_server_use_as_proxy = 1 means: pass through the remote. otherwise directly.
        if (defined($_->[5]) && $_->[5] =~ /\d+/ && $_->[6] == 1) {
            $register_subnodes->{$_->[5]} = [] if (!defined($register_subnodes->{$_->[5]}));
            unshift @{$register_subnodes->{$_->[5]}}, { id => $_->[0], pathscore => 1 };
            next;
        }
        $self->{register_nodes}->{$_->[0]} = 1;
        $register_temp->{$_->[0]} = 1;
        if ($_->[7] == 2) {
            push @$register_nodes, { id => $_->[0], type => 'push_ssh', address => $_->[3], ssh_port => $_->[4] };
        } else {
            push @$register_nodes, { id => $_->[0], type => 'push_zmq', address => $_->[3], port => $_->[4] };
        }
    }

    my $unregister_nodes = [];    
    foreach (keys %{$self->{register_nodes}}) {
        if (!defined($register_temp->{$_})) {
            push @$unregister_nodes, { id => $_ };
            delete $self->{register_nodes}->{$_};
        }
    }

    # We add subnodes
    foreach (@$register_nodes) {
        if (defined($register_subnodes->{ $_->{id} })) {
            $_->{nodes} = $register_subnodes->{ $_->{id} };
        }
    }

    $self->send_internal_action(action => 'SETCOREID', data => { id => $core_id } ) if (defined($core_id));
    $self->send_internal_action(action => 'REGISTERNODES', data => { nodes => $register_nodes } );
    $self->send_internal_action(action => 'UNREGISTERNODES', data => { nodes => $unregister_nodes } );

    $self->{logger}->writeLogDebug("[nodes] -class- finish resync");
    $self->send_log(code => $self->ACTION_FINISH_OK, token => $options{token}, data => { message => 'action nodesresync finished' });
    return 0;
}

sub event {
    while (1) {
        my $message = gorgone::standard::library::zmq_dealer_read_message(socket => $connector->{internal_socket});
        
        $connector->{logger}->writeLogDebug("[nodes] -class- Event: $message");
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

    # Database creation. We stay in the loop still there is an error
    $self->{db_centreon} = gorgone::class::db->new(
        dsn => $self->{config_db_centreon}->{dsn},
        user => $self->{config_db_centreon}->{username},
        password => $self->{config_db_centreon}->{password},
        force => 0,
        logger => $self->{logger}
    );
    ##### Load objects #####
    $self->{class_object} = gorgone::class::sqlquery->new(logger => $self->{logger}, db_centreon => $self->{db_centreon});

    # Connect internal
    $connector->{internal_socket} = gorgone::standard::library::connect_com(
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgonenodes',
        logger => $self->{logger},
        type => $self->{config_core}->{internal_com_type},
        path => $self->{config_core}->{internal_com_path}
    );
    $connector->send_internal_action(
        action => 'CENTREONNODESREADY',
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
            $self->{logger}->writeLogInfo("[nodes] -class- $$ has quit");
            zmq_close($connector->{internal_socket});
            exit(0);
        }

        if (time() - $self->{resync_time} > $self->{last_resync_time}) {
            $self->{last_resync_time} = time();
            $self->action_nodesresync();
        }
    }
}

1;
