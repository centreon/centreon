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

package modules::centreon::autodiscovery::class;

use base qw(centreon::gorgone::module);

use strict;
use warnings;
use centreon::gorgone::common;
use centreon::misc::objects::object;
use ZMQ::LibZMQ4;
use ZMQ::Constants qw(:all);
use JSON::XS;
use Time::HiRes;

my %jobs;
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
    $connector->{sync_time} =
        (defined($options{config}->{sync_time}) && $options{config}->{sync_time} =~ /(\d+)/) ? $1 : 50;
    $connector->{last_sync_time} = -1;

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
    $self->{logger}->writeLogInfo("[autodiscovery] -class- $$ Receiving order to stop...");
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

sub action_adddiscoveryjob {
    my ($self, %options) = @_;

    $options{token} = $self->generate_token() if (!defined($options{token}));
    my $id = 'autodiscovery_job_' . $self->generate_token(length => 12) if (!defined($options{data}->{content}->{id}));

    my $definition = {
        id => $id,
        target => $options{data}->{content}->{target},
        timespec => $options{data}->{content}->{timespec},
        action => 'COMMAND',
        parameters => {
            command => $options{data}->{content}->{command},
            timeout => $options{data}->{content}->{timeout},
            metadata => {
                id => $id,
                source => 'autodiscovery',
            }
        },
        keep_token => 1,
    };
    
    $self->send_internal_action(
        action => 'ADDCRON',
        token => $options{token},
        data => {
            content => [ $definition ],
        }
    );

    $self->send_log(
        code => $self->ACTION_FINISH_OK,
        token => $options{token},
        data => {
            id => $id
        }
    );

    $jobs{$id} = { target => $options{data}->{content}->{target} };
    
    return 0;
}

sub action_getdiscoveryjob {
    my ($self, %options) = @_;

    $options{token} = $self->generate_token() if (!defined($options{token}));

    if (!defined($options{data}->{variables}[0])) {
        $self->{logger}->writeLogError("[autodiscovery] -class- Need to specify job id");
        $self->send_log(
            code => $self->ACTION_FINISH_KO,
            token => $options{token},
            data => {
                message => 'need to specify job id'
            }
        );
        return 1;
    }
    my $id = $options{data}->{variables}[0];
    
    $self->send_log(
        code => $self->ACTION_FINISH_OK,
        token => $options{token},
        data => {
            results => $jobs{$id}->{results}
        }
    );
    
    return 0;
}

sub action_syncdiscoverylogs {
    my ($self, %options) = @_;

    $options{token} = $self->generate_token() if (!defined($options{token}));

    $self->{logger}->writeLogDebug("[autodiscovery] -class- Discovery logs sync start");
    my %synced;
    foreach my $id (keys %jobs) {
        next if (!defined($jobs{$id}->{target}) || defined($synced{$jobs{$id}->{target}}));
        $self->send_internal_action(
            action => 'GETLOG',
            token => $options{token},
            target => $jobs{$id}->{target},
            data => {}
        );
        $synced{$jobs{$id}->{target}} = 1;
    }
    
    return 0;
}

sub action_getdiscoveryresults {
    my ($self, %options) = @_;

    foreach my $id (keys %jobs) {
        $self->{logger}->writeLogDebug("[autodiscovery] -class- Get logs results for job '" . $id . "'");
        $self->send_internal_action(
            action => 'GETLOG',
            data => {
                token => $id
            }
        );
    }
    
    return 0;
}

sub action_updatediscoveryresults {
    my ($self, %options) = @_;

    return if (!defined($options{data}->{data}->{action}) || $options{data}->{data}->{action} ne "getlog" &&
        defined($options{data}->{data}->{result}));

    foreach my $message_id (sort keys %{$options{data}->{data}->{result}}) {
        my $data = JSON::XS->new->utf8->decode($options{data}->{data}->{result}->{$message_id}->{data});
        next if (!defined($data->{exit_code}) || $data->{exit_code} != 0 ||
            !defined($data->{metadata}->{id}) || !defined($data->{metadata}->{source}) ||
            $data->{metadata}->{source} ne 'autodiscovery');

        $jobs{$data->{metadata}->{id}}->{results} = $data;
    }

    return 0;
}

sub event {
    while (1) {
        my $message = centreon::gorgone::common::zmq_dealer_read_message(socket => $connector->{internal_socket});
        
        $connector->{logger}->writeLogDebug("[autodiscovery] -class- Event: $message");
        if ($message =~ /^\[ACK\]\s+\[(.*?)\]\s+(.*)$/m) {
            my $token = $1;
            my $data = JSON::XS->new->utf8->decode($2);
            my $method = $connector->can('action_updatediscoveryresults');
            $method->($connector, data => $data);
        } else {
            $message =~ /^\[(.*?)\]\s+\[(.*?)\]\s+\[.*?\]\s+(.*)$/m;
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
    $self->{class_object} = centreon::misc::objects::object->new(
        logger => $self->{logger},
        db_centreon => $self->{db_centreon}
    );

    # Connect internal
    $connector->{internal_socket} = centreon::gorgone::common::connect_com(
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgoneautodiscovery',
        logger => $self->{logger},
        type => $self->{config_core}->{internal_com_type},
        path => $self->{config_core}->{internal_com_path}
    );
    centreon::gorgone::common::zmq_send_message(
        socket => $connector->{internal_socket},
        action => 'AUTODISCOVERYREADY',
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
        
    $self->send_internal_action(
        action => 'ADDCRON',
        data => {
            content => [
                {
                    id => 'autodiscovery_syncdiscoverylogs',
                    target => undef,
                    timespec => '*/2 * * * *',
                    action => 'SYNCDISCOVERYLOGS',
                    parameters => {},
                    keep_token => 1,
                }
            ]
        }
    );
    
    $self->send_internal_action(
        action => 'ADDCRON',
        data => {
            content => [
                {
                    id => 'autodiscovery_getdiscoveryresults',
                    target => undef,
                    timespec => '* * * * *',
                    action => 'GETDISCOVERYRESULTS',
                    parameters => {},
                    keep_token => 1,
                }
            ]
        }
    );

    while (1) {
        # we try to do all we can
        my $rev = zmq_poll($self->{poll}, 5000);
        if (defined($rev) && $rev == 0 && $self->{stop} == 1) {
            $self->{logger}->writeLogInfo("[autodiscovery] -class- $$ has quit");
            zmq_close($connector->{internal_socket});
            exit(0);
        }
    }
}

1;
