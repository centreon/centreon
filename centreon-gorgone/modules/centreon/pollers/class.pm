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

package modules::centreon::pollers::class;

use base qw(centreon::gorgone::module);

use strict;
use warnings;
use centreon::gorgone::common;
use centreon::misc::objects::object;
use centreon::misc::http::http;
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
    $connector->{register_pollers} = {}; 

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
    $self->{logger}->writeLogInfo("[pollers] -class- $$ Receiving order to stop...");
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

sub action_pollersresync {
    my ($self, %options) = @_;

    $options{token} = $self->generate_token() if (!defined($options{token}));

    $self->send_log(code => centreon::gorgone::module::ACTION_BEGIN, token => $options{token}, data => { message => 'action pollersresync proceed' });

    my $request = "
        SELECT id, name, localhost, ns_ip_address, ssh_port, remote_id, remote_server_centcore_ssh_proxy
        FROM nagios_server
        WHERE ns_activate = '1'
    ";
    my ($status, $datas) = $self->{class_object}->custom_execute(request => $request, mode => 2);
    if ($status == -1) {
        $self->send_log(code => centreon::gorgone::module::ACTION_FINISH_KO, token => $options{token}, data => { message => 'cannot find pollers configuration' });
        $self->{logger}->writeLogError("[pollers] -class- cannot find pollers configuration");
        return 1;
    }

    my $core_id;
    my $register_temp = {};
    my $register_nodes = [];
    my $register_subnodes = {};
    foreach (@$datas) {
        if ($_->[2] == 1) {
            $core_id = $_->[0];
            next;
        }

        # remote_server_centcore_ssh_proxy = 1 means: pass through the remote. otherwise directly.
        if (defined($_->[5]) && $_->[5] =~ /\d+/ && $_->[6] == 1) {
            $register_subnodes->{$_->[5]} = [] if (!defined($register_subnodes->{$_->[5]}));
            push @{$register_subnodes->{$_->[5]}}, $_->[0];
            next;
        }
        $self->{register_pollers}->{$_->[0]} = 1;
        $register_temp->{$_->[0]} = 1;
        push @{$register_nodes}, { id => $_->[0], type => 'push_ssh', address => $_->[3], ssh_port => $_->[4] };
    }

    my $unregister_nodes = [];    
    foreach (keys %{$self->{register_pollers}}) {
        if (!defined($register_temp->{$_})) {
            push @{$unregister_nodes}, { id => $_ };
            delete $self->{register_pollers}->{$_};
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

    $self->{logger}->writeLogDebug("[pollers] -class- finish resync");
    $self->send_log(code => $self->ACTION_FINISH_OK, token => $options{token}, data => { message => 'action pollersresync finished' });
    return 0;
}

sub event {
    while (1) {
        my $message = centreon::gorgone::common::zmq_dealer_read_message(socket => $connector->{internal_socket});
        
        $connector->{logger}->writeLogDebug("[pollers] -class- Event: $message");
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

    # Database creation. We stay in the loop still there is an error
    $self->{db_centreon} = centreon::misc::db->new(
        dsn => $self->{config_db_centreon}->{dsn},
        user => $self->{config_db_centreon}->{username},
        password => $self->{config_db_centreon}->{password},
        force => 2,
        logger => $self->{logger}
    );
    ##### Load objects #####
    $self->{class_object} = centreon::misc::objects::object->new(logger => $self->{logger}, db_centreon => $self->{db_centreon});

    # Connect internal
    $connector->{internal_socket} = centreon::gorgone::common::connect_com(
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgonepollers',
        logger => $self->{logger},
        type => $self->{config_core}->{internal_com_type},
        path => $self->{config_core}->{internal_com_path}
    );
    centreon::gorgone::common::zmq_send_message(
        socket => $connector->{internal_socket},
        action => 'POLLERSREADY', data => { },
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
        my $rev = zmq_poll($self->{poll}, 5000);
        if (defined($rev) && $rev == 0 && $self->{stop} == 1) {
            $self->{logger}->writeLogInfo("[pollers] -class- $$ has quit");
            zmq_close($connector->{internal_socket});
            exit(0);
        }

        if (time() - $self->{resync_time} > $self->{last_resync_time}) {
            $self->{last_resync_time} = time();
            $self->action_pollersresync();
        }
    }
}

1;
