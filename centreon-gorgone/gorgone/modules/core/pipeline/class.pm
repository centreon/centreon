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

package gorgone::modules::core::pipeline::class;

use base qw(gorgone::class::module);

use strict;
use warnings;
use gorgone::class::db;
use gorgone::standard::library;
use gorgone::standard::constants qw(:all);
use JSON::XS;
use EV;

my %handlers = (TERM => {}, HUP => {});
my ($connector);

sub new {
    my ($class, %options) = @_;
    $connector = $class->SUPER::new(%options);
    bless $connector, $class;

    $connector->{timeout} = 600;
    $connector->{pipelines} = {};

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
    $self->{logger}->writeLogDebug("[pipeline] -class- $$ Receiving order to stop...");
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

sub send_listener {
    my ($self, %options) = @_;

    my $current = $self->{pipelines}->{ $options{token} }->{current};

    $self->{pipelines}->{ $options{token} }->{pipe}->[$current]->{created} = time();
    $self->send_internal_action({
        action => 'ADDLISTENER',
        data => [
            {
                identity => 'gorgonepipeline',
                event => 'PIPELINELISTENER',
                target => $self->{pipelines}->{ $options{token} }->{pipe}->[$current]->{target},
                token => $options{token} . '-' . $current,
                timeout => $self->{pipelines}->{ $options{token} }->{pipe}->[$current]->{timeout},
                log_pace => $self->{pipelines}->{ $options{token} }->{pipe}->[$current]->{log_pace}
            }
        ]
    });

    $self->send_internal_action({
        action => $self->{pipelines}->{ $options{token} }->{pipe}->[$current]->{action},
        target => $self->{pipelines}->{ $options{token} }->{pipe}->[$current]->{target},
        token => $options{token} . '-' . $current,
        data => $self->{pipelines}->{ $options{token} }->{pipe}->[$current]->{data}
    });

    $self->{logger}->writeLogDebug("[pipeline] -class- pipeline '$options{token}' run $current");
    $self->send_log(
        code => GORGONE_MODULE_PIPELINE_RUN_ACTION,
        token => $options{token},
        data => { message => 'proceed action ' . ($current + 1), token => $options{token} . '-' . $current }
    );
}

sub action_addpipeline {
    my ($self, %options) = @_;

    $options{token} = $self->generate_token() if (!defined($options{token}));
    #[
    #  { "action": "COMMAND", "data": { "content": [ { "command": "ls" } ] }, "continue": "ok", "continue_custom": "%{last_exit_code} == 1" }, // By default for COMMAND: "continue": "%{last_exit_code} == 0" 
    #  { "action:" "COMMAND", "target": 10, "timeout": 60, "log_pace": 10, "data": { [ "content": { "command": "ls /tmp" } ] } }
    #]

    $self->send_log(code => GORGONE_ACTION_BEGIN, token => $options{token}, data => { message => 'action addpipeline proceed' });

    $self->{pipelines}->{$options{token}} = { current => 0, pipe => $options{data} };
    $self->send_listener(token => $options{token});

    return 0;
}

sub action_pipelinelistener {
    my ($self, %options) = @_;

    return 0 if (!defined($options{token}) || $options{token} !~ /^(.*)-(\d+)$/);
    my ($token, $current_event) = ($1, $2);

    return 0 if (!defined($self->{pipelines}->{ $token }));
    my $current = $self->{pipelines}->{$token}->{current};
    return 0 if ($current != $current_event);

    if ($self->{pipelines}->{$token}->{pipe}->[$current]->{action} eq 'COMMAND') {
        # we want to catch exit_code for command results
        if ($options{data}->{code} == GORGONE_MODULE_ACTION_COMMAND_RESULT) {
            $self->{pipelines}->{$token}->{pipe}->[$current]->{last_exit_code} = $options{data}->{data}->{result}->{exit_code};
            $self->{pipelines}->{$token}->{pipe}->[$current]->{total_exit_code} += $options{data}->{data}->{result}->{exit_code}
                if (!defined($self->{pipelines}->{$token}->{pipe}->[$current]->{total_exit_code}));
            return 0;
        }
    }

    return 0 if ($options{data}->{code} != GORGONE_ACTION_FINISH_OK && $options{data}->{code} != GORGONE_ACTION_FINISH_KO);

    my $continue = GORGONE_ACTION_FINISH_OK;
    if (defined($self->{pipelines}->{$token}->{pipe}->[$current]->{continue}) &&
        $self->{pipelines}->{$token}->{pipe}->[$current]->{continue} eq 'ko') {
        $continue = GORGONE_ACTION_FINISH_KO;
    }

    my $success = 1;
    if ($options{data}->{code} != $continue) {
        $success = 0;
    }
    if ($self->{pipelines}->{$token}->{pipe}->[$current]->{action} eq 'COMMAND') {
        my $eval = '%{last_exit_code} == 0';
        $eval = $self->{pipelines}->{$token}->{pipe}->[$current]->{continue_continue_custom}
            if (defined($self->{pipelines}->{$token}->{pipe}->[$current]->{continue_continue_custom}));
        $eval = $self->change_macros(
            template => $eval,
            macros => {
                total_exit_code => '$self->{pipelines}->{$token}->{pipe}->[$current]->{total_exit_code}',
                last_exit_code  => '$self->{pipelines}->{$token}->{pipe}->[$current]->{last_exit_code}'
            }
        );
        if (! eval "$eval") {
            $success = 0;
        }
    }

    if ($success == 0) {
        $self->{logger}->writeLogDebug("[pipeline] -class- pipeline '$token' failed at $current");
        $self->send_log(code => GORGONE_ACTION_FINISH_KO, token => $token, data => { message => 'action pipeline failed' });
        delete $self->{pipelines}->{$token};
    } else {
        if (defined($self->{pipelines}->{$token}->{pipe}->[$current + 1])) {
            $self->{pipelines}->{$token}->{current}++;
            $self->send_listener(token => $token);
        } else {
            $self->{logger}->writeLogDebug("[pipeline] -class- pipeline '$token' finished successfully");
            $self->send_log(code => GORGONE_ACTION_FINISH_OK, token => $token, data => { message => 'action pipeline finished successfully' });
            delete $self->{pipelines}->{$token};
        }
    }

    return 0;
}

sub check_timeout {
    my ($self, %options) = @_;

    foreach (keys %{$self->{pipelines}}) {
        my $current = $self->{pipelines}->{$_}->{current};
        my $timeout = defined($self->{pipelines}->{$_}->{pipe}->[$current]->{timeout}) && $self->{pipelines}->{$_}->{pipe}->[$current]->{timeout} =~ /(\d+)/ ? 
            $1 : $self->{timeout};

        if ((time() - $self->{pipelines}->{$_}->{pipe}->[$current]->{created}) > $timeout) {
            $self->{logger}->writeLogDebug("[pipeline] -class- delete pipeline '$_' timeout");
            delete $self->{pipelines}->{$_};
            $self->send_log(code => GORGONE_ACTION_FINISH_KO, token => $_, data => { message => 'pipeline timeout reached' });
        }
    }
}

sub periodic_exec {
    if ($connector->{stop} == 1) {
        $connector->{logger}->writeLogInfo("[pipeline] -class- $$ has quit");
        exit(0);
    }

    $connector->check_timeout();
}

sub run {
    my ($self, %options) = @_;

    $self->{internal_socket} = gorgone::standard::library::connect_com(
        context => $self->{zmq_context},
        zmq_type => 'ZMQ_DEALER',
        name => 'gorgone-pipeline',
        logger => $self->{logger},
        type => $self->get_core_config(name => 'internal_com_type'),
        path => $self->get_core_config(name => 'internal_com_path')
    );
    $self->send_internal_action({
        action => 'PIPELINEREADY',
        data => {}
    });

    my $watcher_timer = $self->{loop}->timer(5, 5, \&periodic_exec);
    my $watcher_io = $self->{loop}->io($connector->{internal_socket}->get_fd(), EV::READ, sub { $connector->event() } );
    $self->{loop}->run();
}

1;
