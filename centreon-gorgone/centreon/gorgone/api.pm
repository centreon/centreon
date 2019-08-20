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

package centreon::gorgone::api;

use strict;
use warnings;
use centreon::gorgone::common;
use ZMQ::LibZMQ4;
use ZMQ::Constants qw(:all);
use Time::HiRes;
use JSON::XS;

my $socket;
my $result;

sub root {
    my (%options) = @_;

    $options{logger}->writeLogInfo("[api] Requesting '" . $options{uri} . "' [" . $options{method} . "]");

    my %dispatch;
    foreach my $action (keys $options{modules_events}) {
        next if (!defined($options{modules_events}->{$action}->{api}->{uri}));
        $dispatch{$options{modules_events}->{$action}->{api}->{method} . '_' .
            $options{modules_events}->{$action}->{api}->{uri}} = $action;
    }

    my $response;
    if ($options{method} eq 'GET' && $options{uri} =~ /^\/api\/log\/(.*)$/) {
        $response = get_log(socket => $options{socket}, token => $1);
    } elsif ($options{uri} =~ /^\/api\/(targets\/(\w*)\/)?(\w+)\/?([\w\/]*?)$/
        && defined($dispatch{$options{method} . '_/' . $3})) {
        my @variables = split(/\//, $4);
        $response = call_action(
            socket => $options{socket},
            action => $dispatch{$options{method} . '_/' . $3},
            target => $2,
            data => { 
                content => $options{content},
                parameters => $options{parameters},
                variables => \@variables,
            },
            wait => (defined($options{parameters}->{wait})) ? $options{parameters}->{wait} : undef
        );
    } else {
        $response = '{"error":"method_unknown","message":"Method not implemented"}';
    }

    return $response;
}

sub call_action {
    my (%options) = @_;
    
    centreon::gorgone::common::zmq_send_message(
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
        if (defined($options{wait}) && $options{wait} ne '') {
            Time::HiRes::usleep($options{wait});
            $response = get_log(socket => $options{socket}, token => $result->{token});
        } else {
            $response = '{"token":"' . $result->{token} . '"}';
        }
    }

    return $response;
}

sub get_log {
    my (%options) = @_;

    centreon::gorgone::common::zmq_send_message(
        socket => $options{socket},
        action => 'GETLOG',
        data => {
            token => $options{token}
        },
        json_encode => 1
    );
    
    $socket = $options{socket};
    my $poll = [
        {
            socket  => $options{socket},
            events  => ZMQ_POLLIN,
            callback => \&event,
        }
    ];

    my $rev = zmq_poll($poll, 5000);

    my $response = '{"error":"no_log","message":"No log found for token","token":"' . $options{token} . '"}';
    if (defined($result->{data})) {
        my $content;
        eval {
            $content = JSON::XS->new->utf8->decode($result->{data});
        };
        if ($@) {
            $response = '{"error":"decode_error","message":"Cannot decode response"}';
        } elsif (defined($content->{data}->{result}) && scalar(keys %{$content->{data}->{result}}) > 0) {
            eval {
                $response = JSON::XS->new->utf8->encode($content->{data}->{result});
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
        my $message = centreon::gorgone::common::zmq_dealer_read_message(socket => $socket);
        
        $result = {};
        if ($message =~ /^\[(.*?)\]\s+\[(.*?)\]\s+\[.*?\]\s+(.*)$/m || 
            $message =~ /^\[(.*?)\]\s+\[(.*?)\]\s+(.*)$/m) {
            $result = {
                action => $1,
                token => $2,
                data => $3,
            };
        }
        
        last unless (centreon::gorgone::common::zmq_still_read(socket => $socket));
    }
}

1;
