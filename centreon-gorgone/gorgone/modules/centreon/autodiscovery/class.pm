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

package gorgone::modules::centreon::autodiscovery::class;

use base qw(gorgone::class::module);

use strict;
use warnings;
use gorgone::standard::library;
use gorgone::class::sqlquery;
use ZMQ::LibZMQ4;
use ZMQ::Constants qw(:all);
use JSON::XS;
use Time::HiRes;
use POSIX qw(strftime);
use Digest::MD5 qw(md5_hex);

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

    $connector->{check_interval} = (defined($options{config}->{check_interval}) &&
        $options{config}->{check_interval} =~ /(\d+)/) ? $1 : 15;
    $connector->{sync_wait} = (defined($options{config}->{sync_wait})) ?
        $options{config}->{sync_wait} : 1_000_000;
    $connector->{last_check_time} = -1;

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
    $self->{logger}->writeLogInfo("[autodiscovery] $$ Receiving order to stop...");
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
    
    return if (!$self->is_module_installed());

    $options{token} = $self->generate_token() if (!defined($options{token}));
    my $token = 'autodiscovery_' . $self->generate_token(length => 8);
    
    # Scheduled
    my $query = "UPDATE mod_host_disco_job " . 
        "SET status = '0', duration = '0', discovered_items = '0', message = 'Scheduled' " .
        "WHERE id = '" . $options{data}->{content}->{job_id} . "'";
    my $status = $self->{class_object_centreon}->transaction_query(request => $query);
    if ($status == -1) {
        $self->{logger}->writeLogError('[autodiscovery] Failed to update job status');
        return 1;
    }

    # Retrieve uuid attributes
    my $result;
    $query = "SELECT uuid_attributes " .
        "FROM mod_host_disco_provider mhdp " .
        "JOIN mod_host_disco_job mhdj " .
        "WHERE mhdj.provider_id = mhdp.id AND mhdj.id = '" . $options{data}->{content}->{job_id} . "'";
    ($status, $result) = $self->{class_object_centreon}->custom_execute(request => $query, mode => 2);
    if ($status == -1) {
        $self->{logger}->writeLogError('[autodiscovery] Failed to retrieve uuid attributes');
        return 1;
    }
    
    my $uuid_attributes = $result->[0]->[0];    

    if ($options{data}->{content}->{execution_mode} == 0) {
        # Execute immediately
        $self->action_launchdiscovery(
            data => {
                content => {
                    target => $options{data}->{content}->{target},
                    command => $options{data}->{content}->{command},
                    timeout => $options{data}->{content}->{timeout},
                    job_id => $options{data}->{content}->{job_id},
                    uuid_attributes => $uuid_attributes,
                    token => $token
                }
            }
        );
    } else {
        # Schedule with cron
        $self->{logger}->writeLogInfo("[autodiscovery] Add cron '" . $token . "' for job '" . $options{data}->{content}->{job_id} . "'");
        my $definition = {
            id => $token,
            target => '1',
            timespec => $options{data}->{content}->{timespec},
            action => 'LAUNCHDISCOVERY',
            parameters =>  {
                target => $options{data}->{content}->{target},
                command => $options{data}->{content}->{command},
                timeout => $options{data}->{content}->{timeout},
                job_id => $options{data}->{content}->{job_id},
                uuid_attributes => $uuid_attributes,
                token => $token
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
    }

    $self->send_log(
        code => $self->ACTION_FINISH_OK,
        token => $options{token},
        data => {
            id => $token
        }
    );
    
    return 0;
}

sub action_launchdiscovery {
    my ($self, %options) = @_;
    
    return if (!$self->is_module_installed());

    $self->{logger}->writeLogInfo("[autodiscovery] Launching discovery for job '" . $options{data}->{content}->{job_id} . "'");

    $self->send_internal_action(
        action => 'COMMAND',
        target => $options{data}->{content}->{target},
        token => $options{data}->{content}->{token},
        data => {
            content => [
                {
                    instant => 1,
                    command => $options{data}->{content}->{command},
                    timeout => $options{data}->{content}->{timeout},
                    metadata => {
                        job_id => $options{data}->{content}->{job_id},
                        uuid_attributes => $options{data}->{content}->{uuid_attributes},
                        source => 'autodiscovery'
                    },
                }
            ]
        }
    );

    # Running
    my $query = "UPDATE mod_host_disco_job " .
        "SET status = '3', duration = '0', discovered_items = '0', " .
        "creation_date = '" . strftime("%F %H:%M:%S", localtime) . "', " .
        "token = '" . $options{data}->{content}->{token} . "', message = 'Running' " .
        "WHERE id = '" . $options{data}->{content}->{job_id} . "'";
    my $status = $self->{class_object_centreon}->transaction_query(request => $query);
    if ($status == -1) {
        $self->{logger}->writeLogError('[autodiscovery] Failed to update job status');
        return 1;
    }
}

sub action_getdiscoveryresults {
    my ($self, %options) = @_;
    
    return if (!$self->is_module_installed());
    
    # List running jobs
    my ($status, $data) = $self->{class_object_centreon}->custom_execute(
        request => "SELECT id, monitoring_server_id, token FROM mod_host_disco_job WHERE status = '3'",
        mode => 1,
        keys => 'id'
    );

    # Sync logs and try to retrieve results for each jobs
    foreach my $job_id (keys %{$data}) {
        $self->{logger}->writeLogDebug("[autodiscovery] Get logs results for job '" . $job_id . "'");

        $self->send_internal_action(
            target => $data->{$job_id}->{monitoring_server_id},
            action => 'GETLOG',
        );

        Time::HiRes::usleep($self->{sync_wait});

        $self->send_internal_action(
            action => 'GETLOG',
            data => {
                token => $data->{$job_id}->{token},
                limit => 4
            }
        );
    }
    
    return 0;
}

sub action_updatediscoveryresults {
    my ($self, %options) = @_;
    
    return if (!$self->is_module_installed());

    return if (!defined($options{data}->{data}->{action}) || $options{data}->{data}->{action} ne "getlog" ||
        !defined($options{data}->{data}->{result}) || scalar(@{$options{data}->{data}->{result}} == 0));

    my ($exit_code, $output, $job_id, $token, $uuid_attributes) = -1, undef, undef, undef, undef;

    foreach my $message (@{$options{data}->{data}->{result}}) {
        if ($message->{code} == 2) {
            my $data = JSON::XS->new->utf8->decode($message->{data});
            next if (!defined($data->{result}->{exit_code}) || !defined($data->{metadata}->{job_id}) ||
                !defined($data->{metadata}->{source}) || $data->{metadata}->{source} ne 'autodiscovery');

            $self->{logger}->writeLogInfo("[autodiscovery] Found result for job '" . $data->{metadata}->{job_id} . "'");

            $exit_code = $data->{result}->{exit_code};
            $output = (defined($data->{result}->{stderr}) && $data->{result}->{stderr} ne '') ?
                $data->{result}->{stderr} : $data->{result}->{stdout};
            $job_id = $data->{metadata}->{job_id};
            $token = $message->{token};
            $uuid_attributes = JSON::XS->new->utf8->decode($data->{metadata}->{uuid_attributes});
        } elsif ($message->{code} == 1) {
            my $data = JSON::XS->new->utf8->decode($message->{data});

            $self->{logger}->writeLogInfo("[autodiscovery] Found result for token '" . $message->{token} . "'");

            $output = $data->{message};
            $token = $message->{token};
        }
    }

    if ($exit_code == 0 && defined($job_id)) {
        my $result;
        eval {
            $result = JSON::XS->new->utf8->decode($output);
        };
        if ($@) {
            # Failed
            my $query = "UPDATE mod_host_disco_job " .
                "SET status = '2', duration = '0', discovered_items = '0', " .
                "message = 'Failed to decode discovery plugin response' " .
                "WHERE id = '" . $job_id ."'";
            my $status = $self->{class_object_centreon}->transaction_query(request => $query);
            if ($status == -1) {
                $self->{logger}->writeLogError('[autodiscovery] Failed to decode discovery plugin response');
                return 1;
            }
        }

        # Finished
        my $query = "UPDATE mod_host_disco_job SET status = '1', duration = '" . $result->{duration} ."', " . 
            "discovered_items = '" . $result->{discovered_items} ."', message = 'Finished' " .
            "WHERE id = '" . $job_id ."'";
        my $status = $self->{class_object_centreon}->transaction_query(request => $query);
        if ($status == -1) {
            $self->{logger}->writeLogError('[autodiscovery] Failed to update job status');
            return 1;
        }

        # Delete previous results
        $query = "DELETE FROM mod_host_disco_host WHERE job_id = '" . $job_id ."'";
        $status = $self->{class_object_centreon}->transaction_query(request => $query);
        if ($status == -1) {
            $self->{logger}->writeLogError('[autodiscovery] Failed to delete previous job results');
            return 1;
        }

        # Add new results
        my $values;
        my $append = '';
        foreach my $host (@{$result->{results}}) {
            # Generate uuid based on attributs
            my $uuid_char = '';
            foreach (@{$uuid_attributes}) {
                $uuid_char .= $host->{$_} if (defined($host->{$_}) && $host->{$_} ne '');
            }
            my $ctx = Digest::MD5->new;
            $ctx->add($uuid_char);
            my $digest = $ctx->hexdigest;
            my $uuid = substr($digest, 0, 8) . '-' . substr($digest, 8, 4) . '-' . substr($digest, 12, 4) . '-' .
                substr($digest, 16, 4) . '-' . substr($digest, 20, 12);

            $values .= $append . "('" . $job_id . "', '" . JSON::XS->new->utf8->encode($host) ."', '" . $uuid . "')";
            $append = ', '
        }

        if (defined($values) && $values ne '') {
            $query = "INSERT INTO mod_host_disco_host (job_id, discovery_result, uuid) VALUES " . $values;
            $status = $self->{class_object_centreon}->transaction_query(request => $query);
            if ($status == -1) {
                $self->{logger}->writeLogError('[autodiscovery] Failed to insert job results');
                return 1;
            }
        }
    } elsif ($exit_code > 0 && defined($job_id)) {
        # Failed
        my $query = "UPDATE mod_host_disco_job " .
            "SET status = '2', duration = '0', discovered_items = '0', " .
            "message = " . $self->{class_object_centreon}->quote(value => $output) . " " .
            "WHERE id = '" . $job_id ."'";
        my $status = $self->{class_object_centreon}->transaction_query(request => $query);
        if ($status == -1) {
            $self->{logger}->writeLogError('[autodiscovery] Failed to update job status');
            return 1;
        }
    } elsif (defined($token)) {
        # Failed
        my $query = "UPDATE mod_host_disco_job " .
            "SET status = '2', duration = '0', discovered_items = '0', " .
            "message = " . $self->{class_object_centreon}->quote(value => $output) . " " .
            "WHERE token = '" . $token ."'";
        my $status = $self->{class_object_centreon}->transaction_query(request => $query);
        if ($status == -1) {
            $self->{logger}->writeLogError('[autodiscovery] Failed to update job status');
            return 1;
        }
    }

    return 0;
}

sub is_module_installed {
    my ($self) = @_;

    my ($status, $data) = $self->{class_object_centreon}->custom_execute(
        request => "SELECT id FROM modules_informations WHERE name = 'centreon-autodiscovery-server'",
        mode => 2
    );

    (defined($data->[0]) && scalar($data->[0]) > 0) ? return 1 : return 0;
}

sub event {
    while (1) {
        my $message = gorgone::standard::library::zmq_dealer_read_message(socket => $connector->{internal_socket});
        
        $connector->{logger}->writeLogDebug("[autodiscovery] Event: $message");
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
    
    $self->{class_object_centreon} = gorgone::class::sqlquery->new(
        logger => $self->{logger},
        db_centreon => $self->{db_centreon}
    );

    # Connect internal
    $connector->{internal_socket} = gorgone::standard::library::connect_com(
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgoneautodiscovery',
        logger => $self->{logger},
        type => $self->{config_core}->{internal_com_type},
        path => $self->{config_core}->{internal_com_path}
    );
    $connector->send_internal_action(
        action => 'AUTODISCOVERYREADY',
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
            $self->{logger}->writeLogInfo("[autodiscovery] $$ has quit");
            zmq_close($connector->{internal_socket});
            exit(0);
        }

        if (time() - $self->{check_interval} > $self->{last_check_time}) {
            $self->{last_check_time} = time();
            $self->action_getdiscoveryresults();
        }
    }
}

1;
