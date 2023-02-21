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

package gorgone::standard::api;

use strict;
use warnings;
use gorgone::standard::library;
use Time::HiRes;
use JSON::XS;
use EV;

my $module;
my $socket;
my $results = {};
my $action_token;

sub set_module {
    $module = $_[0];
}

sub root {
    my (%options) = @_;

    $options{logger}->writeLogInfo("[api] Requesting '" . $options{uri} . "' [" . $options{method} . "]");

    $action_token = undef;
    $socket = $options{socket};
    $module = $options{module};
    $results = {};

    my $response;
    if ($options{method} eq 'GET' && $options{uri} =~ /^\/api\/(nodes\/(\w*)\/)?log\/(.*)$/) {
        $response = get_log(
            target => $2,
            token => $3,
            sync_wait => (defined($options{parameters}->{sync_wait})) ? $options{parameters}->{sync_wait} : undef,
            parameters => $options{parameters},
            module => $options{module}
        );
    } elsif ($options{uri} =~ /^\/api\/(nodes\/(\w*)\/)?internal\/(\w+)\/?([\w\/]*?)$/
        && defined($options{api_endpoints}->{$options{method} . '_/internal/' . $3})) {
        my @variables = split(/\//, $4);
        $response = call_internal(
            action => $options{api_endpoints}->{$options{method} . '_/internal/' . $3},
            target => $2,
            data => { 
                content => $options{content},
                parameters => $options{parameters},
                variables => \@variables
            },
            log_wait => (defined($options{parameters}->{log_wait})) ? $options{parameters}->{log_wait} : undef,
            sync_wait => (defined($options{parameters}->{sync_wait})) ? $options{parameters}->{sync_wait} : undef,
            module => $options{module}
        );
    } elsif ($options{uri} =~ /^\/api\/(nodes\/(\w*)\/)?(\w+)\/(\w+)\/(\w+)\/?([\w\/]*?)$/
        && defined($options{api_endpoints}->{$options{method} . '_/' . $3 . '/' . $4 . '/' . $5})) {
        my @variables = split(/\//, $6);
        $response = call_action(
            action => $options{api_endpoints}->{$options{method} . '_/' . $3 . '/' . $4 . '/' . $5},
            target => $2,
            data => { 
                content => $options{content},
                parameters => $options{parameters},
                variables => \@variables
            },
            log_wait => (defined($options{parameters}->{log_wait})) ? $options{parameters}->{log_wait} : undef,
            sync_wait => (defined($options{parameters}->{sync_wait})) ? $options{parameters}->{sync_wait} : undef,
            module => $options{module}
        );
    } else {
        $response = '{"error":"method_unknown","message":"Method not implemented"}';
    }

    return $response;
}

sub stop_ev {
    EV::break();
}

sub call_action {
    my (%options) = @_;

    $action_token = gorgone::standard::library::generate_token() if (!defined($options{token}));

    $options{module}->send_internal_action({
        socket => $socket,
        action => $options{action},
        target => $options{target},
        token => $action_token,
        data => $options{data},
        json_encode => 1
    });

    my $response = '{"token":"' . $action_token . '"}';
    if (defined($options{log_wait}) && $options{log_wait} ne '') {
        Time::HiRes::usleep($options{log_wait});
        $response = get_log(
            target => $options{target},
            token => $action_token,
            sync_wait => $options{sync_wait},
            parameters => $options{data}->{parameters},
            module => $options{module}
        );
    }

    return $response;
}

sub call_internal {
    my (%options) = @_;

    $action_token = gorgone::standard::library::generate_token();
    if (defined($options{target}) && $options{target} ne '') {        
        return call_action(
            target => $options{target},
            action => $options{action},
            token => $action_token,
            data => $options{data},
            json_encode => 1,
            log_wait => $options{log_wait},
            sync_wait => $options{sync_wait},
            module => $options{module}
        );
    }

    $options{module}->send_internal_action({
        socket => $socket,
        action => $options{action},
        token => $action_token,
        data => $options{data},
        json_encode => 1
    });

    my $w1 = EV::timer(5, 0, \&stop_ev);
    EV::run();

    my $response = '{"error":"no_result", "message":"No result found for action \'' . $options{action} . '\'"}';
    if (defined($results->{$action_token}->{data})) {
        my $content;
        eval {
            $content = JSON::XS->new->decode($results->{$action_token}->{data});
        };
        if ($@) {
            $response = '{"error":"decode_error","message":"Cannot decode response"}';
        } else {
            if (defined($content->{data})) {
                eval {
                    $response = JSON::XS->new->encode($content->{data});
                };
                if ($@) {
                    $response = '{"error":"encode_error","message":"Cannot encode response"}';
                }
            } else {
                $response = '';
            }
        }
    }

    return $response;
}

sub get_log {
    my (%options) = @_;

    if (defined($options{target}) && $options{target} ne '') {
        $options{module}->send_internal_action({
            socket => $socket,
            target => $options{target},
            action => 'GETLOG',
            json_encode => 1
        });

        my $sync_wait = (defined($options{sync_wait}) && $options{sync_wait} ne '') ? $options{sync_wait} : 10000;
        Time::HiRes::usleep($sync_wait);
    }

    my $token_log = $options{token} . '-log';
    $options{module}->send_internal_action({
        socket => $socket,
        action => 'GETLOG',
        token => $token_log,
        data => {
            token => $options{token},
            %{$options{parameters}}
        },
        json_encode => 1
    });

    my $w1 = EV::timer(5, 0, \&stop_ev);
    EV::run();

    my $response = '{"error":"no_log","message":"No log found for token","data":[],"token":"' . $options{token} . '"}';
    if (defined($results->{ $token_log }) && defined($results->{ $token_log }->{data})) {
        my $content;
        eval {
            $content = JSON::XS->new->decode($results->{ $token_log }->{data});
        };
        if ($@) {
            $response = '{"error":"decode_error","message":"Cannot decode response"}';
        } elsif (defined($content->{data}->{result}) && scalar(@{$content->{data}->{result}}) > 0) {
            eval {
                $response = JSON::XS->new->encode(
                    {
                        message => "Logs found",
                        token => $options{token},
                        data => $content->{data}->{result}
                    }
                );
            };
            if ($@) {
                $response = '{"error":"encode_error","message":"Cannot encode response"}';
            }
        }
    }

    return $response;
}

sub event {
    my (%options) = @_;

    my $httpserver = defined($options{httpserver}) ? $options{httpserver} : $module;
    while (1) {
        my ($message) = $httpserver->read_message();
        last if (!defined($message));

        if ($message =~ /^\[(.*?)\]\s+\[([a-zA-Z0-9:\-_]*?)\]\s+\[.*?\]\s+(.*)$/m || 
            $message =~ /^\[(.*?)\]\s+\[([a-zA-Z0-9:\-_]*?)\]\s+(.*)$/m) {
            my ($action, $token, $data) = ($1, $2, $3);
            $results->{$token} = {
                action => $action,
                token => $token,
                data => $data
            };
            if ((my $method = $httpserver->can('action_' . lc($action)))) {
                my ($rv, $decoded) = $httpserver->json_decode(argument => $data, token => $token);
                next if ($rv);
                $method->($httpserver, token => $token, data => $decoded);
            }
        }
    }
}

1;
