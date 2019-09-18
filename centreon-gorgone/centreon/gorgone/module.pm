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

package centreon::gorgone::module;

use strict;
use warnings;

use centreon::gorgone::common;
use centreon::misc::misc;
use JSON::XS;

use constant ACTION_BEGIN => 0;
use constant ACTION_FINISH_KO => 1;
use constant ACTION_FINISH_OK => 2;

sub generate_token {
   my ($self, %options) = @_;
   
   return centreon::gorgone::common::generate_token();
}

sub send_internal_action {
    my ($self, %options) = @_;

    centreon::gorgone::common::zmq_send_message(
        socket => $self->{internal_socket},
        token => $options{token},
        action => $options{action},
        target => $options{target},
        data => $options{data},
        json_encode => 1
    );
}

sub send_log {
    my ($self, %options) = @_;

    return if (!defined($options{token}));

    centreon::gorgone::common::zmq_send_message(
        socket => (defined($options{socket})) ? $options{socket} : $self->{internal_socket},
        action => 'PUTLOG',
        token => $options{token},
        data => { code => $options{code}, etime => time(), token => $options{token}, data => $options{data} },
        json_encode => 1
    );
}

sub json_encode {
    my ($self, %options) = @_;

    my $encoded_arguments;
    eval {
        $encoded_arguments = JSON::XS->new->utf8->encode($options{argument});
    };
    if ($@) {
        my $container = '';
        $container = 'container ' . $self->{container_id} . ': ' if (defined($self->{container_id}));
        $self->{logger}->writeLogError("[$self->{module_id}] -class- ${container}$options{method} - cannot encode json: $@");
        return 1;
    }

    return (0, $encoded_arguments);
}

sub json_decode {
    my ($self, %options) = @_;

    my $decoded_arguments;
    eval {
        $decoded_arguments = JSON::XS->new->utf8->decode($options{argument});
    };
    if ($@) {
        my $container = '';
        $container = 'container ' . $self->{container_id} . ': ' if (defined($self->{container_id}));
        $self->{logger}->writeLogError("[$self->{module_id}] -class- ${container}$options{method} - cannot decode json: $@");
        return 1;
    }

    return (0, $decoded_arguments);
}

sub execute_shell_cmd {
    my ($self, %options) = @_;

    my $timeout = defined($options{timeout}) &&  $options{timeout} =~ /(\d+)/ ? $1 : 30;
    my ($lerror, $stdout, $exit_code) = centreon::misc::misc::backtick(
        command => $options{cmd},
        logger => $self->{logger},
        timeout => $timeout,
        wait_exit => 1,
    );
    if ($lerror == -1 || ($exit_code >> 8) != 0) {
        my $container = '';
        $container = 'container ' . $self->{container_id} . ': ' if (defined($self->{container_id}));
        $self->{logger}->writeLogError("[$self->{module_id}] -class- ${container}command execution issue $options{cmd} : " . $stdout);
        return -1;
    }

    return 0;
}

sub change_macros {
    my ($self, %options) = @_;

    $options{template} =~ s/%\{(.*?)\}/$options{macros}->{$1}/g;
    if (defined($options{escape})) {
        $options{template} =~ s/([\Q$options{escape}\E])/\\$1/g;
    }
    return $options{template};
}

1;
