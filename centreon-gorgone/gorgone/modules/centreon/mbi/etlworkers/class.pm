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

package gorgone::modules::centreon::mbi::etlworkers::class;

use base qw(gorgone::class::module);

use strict;
use warnings;
use gorgone::standard::library;
use gorgone::standard::constants qw(:all);
use gorgone::class::http::http;
use JSON::XS;
use Try::Tiny;
use gorgone::modules::centreon::mbi::etlworkers::import::main;
use gorgone::modules::centreon::mbi::etlworkers::dimensions::main;
use gorgone::modules::centreon::mbi::etlworkers::event::main;
use gorgone::modules::centreon::mbi::etlworkers::perfdata::main;
use gorgone::modules::centreon::mbi::libs::Messages;
use EV;

my %handlers = (TERM => {}, HUP => {});
my ($connector);

sub new {
    my ($class, %options) = @_;
    $connector = $class->SUPER::new(%options);
    bless $connector, $class;

    $connector->{pool_id} = $options{pool_id};

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
    $self->{logger}->writeLogDebug("[nodes] $$ Receiving order to stop...");
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

sub db_connections {
    my ($self, %options) = @_;

    if (!defined($self->{dbmon_centstorage_con}) || $self->{dbmon_centstorage_con}->sameParams(%{$options{dbmon}->{centstorage}}) == 0) {
        $self->{dbmon_centstorage_con} = gorgone::class::db->new(
            type => 'mysql',
            force => 2,
            logger => $self->{logger},
            die => 1,
            %{$options{dbmon}->{centstorage}}
        );
    }
    if (!defined($self->{dbbi_centstorage_con}) || $self->{dbbi_centstorage_con}->sameParams(%{$options{dbbi}->{centstorage}}) == 0) {
       $self->{dbbi_centstorage_con} = gorgone::class::db->new(
            type => 'mysql',
            force => 2,
            logger => $self->{logger},
            die => 1,
            %{$options{dbbi}->{centstorage}}
        );
    }

    if (!defined($self->{dbmon_centreon_con}) || $self->{dbmon_centreon_con}->sameParams(%{$options{dbmon}->{centreon}}) == 0) {
        $self->{dbmon_centreon_con} = gorgone::class::db->new(
            type => 'mysql',
            force => 2,
            logger => $self->{logger},
            die => 1,
            %{$options{dbmon}->{centreon}}
        );
    }
    if (!defined($self->{dbbi_centreon_con}) || $self->{dbbi_centreon_con}->sameParams(%{$options{dbbi}->{centreon}}) == 0) {
       $self->{dbbi_centreon_con} = gorgone::class::db->new(
            type => 'mysql',
            force => 2,
            logger => $self->{logger},
            die => 1,
            %{$options{dbbi}->{centreon}}
        );
    }
}

sub action_centreonmbietlworkersimport {
    my ($self, %options) = @_;

    $options{token} = $self->generate_token() if (!defined($options{token}));

    $self->{messages} = gorgone::modules::centreon::mbi::libs::Messages->new();
    my $code = GORGONE_ACTION_FINISH_OK;

    try {
        $self->db_connections(
            dbmon => $options{data}->{content}->{dbmon},
            dbbi => $options{data}->{content}->{dbbi}
        );
        if ($options{data}->{content}->{params}->{type} == 1) {
            gorgone::modules::centreon::mbi::etlworkers::import::main::sql($self, params => $options{data}->{content}->{params});
        } elsif ($options{data}->{content}->{params}->{type} == 2) {
            gorgone::modules::centreon::mbi::etlworkers::import::main::command($self, params => $options{data}->{content}->{params});
        } elsif ($options{data}->{content}->{params}->{type} == 3) {
            gorgone::modules::centreon::mbi::etlworkers::import::main::load($self, params => $options{data}->{content}->{params});
        }
    } catch {
        $code = GORGONE_ACTION_FINISH_KO;
        $self->{messages}->writeLog('ERROR', $_, 1);
    };

    $self->send_log(
        code => $code,
        token => $options{token},
        data => {
            messages => $self->{messages}->getLogs()
        }
    );
}

sub action_centreonmbietlworkersdimensions {
    my ($self, %options) = @_;

    $options{token} = $self->generate_token() if (!defined($options{token}));

    $self->{messages} = gorgone::modules::centreon::mbi::libs::Messages->new();
    my $code = GORGONE_ACTION_FINISH_OK;

    try {
        $self->db_connections(
            dbmon => $options{data}->{content}->{dbmon},
            dbbi => $options{data}->{content}->{dbbi}
        );

        gorgone::modules::centreon::mbi::etlworkers::dimensions::main::execute(
            $self,
            dbmon => $options{data}->{content}->{dbmon},
            dbbi => $options{data}->{content}->{dbbi},
            params => $options{data}->{content}->{params},
            etlProperties => $options{data}->{content}->{etlProperties},
            options => $options{data}->{content}->{options}
        );
    } catch {
        $code = GORGONE_ACTION_FINISH_KO;
        $self->{messages}->writeLog('ERROR', $_, 1);
    };

    $self->send_log(
        code => $code,
        token => $options{token},
        data => {
            messages => $self->{messages}->getLogs()
        }
    );
}

sub action_centreonmbietlworkersevent {
    my ($self, %options) = @_;

    $options{token} = $self->generate_token() if (!defined($options{token}));

    $self->{messages} = gorgone::modules::centreon::mbi::libs::Messages->new();
    my $code = GORGONE_ACTION_FINISH_OK;

    try {
        $self->db_connections(
            dbmon => $options{data}->{content}->{dbmon},
            dbbi => $options{data}->{content}->{dbbi}
        );
        if ($options{data}->{content}->{params}->{type} eq 'sql') {
            gorgone::modules::centreon::mbi::etlworkers::event::main::sql($self, params => $options{data}->{content}->{params});
        } elsif ($options{data}->{content}->{params}->{type} eq 'events') {
            gorgone::modules::centreon::mbi::etlworkers::event::main::events(
                $self,
                dbmon => $options{data}->{content}->{dbmon},
                dbbi => $options{data}->{content}->{dbbi},
                etlProperties => $options{data}->{content}->{etlProperties},
                params => $options{data}->{content}->{params},
                options => $options{data}->{content}->{options}
            );
        } elsif ($options{data}->{content}->{params}->{type} =~ /^availability_/) {
            gorgone::modules::centreon::mbi::etlworkers::event::main::availability(
                $self,
                dbmon => $options{data}->{content}->{dbmon},
                dbbi => $options{data}->{content}->{dbbi},
                etlProperties => $options{data}->{content}->{etlProperties},
                params => $options{data}->{content}->{params}
            );
        }
    } catch {
        $code = GORGONE_ACTION_FINISH_KO;
        $self->{messages}->writeLog('ERROR', $_, 1);
    };

    $self->send_log(
        code => $code,
        token => $options{token},
        data => {
            messages => $self->{messages}->getLogs()
        }
    );
}

sub action_centreonmbietlworkersperfdata {
    my ($self, %options) = @_;

    $options{token} = $self->generate_token() if (!defined($options{token}));

    $self->{messages} = gorgone::modules::centreon::mbi::libs::Messages->new();
    my $code = GORGONE_ACTION_FINISH_OK;

    try {
        $self->db_connections(
            dbmon => $options{data}->{content}->{dbmon},
            dbbi => $options{data}->{content}->{dbbi}
        );

        if ($options{data}->{content}->{params}->{type} eq 'sql') {
            gorgone::modules::centreon::mbi::etlworkers::perfdata::main::sql($self, params => $options{data}->{content}->{params});
        } elsif ($options{data}->{content}->{params}->{type} =~ /^perfdata_/) {
            gorgone::modules::centreon::mbi::etlworkers::perfdata::main::perfdata(
                $self,
                dbmon => $options{data}->{content}->{dbmon},
                dbbi => $options{data}->{content}->{dbbi},
                etlProperties => $options{data}->{content}->{etlProperties},
                params => $options{data}->{content}->{params},
                options => $options{data}->{content}->{options},
                pool_id => $self->{pool_id}
            );
        } elsif ($options{data}->{content}->{params}->{type} =~ /^centile_/) {
            gorgone::modules::centreon::mbi::etlworkers::perfdata::main::centile(
                $self,
                dbmon => $options{data}->{content}->{dbmon},
                dbbi => $options{data}->{content}->{dbbi},
                etlProperties => $options{data}->{content}->{etlProperties},
                params => $options{data}->{content}->{params},
                pool_id => $self->{pool_id}
            );
        }
    } catch {
        $code = GORGONE_ACTION_FINISH_KO;
        $self->{messages}->writeLog('ERROR', $_, 1);
    };

    $self->send_log(
        code => $code,
        token => $options{token},
        data => {
            messages => $self->{messages}->getLogs()
        }
    );
}

sub periodic_exec {
    if ($connector->{stop} == 1) {
        $connector->{logger}->writeLogInfo("[" . $connector->{module_id} . "] $$ has quit");
        exit(0);
    }
}

sub run {
    my ($self, %options) = @_;

    $self->{internal_socket} = gorgone::standard::library::connect_com(
        context => $self->{zmq_context},
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgone-' . $self->{module_id} . '-' . $self->{pool_id},
        logger => $self->{logger},
        type => $self->get_core_config(name => 'internal_com_type'),
        path => $self->get_core_config(name => 'internal_com_path')
    );
    $self->send_internal_action({
        action => 'CENTREONMBIETLWORKERSREADY',
        data => {
            pool_id => $self->{pool_id}
        }
    });

    my $w1 = EV::timer(5, 2, \&periodic_exec);
    my $w2 = EV::io($self->{internal_socket}->get_fd(), EV::READ, sub { $connector->event() } );
    EV::run();
}

1;
