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
use ZMQ::LibZMQ4;
use ZMQ::Constants qw(:all);
use Time::HiRes;
use JSON::XS;

my $socket;
my $result;

sub root {
    my (%options) = @_;

    $options{logger}->writeLogInfo("[api] Requesting '" . $options{uri} . "' [" . $options{method} . "]");

    my $response;
    if ($options{method} eq 'GET' && $options{uri} =~ /^\/api\/(nodes\/(\w*)\/)?log\/(.*)$/) {
        $response = get_log(
            socket => $options{socket},
            target => $2,
            token => $3,
            sync_wait => (defined($options{parameters}->{sync_wait})) ? $options{parameters}->{sync_wait} : undef,
            parameters => $options{parameters}
        );
    } elsif ($options{uri} =~ /^\/api\/(nodes\/(\w*)\/)?internal\/(\w+)\/?([\w\/]*?)$/
        && defined($options{api_endpoints}->{$options{method} . '_/internal/' . $3})) {
        my @variables = split(/\//, $4);
        $response = call_internal(
            socket => $options{socket},
            action => $options{api_endpoints}->{$options{method} . '_/internal/' . $3},
            target => $2,
            data => { 
                content => $options{content},
                parameters => $options{parameters},
                variables => \@variables,
            },
            log_wait => (defined($options{parameters}->{log_wait})) ? $options{parameters}->{log_wait} : undef,
            sync_wait => (defined($options{parameters}->{sync_wait})) ? $options{parameters}->{sync_wait} : undef
        );
    } elsif ($options{uri} =~ /^\/api\/(nodes\/(\w*)\/)?(\w+)\/(\w+)\/(\w+)\/?([\w\/]*?)$/
        && defined($options{api_endpoints}->{$options{method} . '_/' . $3 . '/' . $4 . '/' . $5})) {
        my @variables = split(/\//, $6);
        $response = call_action(
            socket => $options{socket},
            action => $options{api_endpoints}->{$options{method} . '_/' . $3 . '/' . $4 . '/' . $5},
            target => $2,
            data => { 
                content => $options{content},
                parameters => $options{parameters},
                variables => \@variables,
            },
            log_wait => (defined($options{parameters}->{log_wait})) ? $options{parameters}->{log_wait} : undef,
            sync_wait => (defined($options{parameters}->{sync_wait})) ? $options{parameters}->{sync_wait} : undef,
        );
    } else {
        $response = '{"error":"method_unknown","message":"Method not implemented"}';
    }

    return $response;
}

sub call_action {
    my (%options) = @_;
    
    gorgone::standard::library::zmq_send_message(
        socket => $options{socket},
        action => $options{action},
        target => $options{target},
        data => $options{data},
        json_encode => 1
    );

    $socket = $options{socket};    
    my $poll = [
        {
            socket => $options{socket},
            events => ZMQ_POLLIN,
            callback => \&event,
        }
    ];

    my $rev = zmq_poll($poll, 5000);

    my $response = '{"error":"no_token","message":"Cannot retrieve token from ack"}';
    if (defined($result->{token}) && $result->{token} ne '') {
        if (defined($options{log_wait}) && $options{log_wait} ne '') {
            Time::HiRes::usleep($options{log_wait});
            $response = get_log(
                socket => $options{socket},
                target => $options{target},
                token => $result->{token},
                sync_wait => $options{sync_wait},
                parameters => $options{data}->{parameters}
            );
        } else {
            $response = '{"token":"' . $result->{token} . '"}';
        }
    }

    return $response;
}

sub call_internal {
    my (%options) = @_;
    
    $socket = $options{socket};
    my $poll = [
        {
            socket  => $options{socket},
            events  => ZMQ_POLLIN,
            callback => \&event,
        }
    ];

    if (defined($options{target}) && $options{target} ne '') {        
        return call_action(
            socket => $options{socket},
            target => $options{target},
            action => $options{action},
            data => $options{data},
            json_encode => 1,
            log_wait => $options{log_wait},
            sync_wait => $options{sync_wait}
        );
    }

    gorgone::standard::library::zmq_send_message(
        socket => $options{socket},
        action => $options{action},
        data => $options{data},
        json_encode => 1
    );

    my $rev = zmq_poll($poll, 5000);

    my $response = '{"error":"no_result", "message":"No result found for action \'' . $options{action} . '\'"}';
    if (defined($result->{data})) {
        my $content;
        eval {
            $content = JSON::XS->new->utf8->decode($result->{data});
        };
        if ($@) {
            $response = '{"error":"decode_error","message":"Cannot decode response"}';
        } else {
            if (defined($content->{data})) {
                eval {
                    $response = JSON::XS->new->utf8->encode($content->{data});
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
    
    $socket = $options{socket};
    my $poll = [
        {
            socket  => $options{socket},
            events  => ZMQ_POLLIN,
            callback => \&event,
        }
    ];

    if (defined($options{target}) && $options{target} ne '') {        
        gorgone::standard::library::zmq_send_message(
            socket => $options{socket},
            target => $options{target},
            action => 'GETLOG',
            json_encode => 1
        );

        my $sync_wait = (defined($options{sync_wait}) && $options{sync_wait} ne '') ? $options{sync_wait} : '10000';
        Time::HiRes::usleep($sync_wait);

        my $rev = zmq_poll($poll, 5000);
    }

    gorgone::standard::library::zmq_send_message(
        socket => $options{socket},
        action => 'GETLOG',
        data => {
            token => $options{token},
            %{$options{parameters}}
        },
        json_encode => 1
    );

    my $rev = zmq_poll($poll, 5000);

    my $response = '{"error":"no_log","message":"No log found for token","data":[],"token":"' . $options{token} . '"}';
    if (defined($result->{data})) {
        my $content;
        eval {
            $content = JSON::XS->new->utf8->decode($result->{data});
        };
        if ($@) {
            $response = '{"error":"decode_error","message":"Cannot decode response"}';
        } elsif (defined($content->{data}->{result}) && scalar(@{$content->{data}->{result}}) > 0) {
            eval {
                $response = JSON::XS->new->utf8->encode(
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
    while (1) {
        my $message = gorgone::standard::library::zmq_dealer_read_message(socket => $socket);
        
        $result = {};
        if ($message =~ /^\[(.*?)\]\s+\[(.*?)\]\s+\[.*?\]\s+(.*)$/m || 
            $message =~ /^\[(.*?)\]\s+\[(.*?)\]\s+(.*)$/m) {
            $result = {
                action => $1,
                token => $2,
                data => $3,
            };
        }
        
        last unless (gorgone::standard::library::zmq_still_read(socket => $socket));
    }
}

1;
