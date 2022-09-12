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
use gorgone::class::frame;

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
    my ($self) = shift;

    return if (!defined($self->{tokens}->{ $_[0]->{token}}));

    # we want to avoid loop
    my $events = $self->{tokens}->{ $_[0]->{token} };
    if ($_[0]->{code} == GORGONE_ACTION_FINISH_KO || $_[0]->{code} == GORGONE_ACTION_FINISH_OK) {
        delete $self->{tokens}->{ $_[0]->{token} };
    }

    foreach (keys %{$events->{events}}) {
        $self->{logger}->writeLogDebug("[listener] trigger event '$_[0]->{token}'");

        my $message = '[' . $_ . '] [' . $_[0]->{token} . '] [] { "code": ' . $_[0]->{code} . ', "data": ' . ${$_[0]->{data}} . ' }';
        my $frame = gorgone::class::frame->new();
        $frame->setFrame(\$message);

        $self->{gorgone_core}->message_run({ frame => $frame, router_type => 'internal' });
    }
}

sub add_listener {
    my ($self, %options) = @_;

    $self->{logger}->writeLogDebug("[listener] add token '$options{token}'");
    # an issue can happen if the event is unknown (recursive loop)
    if (!defined($self->{tokens}->{$options{token}})) {
        my ($log_pace, $timeout) = (30, 600);
        $log_pace = $1 if (defined($options{log_pace}) && $options{log_pace} =~ /(\d+)/);
        $timeout = $1 if (defined($options{timeout}) && $options{timeout} =~ /(\d+)/);
        $self->{tokens}->{$options{token}} = {
            target   => $options{target},
            log_pace => $log_pace,
            timeout  => $timeout,
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

        return if (defined($self->{gorgone_core}->{id}) && $self->{gorgone_core}->{id} == $self->{tokens}->{$options{token}}->{target});
        
        if ((time() - $self->{tokens}->{$options{token}}->{log_pace}) > $self->{tokens}->{$options{token}}->{getlog_last}) {
            my $message = "[GETLOG] [] [$self->{tokens}->{$options{token}}->{target}] {}";
            my $frame = gorgone::class::frame->new();
            $frame->setFrame(\$message);
            
            $self->{gorgone_core}->message_run({ frame => $frame, router_type => 'internal' });

            $self->{tokens}->{$options{token}}->{getlog_last} = time();
        }
    }
}

sub check {
    my ($self, %options) = @_;

    foreach my $token (keys %{$self->{tokens}}) {
        if (time() - $self->{tokens}->{$token}->{created} > $self->{tokens}->{$token}->{timeout}) {
            $self->{logger}->writeLogDebug("[listener] delete token '$token': timeout");
            gorgone::standard::library::add_history({
                dbh => $self->{gorgone_core}->{db_gorgone},
                code => GORGONE_ACTION_FINISH_KO,
                token => $token,
                data => '{ "message": "listener token ' . $token . ' timeout reached" }'
            });
            delete $self->{tokens}->{$token};
            next;
        }
        $self->check_getlog_token(token => $token);
    }
}

1;
