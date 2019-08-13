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

package centreon::script::gorgoneapi;

use strict;
use warnings;
use centreon::gorgone::common;

sub root {
    my (%options) = @_;

    $options{logger}->writeLogInfo("gorgoneapi - requesting '" . $options{uri} . "' [" . $options{method} . "]");

    my %dispatch;
    foreach my $action (keys $options{modules_events}) {
        next if (!defined($options{modules_events}->{$action}->{api}->{uri}));
        $dispatch{$options{modules_events}->{$action}->{api}->{method} . '_/' .
            $options{modules_events}->{$action}->{module}->{shortname} .
            $options{modules_events}->{$action}->{api}->{uri}} = $action;
    }

    my $response;
    if ($options{method} eq 'GET' && $options{uri} =~ /^\/api\/get\/(.*)$/) {
        $response = get_log(socket => $options{socket}, token => $1);
    } elsif ($options{method} eq 'GET' && $options{uri} =~ /^\/api\/module\/(.*)$/) {
        $response = call_action(socket => $options{socket}, action => $dispatch{'GET_' . $1});
    } elsif ($options{method} eq 'POST' && $options{uri} =~ /^\/api\/module\/(.*)$/) {
        $response = call_action(socket => $options{socket}, action => $dispatch{'POST_' . $1});
    # } elsif {
    # }
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
        # target => $options{target},
        # data => $options{data},
        json_encode => 1
    );
}

sub get_log {
    my (%options) = @_;
    
    centreon::gorgone::common::zmq_send_message(
        socket => $options{socket},
        action => 'GETLOG',
        token => $options{token},
        json_encode => 1
    );
}

1;
