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
use gorgone::standard::constants qw(:all);
use gorgone::modules::centreon::autodiscovery::services::discovery;
use gorgone::class::tpapi::clapi;
use gorgone::class::tpapi::centreonv2;
use gorgone::class::sqlquery;
use gorgone::class::frame;
use JSON::XS;
use Time::HiRes;
use POSIX qw(strftime);
use Digest::MD5 qw(md5_hex);
use Try::Tiny;
use EV;

use constant JOB_SCHEDULED => 0;
use constant JOB_FINISH => 1;
use constant JOB_FAILED => 2;
use constant JOB_RUNNING => 3;
use constant SAVE_RUNNING => 4;
use constant SAVE_FINISH => 5;
use constant SAVE_FAILED => 6;

use constant CRON_ADDED_NONE => 0;
use constant CRON_ADDED_OK => 1;
use constant CRON_ADDED_KO => 2;
use constant CRON_ADDED_PROGRESS => 3;

use constant EXECUTION_MODE_IMMEDIATE => 0;
use constant EXECUTION_MODE_CRON => 1;
use constant EXECUTION_MODE_PAUSE => 2;

use constant MAX_INSERT_BY_QUERY => 100;

my %handlers = (TERM => {}, HUP => {});
my ($connector);

sub new {
    my ($class, %options) = @_;
    $connector = $class->SUPER::new(%options);
    bless $connector, $class;

    $connector->{global_timeout} = (defined($options{config}->{global_timeout}) &&
        $options{config}->{global_timeout} =~ /(\d+)/) ? $1 : 300;
    $connector->{check_interval} = (defined($options{config}->{check_interval}) &&
        $options{config}->{check_interval} =~ /(\d+)/) ? $1 : 15;
    $connector->{tpapi_clapi_name} = defined($options{config}->{tpapi_clapi}) && $options{config}->{tpapi_clapi} ne '' ? $options{config}->{tpapi_clapi} : 'clapi';
    $connector->{tpapi_centreonv2_name} = defined($options{config}->{tpapi_centreonv2}) && $options{config}->{tpapi_centreonv2} ne '' ? 
        $options{config}->{tpapi_centreonv2} : 'centreonv2';

    $connector->{is_module_installed} = 0;
    $connector->{is_module_installed_check_interval} = 60;
    $connector->{is_module_installed_last_check} = -1;

    $connector->{hdisco_synced} = 0;
    $connector->{hdisco_synced_failed_time} = -1;
    $connector->{hdisco_synced_ok_time} = -1;
    $connector->{hdisco_jobs_tokens} = {};
    $connector->{hdisco_jobs_ids} = {};

    $connector->{service_discoveries} = {};

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

=pod

*******************
Host Discovery part
*******************

For cron job, we use discovery token as cron ID.

=cut

sub hdisco_is_running_job {
    my ($self, %options) = @_;

    if ($options{status} == JOB_RUNNING ||
        $options{status} == SAVE_RUNNING) {
        return 1;
    }

    return 0;
}

sub hdisco_add_cron {
    my ($self, %options) = @_;

    if (!defined($options{job}->{execution}->{parameters}->{cron_definition}) || 
        $options{job}->{execution}->{parameters}->{cron_definition} eq '') {
        return (1, "missing 'cron_definition' parameter");
    }

    $self->send_internal_action({
        action => 'ADDLISTENER',
        data => [
            {
                identity => 'gorgoneautodiscovery',
                event => 'HOSTDISCOVERYCRONLISTENER',
                token => 'cron-' . $options{discovery_token}
            }
        ]
    });

    $self->{logger}->writeLogDebug("[autodiscovery] -class- host discovery - add cron for job '" . $options{job}->{job_id} . "'");
    my $definition = {
        id => $options{discovery_token},
        timespec => $options{job}->{execution}->{parameters}->{cron_definition},
        action => 'LAUNCHHOSTDISCOVERY',
        parameters =>  {
            job_id => $options{job}->{job_id},
            timeout => (defined($options{job}->{timeout}) && $options{job}->{timeout} =~ /(\d+)/) ? $1 : $self->{global_timeout}
        }
    };
    $self->send_internal_action({
        action => 'ADDCRON',
        token => 'cron-' . $options{discovery_token},
        data => {
            content => [ $definition ]
        }
    });

    return 0;
}

sub hdisco_addupdate_job {
    my ($self, %options) = @_;
    my ($status, $message);

    my $update = 0;
    my $extra_infos = { cron_added => CRON_ADDED_NONE, listener_added => 0 };
    if (defined($self->{hdisco_jobs_ids}->{ $options{job}->{job_id} })) {
        $extra_infos = $self->{hdisco_jobs_ids}->{ $options{job}->{job_id} }->{extra_infos};
        $update = 1;
    } else {
        $self->{logger}->writeLogDebug("[autodiscovery] -class- host discovery - new job '" . $options{job}->{job_id} . "'");
        # it's running so we have a token
        if ($self->hdisco_is_running_job(status => $options{job}->{status})) {
            $extra_infos->{listener_added} = 1;
            $self->hdisco_add_joblistener(
                jobs => [
                    { job_id => $options{job}->{job_id}, target => $options{job}->{target}, token => $options{job}->{token} }
                ]
            );
        }
    }

    # cron changed: we remove old definition
    # right now: can be immediate or schedule (not both)
    if ($update == 1 &&
        ($self->{hdisco_jobs_ids}->{ $options{job}->{job_id} }->{execution}->{mode} == EXECUTION_MODE_IMMEDIATE || 
         (defined($self->{hdisco_jobs_ids}->{ $options{job}->{job_id} }->{execution}->{parameters}->{cron_definition}) &&
          defined($options{job}->{execution}->{parameters}->{cron_definition}) &&
          $self->{hdisco_jobs_ids}->{ $options{job}->{job_id} }->{execution}->{parameters}->{cron_definition} ne $options{job}->{execution}->{parameters}->{cron_definition}
         )
        )
    ) {
        $self->hdisco_delete_cron(discovery_token => $options{job}->{token});
        $extra_infos->{cron_added} = CRON_ADDED_NONE;
    }

    $self->{hdisco_jobs_ids}->{ $options{job}->{job_id} } = $options{job};
    $self->{hdisco_jobs_ids}->{ $options{job}->{job_id} }->{extra_infos} = $extra_infos;
    if (!defined($options{job}->{token})) {
        my $discovery_token = 'discovery_' . $options{job}->{job_id} . '_' . $self->generate_token(length => 4);
        if ($self->update_job_information(
            values => {
                token => $discovery_token
            },
            where_clause => [
                { id => $options{job}->{job_id} }
            ]
        ) == -1) {
            return (1, 'cannot add discovery token'); 
        }

        $self->{hdisco_jobs_ids}->{ $options{job}->{job_id} }->{token} = $discovery_token;
        $options{job}->{token} = $discovery_token;
    }

    if (defined($options{job}->{token})) {
        $self->{hdisco_jobs_tokens}->{ $options{job}->{token} } = $options{job}->{job_id};
    }

    if ($self->{hdisco_jobs_ids}->{ $options{job}->{job_id} }->{execution}->{mode} == EXECUTION_MODE_CRON &&
        ($extra_infos->{cron_added} == CRON_ADDED_NONE || $extra_infos->{cron_added} == CRON_ADDED_KO)
    ) {
        ($status, $message) = $self->hdisco_add_cron(
            job => $options{job},
            discovery_token => $options{job}->{token}
        );
        return ($status, $message) if ($status);
        $self->{hdisco_jobs_ids}->{ $options{job}->{job_id} }->{extra_infos}->{cron_added} = CRON_ADDED_PROGRESS;
    }

    return 0;
}

sub hdisco_sync {
    my ($self, %options) = @_;

    return if ($self->{is_module_installed} == 0);
    return if ($self->{hdisco_synced} == 0 && (time() - $self->{hdisco_synced_failed_time}) < 60);
    return if ($self->{hdisco_synced} == 1 && (time() - $self->{hdisco_synced_ok_time}) < 600);

    $self->{logger}->writeLogInfo('[autodiscovery] -class- host discovery - sync started');
    my ($status, $results, $message);

    $self->{hdisco_synced} = 0;
    ($status, $results) = $self->{tpapi_centreonv2}->get_scheduling_jobs();
    if ($status != 0) {
        $self->{hdisco_synced_failed_time} = time();
        $self->{logger}->writeLogError('[autodiscovery] -class- host discovery - cannot get host discovery jobs - ' . $self->{tpapi_centreonv2}->error());
        return ;
    }

    my $jobs = {};
    foreach my $job (@{$results->{result}}) {
        ($status, $message) = $self->hdisco_addupdate_job(job => $job);
        if ($status) {
             $self->{logger}->writeLogError('[autodiscovery] -class- host discovery - addupdate job - ' . $message);
        }

        $jobs->{ $job->{job_id} } = 1;
    }

    foreach my $job_id (keys %{$self->{hdisco_jobs_ids}}) {
        next if (defined($jobs->{$job_id}));

        $self->{logger}->writeLogDebug("[autodiscovery] -class- host discovery - delete job '" . $job_id . "'");
        if (defined($self->{hdisco_jobs_ids}->{$job_id}->{token})) {
            $self->hdisco_delete_cron(discovery_token => $self->{hdisco_jobs_ids}->{$job_id}->{token});
            delete $self->{hdisco_jobs_tokens}->{ $self->{hdisco_jobs_ids}->{$job_id}->{token} };
        }
        delete $self->{hdisco_jobs_ids}->{$job_id};
    }

    $self->{hdisco_synced_ok_time} = time();
    $self->{hdisco_synced} = 1;
}

sub get_host_job {
    my ($self, %options) = @_;

    my ($status, $results) = $self->{tpapi_centreonv2}->get_scheduling_jobs(search => '{"id": ' . $options{job_id} . '}');
    if ($status != 0) {
        return (1, "cannot get host discovery job '$options{job_id}' - " . $self->{tpapi_centreonv2}->error());
    }

    my $job;
    foreach my $entry (@{$results->{result}}) {
        if ($entry->{job_id} == $options{job_id}) {
            $job = $entry;
            last;
        }
    }

    return (0, 'ok', $job);
}

sub hdisco_delete_cron {
    my ($self, %options) = @_;

    return if (!defined($self->{hdisco_jobs_tokens}->{ $options{discovery_token} }));
    my $job_id = $self->{hdisco_jobs_tokens}->{ $options{discovery_token} };
    return if (
        $self->{hdisco_jobs_ids}->{$job_id}->{extra_infos}->{cron_added} == CRON_ADDED_NONE ||
        $self->{hdisco_jobs_ids}->{$job_id}->{extra_infos}->{cron_added} == CRON_ADDED_KO
    );

    $self->{logger}->writeLogInfo("[autodiscovery] -class- host discovery - delete job '" . $job_id . "'");

    $self->send_internal_action({
        action => 'DELETECRON',
        token => $options{token},
        data => {   
            variables => [ $options{discovery_token} ]
        }
    });
}

sub action_addhostdiscoveryjob {
    my ($self, %options) = @_;

    $options{token} = $self->generate_token() if (!defined($options{token}));
    if (!$self->is_hdisco_synced()) {
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => {
                message => 'host discovery synchronization issue'
            }
        );
        return ;
    }

    my $data = $options{frame}->getData();

    my ($status, $message, $job);
    ($status, $message, $job) = $self->get_host_job(job_id => $data->{content}->{job_id});
    if ($status != 0) {
        $self->{logger}->writeLogError("[autodiscovery] -class- host discovery - cannot get host discovery job '$data->{content}->{job_id}' - " . $self->{tpapi_centreonv2}->error());
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => {
                message => "cannot get job '$data->{content}->{job_id}'"
            }
        );
        return 1;
    }

    if (!defined($job)) {
        $self->{logger}->writeLogError("[autodiscovery] -class- host discovery - cannot get host discovery job '$data->{content}->{job_id}' - " . $self->{tpapi_centreonv2}->error());
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => {
                message => "cannot get job '$data->{content}->{job_id}'"
            }
        );
        return 1;
    }

    $job->{timeout} = $data->{content}->{timeout};
    ($status, $message) = $self->hdisco_addupdate_job(job => $job);
    if ($status) {
        $self->{logger}->writeLogError("[autodiscovery] -class- host discovery - add job '$data->{content}->{job_id}' - $message");
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => {
                message => "add job '$data->{content}->{job_id}' - $message"
            }
        );
        return 1;
    }

    # Launch a immediate job.
    if ($self->{hdisco_jobs_ids}->{ $data->{content}->{job_id} }->{execution}->{mode} == EXECUTION_MODE_IMMEDIATE) {
        ($status, $message) = $self->launchhostdiscovery(
            job_id => $data->{content}->{job_id},
            timeout => $data->{content}->{timeout},
            source => 'immediate'
        );
        if ($status) {
            $self->send_log(
                code => GORGONE_ACTION_FINISH_KO,
                token => $options{token},
                data => {
                    message => "launch issue - $message"
                }
            );
            return 1;
        }
    }

    $self->send_log(
        code => GORGONE_ACTION_FINISH_OK,
        token => $options{token},
        data => {
            message => 'job ' . $data->{content}->{job_id} . ' added'
        }
    );
    
    return 0;
}

sub launchhostdiscovery {
    my ($self, %options) = @_;
    
    return (1, 'host discovery sync not done') if (!$self->is_hdisco_synced());

    my $job_id = $options{job_id};

    if (!defined($job_id) || !defined($self->{hdisco_jobs_ids}->{$job_id})) {
        return (1, 'trying to launch discovery for inexistant job');
    }
    if ($self->hdisco_is_running_job(status => $self->{hdisco_jobs_ids}->{$job_id}->{status})) {
        return (1, 'job is already running');
    }
    if ($self->{hdisco_jobs_ids}->{$job_id}->{execution}->{mode} == EXECUTION_MODE_PAUSE && $options{source} eq 'cron') {
        return (0, "job '$job_id' is paused");
    }

    $self->{logger}->writeLogInfo("[autodiscovery] -class- host discovery - launching discovery for job '" . $job_id . "'");

    # Running
    if ($self->update_job_information(
        values => {
            status => JOB_RUNNING,
            message => 'Running',
            last_execution => strftime("%F %H:%M:%S", localtime),
            duration => 0,
            discovered_items => 0
        },
        where_clause => [
            {
                id => $job_id
            }
        ]
    ) == -1) {
        return (1, 'cannot update job status');
    }
    $self->{hdisco_jobs_ids}->{$job_id}->{status} = JOB_RUNNING;
    my $timeout = (defined($options{timeout}) && $options{timeout} =~ /(\d+)/) ? $1 : $self->{global_timeout};

    $self->send_internal_action({
        action => 'ADDLISTENER',
        data => [
            {
                identity => 'gorgoneautodiscovery',
                event => 'HOSTDISCOVERYJOBLISTENER',
                target => $self->{hdisco_jobs_ids}->{$job_id}->{target},
                token => $self->{hdisco_jobs_ids}->{$job_id}->{token},
                timeout => $timeout + $self->{check_interval} + 15,
                log_pace => $self->{check_interval}
            }
        ]
    });

    $self->send_internal_action({
        action => 'COMMAND',
        target => $self->{hdisco_jobs_ids}->{$job_id}->{target},
        token => $self->{hdisco_jobs_ids}->{$job_id}->{token},
        data => {
            instant => 1,
            content => [
                {
                    command => $self->{hdisco_jobs_ids}->{$job_id}->{command_line},
                    timeout => $timeout,
                    metadata => {
                        job_id => $job_id,
                        source => 'autodiscovery-host-job-discovery'
                    }
                }
            ]
        }
    });

    return (0, "job '$job_id' launched");
}

sub action_launchhostdiscovery {
    my ($self, %options) = @_;

    $options{token} = $self->generate_token() if (!defined($options{token}));
    if (!$self->is_hdisco_synced()) {
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => {
                message => 'host discovery synchronization issue'
            }
        );
        return ;
    }

    my $data = $options{frame}->getData();

    my ($job_id, $timeout, $source);
    if (defined($data->{variables}->[0]) &&
        defined($data->{variables}->[1]) && $data->{variables}->[1] eq 'schedule') {
        $job_id = $data->{variables}->[0];
        $source = 'immediate';
    } elsif (defined($data->{content}->{job_id})) {
        $job_id = $data->{content}->{job_id};
        $timeout = $data->{content}->{timeout};
        $source = 'cron';
    }

    my ($status, $message, $job);
    ($status, $message, $job) = $self->get_host_job(job_id => $job_id);
    if ($status != 0) {
        $self->{logger}->writeLogError("[autodiscovery] -class- host discovery - cannot get host discovery job '$job_id' - " . $self->{tpapi_centreonv2}->error());
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => {
                message => "cannot get job '$job_id'"
            }
        );
        return 1;
    }

    if (!defined($job)) {
        $self->{logger}->writeLogError("[autodiscovery] -class- host discovery - cannot get host discovery job '$job_id' - " . $self->{tpapi_centreonv2}->error());
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => {
                message => "cannot get job '$job_id'"
            }
        );
        return 1;
    }

    ($status, $message) = $self->hdisco_addupdate_job(job => $job);
    if ($status) {
        $self->{logger}->writeLogError("[autodiscovery] -class- host discovery - add job '$job_id' - $message");
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => {
                message => "add job '$job_id' - $message"
            }
        );
        return 1;
    }

    ($status, $message) = $self->launchhostdiscovery(
        job_id => $job_id,
        timeout => $timeout,
        source => $source
    );
    if ($status) {
        $self->{logger}->writeLogError("[autodiscovery] -class- host discovery - launch discovery job '$job_id' - $message");
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            instant => 1,
            data => {
                message => $message
            }
        );
        return 1;
    }

    $self->send_log(
        code => GORGONE_ACTION_FINISH_OK,
        token => $options{token},
        instant => 1,
        data => {
            message => $message
        }
    );
}

sub discovery_postcommand_result {
    my ($self, %options) = @_;

    my $data = $options{frame}->getData();

    return 1 if (!defined($data->{data}->{metadata}->{job_id}));

    my $job_id = $data->{data}->{metadata}->{job_id};
    if (!defined($self->{hdisco_jobs_ids}->{$job_id})) {
        $self->{logger}->writeLogError("[autodiscovery] -class- host discovery - found result for inexistant job '" . $job_id . "'");
        return 1;
    }

    my $exit_code = $data->{data}->{result}->{exit_code};
    my $output = (defined($data->{data}->{result}->{stderr}) && $data->{data}->{result}->{stderr} ne '') ?
        $data->{data}->{result}->{stderr} : $data->{data}->{result}->{stdout};

    if ($exit_code != 0) {
        $self->{logger}->writeLogError("[autodiscovery] -class- host discovery - execute discovery postcommand failed job '$job_id'");
        $self->update_job_status(
            job_id => $job_id,
            status => SAVE_FAILED,
            message => $output
        );
        return 1;
    }

    $self->{logger}->writeLogDebug("[autodiscovery] -class- host discovery - finished discovery postcommand job '$job_id'");
    $self->update_job_status(
        job_id => $job_id,
        status => SAVE_FINISH,
        message => 'Finished'
    );
}

sub discovery_add_host_result {
    my ($self, %options) = @_;

    if ($options{builder}->{num_lines} == MAX_INSERT_BY_QUERY) {
        my ($status) = $self->{class_object_centreon}->custom_execute(
            request => $options{builder}->{query} . $options{builder}->{values},
            bind_values => $options{builder}->{bind_values}
        );
        if ($status == -1) {
            $self->{logger}->writeLogError("[autodiscovery] -class- host discovery - failed to insert job '$options{job_id}' results");
            $self->update_job_status(
                job_id => $options{job_id},
                status => JOB_FAILED,
                message => 'Failed to insert job results'
            );
            return 1;
        }
        $options{builder}->{num_lines} = 0;
        $options{builder}->{values} = '';
        $options{builder}->{append} = '';
        $options{builder}->{bind_values} = ();
    }

    # Generate uuid based on attributs
    my $uuid_char = '';
    foreach (@{$options{uuid_parameters}}) {
        $uuid_char .= $options{host}->{$_} if (defined($options{host}->{$_}) && $options{host}->{$_} ne '');
    }
    my $ctx = Digest::MD5->new;
    $ctx->add($uuid_char);
    my $digest = $ctx->hexdigest;
    my $uuid = substr($digest, 0, 8) . '-' . substr($digest, 8, 4) . '-' . substr($digest, 12, 4) . '-' .
        substr($digest, 16, 4) . '-' . substr($digest, 20, 12);
    my $encoded_host = JSON::XS->new->encode($options{host});

    # Build bulk insert
    $options{builder}->{values} .= $options{builder}->{append} . '(?, ?, ?)';
    $options{builder}->{append} = ', ';
    push @{$options{builder}->{bind_values}}, $options{job_id}, $encoded_host, $uuid;
    $options{builder}->{num_lines}++;
    $options{builder}->{total_lines}++;

    return 0;
}

sub discovery_command_result {
    my ($self, %options) = @_;

=pod
    use Devel::Size;
    print "frame = " . (Devel::Size::total_size($options{frame}) / 1024 / 1024) . "==\n";

    my $data = $options{frame}->getData();
    print "data = " . (Devel::Size::total_size($data) / 1024 / 1024) . "==\n";

    my $frame = $options{frame}->getFrame();
    print "frame data = " . (Devel::Size::total_size($frame) / 1024 / 1024) . "==\n";

    my $raw = $options{frame}->getRawData();
    print "raw data = " . (Devel::Size::total_size($raw) / 1024 / 1024) . "==\n";

    return 1;
=cut

    my $data = $options{frame}->getData();

    return 1 if (!defined($data->{data}->{metadata}->{job_id}));

    my $job_id = $data->{data}->{metadata}->{job_id};
    if (!defined($self->{hdisco_jobs_ids}->{$job_id})) {
        $self->{logger}->writeLogError("[autodiscovery] -class- host discovery - found result for inexistant job '" . $job_id . "'");
        return 1;
    }

    $self->{logger}->writeLogInfo("[autodiscovery] -class- host discovery - found result for job '" . $job_id . "'");
    my $uuid_parameters = $self->{hdisco_jobs_ids}->{$job_id}->{uuid_parameters};
    my $exit_code = $data->{data}->{result}->{exit_code};

    if ($exit_code != 0) {
        $self->{logger}->writeLogError("[autodiscovery] -class- host discovery - execute discovery plugin failed job '$job_id'");
        $self->update_job_status(
            job_id => $job_id,
            status => JOB_FAILED,
            message => (defined($data->{data}->{result}->{stderr}) && $data->{data}->{result}->{stderr} ne '') ?
                $data->{data}->{result}->{stderr} : $data->{data}->{result}->{stdout}
        );
        return 1;
    }

    # Delete previous results
    my $query = "DELETE FROM mod_host_disco_host WHERE job_id = ?";
    my ($status) = $self->{class_object_centreon}->custom_execute(request => $query, bind_values => [$job_id]);
    if ($status == -1) {
        $self->{logger}->writeLogError("[autodiscovery] -class- host discovery - failed to delete previous job '$job_id' results");
        $self->update_job_status(
            job_id => $job_id,
            status => JOB_FAILED,
            message => 'Failed to delete previous job results'
        );
        return 1;
    }

    # Add new results
    my $builder = {
        query => "INSERT INTO mod_host_disco_host (job_id, discovery_result, uuid) VALUES ",
        num_lines => 0,
        total_lines => 0,
        values => '',
        append => '',
        bind_values => []
    };
    my $duration = 0;

    try {
        my $json = JSON::XS->new();
        $json->incr_parse($data->{data}->{result}->{stdout});
        while (my $obj = $json->incr_parse()) {
            if (ref($obj) eq 'HASH') {
                foreach my $host (@{$obj->{results}}) {
                    my $rv = $self->discovery_add_host_result(host => $host, job_id => $job_id, uuid_parameters => $uuid_parameters, builder => $builder);
                    return 1 if ($rv);
                }
                $duration = $obj->{duration};
            } elsif (ref($obj) eq 'ARRAY') {
                foreach my $host (@$obj) {
                    my $rv = $self->discovery_add_host_result(host => $host, job_id => $job_id, uuid_parameters => $uuid_parameters, builder => $builder);
                    return 1 if ($rv);
                }
            }
        }
    } catch {
        $self->{logger}->writeLogError("[autodiscovery] -class- host discovery - failed to decode discovery plugin response job '$job_id'");
        $self->update_job_status(
            job_id => $job_id,
            status => JOB_FAILED,
            message => 'Failed to decode discovery plugin response'
        );
        return 1;
    };

    if ($builder->{values} ne '') {
        ($status) = $self->{class_object_centreon}->custom_execute(request => $builder->{query} . $builder->{values}, bind_values => $builder->{bind_values});
        if ($status == -1) {
            $self->{logger}->writeLogError("[autodiscovery] -class- host discovery - failed to insert job '$job_id' results");
            $self->update_job_status(
                job_id => $job_id,
                status => JOB_FAILED,
                message => 'Failed to insert job results'
            );
            return 1;
        }
    }

    if (defined($self->{hdisco_jobs_ids}->{$job_id}->{post_execution}->{commands}) &&
        scalar(@{$self->{hdisco_jobs_ids}->{$job_id}->{post_execution}->{commands}}) > 0) {
        $self->{logger}->writeLogDebug("[autodiscovery] -class- host discovery - execute post command job '$job_id'");
        my $post_command = $self->{hdisco_jobs_ids}->{$job_id}->{post_execution}->{commands}->[0];

        $self->send_internal_action({
            action => $post_command->{action},
            token => $self->{hdisco_jobs_ids}->{$job_id}->{token},
            data => {
                instant => 1,
                content => [
                    {
                        command => $post_command->{command_line} . ' --token=' . $self->{tpapi_centreonv2}->get_token(),
                        metadata => {
                            job_id => $job_id,
                            source => 'autodiscovery-host-job-postcommand'
                        }
                    }
                ]
            }
        });
    }
    
    $self->{logger}->writeLogDebug("[autodiscovery] -class- host discovery - finished discovery command job '$job_id'");
    $self->update_job_status(
        job_id => $job_id,
        status => JOB_FINISH,
        message => 'Finished',
        duration => $duration,
        discovered_items => $builder->{total_lines}
    );

    return 0;
}

sub action_deletehostdiscoveryjob {
    my ($self, %options) = @_;

    #  delete is call when it's in pause (execution_mode 2).
    #  in fact, we do a curl to sync. If don't exist in database, we remove it. otherwise we do nothing
    $options{token} = $self->generate_token() if (!defined($options{token}));
    if (!$self->is_hdisco_synced()) {
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => {
                message => 'host discovery synchronization issue'
            }
        );
        return ;
    }

    my $data = $options{frame}->getData();

    my $discovery_token = $data->{variables}->[0];
    my $job_id = (defined($discovery_token) && defined($self->{hdisco_jobs_tokens}->{$discovery_token})) ? 
        $self->{hdisco_jobs_tokens}->{$discovery_token} : undef;
    if (!defined($discovery_token) || $discovery_token eq '') {
        $self->{logger}->writeLogError("[autodiscovery] -class- host discovery - missing ':token' variable to delete discovery");
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'missing discovery token' }
        );
        return 1;
    }

    my ($status, $message, $job);
    ($status, $message, $job) = $self->get_host_job(job_id => $job_id);
    if ($status != 0) {
        $self->{logger}->writeLogError("[autodiscovery] -class- host discovery - cannot get host discovery job '$job_id' - " . $self->{tpapi_centreonv2}->error());
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => {
                message => "cannot get job '$job_id'"
            }
        );
        return 1;
    }

    if (!defined($job)) {
        $self->{logger}->writeLogDebug("[autodiscovery] -class- host discovery - delete job '" . $job_id . "'");
        if (defined($self->{hdisco_jobs_ids}->{$job_id}->{token})) {
            $self->hdisco_delete_cron(discovery_token => $discovery_token);
            delete $self->{hdisco_jobs_tokens}->{$discovery_token};
        }
        delete $self->{hdisco_jobs_ids}->{$job_id};
    } else {
        $self->hdisco_addupdate_job(job => $job);
    }

    $self->send_log(
        code => GORGONE_ACTION_FINISH_OK,
        token => $options{token},
        data => { message => 'job ' . $discovery_token . ' deleted' }
    );
    
    return 0;
}

sub update_job_status {
    my ($self, %options) = @_;

    my $values = { status => $options{status}, message => $options{message} };
    $values->{duration} = $options{duration} if (defined($options{duration}));
    $values->{discovered_items} = $options{discovered_items} if (defined($options{discovered_items}));
    $self->update_job_information(
        values => $values,
        where_clause => [
            {
                id => $options{job_id}
            }
        ]
    );
    $self->{hdisco_jobs_ids}->{$options{job_id}}->{status} = $options{status};
}

sub update_job_information {
    my ($self, %options) = @_;

    return 1 if (!defined($options{where_clause}) || ref($options{where_clause}) ne 'ARRAY' || scalar($options{where_clause}) < 1);
    return 1 if (!defined($options{values}) || ref($options{values}) ne 'HASH' || !keys %{$options{values}});
    
    my $query = "UPDATE mod_host_disco_job SET ";
    my @bind_values = ();
    my $append = '';
    foreach (keys %{$options{values}}) {
        $query .= $append . $_ . ' = ?';
        $append = ', ';
        push @bind_values, $options{values}->{$_};
    }

    $query .= " WHERE ";
    $append = '';
    foreach (@{$options{where_clause}}) {
        my ($key, $value) = each %{$_};
        $query .= $append . $key . " = ?";
        $append = 'AND ';
        push @bind_values, $value;
    }

    my ($status) = $self->{class_object_centreon}->custom_execute(request => $query, bind_values => \@bind_values);
    if ($status == -1) {
        $self->{logger}->writeLogError('[autodiscovery] Failed to update job information');
        return -1;
    }

    return 0;
}

sub action_hostdiscoveryjoblistener {
    my ($self, %options) = @_;

    return 0 if (!$self->is_hdisco_synced());
    return 0 if (!defined($options{token}));
    return 0 if (!defined($self->{hdisco_jobs_tokens}->{ $options{token} }));

    my $data = $options{frame}->getData();

    my $job_id = $self->{hdisco_jobs_tokens}->{ $options{token} };
    if ($data->{code} == GORGONE_MODULE_ACTION_COMMAND_RESULT && 
        $data->{data}->{metadata}->{source} eq 'autodiscovery-host-job-discovery') {
        $self->discovery_command_result(%options);
        return 1;
    }
    #if ($data->{code} == GORGONE_MODULE_ACTION_COMMAND_RESULT && 
    #    $data->{data}->{metadata}->{source} eq 'autodiscovery-host-job-postcommand') {
    #    $self->discovery_postcommand_result(%options);
    #    return 1;
    #}

    # Can happen if we have a execution command timeout
    my $message = defined($data->{data}->{result}->{stdout}) ? $data->{data}->{result}->{stdout} : $data->{data}->{message};
    $message = $data->{message} if (!defined($message));
    if ($data->{code} == GORGONE_ACTION_FINISH_KO) {
        $self->{hdisco_jobs_ids}->{$job_id}->{status} = JOB_FAILED;
        $self->update_job_information(
            values => {
                status => JOB_FAILED,
                message => $message,
                duration => 0,
                discovered_items => 0
            },
            where_clause => [
                {
                    id => $job_id
                }
            ]
        );
        return 1;
    }

    return 1;
}

sub action_hostdiscoverycronlistener {
    my ($self, %options) = @_;

    return 0 if (!defined($options{token}) || $options{token} !~ /^cron-(.*)/);
    my $discovery_token = $1;

    return 0 if (!defined($self->{hdisco_jobs_tokens}->{ $discovery_token }));

    my $data = $options{frame}->getData();

    my $job_id = $self->{hdisco_jobs_tokens}->{ $discovery_token };
    if ($data->{code} == GORGONE_ACTION_FINISH_KO) {
        $self->{logger}->writeLogError("[autodiscovery] -class- host discovery - job '" . $job_id . "' add cron error");
        $self->{hdisco_jobs_ids}->{$job_id}->{extra_infos}->{cron_added} = CRON_ADDED_KO;
    } elsif ($data->{code} == GORGONE_ACTION_FINISH_OK) {
        $self->{logger}->writeLogInfo("[autodiscovery] -class- host discovery - job '" . $job_id . "' add cron ok");
        $self->{hdisco_jobs_ids}->{$job_id}->{extra_infos}->{cron_added} = CRON_ADDED_OK;
    }

    return 1;
}

sub hdisco_add_joblistener {
    my ($self, %options) = @_;

    foreach (@{$options{jobs}}) {
        $self->{logger}->writeLogDebug("[autodiscovery] -class- host discovery - register listener for '" . $_->{job_id} . "'");

        $self->send_internal_action({
            action => 'ADDLISTENER',
            data => [
                {
                    identity => 'gorgoneautodiscovery',
                    event => 'HOSTDISCOVERYJOBLISTENER',
                    target => $_->{target},
                    token => $_->{token},
                    log_pace => $self->{check_interval}
                }
            ]
        });
    }

    return 0;
}

=pod

**********************
Service Discovery part
**********************

=cut

sub action_servicediscoverylistener {
    my ($self, %options) = @_;

    return 0 if (!defined($options{token}));

    # 'svc-disco-UUID-RULEID-HOSTID' . $self->{service_uuid} . '-' . $service_number . '-' . $rule_id . '-' . $host->{host_id}
    return 0 if ($options{token} !~ /^svc-disco-(.*?)-(\d+)-(\d+)/);

    my ($uuid, $rule_id, $host_id) = ($1, $2, $3);
    return 0 if (!defined($self->{service_discoveries}->{ $uuid }));

    $self->{service_discoveries}->{ $uuid }->discoverylistener(
        rule_id => $rule_id,
        host_id => $host_id,
        %options
    );

    if ($self->{service_discoveries}->{ $uuid }->is_finished()) {
        delete $self->{service_discoveries}->{ $uuid };
    }
}

sub action_launchservicediscovery {
    my ($self, %options) = @_;

    $options{token} = $self->generate_token() if (!defined($options{token}));

    $self->{service_number}++;
    my $svc_discovery = gorgone::modules::centreon::autodiscovery::services::discovery->new(
        module_id => $self->{module_id},
        logger => $self->{logger},
        tpapi_clapi => $self->{tpapi_clapi},
        internal_socket => $self->{internal_socket},
        config => $self->{config},
        config_core => $self->{config_core},
        service_number => $self->{service_number},
        class_object_centreon => $self->{class_object_centreon},
        class_object_centstorage => $self->{class_object_centstorage}
    );
    my $status = $svc_discovery->launchdiscovery(
        token => $options{token},
        frame => $options{frame}
    );
    if ($status == -1) {
        $self->send_log(
            code => GORGONE_ACTION_FINISH_KO,
            token => $options{token},
            data => { message => 'cannot launch discovery' }
        );
    } elsif ($status == 0) {
        $self->{service_discoveries}->{ $svc_discovery->get_uuid() } = $svc_discovery;
    }
}

sub is_module_installed {
    my ($self) = @_;

    return 1 if ($self->{is_module_installed} == 1);
    return 0 if ((time() - $self->{is_module_installed_check_interval}) < $self->{is_module_installed_last_check});

    $self->{logger}->writeLogDebug('[autodiscovery] -class- host discovery - check centreon module installed');
    $self->{is_module_installed_last_check} = time();

    my ($status, $results) = $self->{tpapi_centreonv2}->get_platform_versions();
    if ($status != 0) {
        $self->{logger}->writeLogError('[autodiscovery] -class- host discovery - cannot get platform versions - ' . $self->{tpapi_centreonv2}->error());
        return 0;
    }

    if (defined($results->{modules}) && ref($results->{modules}) eq 'HASH' &&
        defined($results->{modules}->{'centreon-autodiscovery-server'})) {
        $self->{logger}->writeLogDebug('[autodiscovery] -class- host discovery - module autodiscovery installed');
        $self->{is_module_installed} = 1;
    }

    return $self->{is_module_installed};
}

sub is_hdisco_synced {
    my ($self) = @_;

    return $self->{hdisco_synced} == 1 ? 1 : 0;
}

sub event {
    while (1) {
        my $frame = gorgone::class::frame->new();
        my (undef, $rv) = $connector->read_message(frame => $frame);
        last if ($rv);

        my $raw = $frame->getFrame();
        $connector->{logger}->writeLogDebug("[autodiscovery] Event: " . $$raw) if ($connector->{logger}->is_debug());
        if ($$raw =~ /^\[(.*?)\]/) {
            if ((my $method = $connector->can('action_' . lc($1)))) {
                next if ($frame->parse({ releaseFrame => 1, decode => 1 }));

                $method->($connector, token => $frame->getToken(), frame => $frame);
            }
        }
    }
}

sub periodic_exec {
    $connector->is_module_installed();
    $connector->hdisco_sync();

    if ($connector->{stop} == 1) {
        $connector->{logger}->writeLogInfo("[autodiscovery] $$ has quit");
        exit(0);
    }
}

sub run {
    my ($self, %options) = @_;

    $self->{tpapi_clapi} = gorgone::class::tpapi::clapi->new();
    $self->{tpapi_clapi}->set_configuration(
        config => $self->{tpapi}->get_configuration(name => $self->{tpapi_clapi_name})
    );
    $self->{tpapi_centreonv2} = gorgone::class::tpapi::centreonv2->new();
    my ($status) = $self->{tpapi_centreonv2}->set_configuration(
        config => $self->{tpapi}->get_configuration(name => $self->{tpapi_centreonv2_name}),
        logger => $self->{logger}
    );
    if ($status) {
        $self->{logger}->writeLogError('[autodiscovery] -class- host discovery - configure api centreonv2 - ' . $self->{tpapi_centreonv2}->error());
    }

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
    
    $self->{class_object_centreon} = gorgone::class::sqlquery->new(
        logger => $self->{logger},
        db_centreon => $self->{db_centreon}
    );
    $self->{class_object_centstorage} = gorgone::class::sqlquery->new(
        logger => $self->{logger},
        db_centreon => $self->{db_centstorage}
    );

    $self->{internal_socket} = gorgone::standard::library::connect_com(
        context => $self->{zmq_context},
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgone-autodiscovery',
        logger => $self->{logger},
        type => $self->get_core_config(name => 'internal_com_type'),
        path => $self->get_core_config(name => 'internal_com_path')
    );
    $self->send_internal_action({
        action => 'AUTODISCOVERYREADY',
        data => {}
    });

    $self->is_module_installed();
    $self->hdisco_sync();

    my $w1 = EV::timer(5, 2, \&periodic_exec);
    my $w2 = EV::io($self->{internal_socket}->get_fd(), EV::READ|EV::WRITE, \&event);
    EV::run();
}

1;
