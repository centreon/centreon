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

package gorgone::class::listener;

use strict;
use warnings;
use gorgone::standard::constants qw(:all);
use gorgone::standard::library;

sub new {
    my ($class, %options) = @_;
    my $self  = {};
    bless $self, $class;

    $self->{logger} = $options{logger};
    $self->{gorgone_core} = $options{gorgone};
    $self->{tokens} = {};

    return $self;
}

sub event_log {
    my ($self, %options) = @_;

    return if (!defined($self->{tokens}->{$options{token}}));

    foreach (keys %{$self->{tokens}->{ $options{token} }->{events}}) {
        $self->{logger}->writeLogDebug("[listener] trigger event '$options{token}'");

        $self->{gorgone_core}->message_run(
            message => '[' . $_ . '] [' . $options{token} . '] [] { "code": ' . $options{code} . ', "data": ' . $options{data} . ' }',
            router_type => 'internal'
        );
    }
    if ($options{code} == GORGONE_ACTION_FINISH_KO || $options{code} == GORGONE_ACTION_FINISH_OK) {
        delete $self->{tokens}->{$options{token}};
    }
}

sub add_listener {
    my ($self, %options) = @_;

    $self->{logger}->writeLogDebug("[listener] add token '$options{token}'");
    if (!defined($self->{tokens}->{$options{token}})) {
        $self->{tokens}->{$options{token}} = {
            target   => $options{target},
            log_pace => defined($options{log_pace}) && $options{log_pace} =~ /(\d+)/ ? $1 : 30,
            timeout  => defined($options{timeout}) && $options{timeout} =~ /(\d+)/ ? $1 : 3600,
            events   => { $options{event} => $options{identity} },
            getlog_last => -1,
            created => time()
        };
    } else {
        $self->{tokens}->{$options{token}}->{events}->{$options{event}} = $options{identity};
    }

    $self->check_getlog_token(token => $options{token});
}

sub check_getlog_token {
    my ($self, %options) = @_;

    if (defined($self->{tokens}->{$options{token}}->{target}) &&
        $self->{tokens}->{$options{token}}->{target}) {
        if ((time() - $self->{tokens}->{$options{token}}->{log_pace}) > $self->{tokens}->{$options{token}}->{getlog_last}) {
            $self->{gorgone_core}->message_run(
                message => "[GETLOG] [] [$self->{tokens}->{$options{token}}->{target}]",
                router_type => 'internal'
            );

            $self->{tokens}->{$options{token}}->{getlog_last} = time();
        }
    }
}

sub check {
    my ($self, %options) = @_;

    foreach my $token (keys %{$self->{tokens}}) {
        if (time() - $self->{tokens}->{$token}->{created} > $self->{tokens}->{$token}->{timeout}) {
            delete $self->{tokens}->{$token};
            $self->{logger}->writeLogDebug("[listener] delete token '$token': timeout");
            next;
        }
        $self->check_getlog_token(token => $token);
    }
}

1;
