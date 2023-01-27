#
# Copyright 2020 Centreon (http://www.centreon.com/)
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
use gorgone::standard::constants qw(:all);
use gorgone::class::sqlquery;
use gorgone::class::http::http;
use ZMQ::LibZMQ4;
use ZMQ::Constants qw(:all);
use JSON::XS;
use IO::Compress::Bzip2;
use MIME::Base64;

my %handlers = (TERM => {}, HUP => {});
my ($connector);

sub new {
    my ($class, %options) = @_;
    $connector = $class->SUPER::new(%options);
    bless $connector, $class;

    $connector->{resync_time} = (defined($options{config}->{resync_time}) && $options{config}->{resync_time} =~ /(\d+)/) ? $1 : 600;
    $connector->{thresholds_sync_time} = (defined($options{config}->{thresholds_sync_time}) && $options{config}->{thresholds_sync_time} =~ /(\d+)/) ? $1 : 28800;
    $connector->{last_resync_time} = -1;
    $connector->{saas_token} = undef;
    $connector->{saas_url} = undef;
    $connector->{proxy_url} = undef; # format http://[username:password@]server:port
    $connector->{centreon_metrics} = {};
    $connector->{unregister_metrics_centreon} = {};

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

sub http_check_error {
    my ($self, %options) = @_;

    if ($options{status} == 1) {
        $self->{logger}->writeLogError("[anomalydetection] -class- $options{endpoint} issue");
        return 1;
    }

    my $code = $self->{http}->get_code();
    if ($code !~ /$options{http_code_continue}/) {
        $self->{logger}->writeLogError("[anomalydetection] -class- $options{endpoint} issue - " . $self->{http}->get_message());
        return 1;
    }

    return 0;
}

sub get_localhost_poller {
    my ($self, %options) = @_;

    my $instance;
    foreach (keys %{$self->{pollers}}) {
        if ($self->{pollers}->{$_}->{localhost} == 1) {
            $instance = $_;
            last;
        }
    }

    return $instance;
}

sub get_poller {
    my ($self, %options) = @_;

    return $self->{pollers}->{$options{instance}};
}

sub write_file {
    my ($self, %options) = @_;

    my $fh;
    if (!open($fh, '>', $options{file})) {
        $self->{logger}->writeLogError("[anomalydetection] -class- cannot open file '" . $options{file} . "': $!");
        return 1;
    }
    print $fh $options{content};
    close($fh);
    return 0;
}

sub saas_api_request {
    my ($self, %options) = @_;

    my ($status, $payload);
    if (defined($options{payload})) {
        ($status, $payload) = $self->json_encode(argument => $options{payload});
        return 1 if ($status == 1);
    }
    my $accept = defined $options{accept} ? $options{accept} : '*/*';

    ($status, my $response) = $self->{http}->request(
        method => $options{method}, hostname => '',
        full_url => $self->{saas_url} . $options{endpoint},
        query_form_post => $payload,
        header => [
            'Accept-Type: application/json; charset=utf-8',
            'Content-Type: application/json; charset=utf-8',
            'Accept: ' . $accept,
            'x-api-key: ' . $self->{saas_token}
        ],
        proxyurl => $self->{proxy_url},
        curl_opt => ['CURLOPT_SSL_VERIFYPEER => 0']
    );
    return 1 if ($self->http_check_error(status => $status, endpoint => $options{endpoint}, http_code_continue => $options{http_code_continue}) == 1);

    ($status, my $result) = $self->json_decode(argument => $response);
    return 1 if ($status == 1);

    return (0, $result);
}

sub connection_informations {
    my ($self, %options) = @_;

    my ($status, $datas) = $self->{class_object_centreon}->custom_execute(
        request => "select `key`, `value` from options WHERE `key` IN ('saas_url', 'saas_token', 'proxy_url', 'proxy_port', 'proxy_user', 'proxy_password')",
        mode => 2
    );
    if ($status == -1) {
        $self->{logger}->writeLogError('[anomalydetection] -class- cannot get connection informations');
        return 1;
    }

    $self->{$_->[0]} = $_->[1] foreach (@$datas);

    if (!defined($self->{saas_url}) || $self->{saas_url} eq '') {
        $self->{logger}->writeLogInfo('[anomalydetection] -class- database: saas_url is not defined');
        return 1;
    }
    $self->{saas_url} =~ s/\/$//g;

    if (!defined($self->{saas_token}) || $self->{saas_token} eq '') {
        $self->{logger}->writeLogInfo('[anomalydetection] -class- database: saas_token is not defined');
        return 1;
    }

    if (defined($self->{proxy_url})) {
        if ($self->{proxy_url} eq '') {
            $self->{proxy_url} = undef;
            return 0;
        }

        $self->{proxy_url} = $self->{proxy_user} . ':' . $self->{proxy_password} . '@' . $self->{proxy_url}
            if (defined($self->{proxy_user}) && $self->{proxy_user} ne '' &&
                defined($self->{proxy_password}) && $self->{proxy_password} ne '');
        $self->{proxy_url} = $self->{proxy_url} . ':' . $self->{proxy_port}
            if (defined($self->{proxy_port}) && $self->{proxy_port} =~ /(\d+)/);
        $self->{proxy_url} = 'http://' . $self->{proxy_url};
    }

    return 0;
}

sub get_centreon_anomaly_metrics {
    my ($self, %options) = @_;

    my ($status, $datas) = $self->{class_object_centreon}->custom_execute(
        request =>
            'SELECT nagios_server_id, cfg_dir, centreonbroker_cfg_path, localhost, ' .
            'engine_start_command, engine_stop_command, engine_restart_command, engine_reload_command, ' .
            'broker_reload_command ' .
            'FROM cfg_nagios ' .
            'JOIN nagios_server ' .
            'WHERE id = nagios_server_id',
        mode => 1,
        keys => 'nagios_server_id'
    );
    if ($status == -1) {
        $self->{logger}->writeLogError('[anomalydetection] cannot get configuration for pollers');
        return 1;
    }
    $self->{pollers} = $datas;

    ($status, $datas) = $self->{class_object_centreon}->custom_execute(
        request => '
            SELECT mas.*, hsr.host_host_id as host_id, nhr.nagios_server_id as instance_id
            FROM mod_anomaly_service mas
            LEFT JOIN (host_service_relation hsr, ns_host_relation nhr) ON
                (mas.service_id = hsr.service_service_id AND hsr.host_host_id = nhr.host_host_id)
        ',
        keys => 'id',
        mode => 1
    );
    if ($status == -1) {
        $self->{logger}->writeLogError('[anomalydetection] -class- database: cannot get metrics from centreon');
        return 1;
    }

    $self->{centreon_metrics} = $datas;

    return 0;
}

sub save_centreon_previous_register {
    my ($self, %options) = @_;

    my ($query, $query_append) = ('', '');
    foreach (keys %{$self->{unregister_metrics_centreon}}) {
        $query .= $query_append .
            'UPDATE mod_anomaly_service SET' .
            ' saas_model_id = ' . $self->{class_object_centreon}->quote(value => $self->{unregister_metrics_centreon}->{$_}->{saas_model_id}) . ',' .
            ' saas_metric_id = ' . $self->{class_object_centreon}->quote(value => $self->{unregister_metrics_centreon}->{$_}->{saas_metric_id}) . ',' .
            ' saas_creation_date = ' . $self->{unregister_metrics_centreon}->{$_}->{creation_date} . ',' .
            ' saas_update_date = ' . $self->{unregister_metrics_centreon}->{$_}->{creation_date} .
            ' WHERE `id` = ' . $_;
        $query_append = ';';
    }
    if ($query ne '') {
        my $status = $self->{class_object_centreon}->transaction_query_multi(request => $query);
        if ($status == -1) {
            $self->{logger}->writeLogError('[anomalydetection] -class- database: cannot save centreon previous register');
            return 1;
        }

        foreach (keys %{$self->{unregister_metrics_centreon}}) {
            $self->{centreon_metrics}->{$_}->{saas_creation_date} = $self->{unregister_metrics_centreon}->{$_}->{creation_date};
            $self->{centreon_metrics}->{$_}->{saas_update_date} = $self->{unregister_metrics_centreon}->{$_}->{creation_date};
            $self->{centreon_metrics}->{$_}->{saas_model_id} = $self->{unregister_metrics_centreon}->{$_}->{saas_model_id};
            $self->{centreon_metrics}->{$_}->{saas_metric_id} = $self->{unregister_metrics_centreon}->{$_}->{saas_metric_id};
        }
    }

    $self->{unregister_metrics_centreon} = {};
    return 0;
}

sub saas_register_metrics {
    my ($self, %options) = @_;

    my $register_centreon_metrics = {};
    my ($query, $query_append) = ('', '');

    $self->{generate_metrics_lua} = 0;
    foreach (keys %{$self->{centreon_metrics}}) {
        # saas_creation_date is set when we need to register it
        next if (defined($self->{centreon_metrics}->{$_}->{saas_creation_date}));
        next if ($self->{centreon_metrics}->{$_}->{saas_to_delete} == 1);

        my $payload = {
            metrics => [
                {
                    name => $self->{centreon_metrics}->{$_}->{metric_name},
                    labels => {
                        host_id => "" . $self->{centreon_metrics}->{$_}->{host_id},
                        service_id => "" . $self->{centreon_metrics}->{$_}->{service_id}
                    },
                    preprocessingOptions =>  {
                        bucketize => {
                            bucketizeFunction => 'mean',
                            period => 300
                        }
                    }
                }
            ],
            algorithm => {
                type => $self->{centreon_metrics}->{$_}->{ml_model_name},
                options => {
                    period => '30d'
                }
            }
        };

        my ($status, $result) = $self->saas_api_request(
            endpoint => '/machinelearning',
            method => 'POST',
            payload => $payload,
            http_code_continue => '^2'
        );
        return 1 if ($status);

        $self->{logger}->writeLogDebug(
            "[anomalydetection] -class- saas: metric '$self->{centreon_metrics}->{$_}->{host_id}/$self->{centreon_metrics}->{$_}->{service_id}/$self->{centreon_metrics}->{$_}->{metric_name}' registered"
        );

        # {
        #    "metrics": [
        #        {
        #            "name": "system_load1",
        #            "labels": { "hostname":"srvi-monitoring" },
        #            "preprocessingOptions": {
        #                "bucketize": {
        #                    "bucketizeFunction": "mean", "period": 300
        #                }
        #            },
        #            "id": "e255db55-008b-48cd-8dfe-34cf60babd01"
        #        }
        #    ],
        #    "algorithm": {
        #        "type": "h2o",
        #        "options": { "period":"180d" }
        #    },
        #  "id":"257fc68d-3248-4c92-92a1-43c0c63d5e5e"
        # }

        $self->{generate_metrics_lua} = 1;
        $register_centreon_metrics->{$_} = {
            saas_creation_date => time(),
            saas_model_id => $result->{id},
            saas_metric_id => $result->{metrics}->[0]->{id}
        };

        $query .= $query_append .
            'UPDATE mod_anomaly_service SET' .
            ' saas_model_id = ' . $self->{class_object_centreon}->quote(value => $register_centreon_metrics->{$_}->{saas_model_id}) . ',' .
            ' saas_metric_id = ' . $self->{class_object_centreon}->quote(value => $register_centreon_metrics->{$_}->{saas_metric_id}) . ',' .
            ' saas_creation_date = ' . $register_centreon_metrics->{$_}->{saas_creation_date} . ',' .
            ' saas_update_date = ' . $register_centreon_metrics->{$_}->{saas_creation_date} .
            ' WHERE `id` = ' . $_;
        $query_append = ';';
    }

    return 0 if ($query eq '');

    my $status = $self->{class_object_centreon}->transaction_query_multi(request => $query);
    if ($status == -1) {
        $self->{unregister_metrics_centreon} = $register_centreon_metrics;
        $self->{logger}->writeLogError('[anomalydetection] -class- database: cannot update centreon register');
        return 1;
    }

    foreach (keys %$register_centreon_metrics) {
        $self->{centreon_metrics}->{$_}->{saas_creation_date} = $register_centreon_metrics->{$_}->{saas_creation_date};
        $self->{centreon_metrics}->{$_}->{saas_update_date} = $register_centreon_metrics->{$_}->{saas_creation_date};
        $self->{centreon_metrics}->{$_}->{saas_metric_id} = $register_centreon_metrics->{$_}->{saas_metric_id};
        $self->{centreon_metrics}->{$_}->{saas_model_id} = $register_centreon_metrics->{$_}->{saas_model_id};
    }

    return 0;
}

sub saas_delete_metrics {
    my ($self, %options) = @_;

    my $delete_ids = [];
    foreach (keys %{$self->{centreon_metrics}}) {
        next if ($self->{centreon_metrics}->{$_}->{saas_to_delete} == 0);

        if (defined($self->{centreon_metrics}->{$_}->{saas_model_id})) {
            my ($status, $result) = $self->saas_api_request(
                endpoint => '/machinelearning/' . $self->{centreon_metrics}->{$_}->{saas_model_id},
                method => 'DELETE',
                http_code_continue => '^(?:2|404)'
            );
            next if ($status);

            $self->{logger}->writeLogDebug(
                "[anomalydetection] -class- saas: metric '$self->{centreon_metrics}->{$_}->{service_id}/$self->{centreon_metrics}->{$_}->{metric_name}' deleted"
            );

            next if (!defined($result->{message}) ||
                $result->{message} !~ /machine learning request id is not found/i);
        }

        push @$delete_ids, $_;
    }

    return 0 if (scalar(@$delete_ids) <= 0);

    my $status = $self->{class_object_centreon}->transaction_query(
        request => 'DELETE FROM mod_anomaly_service WHERE id IN (' . join(', ', @$delete_ids) . ')'
    );
    if ($status == -1) {
        $self->{logger}->writeLogError('[anomalydetection] -class- database: cannot delete centreon saas');
        return 1;
    }

    return 0;
}

sub generate_lua_filter_file {
    my ($self, %options) = @_;

    my $data = { filters => { } };
    foreach (values %{$self->{centreon_metrics}}) {
        next if ($_->{saas_to_delete} == 1);
        next if (!defined($_->{saas_creation_date}));
        next if (!defined($_->{host_id}));

        $data->{filters}->{ $_->{host_id} } = {}
            if (!defined($data->{filters}->{ $_->{host_id} }));
        $data->{filters}->{ $_->{host_id} }->{ $_->{service_id} } = {}
            if (!defined($data->{filters}->{ $_->{host_id} }->{ $_->{service_id} }));
        $data->{filters}->{ $_->{host_id} }->{ $_->{service_id} }->{ $_->{metric_name} } = 1;
    }

    my ($status, $content) = $self->json_encode(argument => $data);
    if ($status == 1) {
        $self->{logger}->writeLogError('[anomalydetection] -class- cannot encode lua filter file');
        return 1;
    }

    my $instance = $self->get_localhost_poller();
    if ($status == 1) {
        $self->{logger}->writeLogError('[anomalydetection] -class- cannot find localhost poller');
        return 1;
    }

    my $poller = $self->get_poller(instance => $instance);
    my $file = $poller->{centreonbroker_cfg_path} . '/anomaly-detection-filters.json';
    if (! -w $poller->{centreonbroker_cfg_path}) {
        $self->{logger}->writeLogError("[anomalydetection] -class- cannot write file '" . $file . "'");
        return 1;
    }

    return 1 if ($self->write_file(file => $file, content => $content));

    $self->{logger}->writeLogDebug('[anomalydetection] -class- reload centreon-broker');

    $self->send_internal_action(
        action => 'COMMAND',
        token => $options{token},
        data => {
            content => [ { command => 'sudo ' . $poller->{broker_reload_command} } ]
        },
    );

    return 0;
}

sub saas_get_predicts {
    my ($self, %options) = @_;

    my ($query, $query_append, $status) = ('', '');
    my $engine_reload = {};
    foreach (keys %{$self->{centreon_metrics}}) {
        next if ($self->{centreon_metrics}->{$_}->{saas_to_delete} == 1);
        #next if (!defined($self->{centreon_metrics}->{$_}->{thresholds_file}) ||
        #    $self->{centreon_metrics}->{$_}->{thresholds_file} eq '');
        next if (!defined($self->{centreon_metrics}->{$_}->{saas_update_date}) ||
            $self->{centreon_metrics}->{$_}->{saas_update_date} > time() - $self->{thresholds_sync_time});

        ($status, my $result) = $self->saas_api_request(
            endpoint => '/machinelearning/' . $self->{centreon_metrics}->{$_}->{saas_model_id} . '/predicts',
            method => 'GET',
            http_code_continue => '^2',
            accept => 'application/vnd.centreon.v2+json'
        );
        next if ($status);

        $self->{logger}->writeLogDebug(
            "[anomalydetection] -class- saas: get predict metric '$self->{centreon_metrics}->{$_}->{host_id}/$self->{centreon_metrics}->{$_}->{service_id}/$self->{centreon_metrics}->{$_}->{metric_name}'"
        );

        next if (!defined($result->[0]) || !defined($result->[0]->{predict}));

        my $data = [
            {
                host_id => $self->{centreon_metrics}->{$_}->{host_id},
                service_id => $self->{centreon_metrics}->{$_}->{service_id},
                metric_name => $self->{centreon_metrics}->{$_}->{metric_name},
                predict => $result->[0]->{predict}
            }
        ];
        ($status, my $content) = $self->json_encode(argument => $data);
        next if ($status == 1);

        my $encoded_content;
        if (!IO::Compress::Bzip2::bzip2(\$content, \$encoded_content)) {
            $self->{logger}->writeLogError('[anomalydetection] -class- cannot compress content: ' . $IO::Compress::Bzip2::Bzip2Error);
            next;
        }

        $encoded_content = MIME::Base64::encode_base64($encoded_content, '');

        my $poller = $self->get_poller(instance => $self->{centreon_metrics}->{$_}->{instance_id});
        $self->send_internal_action(
            action => 'COMMAND',
            target => $self->{centreon_metrics}->{$_}->{instance_id},
            token => $options{token},
            data => {
                content => [ { command => 'mkdir -p ' . $poller->{cfg_dir} . '/anomaly/' . '; echo -n ' . $encoded_content . ' | base64 -d | bzcat -d > "' . $poller->{cfg_dir} . '/anomaly/' . $_ . '.json"' } ]
            }
        );

        $engine_reload->{ $self->{centreon_metrics}->{$_}->{instance_id} } = [] if (!defined($engine_reload->{ $self->{centreon_metrics}->{$_}->{instance_id} }));
        push @{$engine_reload->{ $self->{centreon_metrics}->{$_}->{instance_id} }}, $poller->{cfg_dir} . '/anomaly/' . $_ . '.json';

        $query .= $query_append .
            'UPDATE mod_anomaly_service SET' .
            ' saas_update_date = ' . time() .
            ' WHERE `id` = ' . $_;
        $query_append = ';';
    }

    return 0 if ($query eq '');

    foreach my $instance_id (keys %$engine_reload) {
        $self->{logger}->writeLogDebug('[anomalydetection] -class- send engine threshold files external command ' . $instance_id);
        my $contents = [];
        foreach (@{$engine_reload->{$instance_id}}) {
            push @$contents, {
                target => $instance_id,
                 command => 'EXTERNALCMD',
                  param => '[' . time() . '] NEW_THRESHOLDS_FILE;' . $_
            };
        }

        $self->send_internal_action(
            action => 'CENTREONCOMMAND',
            token => $options{token},
            data => {
                content => $contents
            }
        );
    }

    $status = $self->{class_object_centreon}->transaction_query_multi(request => $query);
    if ($status == -1) {
        $self->{logger}->writeLogError('[anomalydetection] -class- database: cannot update predicts');
        return 1;
    }

    return 0;
}

sub action_saaspredict {
    my ($self, %options) = @_;

    $self->{logger}->writeLogDebug('[anomalydetection] -class - start saaspredict');
    $options{token} = $self->generate_token() if (!defined($options{token}));
    $self->send_log(code => GORGONE_ACTION_BEGIN, token => $options{token}, data => { message => 'action saaspredict proceed' });

    $self->saas_get_predicts(token => $options{token});

    $self->{logger}->writeLogDebug('[anomalydetection] -class- finish saaspredict');
    $self->send_log(code => GORGONE_ACTION_FINISH_OK, token => $options{token}, data => { message => 'action saaspredict finished' });
    return 0;
}

sub action_saasregister {
    my ($self, %options) = @_;

    $self->{logger}->writeLogDebug('[anomalydetection] -class- start saasregister');
    $options{token} = $self->generate_token() if (!defined($options{token}));
    $self->send_log(code => GORGONE_ACTION_BEGIN, token => $options{token}, data => { message => 'action saasregister proceed' });

    if ($self->connection_informations()) {
        $self->send_log(code => GORGONE_ACTION_FINISH_KO, token => $options{token}, data => { message => 'cannot get connection informations' });
        return 1;
    }

    if ($self->save_centreon_previous_register()) {
        $self->send_log(code => GORGONE_ACTION_FINISH_KO, token => $options{token}, data => { message => 'cannot save previsous register' });
        return 1;
    }

    if ($self->get_centreon_anomaly_metrics()) {
        $self->send_log(code => GORGONE_ACTION_FINISH_KO, token => $options{token}, data => { message => 'cannot get metrics from centreon' });
        return 1;
    }

    if ($self->saas_register_metrics()) {
        $self->send_log(code => GORGONE_ACTION_FINISH_KO, token => $options{token}, data => { message => 'cannot get declare metrics in saas' });
        return 1;
    }

    if ($self->{generate_metrics_lua} == 1) {
        $self->generate_lua_filter_file(token => $options{token});
    }

    if ($self->saas_delete_metrics()) {
        $self->send_log(code => GORGONE_ACTION_FINISH_KO, token => $options{token}, data => { message => 'cannot delete metrics in saas' });
        return 1;
    }

    $self->{logger}->writeLogDebug('[anomalydetection] -class- finish saasregister');
    $self->send_log(code => GORGONE_ACTION_FINISH_OK, token => $options{token}, data => { message => 'action saasregister finished' });
    return 0;
}

sub event {
    while (1) {
        my $message = $connector->read_message();
        last if (!defined($message));

        $connector->{logger}->writeLogDebug("[anomalydetection] Event: $message");
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

sub run {
    my ($self, %options) = @_;

    $self->{db_centreon} = gorgone::class::db->new(
        dsn => $self->{config_db_centreon}->{dsn} . ';mysql_multi_statements=1',
        user => $self->{config_db_centreon}->{username},
        password => $self->{config_db_centreon}->{password},
        force => 2,
        logger => $self->{logger}
    );

    ##### Load objects #####
    $self->{class_object_centreon} = gorgone::class::sqlquery->new(logger => $self->{logger}, db_centreon => $self->{db_centreon});
    $self->{http} = gorgone::class::http::http->new(logger => $self->{logger});

    # Connect internal
    $connector->{internal_socket} = gorgone::standard::library::connect_com(
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgone-anomalydetection',
        logger => $self->{logger},
        type => $self->get_core_config(name => 'internal_com_type'),
        path => $self->get_core_config(name => 'internal_com_path')
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
        my $rev = scalar(zmq_poll($self->{poll}, 5000));
        if ($rev == 0 && $self->{stop} == 1) {
            $self->{logger}->writeLogInfo("[anomalydetection] -class- $$ has quit");
            zmq_close($connector->{internal_socket});
            exit(0);
        }

        if (time() - $self->{resync_time} > $self->{last_resync_time}) {
            $self->{last_resync_time} = time();
            $self->action_saasregister();
            $self->action_saaspredict();
        }
    }
}

1;
