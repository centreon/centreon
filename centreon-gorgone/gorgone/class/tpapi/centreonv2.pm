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

package gorgone::class::tpapi::centreonv2;

use strict;
use warnings;
use JSON::XS;

sub new {
    my ($class, %options) = @_;
    my $self  = {};
    bless $self, $class;

    $self->{is_error} = 1;
    $self->{error} = 'configuration missing';
    $self->{is_logged} = 0;

    return $self;
}

sub json_decode {
    my ($self, %options) = @_;

    my $decoded;
    eval {
        $decoded = JSON::XS->new->utf8->decode($options{content});
    };
    if ($@) {
        $self->{is_error} = 1;
        $self->{error} = "cannot decode json response: $@";
        return undef;
    }

    return $decoded;
}

sub error {
    my ($self, %options) = @_;

    return $self->{error};
}

sub set_configuration {
    my ($self, %options) = @_;

    if (!defined($options{config})) {
        return 1;
    }

    foreach (('base_url', 'username', 'password')) {
        if (!defined($options{config}->{$_}) ||
            $options{config}->{$_} eq '') {
            $self->{error} = $_ . ' configuration missing';
            return 1;
        }

        $self->{$_} = $options{config}->{$_};
    }

    $self->{base_url} =~ s/\/$//;

    $self->{http_backend} = defined($options{config}->{backend}) ? $options{config}->{backend} : 'curl';

    $self->{curl_opts} = ['CURLOPT_SSL_VERIFYPEER => 0'];
    my $curl_opts = [];
    if (defined($options{config}->{curlopts})) {
        foreach (keys %{$options{config}->{curlopts}}) {
            push @{$curl_opts}, $_ . ' => ' . $options{config}->{curlopts}->{$_};
        }
    }
    if (scalar(@$curl_opts) > 0) {
        $self->{curl_opts} = $curl_opts;
    }

    $self->{http} = gorgone::class::http::http->new(logger => $options{logger});
    $self->{is_error} = 0;
    return 0;
}

sub authenticate {
    my ($self, %options) = @_;

    my $json_request = {
        security => {
            credentials => {
                login => $self->{username},
                password => $self->{password}
            }
        }
    };
    my $encoded;
    eval {
        $encoded = encode_json($json_request);
    };
    if ($@) {
        $self->{is_error} = 1;
        $self->{error} = "cannot encode json request: $@";
        return undef;
    }

    my ($code, $content) = $self->{http}->request(
        http_backend => $self->{http_backend},
        method => 'POST',
        hostname => '',
        full_url => $self->{base_url} . '/login',
        query_form_post => $encoded,
        header => [
            'Accept-Type: application/json; charset=utf-8',
            'Content-Type: application/json; charset=utf-8',
        ],
        curl_opt => $self->{curl_opts},
        warning_status => '',
        unknown_status => '',
        critical_status => ''
    );
    if ($code) {
        $self->{is_error} = 1;
        $self->{error} = 'http request error';
        return undef;
    }
    if ($self->{http}->get_code() < 200 || $self->{http}->get_code() >= 300) {
        $self->{is_error} = 1;
        $self->{error} =  "Login error [code: '" . $self->{http}->get_code() . "'] [message: '" . $self->{http}->get_message() . "']";
        return undef;
    }

    my $decoded = $self->json_decode(content => $content);
    return if (!defined($decoded));

    my $token = defined($decoded->{security}->{token}) ? $decoded->{security}->{token} : undef;
    if (!defined($token)) {
        $self->{is_error} = 1;
        $self->{error} = 'authenticate issue - cannot get token';
        return undef;
    }

    $self->{token} = $token;
    $self->{is_logged} = 1;
}

sub request {
    my ($self, %options) = @_;

    if (!defined($self->{base_url})) {
        $self->{is_error} = 1;
        $self->{error} = 'configuration missing';
        return 1;
    }

    $self->{is_error} = 0;
    if ($self->{is_logged} == 0) {
        $self->authenticate();
    }

    return 1 if ($self->{is_logged} == 0);

    # TODO: manage it properly
    my $get_param = ['page=1', 'limit=10000'];
    if (defined($options{get_param})) {
        push @$get_param, @{$options{get_param}};
    }

    my ($code, $content) = $self->{http}->request(
        http_backend => $self->{http_backend},
        method => $options{method},
        hostname => '',
        full_url => $self->{base_url} . $options{endpoint},
        query_form_post => $options{query_form_post},
        get_param => $get_param,
        header => [
            'Accept-Type: application/json; charset=utf-8',
            'Content-Type: application/json; charset=utf-8',
            'X-AUTH-TOKEN: ' . $self->{token}
        ],
        curl_opt => $self->{curl_opts},
        warning_status => '',
        unknown_status => '',
        critical_status => ''
    );

    # code 403 means forbidden (token not good maybe)
    if ($self->{http}->get_code() == 403) {
        $self->{token} = undef;
        $self->{is_logged} = 0;
        $self->{is_error} = 1;
        $self->{error} = 'token forbidden';
        return 1;
    }

    if ($self->{http}->get_code() < 200 || $self->{http}->get_code() >= 300) {
        $self->{is_error} = 1;
        $self->{error} =  "request error [code: '" . $self->{http}->get_code() . "'] [message: '" . $self->{http}->get_message() . "']";
        return 1;
    }

    my $decoded = $self->json_decode(content => $content);
    return 1 if (!defined($decoded));

    return (0, $decoded);
}

sub get_monitoring_hosts {
    my ($self, %options) = @_;

    my $endpoint = '/monitoring/hosts';
    $endpoint .= '/' . $options{host_id} if (defined($options{host_id}));

    my $get_param;
    if (defined($options{search})) {
        $get_param = ['search=' . $options{search}];
    }
    
    return $self->request(
        method => 'GET',
        endpoint => $endpoint,
        get_param => $get_param
    );
}

sub get_scheduling_jobs {
    my ($self, %options) = @_;

=pod
    my $results = [
        {
            execution => {
                parameters => {
                    cron_definition => "* * * * *",
                    is_paused => 0
                },
                mode => 1
            },
            post_execution => {
                commands => [
                    {
                        action => 'COMMAND',
                        command_line => '/usr/share/centreon/www/modules/centreon-autodiscovery-server/script/run_save_discovered_host --all --job-id=9'
                    }
                ]
            },
            job_id => 9,
            token => "discovery_9_f2b0ea11",
            command_line => "/usr/lib/centreon/plugins/centreon_generic_snmp.pl --plugin=os::linux::local::plugin --mode=discovery-snmp --subnet='127.0.0.1/32' --snmp-community='public' --snmp-version='2c' --snmp-port='161' --snmp-timeout='1' \$_EXTRAOPTIONS\$",
            target => 1,
            status => 1,
            last_execution => undef,
            uuid_attributes => ["hostname", "ip"]
        }
    ];
    return (0, $results);
=cut

    my $get_param;
    if (defined($options{search})) {
        $get_param = ['search=' . $options{search}];
    }

    my $endpoint = '/auto-discovery/scheduling/jobs';
    return $self->request(
        method => 'GET',
        endpoint => $endpoint,
        get_param => $get_param
    );
}

sub DESTROY {
    my ($self) = @_;

    if ($self->{is_logged} == 1) {
        $self->request(
            method => 'GET',
            endpoint => '/logout'
        );
    }
}

1;
